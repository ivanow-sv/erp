<?php

class Menus extends Zend_Db_Table
{

	private $dbAdapter;
	private $tableRoles;
//	private $dbAdapter;
	private $tableNav;

	public function __construct()
	{
		$db = $this->getDefaultAdapter();
		$this->dbAdapter=$db;
		$this->tableRoles='acl_roles';
		$this->tableNav='nav_items';
	}

	/**
	 * список ролей текущего родителя
	 * если родитель 0 - то вершок
	 * @param integer $parent
	 * @return array
	 */
	public function getRolesList($parent=0)
	{
		$SQL="SELECT `id` AS `key`, `title` AS `value` FROM ".$this->tableRoles." WHERE 'parent' = ".$parent;
//				echo $SQL;
//				die();
		$rows=$this->dbAdapter->fetchAll($SQL);
		return $rows;

	}

	/**
	 * вытаскивает меню указанного родителя
	 * @param integer $parent ОПЦИОНАЛЬНО родитель, если нет - самый верхний уровень
	 */
	public function getItems($parent=0)
	{
		$sql='SELECT * FROM nav_items WHERE parent = '.$parent." ORDER by `order` ASC";
		$rows=$this->dbAdapter->fetchAll($sql);
		return $rows;
	}


	/**
	 * возрат данных о пункте меню
	 * @param integer $id пункт меню
	 * @return array инфо о пункте меню
	 */
	public function getItemInfo($id)
	{
		$sql='SELECT * FROM nav_items WHERE id = '.$id;
		$info=$this->dbAdapter->fetchRow($sql);
		return $info;
	}

	/**
	 * удалить пункт менню
	 * @param integer $id
	 */
	public function deleteItem($id)
	{
		$table='nav_items';
		$this->dbAdapter->delete($table,'id = '.$id);
		;
	}

	/**
	 * изменение инфо о пункте меню
	 * @param array $d данные из POST
	 */
	public function setItemInfo($d)
	{
		$table='nav_items';
		$id=$d["id"];
		$data=array();

		$data=array(
			'label'		=>	$d['label'],
			'title'		=>	$d['title'],
			'class'		=>	$d['class'],
			'target'	=>	$d['target'],
			'resource'	=>	$d['resource'],
			'privilege'	=>	$d['privilege'],
			'module'	=>	$d['module'],
			'controller'=>	$d['controller'],
			'action'	=>	$d['action'],
			'params'	=>	$d['params'],
			'parent'	=>	$d['parent'],
			'order'		=>	$d['order'],
			'disabled'	=>	$d['disabled']
		);
		$this->dbAdapter->update($table,$data,'id = '.$id);
		
		// назначим роли
		//
		$table="acl_allow";
//		echo "<pre>".print_r($d["rolesList"],true)."</pre>";
//		die();

	}

	/**
	 * Добавляет пункт и возвращет его ID
	 * @param string $label название пунктам меню
	 */
	public function addItemByLabel($label)
	{
		$data=array('label'=>$label,'module'=>'default','controller'=>'index');
		$table='nav_items';
		$this->dbAdapter->insert(array('name'=>$table),$data);
		return $this->dbAdapter->lastInsertId();
	}

}