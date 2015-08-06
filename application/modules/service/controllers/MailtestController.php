<?php
class Service_MailtestController extends Zend_Controller_Action
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
	}


	function indexAction()
	{
		$pass="My_Pass";
		$salt=md5("_My_Salt_");
	
		echo print_r(hash('sha256',$pass,$salt),true);
		
	
		
// 		Zend_Loader::loadclass('Zend_Mail');
// 		Zend_Loader::loadclass('Zend_Mail_Storage_Pop3');
// 		$options=array(
// 				"host"		=>	"mail.academy21.ru",
// 				"port"		=> 	110,
// // 				"user"		=>	"vasya-pupkin",
// // 				"password"	=>	"superparol"
// 				);
		
// 		try
// 		{
// 			$mail=new Zend_Mail_Storage_Pop3($options);
// 		}
// 		// словили ошибку 
// 		catch (Zend_Exception $e) 
// 		{
// 			echo "<pre>".print_r($e->getMessage(),true)."</pre>";
// 			echo "<pre>".print_r($e->getCode(),true)."</pre>";
// 		}
// // 		echo $mail->countMessages() . " messages found\n";
		

	}


}