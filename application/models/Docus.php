<?php

/**
 * @author zlydden
 *
 */
class Docus extends Zend_Db_Table
{

	private $db;
	private $table;
	//private $tablePrivateInfo;

	public function __construct()
	{
		$this->db= $this->getDefaultAdapter();
		//		$this->dbAdapter=$db;
		$this->table='docus';
		//$this->tablePrivateInfo='personal';

	}

	/**
	 * 
	 * @param integer $titleNum
	 * @param string $titleLetter
	 * @param string $titleDate
	 * @param integer $author userid
	 * @param integer $type
	 * @param string $comment
	 */
	public function addRecord($titleNum,$titleLetter,$titleDate,$author,$type,$comment='') 
	{
		$data["titleDate"]	=	$titleDate;
		$data["titleLetter"]=	$titleLetter;
		$data["titleNum"]	=	$titleNum;
		$data["author"]		=	$author;
		$data["type"]		=	$type;
		$data["comment"]	=	$comment;
		$data["createtime"]	=	date("Y-m-d H:i:s");
		$this->db->insert($this->table,$data);
		return $this->db->lastInsertId();		
		;
	}
	
}