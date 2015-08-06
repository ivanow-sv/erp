<?php
Zend_Loader::loadClass('My_Spravtypic');

class Sprav_DisciplineController extends My_Spravtypic
{
	private $table='discipline';
	private $title='Справочник: Дисциплины вступительных испытаний';

	function init()
	{
		parent::setTable($this->table);
		parent::setTitle($this->title);
		parent::init();
	}

	function indexAction()
	{
		// это для формы добавления
		$this->view->curact = 'add';
		// данные для списка
		$this->view->entries = $this->data->otherList();
	}

	function addAction()
	{
		$this->view->title = $this->view->title. ' - Добавлена запись';

		if ($this->_request->isPost()) {
			Zend_Loader::loadClass('Zend_Filter_StripTags');
			Zend_Loader::loadClass('Zend_Filter_Digits');
			$filter = new Zend_Filter_StripTags();
			$title = $filter->filter($this->_request->getPost('title'));
			$title = trim($title);
			$this->view->fieldTitle=$title;
			$filterD=new Zend_Filter_Digits();
			$ball=$filterD->filter($this->_request->getPost('ball'));

			if ($title != '') {
				$this->data->otherAdd(array("title"=>$title,"ball"=>$ball));
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
			Zend_Loader::loadClass('Zend_Filter_Digits');

			$filter = new Zend_Filter_StripTags();
			$id = (int)$this->_request->getPost('id');
			$title = trim($filter->filter($this->_request->getPost('title')));
			$this->view->fieldTitle=$title;

			$filterD=new Zend_Filter_Digits();
			$ball=$filterD->filter($this->_request->getPost('ball'));

			if ($id !== false) {
				if ($title != '') {
					$this
					->data
					->otherChange($id,array
					(
					"ball"=>$ball,
					"title"=>$title
					));
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