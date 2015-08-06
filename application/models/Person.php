<?php

/**
 * @author zlydden
 *
 */
class Person extends Zend_Db_Table
{

	private $dbAdapter;
	private $table;
	private $tablePrivateInfo;

	public function __construct()
	{
		$this->dbAdapter = $this->getDefaultAdapter();
		//		$this->dbAdapter=$db;
		$this->table='personprocess';
		$this->tablePrivateInfo='personal';

	}

	public function getPersonProcessLog($userid,$sort="DESC")
	{
		$q="SELECT pr.id, pr.operation, pr.userid, pr.createdate, pr.comment"
		."\n , pr.paramName, pr.paramValue"
		
		// название параметра в журнале операций
		."\n , CASE "
		// выясним приказ о зачислении
		//		."\n WHEN (pr.operation=1 ) " //
		//		."\n THEN (SELECT CONCAT(abitur_prikazNum,' от ',DATE_FORMAT(abitur_prikazDate,'%d.%m.%Y')) FROM students WHERE userid=".$userid.")" //
		// выясним название подгруппы
		."\n WHEN (pr.paramName LIKE 'subgroup' AND  pr.operation=5 ) " //
		."\n THEN (SELECT title FROM studsubgroups WHERE id=pr.paramValue)" //
		// номер зачетки
		."\n WHEN (pr.paramName LIKE 'zach' AND  pr.operation=22 ) " //
		."\n THEN pr.paramValue" //
//		// личная инфа
//		."\n WHEN (pr.operation=23 ) " //
//		."\n THEN pr.paramValue" //
		// значение по умолчанию - пусто
		."\n ELSE ''"  //
		."\n END "
		."\n AS paramTitle" //

		// приказ
		."\n , pr.documentid"
		."\n , CONCAT(d.titleNum,d.titleLetter,' от ',DATE_FORMAT(d.titleDate,'%d.%m.%Y')) AS docusTitle"
		
		."\n , pr.author, u.login AS authorLogin"
		."\n , pr_oper.title AS operTitle"
		."\n FROM ".$this->table." AS pr"
		."\n LEFT JOIN personoperations AS pr_oper"
		."\n ON pr_oper.id=pr.operation"
		."\n LEFT JOIN acl_users AS u"
		."\n ON u.id=pr.author"
		."\n LEFT JOIN docus AS d"
		."\n ON d.id=pr.documentid"
		."\n WHERE pr.userid=".$userid
		."\n ORDER BY pr.createdate ".$sort
		;
		$result=$this->dbAdapter->fetchAll($q);
		return $result;
	}


	/**
	 * добавить запись в журнал оперпций с субъектом
	 * @param integer ID пользователя
	 * @param integer ID операции
	 * @param integer ID документа
	 * @param string коментарий
	 * @param string имя параметра
	 * @param string значение параметра
	 * @param integer ID пользователя-автора
	 * @return integer id записи
	 */
	public function addRecord($userid,$operation,$documentid=0,$comment='',$param,$value='',$author=0)
	{
		$curdate=date("Y-m-d H:i:s");
		$data=array();
		$data["userid"]=$userid;
		$data["operation"]=$operation;
		$data["createdate"]=$curdate;
		$data["paramName"]=$param;
		$data["paramValue"]=$value;
		$data["author"]=$author;
		if($documentid!=0) $data["documentid"]=$documentid;
		if($comment!=='') $data["comment"]=$comment;
		$this->dbAdapter->insert($this->table,$data);
		return $this->dbAdapter->lastInsertId();
	}

	/** ввод записи из массива - проверка не производится чо там
	 * @param array $array
	 * @return array
	 */
	public function addRecordByArray($array)
	{
		$array["createdate"]=date("Y-m-d H:i:s");
		$this->dbAdapter->insert($this->table,$array);
		return $this->dbAdapter->lastInsertId();
	}

	/**
	 * Изменение записей - WHERE не проверятся!!!!
	 * @param array $where
	 * @param array $data
	 * @return integer затронуто записей
	 */
	public function updateRecords($where,$data)
	{
		$aff=$this->dbAdapter->update($this->table,$data,$where);
		return $aff;
	}
}