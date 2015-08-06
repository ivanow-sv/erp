<?php


class ErrorController extends Zend_Controller_Action
{
	function init()
	{
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->user = Zend_Auth::getInstance()->getIdentity();
		//		Zend_Loader::loadClass('Album');
		//       $album = new Album();
		//		$this->view->albums = $album->fetchAll();
		// если у нас AJAX 
		if ($this->_request->isXmlHttpRequest()) 
		{
			$ajaxContext = $this->_helper->getHelper('AjaxContext');
			$ajaxContext ->addActionContext('erroracl', 'json')->initContext('json');
			
		}
				
	}

	function indexAction()
	{
		$this->view->title = "Ошибка";

	}

	function erroraclAction()
	{
		$text="Ошибка авторизации - недостаточно прав";
		if (!$this->_request->isXmlHttpRequest())
		{
			$this->view->title = $text;
		}
		else
		{
			$this->view->clearVars();
//			$this->view->baseLink=$this->baseLink;
//			$this->view->baseUrl = $this->_request->getBaseUrl();

			$out["formMsg"]=$text;
			$this->view->out=$out;
		}

	}

	function errorAction()
	{
		$this->view->title = "Ошибка в приложении";
		$this->view->request=$this->getRequest();

	}

}