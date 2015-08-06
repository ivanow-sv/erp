<?php
Zend_Loader::loadClass('My_Spravtypic');

class Sprav_ExamtypesController extends My_Spravtypic
{
	private $table='exam_type';
	private $title='Справочник: Типы вступ. испытаний';
	
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