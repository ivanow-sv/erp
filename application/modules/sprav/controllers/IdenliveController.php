<?php
Zend_Loader::loadClass('My_Spravtypic');

class Sprav_IdenliveController extends My_Spravtypic
{
	private $table='iden_live';
	private $title='Справочник: Откуда родом абитуриент';
	
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