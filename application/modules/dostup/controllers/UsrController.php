<?php
class Dostup_UsrController extends Zend_Controller_Action
{

	private $hereLink;
	private $fullLink;
	private $session;
	private $_model;
	private $_aclmodel;
	private $_modelGrp;
	private	$confirmWord="УДАЛИТЬ";
	private $_hlp;
	private $_hlpFrm;
	private $_author;
	private $groupCurrent;
	private $groupsAll;

	public function init()
	{
		// выясним название текущего модуля для путей в ссылках
		$this->view->baseUrl = $this->_request->getBaseUrl();
		
		$this->view->currentModuleName=$this->_request->getModuleName();
		$this->view->currentController = $this->_request->getControllerName();
		$this->fullLink=$this->_request->getBaseUrl()
		."/".$this->_request->getModuleName()
		."/".$this->_request->getControllerName();
		$this->view->fullLink=$this->fullLink;
		$this->hereLink=$this->_request->getModuleName()."/".$this->_request->getControllerName();
		$this->view->icoPath=$this->_request->getBaseUrl()."/public/images/";
		// классы
		Zend_Loader::loadClass('Users');
		Zend_Loader::loadClass('Zend_Form');
		Zend_Loader::loadClass('Formochki');
		Zend_Loader::loadClass('Aclgroups');
		Zend_Loader::loadClass('Aclmodel');
		Zend_Loader::loadClass('Zend_Session');

		// модель
		$this->_model=new Users;
		$this->_modelGrp=new Aclgroups;
		$this->_aclmodel=new Aclmodel;
		// помощники
		$this->_hlp=$this->_helper->getHelper('Typic');
		$this->_hlpFrm=$this->_helper->getHelper('Forms');
		// для списков формах - работа с БД
		$this->_hlpFrm->setDBadapter($this->_model->getDefaultAdapter());
		//активируем хэлпер AjaxContext
		//и передаём туда имя action'а, который необходимо интегрировать с AJAX'ом
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		$ajaxContext ->addActionContext('show', 'json')->initContext('json');
		$ajaxContext ->addActionContext('formchanged', 'json')->initContext('json');
		$ajaxContext ->addActionContext('state', 'json')->initContext('json');
		$ajaxContext ->addActionContext('showdetails', 'json')->initContext('json');
		$ajaxContext ->addActionContext('editdetails', 'json')->initContext('json');
		// HTML заголовки
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/dostup.js');

		// сессия
		$this->session=new Zend_Session_Namespace('my');
		// кто? мы
		$this->_author=Zend_Auth::getInstance()->getIdentity();


		// особенности
		$this->groupsAll=$this->_modelGrp->getGroupsAll();
		// группа по умолчанию - или из сессии или первая из списка
		$this->groupCurrent=array_key_exists($this->session->aclgroup, $this->groupsAll)
		? $this->session->aclgroup
		: $this->_hlp->getFirstElemKey($this->groupsAll);

	}

	public function indexAction ()
	{
		// фильтр групп
		$formFilter=$this->createForm_Filter($this->groupsAll);
		// были какието параметры
		$params=$this->_request->getParams();
		if (isset($params["aclgroup"]) && !empty($params["aclgroup"]) )
		{
			// проверим входящие параметры
			$chk=$formFilter->isValid($params);
			if ($chk)
			{
				// нормуль, зададим в сессию
				$this->groupCurrent=$formFilter->getValue("aclgroup");
				$this->session->aclgroup=$this->groupCurrent;

			}
			else
			{
				// возьмем по умолчанию
				$this->groupCurrent=$this->session->aclgroup;
			}
			// перейдем на нужный URL
			$this->_redirect($this->hereLink);
		}
		// зададим параметр в фильтре
		$formFilter->getElement("aclgroup")->setValue($this->groupCurrent);

		// список пользователей
		$this->view->items=$this->_model->getListInGroup($this->groupCurrent);

		// форма добавить пользователя
		$form=$this->_hlpFrm->add();
		$form->setAction($this->fullLink."/add");
		$this->view->formAdd=$form;

		// форма удалить
		$form=$this->_hlpFrm->del();
		$form->setAction($this->fullLink."/del");
		$this->view->formDel=$form;

		// форма перенос
		$formMove=$this->createForm_Move($this->groupsAll);
		$this->view->formMove=$formMove;

		// форма копировать
		$this->view->formCopy=$this->createForm_Copy($this->groupsAll);

		// форма редактировать
		// 		$formedit=$form=$this->_hlpFrm->userInfo();
		// // 		$formedit->setAction($this->fullLink."/edit");
		// 		$this->view->formedit=$formedit;

		$this->view->formFilter=$formFilter;

	}

	public function formchangedAction()
	{
		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->hereLink);

		// фильтр групп
		$formFilter=$this->createForm_Filter($this->groupsAll);
		$chk=$formFilter->isValid($this->_request->getParam("formData"));
		if (!$chk) $this->_redirect($this->hereLink);
		$this->groupCurrent=$formFilter->getValue("aclgroup");
		// запомним в сессии
		$this->session->aclgroup=$this->groupCurrent;
		// заменим элемент фильтра
		$out["aclgroup"]=$formFilter->getElement("aclgroup")->removeDecorator("Description")->render();
		// новый список пользователей
		$this->view->items=$this->_model->getListInGroup($this->groupCurrent);
		$out["userslist"]=$this->view->render($this->_request->getControllerName().'/_list.phtml');
		$this->view->out=$out;
		;
	}

	public function addAction()
	{
		if (!$this->_request->isPost()) $this->_redirect($this->hereLink);
		// форма
		$form=$this->_hlpFrm->add();
		$form->setAction($this->fullLink."/add");

		// валидатор заново
		$form->getElement("id")
		->setRequired(true)
		->addValidator("Digits",true)
		->addValidator("NotEmpty",true,array("integer","zero"))
		->addValidator("InArray",true,array(array_keys($this->groupsAll)))
		;
		$form->getElement("title")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		// @TODO валидаторы для логина
		->addValidator("StringLength",true,array('min' => 2))
		->addValidator("Regex",true,array('pattern' => '/^[a-zA-Z0-9]+$/'))
// 		->addValidator("Alnum",true,array('allowWhiteSpace' => false))
		->setDescription("Наименование");

		$params=$this->_request->getParams();
		$chk=$form->isValid($params);
		if (!$chk)
		{
			// показать ошибки
			$this->view->formAdd=$form;
			$this->view->msg="Ошибка при заполнении";

		}
		else
		{
			$values=$form->getValues();
			//	сделать чота полезное
			$res=$this->_model->addUserByLogin_v2($values["title"],$values["id"]);
			if ($res["status"])
			{
				/// нормуль перейдем к редактированию
				$this->_redirect($this->hereLink."/edit/userid/".$res["userid"]);
			}
			else
			{
				// показать ошибку
				$this->view->msg="Ошибка при работе с БД";
			}

		}

	}

	public function delAction()
	{
		;
	}

	public function showAction()
	{
		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->hereLink);

		$id=(int)$this->_request->getParam("id");
		// есть ли пользователь
		$info=$this->_model->getInfo(array($id));
		$info["userid"]=$info["id"];
		if (empty($info)) $this->_redirect($this->hereLink);

		$form=$this->_hlpFrm->userInfo();
		// заполним форму
		$form->setAction($this->fullLink."/edit");
		$form->populate($info);
		$emails=$this->_model->getEmails($id);
		$this->view->emails=$emails;
		// покажем
		$this->view->info=$info;
		$this->view->formedit=$form;
		$this->view->formEditWrapper=$this->view->render($this->_request->getControllerName().'/_formEdit.phtml');
	}

	/**
	 * правка информации о пользователе
	 *  
	 */
	public function editAction()
	{
		$id=(int)$this->_request->getParam("userid");
		// есть ли пользователь
		$info=$this->_model->getInfo(array($id));
// 		$logger=Zend_Registry::get("logger");
// 		$logger->log($info,Zend_Log::INFO);
// 		$logger->log(is_null($info["family"]),Zend_Log::INFO);
		if (empty($info)) $this->_redirect($this->hereLink);
		// электронные адреса
		$emails=$this->_model->getEmails($id);
		$this->view->emails=$emails;
		// если отправлялась форма
		if ($this->_request->isPost())
		{
			$params=$this->_request->getParams();
			// 			$this->_redirect($this->hereLink);
		}
		// остальные случаи, в т.ч. по ссылке
		else
		{
			$params=$info;
			$params["userid"]=$id;
		}
		// 		echo "<pre>".print_r($params,true)."</pre>";
		$form=$this->_hlpFrm->userInfo();
		$chk=$form->isValid($params);
		if (!$chk)
		{
			// показать ошибки
			$this->view->formedit=$form;
			$this->view->msg="Ошибка при заполнении";
		}
		else
		{
			$values=$form->getValues();
			// сделаем чонить полезное
			$loginInfo=array(
					'login'		=>	$values['login'],
					'role'		=>	null,
					'disabled'	=>	$values['disabled'],
					'comment'	=>	$values['comment']
			);
			// сменился пароль
			if (!empty($values['pass'])) $loginInfo['pass']=	md5($values['pass']);
			$privateInfo=array(
					"family"	=>$values["family"],
					"name"		=>$values["name"],
					"otch"		=>$values["otch"],
			);
			// надо ли создавать ФИО
			$_prvFlag=is_null($info["family"])?true:false;
			$res=$this->_model->setInfoMinimal($id,$loginInfo,$privateInfo,$values["email"],$_prvFlag);

			// ответ БД
			if ($res["status"])
			{
				/// нормуль
				// 				$this->_redirect($this->hereLink);
			}
			else
			{
				// показать ошибку
				$this->view->msg="Ошибка при работе с БД:<br>".$res["errorMsg"];
			}

		}
		$this->view->formedit=$form;


	}

	/**
	 * отображение назначенных ролей выбранной группе
	 */
	public function showdetailsAction()
	{
		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->hereLink);
		$form=$this->createForm_RolesManage();
		$form->getElement("aclgroup")->setValue($this->groupCurrent);
		$this->view->formManage=$form;
		$this->view->rolesManage=$this->view->render($this->_request->getControllerName().'/_formDetails.phtml')
		
		;
	}
	
	
	/**
	 * выбор ролей для данной группы 
	 */
	public function editdetailsAction()
	{
		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->hereLink);
		$group=(int)$this->_request->getPost("id");
		$added=$this->_request->getPost("added");
		$roles=$this->_aclmodel->getRoles();
		// роли и группа нужные?
		// найдем среди выбранных ролей те, что действительно сущестуют
		$_added=array_intersect($added,array_keys($roles));
		$chk=$group==$this->groupCurrent && (!empty($_added)); 
		if (!$chk) $this->_redirect($this->hereLink);
		
		// дальше все ок - уберется старое, новое добавится
		$res=$this->_aclmodel->setRoles2Group($group,$added);
		if ($res["status"])
		{
			/// нормуль перейдем к редактированию
// 			$this->_redirect($this->hereLink."/edit/userid/".$res["userid"]);
			$this->view->OK=true;
			$this->view->msg="Успешно";
		}
		else
		{
			// показать ошибку
			$this->view->OK=false;
			$this->view->msg="Ошибка при работе с БД:<br>".$res["errorMsg"];			
			
		}
		
		;
	}

	public function moveAction()
	{
		if (!$this->_request->isPost()) $this->_redirect($this->hereLink);
		// форма
		$form=$this->createForm_Move($this->groupsAll);

		$params=$this->_request->getParams();
		$chk=$form->isValid($params);
		if (!$chk)
		{
			// показать ошибки
			$this->view->formAdd=$form;
			$this->view->msg="Ошибка при заполнении";

		}
		else
		{
			$values=$form->getValues();

			// найдем пользователя в БД в группе
			$info=$this->_model->getInfo($values["id"],$this->groupCurrent);
			// если не найден - послать
			if (empty($info))
			{
				$this->view->msg="Пользователь в указанной группе не обнаружен";
			}
			else
			{
				// все OK - сделать чота полезное
				$res=$this->_model->moveUsers($values["id"], $values["destination"],$this->groupCurrent);
				if ($res["status"])
				{
					/// нормуль
					// перейдем в группу получатель
					$this->session->aclgroup=$values["destination"];
					$this->_redirect($this->hereLink);
				}
				else
				{
					// показать ошибку
					$this->view->msg="Ошибка при работе с БД";
				}

			}


		}

		;
	}

	public function copyAction()
	{
		;
	}


	public function stateAction()
	{
		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->hereLink);
		$id=$this->_request->getPost("id");
		$state=(int)$this->_request->getPost("state");
		$state=$state===0?0:1;
		$info=$this->_model->getInfo(array($id));
		if (empty($info)) $this->_redirect($this->hereLink);
		if ($state==1)
		{
			$dis="disabled";
			$stateIco="lockedIcoSmall";
			$stateTitle="Отключено. Включить";
			$stateNew="0";
			$stateClass="disabled";
			$stateIco="lockedIcoSmall";
		}
		else
		{
			$dis="";
			$stateIco="unlockedIcoSmall";
			$stateTitle="Включено. Отключить";
			$stateNew="1";
			$stateClass="enabled";
			$stateIco="unlockedIcoSmall";
		}
		$this->_model->setState(array($id),$state);
		$this->view->id=$id;
		$this->view->newbutton='<span class="state '.$stateIco.' inlineLink" title="'.$stateTitle.'"
		onclick="usrState('.$id.','.$stateNew.');">		</span>';

		;
	}

	private function createForm_Move($list)
	{
		$_lisTree=$this->_hlp->treeArray($list);
		$_groups=$this->_hlp->itemsForSelect($_lisTree,false);
		// форма перенос
		$form=$this->_hlpFrm->move($_groups);
		$form->setAction($this->fullLink."/move");

		return $form;
	}


	private function createForm_Copy($list)
	{
		$_lisTree=$this->_hlp->treeArray($list);
		$_groups=$this->_hlp->itemsForSelect($_lisTree,false);
		// форма копирования
		$form=$this->_hlpFrm->move($_groups);
		$form->setAttrib('name','copy');
		$form->setAttrib('id','copy');
		$form->setAction($this->fullLink."/copy");

		return $form;
	}

	private function createForm_Edit()
	{
		$_lisTree=$this->_hlp->treeArray($list);
		$_groups=$this->_hlp->itemsForSelect($_lisTree,false);
		// форма копирования
		$form=$this->_hlpFrm->move($_groups);
		$form->setAttrib('name','copy');
		$form->setAttrib('id','copy');
		$form->setAction($this->fullLink."/copy");

		return $form;
	}

	private function createForm_RolesManage()
	{
		$form=new Formochki();
		$form->setAttrib('name','rolesManage');
		$form->setAttrib('id','rolesManage');
		$form->setMethod('POST');
		$form->setAction($this->fullLink."/editdetails");

		$groups=$this->groupsAll;
		$form->addElement("hidden","aclgroup");
		$form->getElement("aclgroup")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		->addValidator("Digits",true)
		->addValidator("InArray",true,array_keys($groups));
		;
		
		// все роли
		$roles=$this->_aclmodel->getRoles();
		// роли приписаныне к данной группе
		$r=$this->_aclmodel->getGroupRoles($this->groupCurrent);
		$_added=$this->_hlp->createMultiselectList("added",$r);
// 		$form->getElement("added")->setOptions(array("class"=>"medinput"));
		
		$form->addElement($_added);

		// остальные роли
		$rr=array_diff($roles,$r);
		$_other=$this->_hlp->createMultiselectList("other",$rr);
		$form->addElement($_other);

// 		$form->addElement("button","btnAdd",array("name"=>"Добавить"));
// 		$form->addElement("button","btnRem",array("name"=>"Убрать"));
// 		$form->addElement("reset","RES",array("class"=>"apply_text"));
// 		$form->getElement("RES")->setName("Вернуть");
		
// 		$form->addElement("button","OK",array("class"=>"apply_text"));
// 		$form->getElement("OK")->setName("Сохранить");
		
		return $form;
				;
	}

	private function createForm_Filter($list)
	{
		$form=new Formochki();
		$form->setAttrib('name','filterForm');
		$form->setAttrib('id','filterForm');
		$form->setMethod('POST');
		$form->setAction($this->fullLink);

		$_list=$this->_hlp->treeArray($list);
		$_groups=$this->_hlp->itemsForSelect($_list,false);
		$groups=$this->_hlp->createSelectList("aclgroup",$_groups);
		$form->addElement($groups);
		$form->getElement("aclgroup")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		->addValidator("InArray",true,array(array_keys($list)))
		->setDescription("Группа пользователей")
		;
		return $form;
	}

	private function sessionUpdate($params)
	{

	}

	private function buildCriteria($in=null)
	{
		if (is_null($in)) $in=$this->session->getIterator();
		$criteria['group']=( !isset($in['group']) || $in['group'] < 0)
		?	1
		:	$in['group']
		;
		return $criteria;
	}

}