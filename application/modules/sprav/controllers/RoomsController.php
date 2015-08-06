<?php
Zend_Loader::loadClass('My_Spravtypic');

class Sprav_RoomsController extends My_Spravtypic
{
	private $table='room';
	private $title='Справочник: Потребность в общежитии';
	
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