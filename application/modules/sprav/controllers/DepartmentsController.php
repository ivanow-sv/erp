<?php
Zend_Loader::loadClass('My_Spravtypic');

class Sprav_DepartmentsController extends My_Spravtypic
{
	private $table='departments';
	private $title='Справочник: Подразделения предприятия';
	
	function init()
	{
		parent::setTable($this->table);
		parent::setTitle($this->title);
		parent::init();
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/sprav.js');
	}

	function indexAction()
	{
		        // это для формы добавления
        $this->view->curact = 'add';
		// данные для списка
        $this->view->entries = $this->data->otherList("departments");
		

	}

	function addAction()
	{
        $this->view->title = $this->view->title. ' - Добавлена запись';

        if ($this->_request->isPost()) {
            Zend_Loader::loadClass('Zend_Filter_StripTags');
            $filter = new Zend_Filter_StripTags();
            $title = $filter->filter($this->_request->getPost('title'));
            $title = trim($title);
            $title_small = $filter->filter($this->_request->getPost('title_small'));
            $title_small = trim($title_small);
            $this->view->fieldTitle="(".$title_small.")".$title;
            
            if ($title != '') {
            	$this->data->otherAdd(array("title"=>$title,"title_small"=>$title_small));
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
            $title_small = trim($filter->filter($this->_request->getPost('title_small')));
            $this->view->fieldTitle="(".$title_small.")".$title;

            if ($id !== false) {
                if ($title != '') {
					$this->data->otherChange($id, array("title"=>$title,"title_small"=>$title_small));
                    return;
                }
            }
        }
        $this->_redirect($this->redirectLink);
		
	}

	function delAction()
	{
		parent::delAction();
	}

}