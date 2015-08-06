<?php
class Study_IndexController extends Zend_Controller_Action
{

    public function init()
    {
        // выясним название текущего модуля для путей в ссылках
        $currentModule=$this->_request->getModuleName();
        $this->view->currentModuleName=$currentModule;
        $this->view->baseUrl = $this->_request->getBaseUrl();
        $this->view->title='Учебный процесс';
        
    }

    public function indexAction ()
    {
    }

}