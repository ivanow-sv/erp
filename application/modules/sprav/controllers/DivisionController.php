<?php
Zend_Loader::loadClass('My_Spravtypic');

class Sprav_DivisionController extends My_Spravtypic
{
	private $table='division';
	private $title='Справочник: отделения обучения';


	function init()
	{
		parent::setTable($this->table);
		parent::setTitle($this->title);
		parent::init();
	}

	function indexAction()
	{
		parent::indexAction();

	}

	function addAction()
	{
		parent::addAction();
	}

	function editAction()
	{
		parent::editAction();
	}

	function delAction()
	{
		parent::delAction();
	}

}