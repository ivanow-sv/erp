<?php
class Sprav_HelpdeskconfigController extends Zend_Controller_Action
{
	protected 	$session;
	protected 	$baseLink;
	protected	$redirectLink; // ссылка в этот модуль/контроллер
	private 	$_model;
	private 	$hlp; // помощник действий Typic
	private 	$_author; // пользователь шо щаз залогинен
	private 	$confirmWord	=	"УДАЛИТЬ";

	function init()
	{
		// выясним название текущего модуля для путей в ссылках
		$currentModule=$this->_request->getModuleName();
		$this->view->currentModuleName=$currentModule;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->currentController = $this->_request->getControllerName();
		$this->baseLink=$this->_request->getBaseUrl()."/".$currentModule."/".$this->_request->getControllerName();
		$this->view->baseLink=$this->baseLink;
		$this->redirectLink=$this->_request->getModuleName()."/".$this->_request->getControllerName();
		$this->hlp=$this->_helper->getHelper('Typic');
		Zend_Loader::loadClass('Helpdesk');

		$groupEnv=Zend_Registry::get("groupEnv");
		//		$this->currentFacultId=$roleEnv['currentFacult'];
		$this->_model=new Helpdesk();
		$moduleTitle=Zend_Registry::get("ModuleTitle");
		$modContrTitle=Zend_Registry::get("ModuleControllerTitle");
		$this->view->title=$moduleTitle
		.". ".$modContrTitle.'. ';
		$this->view->addHelperPath('./application/views/helpers/','My_View_Helper');

		Zend_Loader::loadClass('Zend_Session');
		Zend_Loader::loadClass('Zend_Form');
		Zend_Loader::loadClass('Formochki');
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$this->session=new Zend_Session_Namespace('my');
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		//		$ajaxContext ->addActionContext('formchanged', 'json')->initContext('json');
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/helpdesk.js');
		//		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/styles/dekanat.css');
		$this->_author=Zend_Auth::getInstance()->getIdentity();
	}


	function indexAction()
	{
		// покажем перечень единиц техники
		$utypes=$this->_model->getUTypes();
		// 		print_r($utypes);
		$this->view->utypes=$utypes;
		// форма редактирования
		$this->view->formEdit=$this->createForm_editUType();;
		$this->view->formAdd=$this->createForm_addUType();
		$this->view->formDel=$this->createForm_deleteUType($utypes);
		$this->view->confirmWord=$this->confirmWord;
	}

	function addAction()
	{
		if (!$this->_request->isPost()) $this->_redirect($this->redirectLink);
		$form=$this->createForm_addUType();
		$params = $this->_request->getParams();
		$chk=$form->isValid($params);
		if ($chk)
		{
			$data["title"]=$params["title"];
			$data["tbl"]=$params["tbl"];
			$res=$this->_model->addUType($data);
			
			if ($res["status"]) $this->_redirect($this->redirectLink);
			else $this->view->msg="Ошибка БД. ".$res["msg"];
		}
		else
		{
			$this->view->msg="Неверные данные";
		}


	}

	function delAction()
	{
		if (!$this->_request->isPost()) $this->_redirect($this->redirectLink);
		$utypes=$this->_model->getUTypes();
		$form=$this->createForm_deleteUType($utypes);
		$params = $this->_request->getParams();
		$chk=$form->isValid($params);
		if ($chk)
		{
			$res=$this->_model->delUType($params[id]);

			if ($res["status"]) $this->_redirect($this->redirectLink);
			else $this->view->msg="Ошибка БД. ".$res["msg"];
		}
		else
		{
			$this->view->msg="Неверные данные";
		}


	}

	function editAction()
	{
		if (!$this->_request->isPost()) $this->_redirect($this->redirectLink);
		$form=$this->createForm_editUType();
		// найдем все существующие типы чтобы не править несуществующее
		$_ids=$this->_model->getUTypes();
		$ids=array();
		foreach ($_ids as $elem)
		{
			$ids[]=$elem["id"];
			;
		}
		// добавим валидатор
		$form->getElement("id")->addValidator("InArray",true,array($ids));
		// что пришло?
		$params = $this->_request->getParams();
		$chk=$form->isValid($params);
		if ($chk)
		{
			$data["title"]=$params["title"];
			$data["tbl"]=$params["tbl"];
			$res=$this->_model->editUType($data,$params["id"]);
			if ($res["status"]) $this->_redirect($this->redirectLink);
			else $this->view->msg="Ошибка БД. ".$res["msg"];

		}
		else
		{
			$this->view->msg="Неверные данные";
		}


	}
	
	
	private function createForm_deleteUType($utypes)
	{
		$form=new Formochki();
		$form->setAttrib('name','delForm');
		$form->setAttrib('id','delForm');
		$form->setMethod('POST');
		$form->setAction($this->view->baseUrl
				.'/'.$this->view->currentModuleName
				.'/'.$this->_request->getControllerName()
				.'/del'
		);
		$textOptions=array('class'=>'typic_input');
		
		// найдем все существующие типы 
		$ids=array();
		foreach ($utypes as $elem)
		{
			$ids[]=$elem["id"];
			;
		}
		$form->addElement("hidden","id");
		$form->getElement("id")
		->addValidator("NotEmpty",true)
		->setRequired(true)
		
		->addValidator("InArray",true,array($ids))
		;
		
		$form->addElement("text","confirm",$textOptions);
		$form->getElement("confirm")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		->addValidator("Identical",true,array("token"=>$this->confirmWord))		
		->setDescription("Подтверждение")
		;
		
		$form->addElement("submit","OK",array(
				"class"=>"apply_text"
		));
		$form->getElement("OK")->setName("Применить");
		return $form;
		;
		
	}

	private function createForm_addUType()
	{
		$form=$this->createForm_editUType();
		// форма создания нового типа единиц техники отличается чуток
		$form->setAttrib('name','addForm');
		$form->setAttrib('id','addForm');
		$form->setAction($this->view->baseUrl
				.'/'.$this->view->currentModuleName
				.'/'.$this->_request->getControllerName()
				.'/add'
		);
		$form->removeElement("id");

		return $form;
	}

	private function createForm_editUType()
	{
		$form=new Formochki();
		$form->setAttrib('name','editForm');
		$form->setAttrib('id','editForm');
		$form->setMethod('POST');
		$form->setAction($this->view->baseUrl
				.'/'.$this->view->currentModuleName
				.'/'.$this->_request->getControllerName()
				.'/edit'
		);
		$textOptions=array('class'=>'typic_input');

		$form->addElement("hidden","id");
		$form->getElement("id")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		;

		$form->addElement("text","title",$textOptions);
		$form->getElement("title")
		->addValidator("NotEmpty",true)
		->setRequired(true)
		->setDescription("Наименование")
		;
		$form->addElement("text","tbl",$textOptions);
		$form->getElement("tbl")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		->setDescription("Таблица")
		;

		$form->addElement("submit","OK",array(
				"class"=>"apply_text"
		));
		$form->getElement("OK")->setName("Применить");
		return $form;
		;
	}


}