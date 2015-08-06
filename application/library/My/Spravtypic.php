<?php
class My_Spravtypic extends Zend_Controller_Action
{
	private $table;
	private $title;
	
	protected $redirectLink;
	protected $data;
	protected $confirmWord='УДАЛИТЬ';
	
	function setTable($table)
	{
		$this->table=$table;
	}

	function setTitle($title)
	{
		$this->title=$title;
	}
	
    function init()
    {
        $this->redirectLink=$this->_request->getModuleName()."/".$this->_request->getControllerName();
    	$this->view->baseUrl = $this->_request->getBaseUrl();
        $this->view->curact = $this->_request->action;
        $this->view->curcont = $this->_request->controller;
		$this->view->selfLink= $this->_request->getBaseUrl()."/".$this->redirectLink;
        $this->view->icoPath=$this->_request->getBaseUrl()."/public/images/";
        $this->view->confirmWord=$this->confirmWord;
        Zend_Loader::loadClass('Sprav');
        $this->view->title = $this->title;
		$this->data=new Sprav($this->table);


    }

    function indexAction()
    {
        // это для формы добавления
        $this->view->curact = 'add';
		// данные для списка
        $this->view->entries = $this->data->typicList();
    }

    function addAction()
    {
        $this->view->title = $this->view->title. ' - Добавлена запись';

        if ($this->_request->isPost()) {
            Zend_Loader::loadClass('Zend_Filter_StripTags');
            $filter = new Zend_Filter_StripTags();
            $title = $filter->filter($this->_request->getPost('title'));
            $title = trim($title);
            $this->view->fieldTitle=$title;
            
            if ($title != '') {
            	$this->data->typicAdd($title);
                return;
            }

            $this->_redirect($this->redirectLink);

        }

    }

    function editAction()
    {
        $this->view->title = $this->view->title. ' - Изменения записи';
        if ($this->_request->isPost()) {
            Zend_Loader::loadClass('Zend_Filter_StripTags');
            $filter = new Zend_Filter_StripTags();
            $id = (int)$this->_request->getPost('id');
            $title = trim($filter->filter($this->_request->getPost('title')));
            $this->view->fieldTitle=$title;

            if ($id !== false) {
                if ($title != '') {
					$this->data->typicChange($id,$title);
                    return;
                }
            }
        }
        $this->_redirect($this->redirectLink);

    }

    function delAction()
    {
        $this->view->title = $this->view->title. ' - Удаление записи';

        if ($this->_request->isPost()) {
            Zend_Loader::loadClass('Zend_Filter_Alpha');
            $filter = new Zend_Filter_Alpha();
            $id = (int)$this->_request->getPost('id');
            $confirm = $filter->filter($this->_request->getPost('confirmWord'));
            if ($confirm===$this->confirmWord && $id>0)
            {
				$this->data->typicDel($id);
            }
        }
        $this->_redirect($this->redirectLink);
    }
}