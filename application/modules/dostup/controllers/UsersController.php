<?php
// @TODO - сделать использование гостей - ID=0

class Dostup_UsersController extends Zend_Controller_Action
{

	private $redirectLink;
	
	public function init()
	{
		// выясним название текущего модуля для путей в ссылках
		$currentModule=$this->_request->getModuleName();
		$this->view->currentModuleName=$currentModule;
		$this->view->currentController=$this->_request->getControllerName();
		$this->view->currentAction=$this->_request->getActionName();
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->title='Управление доступом. Пользователи. ';
		$this->redirectLink=$this->_request->getModuleName()."/".$this->_request->getControllerName();
		
		Zend_Loader::loadClass('Users');
		$this->_model=new Users;

		//активируем хэлпер AjaxContext
		//и передаём туда имя action'а, который необходимо интегрировать с AJAX'ом
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		$ajaxContext ->addActionContext('index', 'json')->initContext('json');

		Zend_Loader::loadClass('Zend_Session');
		$this->filterSession=new Zend_Session_Namespace('my');

	}

	/**
	 *
	 * покажем роли
	 *
	 */
	public function indexAction ()
	{
		// получим данные из запроса
		$params = $this->_request->getParams();

		//		echo "<pre>".print_r($params,true)."</pre>";
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$filter = new Zend_Filter_StripTags();
		foreach ($params as $param)
		{
			$param=$filter->filter($param);
		}

		// форма фильтра
		Zend_Loader::loadClass('Formochki');
		$form=new Formochki;
		$form->setAttrib('name','userFilterForm');
		$form->setAttrib('id','userFilterForm');
		$form->setMethod('GET');
		$form->setAction($this->view->baseUrl.'/'.$this->redirectLink);

		// Если фильтр отправлен через форму
		if ($params["filterActivated"]==1)
		{
			//	обновить сессию
			foreach ($params as $param=>$value)
			{
				$this->filterSession->$param=$value;
			}
		}
		// иначе брать из сессии или установим по умолчанию
		else
		{
			// а в сессии есть данные о фильтре? если нет, то нада шобы стало по умолчанию
			if ($this->filterSession->filterActivated!=1)
			{
				$this->filterSession->filterLogin=''; //пустое поле
				$this->filterSession->filterRole='1'; //администраторы
				$this->filterSession->filterDisabled='0'; //активные
				$this->filterSession->filterComment=''; //активные

			}

		}
		$form->addElement('hidden','filterActivated',array('value'=>1));
		$form->addElement('text','filterLogin',array('class'=>'medinput', 'value'=>$this->filterSession->filterLogin));
		$form->addElement('text','filterComment',array('class'=>'medinput', 'value'=>$this->filterSession->filterComment));

		// список групп
		$roleList=$this->_model->getRolesAll();
		// сообразим нужный массив
		$roleList =$this->treeArray($roleList);
		$roleList =$this->rolesForSelect($roleList,$this->filterSession->filterRole);

		//			$logger = Zend_Registry::get('logger');
		//
		//			$logger->log($roleList, Zend_Log::INFO);

		Zend_Loader::loadClass('Zend_Form_Element_Select');
		$roleSelect = new Zend_Form_Element_Select('filterRole');
		//				$roleList=array(0=>"гость", 1=>"Адми","sss"=>array(1=>"aaaa",3=>"bbb"));

		foreach ($roleList as $key=>$value)
		{
			$roleSelect ->addMultiOption($key,$value);
		}
		// выбранное значение SELECTED
		$roleSelect ->setValue($this->filterSession->filterRole);
		$roleSelect ->removeDecorator('Label');
		$roleSelect ->removeDecorator('HtmlTag');
			
		$form->addElement($roleSelect );

		$disabledSelect=new Zend_Form_Element_Select('filterDisabled');
		$disabledSelect->addMultiOption(0,'Активные');
		$disabledSelect->addMultiOption(1,'Отключенные');
		$disabledSelect->addMultiOption(2,'Все');
		$disabledSelect->setValue($this->filterSession->filterDisabled);
		$disabledSelect->removeDecorator('Label');
		$disabledSelect->removeDecorator('HtmlTag');
		//		$disabledSelect->setAttrib('onChange','userFilterChanged()');
		$form->addElement($disabledSelect);

		//		$form->addElement('button','filterSubmit',
		//		array(
		//		'title'=>'Применить',
		//		'class'=>'apply_button',
		//		)
		//		);
		//		$form->getElement('filterSubmit')->setAttrib('onSubmit','userFilterChanged()');
		//		$form->getElement('filterSubmit')->setAttrib('value','a');


		$this->view->userFilterForm=$form;

		$rows=$this->_model->getList($this->filterSession);
		$this->view->items=$rows;

	}

	/**
	 * добавим роль
	 */
	public function addAction()
	{
		// куда по умаолчанию пойдем для продолжения работы
		// переход на управление ролями
//		$redirectLink=$this->view->baseUrl.'/'.$this->view->currentModuleName.'/'.$this->view->currentController;
		$redirectLink=$this->redirectLink;
		// заголовок страницы
		$this->view->title.="Добавлено новое";
		if ($this->_request->isPost()) {
			// получим и отфильтруем название
			Zend_Loader::loadClass('Zend_Filter_StripTags');
			$filter = new Zend_Filter_StripTags();
			$loginName = $filter->filter($this->_request->getPost('loginName'));
			$loginName = trim($loginName);
			$this->view->fieldloginName=$loginName;
			// если оно НЕ пусто
			if ($loginName != '') {
				// Если активен фильттр по роли
				if (isset($this->filterSession->filterRole)) $role=$this->filterSession->filterRole;
				// добавим и узнаем ID
				$addedId=$this->_model->addUserByLogin($loginName,$role);
				// добавлено, направим к редактированию
				$redirectLink=$this->redirectLink.'/edit/id/'.$addedId;
			}


		}
		// продолжим работу
		// перейдем к редактированию новой роли
		$this->_redirect($redirectLink);
	}

	/**
	 * удаление существующего
	 */
	public function deleteAction()
	{
		$id = (int)$this->_request->getParam('id',0);
		if ($id!=0)
		{
			$this->_model->deleteUser($id);

		}

		// перенаправим
//		$redirectLink=$this->view->baseUrl.'/'.$this->view->currentModuleName.'/'.$this->view->currentController;
		$this->_redirect($this->redirectLink);


	}


	public function editprivateAction()
	{


		$id = (int)$this->_request->getParam('id',0);
		if ($id!==0)
		{
//			$selfLink=$this->view->baseUrl.'/'.$this->view->currentModuleName.'/'.$this->view->currentController.'/'.$this->view->currentAction.'/id/'.$id;
			$selfLink=$this->redirectLink.'/'.$this->view->currentAction.'/id/'.$id;
			// если отправлена форма
			// получим параметры и внесем в БД
			if ($this->_request->isPost())
			{
				// получим данные
				$params = $this->_request->getPost();
				Zend_Loader::loadClass('Zend_Filter_StripTags');
				$filter = new Zend_Filter_StripTags();
				foreach ($params as $param)
				{
					$param=$filter->filter($param);
				}
				// @TODO внести информацию
				$this->_model->setInfoPrivate($params);
				// исправили, направим к редактированию сябя же
					
				$this->_redirect($selfLink);

			}
			else
			{
				$info=$this->_model->personalInfoGet($id);
				$loginInfo=$this->_model->getInfo($id);
				$this->view->title.="Личные данные уч. записи ".$loginInfo["login"];
				//построим форму
				Zend_Loader::loadClass('Formochki');
				$form=new Formochki;

				$form->setAttrib('name','privateEditForm');
				$form->setAttrib('id','privateEditForm');
				$form->setMethod('post');
				$form->setAction($this->view->baseUrl.'/'.$selfLink);
					
				$form->addElement('hidden','id',array('value'=>$id));
				$form->addElement('text','loginName',array('class'=>'medinput', 'value'=>$loginInfo["login"]));
				$form->addElement('text','family',array('class'=>'medinput', 'value'=>$info["family"]));
				$form->addElement('text','name',array('class'=>'medinput', 'value'=>$info["name"]));
				$form->addElement('text','otch',array('class'=>'medinput', 'value'=>$info["otch"]));
					
				$genders=$this->_model->getInfoForSelectList('gender');
				$genderList=$this->createSelectList('gender',$genders,$info["gender"]);
				$form->addElement($genderList  );

				$idents=$this->_model->getInfoForSelectList('identity');
				$List=$this->createSelectList('identity',$idents,$info["identity"]);
				$form->addElement($List  );
				$form->addElement('text','iden_serial',array('class'=>'medinput', 'value'=>$info["iden_serial"]));
				$form->addElement('text','iden_num',array('class'=>'medinput', 'value'=>$info["iden_num"]));
				$form->addElement('textarea','iden_give',array('class'=>'medinput', 'value'=>$info["iden_give"]));
				$form->addElement('textarea','iden_reg',array('class'=>'medinput', 'value'=>$info["iden_reg"]));
				$src=$this->_model->getInfoForSelectList('iden_live');
				$List=$this->createSelectList('iden_live',$src,$info["iden_live"]);
				$form->addElement($List  );
				$form->addElement('text','birth_date',array('class'=>'medinput', 'value'=>$info["birth_date"]));
				$form->addElement('textarea','birth_place',array('class'=>'medinput', 'value'=>$info["birth_place"]));
				//			$form->addElement('textarea','iden_live',array('class'=>'medinput', 'value'=>$info["iden_live"]));
				$src=$this->_model->getInfoForSelectList('edu_docs');
				$List=$this->createSelectList('edu_doc',$src,$info["edu_doc"]);
				$form->addElement($List  );
				$form->addElement('text','edu_serial',array('class'=>'medinput', 'value'=>$info["edu_serial"]));
				$form->addElement('text','edu_num',array('class'=>'medinput', 'value'=>$info["edu_num"]));
				$form->addElement('textarea','edu_give',array('class'=>'medinput', 'value'=>$info["edu_give"]));
				$form->addElement('text','edu_date',array('class'=>'medinput', 'value'=>$info["edu_date"]));
				$form->addElement('text','edu_res',array('class'=>'medinput', 'value'=>$info["edu_res"]));
					
				$form->addElement('text','edu_info',array('class'=>'medinput', 'value'=>$info["edu_info"]));
				// -----------
				$src=$this->_model->getInfoForSelectList('categories');
				$List=$this->createSelectList('category',$src,$info["category"]);
				$form->addElement($List  );
				$form->addElement('textarea','category_detail',array('class'=>'medinput', 'value'=>$info["category_detail"]));
				$src=$this->_model->getInfoForSelectList('awards');
				$List=$this->createSelectList('award',$src,$info["award"]);
				$form->addElement($List  );
				$form->addElement('textarea','olympic_detail',array('class'=>'medinput', 'value'=>$info["olympic_detail"]));
					
				$form->addElement('text','abitur_year',array('class'=>'medinput', 'value'=>$info["abitur_year"]));
				$form->addElement('text','abitur_id',array('class'=>'medinput', 'value'=>$info["abitur_id"]));
					
				$form->addElement('submit','formSubmit',array('name'=>'Сохранить'));
					
				$this->view->privateForm=$form;
			}
		}

		// перенаправим
		//		$redirectLink=$this->view->baseUrl.'/'.$this->view->currentModuleName.'/'.$this->view->currentController;
		//		$this->_redirect($redirectLink);

	}

	/**
	 * правка существующего
	 */
	public function editAction()
	{
		$this->view->title.="Правка";
		// @TODO ссылка на уровень верх
		$parentLink= $this->redirectLink;
		$this->view->parentLink=$this->view->baseUrl.'/'.$parentLink;

		$id = (int)$this->_request->getParam('id',0);
		//		echo $id;
		//		die();
		if ($id!==0)
		{
			// ссылка "сюда же". Нужна после внесения изменений и чтобы перезагрузить форму с данными из БД
			$selfLink=$this->redirectLink;//.'/'.$this->view->currentAction.'/id/'.$id;
			$this->view->selfLink=$this->view->baseUrl.'/'.$selfLink;

			// ссылка на редактирование личных данных
			$editPrivateLink=$this->view->baseUrl
			.'/'.$this->view->currentModuleName
			.'/'.$this->view->currentController
			.'/'.'editprivate'
			.'/'.'id'
			.'/'.$id
			;
			$this->view->editPrivateLink=$editPrivateLink;
				
			// если отправлена форма
			// получим параметры и внесем в БД
			if ($this->_request->isPost())
			{
				// получим данные
				$params = $this->_request->getPost();
				Zend_Loader::loadClass('Zend_Filter_StripTags');
				$filter = new Zend_Filter_StripTags();
				foreach ($params as $param)
				{
					$param=$filter->filter($param);
				}
				$this->_model->setInfo($params);
				// исправили, направим к редактированию сябя же
					
				$this->_redirect($selfLink);


			}
			// если просто зашли
			// берем данные и рисуем форму
			else
			{
				// @TODO секьюрность пароля и проверка полей на корретность
				$info=$this->_model->getInfo($id);
				$this->view->title.=" <".$info["login"].">";

				//	сообразим форму
				Zend_Loader::loadClass('Formochki');
				$form=new Formochki;
				$form->setAttrib('name','userEditForm');
				$form->setAttrib('id','userEditForm');
				$form->setMethod('post');
				$form->setAction($this->view->baseUrl.'/'.$this->redirectLink.'/'.$this->view->currentAction);

				$form->addElement('hidden','id',array('value'=>$id));
				$form->addElement('text','loginName',array('class'=>'medinput', 'value'=>$info["login"]));
				$form->addElement('password','pass',array('class'=>'medinput'));
				// список групп
				// список групп
				$roleList=$this->_model->getRolesAll();
				// сообразим нужный массив
				$roleList =$this->treeArray($roleList);
				$roleList =$this->rolesForSelect($roleList,$this->filterSession->filterRole);

				//			$logger = Zend_Registry::get('logger');
				//
				//			$logger->log($roleList, Zend_Log::INFO);

				Zend_Loader::loadClass('Zend_Form_Element_Select');
				$roleSelect = new Zend_Form_Element_Select('role');
				foreach ($roleList as $key=>$value)
				{
					$roleSelect ->addMultiOption($key,$value);
				}
				// выбранное значение SELECTED
				$roleSelect ->setValue($info["role"]);
				$roleSelect ->removeDecorator('Label');
				$roleSelect ->removeDecorator('HtmlTag');
				$form->addElement($roleSelect );

				$form->addElement('textarea','comment',array('class'=>'medinput', 'value'=>$info["comment"]));
				$form->addElement('checkbox','disabled');
				$form->getElement('disabled')->setValue($info["disabled"]);;

				$form->addElement('submit','formSubmit',array('name'=>'Сохранить'));

				$this->view->form=$form;
			}

		}

	}

	public function enableAction()
	{
		$id = (int)$this->_request->getParam('id',0);
		if ($id!==0)
		{
			$this->_model->enableUser($id);
		}
		// перенаправим
//		$redirectLink=$this->view->baseUrl.'/'.$this->view->currentModuleName.'/'.$this->view->currentController;
		$this->_redirect($this->redirectLink);

	}

	public function disableAction()
	{
		$id = (int)$this->_request->getParam('id',0);
		if ($id!==0)
		{
			$this->_model->disableUser($id);
		}
		// перенаправим
//		$redirectLink=$this->view->baseUrl.'/'.$this->view->currentModuleName.'/'.$this->view->currentController;
		$this->_redirect($this->redirectLink);

	}

	/**
	 * функция для рекурсии
	 * для переделки из древовидного многоуровневого массива массив для выпадающего списка
	 * @param array $item
	 * @param string $deep показатель глубины
	 * @param array $result
	 */
	private function selecElem(&$item,&$deep,&$result,$selected)
	{
		if (is_array($item))
		{
			$result[$item["id"]]=$deep.$item["title"];
			if ($selected==$item["id"]) $result[$item["id"]].=" <==";
		}
		if (isset($item["child"]))
		{
			// если нет родителей - занчит начало ветки
			if (is_null($item["parent"])) $deep="";

			// переберем "детей"
			$childs=explode(" ",$item["child"]);
			foreach ($childs as $name)
			{
				// если нижеследующий прямой потомок
				if ($item[$name]["parent"]==$item['id']) $dp="|".ltrim($deep."--","|");
				else $dp=$deep;
				$this->selecElem($item[$name],$dp,$result,$selected);
			}


		}

	}

	/**
	 * делает из древовидного многоуровневого массива массив для выпадающего списка
	 * @param array $rows в виде дерева
	 * @return Array
	 */
	private function rolesForSelect($rows,$selected=0)
	{
		$deep='';
		$result='';
		foreach ($rows as $k=>$item)
		{
			$this->selecElem($item,$deep,$result,$selected);
		}
		return($result);
	}

	/**
	 * делает из входного массива древовидный
	 * @param array $rows данные из БД
	 * @return array
	 */
	private function treeArray($rows)
	{
		$items=array();
		// верхний цикл
		foreach ($rows as $kk=>$row)
		{
			$aaa=array();
			$aaa["id"]=$row['id'];
			$aaa["title"]=$row['title'];
			$aaa["comment"]=$row['comment'];
			$aaa["disabled"]=$row['disabled'];
			$aaa["parent"]=$row['parent'];

			// это чей-то наследник?
			if (!is_null($row['parent']))
			{
				$this->appendChilds(&$items,$aaa,$row['parent']);
			}
			else
			{
				$items[$row['id']]=$aaa;
			}
		}

		return $items;
	}

	/**
	 * добавляет детей к элементу массива
	 * @param array $inArrayв какой массив добавляется
	 * @param array $ArrayAdd массив, который пристыкуется
	 * @param unknown_type $key "точка монтирования" :)
	 */
	private function appendChilds(&$inArray,$ArrayAdd,$key)
	{
		//				if ($ArrayAdd["id"]==18) echo "<pre>".print_r($inArray,true)."</pre>";
		//				if ($key==0) echo "<pre>".print_r($inArray,true)."</pre>";
		// если это массив - переберем его
		if (is_array($inArray))
		{
			//			if ($inArray["id"]===$key ) $inArray[$key][$ArrayAdd["id"]]=$ArrayAdd;
			//			echo "<pre>".print_r($inArray,true)."</pre>";
			// перебор вот он, значок амперсант важен
			foreach ($inArray as $kk=>&$subArray)
			{
				// если искомое - добавим
				if ($subArray["id"]==$key && is_array($subArray))
				{
					$subArray["child"]=trim($subArray["child"]." ".$ArrayAdd["id"]);
					$subArray[$ArrayAdd["id"]]=$ArrayAdd;
				}
				// если и он массив - то опять ввызов
				if (is_array($subArray))$this->appendChilds($subArray,$ArrayAdd,$key);
				//				else $this->appendChild($subArray,$ArrayAdd,$key);
			}
		}
		//		continue;
	}

	/**
	 * построить список выбора
	 * @param string $elemName
	 * @param array $src data ID=>value
	 * @param integer $defaultValue selected option
	 * @return Zend_Form_Element_Select
	 */
	private function createSelectList($elemName,$src,$selected=false)
	{
		Zend_Loader::loadClass('Zend_Form_Element_Select');
		$result = new Zend_Form_Element_Select($elemName);
		foreach ($src as $key=>$value)
		{
			$result ->addMultiOption($key,$value);
		}
		// выбранное значение SELECTED
		if ($selected !==false) $result  ->setValue($selected);
		$result  ->removeDecorator('Label');
		$result  ->removeDecorator('HtmlTag');
		return $result;
	}

}