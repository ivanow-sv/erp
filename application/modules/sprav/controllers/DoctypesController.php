<?php
Zend_Loader::loadClass('My_Spravtypic');

class Sprav_DoctypesController extends My_Spravtypic
{
	private $table='docus_types';
	private $title='Справочник: Типы формируемых документов';
	
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