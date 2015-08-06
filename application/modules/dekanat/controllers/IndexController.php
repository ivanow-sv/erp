<?php
class Dekanat_IndexController extends Zend_Controller_Action
{
	protected 	$currentFacultId;
	private 	$data;
	
	
    public function init()
    {
        // выясним название текущего модуля для путей в ссылках
        $currentModule=$this->_request->getModuleName();
        $this->view->currentModuleName=$currentModule;
        $this->view->baseUrl = $this->_request->getBaseUrl();
		
        
        Zend_Loader::loadClass('Dekanat');
		$groupEnv=Zend_Registry::get("groupEnv");
		$this->currentFacultId=$groupEnv['currentFacult'];
		$this->data=new Dekanat($this->currentFacultId);
		
		$this->view->facultInfo=$this->data->getFacultInfo($this->currentFacultId);
		
		$moduleTitle=Zend_Registry::get("ModuleTitle");
		$modContrTitle=Zend_Registry::get("ModuleControllerTitle");
		$this->view->title=$moduleTitle
		.'. Факультет - '.$this->view->facultInfo['title']
		.". ".$modContrTitle.'. ';
		

    }

    public function indexAction ()
    {
    	    	
    }

}