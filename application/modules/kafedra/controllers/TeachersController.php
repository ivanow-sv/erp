<?php
class Kafedra_TeachersController extends Zend_Controller_Action
{
	protected 	$curKafedra;
	protected  	$_model;
	private 	$kafInfo;
	private 	$hlp; // помощник действий Typic
	private 	$studyYear_now;
	private 	$currentDivision	=	1;
	private		$session;
	private		$baseLink;
	private		$redirectLink;
	private 	$_author; // пользователь шо щаз залогинен
	private 	$confirmWord="УДАЛИТЬ";
	private 	$disciplines; // перечень дисциплин данной кафедры

	public function init()
	{
		// выясним название текущего модуля для путей в ссылках
		$currentModule=$this->_request->getModuleName();
		$this->view->currentModuleName=$currentModule;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->iconpath= $this->view->baseUrl."/"."public"."/"."images"."/";

		$this->view->currentController = $this->_request->getControllerName();
		$this->baseLink=$this->_request->getBaseUrl()."/".$currentModule."/".$this->_request->getControllerName();
		$this->view->baseLink=$this->baseLink;
		$this->redirectLink=$this->_request->getModuleName()."/".$this->_request->getControllerName();
		//		Zend_Controller_Action_HelperBroker::addPrefix('My_Helper');
		$this->hlp=$this->_helper->getHelper('Typic');

		Zend_Loader::loadClass('Kafedra');
		$groupEnv=Zend_Registry::get("groupEnv");
		//		print_r($roleEnv);
		$this->curKafedra=$groupEnv['kafedra'];
		$this->_model=new Kafedra($this->curKafedra);

		$moduleTitle=Zend_Registry::get("ModuleTitle");
		$modContrTitle=Zend_Registry::get("ModuleControllerTitle");
		$this->kafInfo=$this->_model->getInfo();
		$this->view->title=$moduleTitle." "
		.$this->kafInfo['title']
		.". ".$modContrTitle.'. ';

		$this->disciplines=$this->_model->getDisciplines();

		Zend_Loader::loadClass('Zend_Session');
		Zend_Loader::loadClass('Zend_Form');
		Zend_Loader::loadClass('Formochki');
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$this->session=new Zend_Session_Namespace('my');
		$ajaxContext = $this->_helper->getHelper('AjaxContext');

		$this->studyYear_now=$this->_model->getStudyYear_Now();
		$this->_author=Zend_Auth::getInstance()->getIdentity();
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/kafedra.js');

	}

	public function indexAction ()
	{
		// составим перечень дисциплин кафедры
		$this->view->dis=$this->disciplines;
		// узнаем существующие привязки
		$disAssoc=$this->_model->getDisciplinesAssoc();
		// переделаем массив, в 2-мерный, чтобы по ключу дисциплины выяснять её преподов
		$_disASssoc=array();
		foreach ($disAssoc as $key=>$item)
		{
			$_disASssoc[$item["discipline"]][]=$item;
			;
		}
		$this->view->disAssoc=$_disASssoc;
		$this->view->formAdd=$this->createForm_assign();
		$this->view->formDel=$this->createForm_unassign();
		$this->view->confirmWord=$this->confirmWord;
	}

	public function addAction()
	{
		if (!$this->_request->isPost()) $this->_redirect($this->redirectLink);
		$form=$this->createForm_assign();
		$params = $this->_request->getParams();
		$chk=$form->isValid($params);
		if ($chk)
		{
			$discipline=$form->discipline->getValue();
			$teacher=$form->teacher->getValue();
			$kafTeachers=$this->_model->getUsersList($this->kafInfo["id"]);
			// если посторонний - нах
			$_chk=array_key_exists($discipline, $this->disciplines)
			&& array_key_exists($teacher, $kafTeachers);
			if (!$_chk)$this->_redirect($this->redirectLink);

			// проверим привязку
			$chkAssign=$this->_model->teacherAssignCheck($teacher,$discipline);
			if (count($chkAssign)>0)
			{
				// сообщим если уже есть
				$msg="Уже есть такая запись";
			}
			else
			{
				// привяжем
				$rez=$this->_model->teacherAssign($teacher,$discipline);
				// перейдем к списку
				$this->_redirect($this->redirectLink);
			}

		}
		else
		{
			$msg=$form->getMessages();
		}
		$this->view->msg=$msg;
		;
	}

	public function delAction()
	{
		if (!$this->_request->isPost()) $this->_redirect($this->redirectLink);
		$form=$this->createForm_unassign();
		$params = $this->_request->getParams();
		$chk=$form->isValid($params);
		if ($chk)
		{
			$discipline=$form->discipline->getValue();
			$teacher=$form->teacher->getValue();
			$kafTeachers=$this->_model->getUsersList($this->kafInfo["id"]);
			// если посторонний - нах
			$_chk=array_key_exists($discipline, $this->disciplines)
			&& array_key_exists($teacher, $kafTeachers);
			if (!$_chk)$this->_redirect($this->redirectLink);

			$rez=$this->_model->teacherUnassign($teacher,$discipline);
			// перейдем к списку
			$this->_redirect($this->redirectLink);
				
		}
		else
		{
			$msg=$form->getMessages();
		}
		$this->view->msg=$msg;
			
		;
	}

	private function createForm_assign()
	{
		$formNew=new Formochki();
		$formNew->setAttrib('name','addForm');
		$formNew->setAttrib('id','addForm');
		//		$formNew->setAttrib('enctype', 'multipart/form-data');
		$formNew->setMethod('POST');
		$formNew->setAction($this->baseLink."/"."add");

		$formNew->addElement("hidden","discipline");
		$formNew->getElement("discipline")
		->setRequired(true)
		->addValidator('NotEmpty');

		$src=$this->_model->getUsersList($this->kafInfo["id"]);
		$teachers=$this->hlp->createSelectList("teacher",$this->teachersListPrepare($src));
		//		echo "<pre>".print_r($src,true)."</pre>";
		$formNew->addElement($teachers);
		$formNew->getElement("teacher")
		->setRequired(true)
		->addValidator('NotEmpty');

		$formNew->addElement("submit","OK",array("class"=>"apply_text"));
		$formNew->getElement("OK")->setName("ПРИМЕНИТЬ");


		return $formNew;
		;
	}

	private function createForm_unassign()
	{
		$formNew=new Formochki();
		$formNew->setAttrib('name','delForm');
		$formNew->setAttrib('id','delForm');
		//		$formNew->setAttrib('enctype', 'multipart/form-data');
		$formNew->setMethod('POST');
		$formNew->setAction($this->baseLink."/"."del");

		$formNew->addElement("hidden","discipline");
		$formNew->getElement("discipline")
		->setRequired(true)
		->addValidator('NotEmpty');

		$formNew->addElement("hidden","teacher");
		$formNew->getElement("teacher")
		->setRequired(true)
		->addValidator('NotEmpty');

		$formNew->addElement("text","confirmWord",array("class"=>"typic_input"));
		$formNew->getElement("confirmWord")
		->setRequired(true)
		->addValidator('NotEmpty')
		->addValidator('Identical',true,array("token"=>$this->confirmWord));


		$formNew->addElement("submit","OK",array("class"=>"apply_text"));
		$formNew->getElement("OK")->setName("ПРИМЕНИТЬ");


		return $formNew;
		;
	}


	private function teachersListPrepare($list)
	{
		$result=array();
		foreach ($list as $key => $elem)
		{
			$result[$elem["id"]]=$elem["login"].", ".$elem["fio"];
			;
		}
		return $result;
	}
}