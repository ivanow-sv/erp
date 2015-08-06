<?php
class Helpdesk_TestController extends Zend_Controller_Action
{
	protected 	$session;
	protected 	$baseLink;
	protected	$redirectLink; // ссылка в этот модуль/контроллер
	private 	$_model;
	private 	$hlp; // помощник действий Typic
	private 	$_author; // пользователь шо щаз залогинен


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
		$this->view->addHelperPath('./application/views/helpers/','My_View_Helper');

		Zend_Loader::loadClass('Zend_Session');
		// 		Zend_Loader::loadClass('Zend_Form');
		// 		Zend_Loader::loadClass('Formochki');
		// 		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$this->session=new Zend_Session_Namespace('my');
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		// 				$ajaxContext ->addActionContext('formchanged', 'json')->initContext('json');
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/helpdesk.js');
		//		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/styles/dekanat.css');
		$this->_author=Zend_Auth::getInstance()->getIdentity();
		// немного другой шаблон
		$this->_helper->layout->setLayout('helpdesk');
		
	}


	function indexAction()
	{
// 		Zend_Loader::loadClass(Zend_Mail);
// 		Zend_Loader::loadClass(Zend_Mail_Storage_Pop3);
// 		try {
// 			$mail = new Zend_Mail_Storage_Pop3(array(
// 					'host'     => 'mail.academy21.ru',
// 					'user'     => 'umu1@academy21.ru',
// 					'password' => 'arm932',
// 					'port'		=> 110,
// 					'ssl'      => 'SSL'));
				
// 		} catch (Zend_Mail_Exception $e) 
// 		{
// 			echo $e->getMessage();
// 			echo "<pre>".print_r($e->getTrace(),true)."</pre>";
// 		}
// 		echo $mail->getCapabilities();
// 		phpinfo();
// 		$mail->
	}


}