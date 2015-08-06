<?php
/**
 * Контроллер должностей
 */
Zend_Loader::loadClass('My_Spravtypic');


class Sprav_PositionController extends My_Spravtypic
{
	private $table='position';
	private $title='Справочник: Перечень должностей';
	
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