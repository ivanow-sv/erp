<?php
class Dostup_IndexController extends Zend_Controller_Action
{

    public function init()
    {
        // выясним название текущего модуля для путей в ссылках
        $currentModule=$this->_request->getModuleName();
        $this->view->currentModuleName=$currentModule;
        $this->view->baseUrl = $this->_request->getBaseUrl();
        $this->view->title='Управление доступом';
        
    }

    public function indexAction ()
    {
//    	$acl= Zend_Registry::get("acl");
//    	$logger=Zend_Registry::get("logger");
//    					$logger->log($acl->getResources(), Zend_Log::INFO);
    	
    	
    }

}