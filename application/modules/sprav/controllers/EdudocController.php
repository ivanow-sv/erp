<?php
Zend_Loader::loadClass('My_Spravtypic');

class Sprav_EdudocController extends My_Spravtypic
{
	private $table='edu_docs';
	private $title='Справочник: Документы об образовании';
	
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