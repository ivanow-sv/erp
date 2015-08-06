<?php

class Aclgroups extends Zend_Db_Table
{

	private $db;
	private $t; // активная таблица
	private $_restricted=array(
			1=>1,
			2=>2,
			3=>3,
			4=>4,
			5=>5,
			6=>6,
			7=>7,
			8=>8,
			9=>9,
			10=>10
			); // привилигированные группы - нельзя удалять
	
	public function __construct()
	{
		$db = $this->getDefaultAdapter();
		$this->db=$db;
		$this->t='acl_groups'; // таблица

	}

	
	public function getRestricted()
	{
		$s=$this->db->select();
		$s->from($this->t);
		$s->where("id IN(".implode (",",$this->_restricted).")");
		$s->order("id ASC");
		$res=$this->db->fetchAssoc($s);
		return $res;
	}
	
	/**
	 * список ролей текущего родителя
	 * если родитель null - то вершок
	 * @param integer $parent
	 * @return array
	 */
	public function getGroupsList($parent=null)
	{
		$s=$this->db->select();
		$s->from($this->t);
		$_p=is_null($parent)
		?	" `parent` is null"
		:	" `parent` = ".$parent;
		$s->where($_p);
		$rows=$this->db->fetchAll($s);
		return $rows;

	}

	public function getGroupsAll()
	{
		$s=$this->db->select();
		$s->from(array("g1"=>$this->t));
		$s->joinLeft(array("g2"=>$this->t),'g2.id=g1.parent',
				array(
						"parent_title"=>"g2.title",
						"parent_comment"=>"g2.comment",
						"parent_disabled"=>"g2.disabled"
						));
		$s->order("g1.parent ASC");
		$rows=$this->db->fetchAssoc($s);
		return $rows;
		
	}

	/**
	 * создание новой группы - вносицо название
	 * @param string $title название группы
	 * @param integer $parent родитель
	 * @return integer ID новой группы
	 */
	public function addGroupByTitle($title,$parent=0)
	{
		$data=array('title'=>$title);
		if (!empty($parent)) $data["parent"]=$parent;

		$this->db->insert(array('name'=>$this->t),$data);
		return $this->db->lastInsertId();
	}

	/**
	 * удалить
	 * @param integer $id
	 */
	public function deleteGroup($id)
	{
		
		$this->db->delete($this->t,'id = '.$id);
		;
	}

	
	/**
	 * возрат данных о группы
	 * @param integer $id 
	 * @return array инфо 
	 */
	public function getInfo($id)
	{
		$s=$this->db->select();
		$s->from($this->t);
		$s->where("id = ".$id);
		
		$info=$this->db->fetchRow($s);
		return $info;
	}
	
	public function saveInfo($params)
	{
		$id=$params["id"];
		unset($params["id"]);
		$this->db->update($this->t, $params,"id=".$id);
		
		;
	}
	
	
	/**
	 * возрат данных об переменных окружения группы
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
		$info=$this->db->fetchOne($sql);
		return $info;
	}

	
	
	/** 
	 * имеет ли потомков
	 * @param integer $id
	 * @return boolean
	 */
	public function hasChild($id) 
	{
		$s=$this->db->select();
		$s->from($this->t,array("id"));
		$s->where("parent=".$id);
		$res=$this->db->fetchOne($s);
// 		return $res;
		if (empty($res)) return false ;
		else return true;
		
	}
	
}