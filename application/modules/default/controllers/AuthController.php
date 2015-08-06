<?php

class AuthController extends Zend_Controller_Action
{
	private $session;

	function init()
	{
		//		$acl=Zend_Registry::get("acl");
		$this->initView();
		$this->view->baseUrl = $this->_request->getBaseUrl();
		// 		$this->session=new Zend_Session_Namespace('my');

	}

	function indexAction()
	{
		//        $this->_redirect('/auth/login');
	}

	function loginAction()
	{
		$this->view->message = '';
		// если уже залогинен и почемуто сюда попал - послать на начало
		if (Zend_Auth::getInstance()->hasIdentity()) $this->_redirect("/");

		$_ref=$this->_request->getHeader('REFERER');
		if ($this->_request->isPost())
		{
			// collect the data from the user
			Zend_Loader::loadClass('Zend_Filter_StripTags');
			$f = new Zend_Filter_StripTags();
			// @FIXME нормальные валидаторы и фильтры
			$username = $f->filter($this->_request->getPost('username'));
			$password = $f->filter($this->_request->getPost('password'));

			if (empty($username) || empty($password))
			{
				$this->view->message = 'Введите имя учетной записи и пароль';
			}
			else
			{
				
				// setup Zend_Auth adapter for a database table
				Zend_Loader::loadClass('Zend_Auth_Adapter_DbTable');
								
				if (strpos($username,"@")===false)
				{
					$password=md5($password);
					$authAdapter=$this->auth_db($username,$password);
				}
				else
				{
					// попробуем почту
					$authAdapter=new My_Auth_Adapter_Email($username, $password,"mail.academy21.ru",110);
					// логины в INI файле
// 					$authAdapter=new My_Auth_Adapter_Ini($username, $password,"emails.ini","logins");
				}

				// do the authentication
				$auth = Zend_Auth::getInstance();
				$result = $auth->authenticate($authAdapter);
				
				
				//      Zend_Auth_Result::SUCCESS
				//      Zend_Auth_Result::FAILURE
				//      Zend_Auth_Result::FAILURE_IDENTITY_NOT_FOUND
				//      Zend_Auth_Result::FAILURE_IDENTITY_AMBIGUOUS
				//      Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID
				//      Zend_Auth_Result::FAILURE_UNCATEGORIZED
				switch ($result->getCode())
				{

					case Zend_Auth_Result::FAILURE_IDENTITY_NOT_FOUND:
						/** Выполнить действия при несуществующем идентификаторе **/
						$this->view->message = 'Пользователь не существует или отключен';
						break;

					case Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID:
						/** Выполнить действия при некорректных учетных данных **/
						// failure: clear database row from session
						$this->view->message = 'НЕверные логин/пароль';

						break;

					case Zend_Auth_Result::SUCCESS:
						/** Выполнить действия при успешной аутентификации **/
						// success: store database row to auth's storage
						// system. (Not the password though!)
						/* Пишем в сессию необходимые нам данные (пароль обнуляем, он нам в сессии не нужен :) */
						$data = $authAdapter->getResultRowObject(null, array('pass','email'));
// 						print_r($data);
						$auth->getStorage()->write($data);
						// тут редирект туда, куда просили и куда празрешено
						// @FIXME если до этого был неверный логин - пошлет на страницу авторизации

						// 						$_ref=$this->session->referer1st;
						// 						// если зашли, referer1st уже не нужен
						// 						$this->session->referer1st=" ";
						$this->_redirect($_ref);

						break;

					default:
						/** Выполнить действия для остальных ошибок **/
						$this->view->message = 'Ошибка входа';
						// 						echo print_r($result->getMessages());
						break;
				}

			}
		}

		$this->view->title = "Представьтесь, пожалуйста";
		$this->render();
	}

	function logoutAction()
	{
		Zend_Auth::getInstance()->clearIdentity();
		Zend_Session::destroy();
		// 		$this->session->referer1st=" ";
		$this->_redirect('/');
	}

	private function auth_db($username,$password)
	{


		// берем базу
		$dbAdapter=Zend_Db_Table::getDefaultAdapter();

		$authAdapter = new Zend_Auth_Adapter_DbTable($dbAdapter);
		$authAdapter->setTableName('acl_users');
		$authAdapter->setIdentityColumn('login');
		$authAdapter->setCredentialColumn('pass');
		// условие, шо не отключен пользователь
		$select=$authAdapter->getDbSelect();
		$select->where('acl_users.disabled = 0');
		// добавим ФИО
		$select->joinLeft(array("p"=>"personal"),
				"p.userid=acl_users.id",
				array("fio"=>"CONCAT_WS(' ',family,name,otch)"));
		// добавим группу
		// 				$select->joinLeft(array("gru"=>"acl_groups_has_users"),
		// 						 "gru.acl_users_id=acl_users.id",
		// 						array("group"=>"acl_groups_id"));

		// 				echo $username; die();
		// а если отключена группа ?
		// 				$select->joinLeft(array("g"=>"acl_groups"),
		// 						"g.id=gru.acl_groups_id",
		// 						array("grTitle"=>"g.title"));
		// 				$select->where("g.disabled = 0");
		// 				$select->limit(1);

		// Set the input credential values
		// to authenticate against
		$authAdapter->setIdentity($username);
		$authAdapter->setCredential($password);
		return $authAdapter;
	}

}