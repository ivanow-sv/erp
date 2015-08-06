<?php

class Roles extends Zend_Db_Table
{

	private $dbAdapter;
	protected $tbl_rolesEnv="acl_rolesEnv";
	private $table='acl_roles';
	private $_restricted=array(
			1=>1, // супер админы
			2=>2, // гости
			3=>3,
			4=>4,
			5=>5,
			6=>6,
			7=>7,
			8=>8,
			9=>9,
			10=>10
	); // привилигированные роли - нельзя удалять
		

	public function __construct()
	{
		$db = $this->getDefaultAdapter();
		$this->dbAdapter=$db;


	}

	public function getRestricted()
	{
		$s=$this->dbAdapter->select();
		$s->from($this->table);
		$s->where("id IN(".implode (",",$this->_restricted).")");
		$s->order("id ASC");
		$res=$this->dbAdapter->fetchAssoc($s);
		return $res;
	}	
	
	/**
	 * список ролей текущего родителя
	 * если родитель null - то вершок
	 * @param integer $parent
	 * @return array
	 */
	public function getRolesList($parent=null)
	{
		$SQL="SELECT * FROM `".$this->table."` WHERE `parent` ";
		$parent=is_null($parent)?" is null":" = ".$parent;
		$SQL.=$parent;
//				echo $SQL;
//				die();
		$rows=$this->dbAdapter->fetchAll($SQL);
		return $rows;

	}

	public function getRolesAll()
	{
		$q="SELECT r.*, p.title AS parent_title"
		.", p.comment AS parent_comment, p.disabled AS parent_disabled"
		."\n FROM acl_roles AS r"
		."\n LEFT JOIN acl_roles AS p"
		."\n ON p.id=r.parent" 
		."\n ORDER BY r.parent ASC";
		$rows=$this->dbAdapter->fetchAssoc($q);
				// гости
		$aaa=array();
		$aaa["id"]=0;
		$aaa["title"]='Гости';
		$aaa["comment"]='';
		$aaa["disabled"]=0;
		$aaa["parent"]=null;
		array_unshift($rows,$aaa);
		return $rows;
		
	}

	/**
	 * создание новой роли - вносицо название
	 * @param string $title название роли
	 * @return integer ID новой роли
	 */
	public function addRoleByTitle($title)
	{
		$data=array('title'=>$title);

		$this->dbAdapter->insert(array('name'=>$this->table),$data);
		return $this->dbAdapter->lastInsertId();
	}

	/**
	 * удалить
	 * @param integer $id
	 */
	public function deleteRole($id)
	{

		$this->dbAdapter->delete($this->table,'id = '.$id);
		;
	}

	
	public function setInfo_v2($data,$id) 
	{
		// @TODO обернуть в exception
		$this->dbAdapter->update($this->table,$data,'id = '.$id);
		
		;
	}
	
	/**
	 * изменение инфо
	 * @param array $d данные из POST
	 */
	public function setInfo($d)
	{
		$id=$d["id"];
		$data=array();
		switch ($d['parent'])
		{
			case -1:
				$d['parent']=null;
				break;
				
			case $id:
				$d['parent']=0;
				break;
				
			default:
				
				break;
		}

		$data=array(
			'title'		=>	$d['title'],
			'comment'	=>	$d['comment'],
			'parent'	=>	$d['parent']==$id?0:$d['parent'],
			'disabled'	=>	$d['disabled']
		);
		$this->dbAdapter->update($this->table,$data,'id = '.$id);

		// параметры роли
		$data=array("paramz"=>$d['paramz']);
		$aff=$this->dbAdapter->update($this->tbl_rolesEnv,$data,'roleid = '.$id);
		if ($aff==0) $this->dbAdapter
					->insert($this->tbl_rolesEnv,
							array("roleid"=>$id,"paramz"=>$d["paramz"]));
		
	}

	/**
	 * возрат данных о роли
	 * @param integer $id 
	 * @return array инфо 
	 */
	public function getInfo($id)
	{
		$sql='SELECT * FROM '.$this->table.' WHERE id = '.$id;
		$info=$this->dbAdapter->fetchRow($sql);
		return $info;
	}
	/**
	 * возрат данных об переменных окружения роли
	 * @param integer $id 
	 * @return array инфо 
	 */
	public function getParams($id)
	{
//		$sql="SELECT paramName AS `key`,paramValue AS `value`"
//		."\n FROM ".$this->tbl_rolesEnv
//		."\n WHERE roleid = ".$id;
		$sql="SELECT paramz FROM ".$this->tbl_rolesEnv
		."\n WHERE roleid=".$id;
		$info=$this->dbAdapter->fetchOne($sql);
		return $info;
	}


}