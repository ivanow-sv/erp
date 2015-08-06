<?php
/*
 * отображаемый текст-анотация для /__MODULE__/index
*/
class Service_AnnotationsController extends Zend_Controller_Action
{
	protected 	$session;
	protected 	$baseLink;
	protected	$redirectLink; // ссылка в этот модуль/контроллер
	private 	$_model;
	private 	$_aclmodel;
	private 	$_resmodel;
	private 	$hlp; // помощник действий Typic

	private 	$logPath; // путь к логам
	private 	$patchDescription="descript.ion"; // имя фала-описания патчей, находится в папка с логами
	private 	$patchCurrent=''; // текущего патча
	private $_author; // пользователь шо щаз залогинен


	function init()
	{
		Zend_Loader::loadClass('Resources');
		Zend_Loader::loadClass('Zend_Session');
		Zend_Loader::loadClass('Zend_Form');
		Zend_Loader::loadClass('Formochki');
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		Zend_Loader::loadClass('Service');
		
		// выясним название текущего модуля для путей в ссылках
		$currentModule=$this->_request->getModuleName();
		$this->view->currentModuleName=$currentModule;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->currentController = $this->_request->getControllerName();
		$this->baseLink=$this->_request->getBaseUrl()."/".$currentModule."/".$this->_request->getControllerName();
		$this->view->baseLink=$this->baseLink;
		$this->redirectLink=$this->_request->getModuleName()."/".$this->_request->getControllerName();
		$this->hlp=$this->_helper->getHelper('Typic');


		$this->_aclmodel=new Resources();
		$this->_model=new Service();

		$groupEnv=Zend_Registry::get("groupEnv");
		$moduleTitle=Zend_Registry::get("ModuleTitle");
		$modContrTitle=Zend_Registry::get("ModuleControllerTitle");
		$this->view->title=$moduleTitle
		.". ".$modContrTitle.'. ';
		$this->view->addHelperPath('./application/views/helpers/','My_View_Helper');

		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		$ajaxContext ->addActionContext('show', 'json')->initContext('json');
		$ajaxContext ->addActionContext('edit', 'json')->initContext('json');
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/ckeditor/ckeditor.js');
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/ckeditor/adapters/jquery.js');
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/service-annotations.js');

		$this->session=new Zend_Session_Namespace('my');
		$this->_author=Zend_Auth::getInstance()->getIdentity();
	}

	public function indexAction()
	{

		// $tree - модуль => имя
		$this->view->tree=$this->getModules();

	}

	public function showAction()
	{
		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->redirectLink);
		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		// генерация формы с CKEditor
		$editform=$this->createForm_edit($this->getModules());

		// заполнение формы данными
		$params = $this->_request->getParams();
		if ($editform->isValid($params))
		{
			$id=$editform->getElement("id");
			$module=$id->getValue();
			$titles=$this->_aclmodel->getResourcesDescription();
			// вытащим из БД
			$text=$this->_aclmodel->getModuleAnnotation($module);
			// заполним
			$editform->getElement("editor1")->setValue($text);
			// вывод
			$this->view->editForm=$editform;
			$this->view->title=$titles[$module];
			$this->view->details=$this->view->render($this->_request->getControllerName().'/_editForm.phtml');
		}
		else
		{
			$this->view->msg=$editform->getMessages();


		}
	}

	public function editAction()
	{
		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->redirectLink);
		// 		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		// генерация формы с CKEditor
		$editform=$this->createForm_edit($this->getModules());
		// заполнение формы данными
		$params = $this->_request->getParams();
		if ($editform->isValid($params))
		{
			$_module=$editform->getElement("id");
			$module=$_module->getValue();

			$_text=$editform->getElement("editor1");
			$text=$_text->getValue();

			// узнаем resID ресурса по имени модуля
			$resInfo=$this->_aclmodel->getResInfo($module);
				
			// узнаем - ообновлять или добавлять
			$_textOld=$this->_model->annotations_getTextByResID($resInfo["id"]);
			// не было никогда аннотации
			if ($_textOld===false) 
			{
				// добавить
				$res=$this->_model->annotations_newText($text, $resInfo["id"]);
			}
			else 
			{
				// обновить
				$res=$this->_model->annotations_changeText($text, $resInfo["id"]);
			}
			
			// @TODO ошибки еси есть показать
			$this->view->msg=$res;
			
		}
		// @TODO неверные данные в форме
		else
		{

		}
	}


	private function createForm_edit($modules)
	{
		// перебросим ключ в значения
		$modules=array_keys($modules);

		$form=new Formochki();
		$form->setAttrib('name','editForm');
		$form->setAttrib('id','editForm');
		$form->setMethod('POST');
		// этот скрипт будет запускаться при отправке формы
		$form->setAction("javascript:saveAn();void(0);");
		// 		$form->setAction($this->baseLink."/edit");
		$form->addElement("hidden","id");
		$form->getElement("id")
		->addValidator("NotEmpty",true)
		->addValidator("Alpha",true, array("allowWhiteSpace"=>false))
		// только эти значения
		->addValidator("InArray",true,array("haystack"=>$modules))
		->setRequired(true)
		->setDescription("ID")
		// 		->isValid("abcdefghijklmnopqrstuvwxyz")
		;
		// для CKEDITOR обязательно 'editor1'
		$form->addElement("textarea","editor1");
		return $form;
	}

	/** отфильтровывает модули от дерева ресурсов
	 * @param array $tree (_МОДУЛЬ-КОНТРОЛЕР_ => НАЗВАНИЕ)
	 * @return array (МОДУЛЬ => НАЗВАНИЕ)
	 */
	private function getModules()
	{
		$_tree=$this->_aclmodel->getResourcesDescription();

		$tree=array();
		// оставим тока модули
		foreach ($_tree as $key => $value)
		{
			if (strpos($key,"-")===false) $tree[$key]=$value;
		}
		return $tree;
	}

}