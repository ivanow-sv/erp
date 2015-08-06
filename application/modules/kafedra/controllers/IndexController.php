<?php
class Kafedra_IndexController extends Zend_Controller_Action
{
	protected 	$curKafedra;
	protected  	$_model;
	
	
    public function init()
    {
        // выясним название текущего модуля для путей в ссылках
        $currentModule=$this->_request->getModuleName();
        $this->view->currentModuleName=$currentModule;
        $this->view->baseUrl = $this->_request->getBaseUrl();
		
        
        Zend_Loader::loadClass('Kafedra');
		$groupEnv=Zend_Registry::get("groupEnv");
		$this->curKafedra=$groupEnv['kafedra'];
		$this->_model=new Kafedra($this->curKafedra);
		
		
		$moduleTitle=Zend_Registry::get("ModuleTitle");
		$modContrTitle=Zend_Registry::get("ModuleControllerTitle");
		$this->view->title=$moduleTitle
		.$this->view->facultInfo['title']
		.". ".$modContrTitle.'. ';
		

    }

    public function indexAction ()
    {
    }

}