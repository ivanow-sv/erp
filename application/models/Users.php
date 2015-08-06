<?php

class Users extends Zend_Db_Table
{

	private $dbAdapter;
	private $table;
	private $tablePrivateInfo;
	private $tableEmails;
	private $roletable;
	private $grpTable='acl_groups';
	private $grpTableUsr='acl_groups_has_users';

	public function __construct()
	{
		$db = $this->getDefaultAdapter();
		$this->dbAdapter=$db;
		$this->table='acl_users';
		$this->tablePrivateInfo='personal';
		$this->tableEmails="acl_users_emails";
		$this->roletable='acl_roles';
		$this->grpTable='acl_groups';
		$this->grpTableUsr='acl_groups_has_users';

	}

	/**
	 * список пользователей
	 * @TODO фильтр по разным параметрам
	 * @param array $params
	 * @return array
	 */
	public function getList($params)
	{
		$SQL="SELECT u.*,r.title AS roleTitle FROM ".$this->table." AS u";
		$SQL.="\n LEFT JOIN ".$this->roletable." AS r";
		$SQL.="\n ON u.role=r.id";
		$SQL.="\n WHERE login like '%".$params->filterLogin."%'";
		$SQL.="\n AND u.comment like '%".$params->filterComment."%'";
		$SQL.="\n AND u.role=".$params->filterRole;
		$SQL.=$params->filterDisabled>1?"":"\n AND u.disabled=".$params->filterDisabled;
		//		echo $SQL;die();
		$rows=$this->dbAdapter->fetchAll($SQL);
		return $rows;
	}

	public function getListInRole($role)
	{
		$db = $this->getDefaultAdapter();
		$select=$db->select();

		$select->from(array("u"=>$this->table),
				array("id"=>"id",
						"login"=>"login")
		);
		$select->joinLeft(
				array("p"=>"personal"),
				"p.userid=u.id",
				array("fio"=>"CONCAT_WS(' ',family,name,otch)")
		);

		$select->where("u.role = ".$role);
		$select->order("fio ASC");
		$stmt = $db->query($select);
		$result = $stmt->fetchAll();
		return $result;
	}

	public function getListInGroup($group)
	{
		$db = $this->getDefaultAdapter();
		$select=$db->select();

		$select->from(array("u"=>$this->table),
				array("id"=>"id",
						"login"=>"login",
						"disabled"=>"disabled",
						"comment"=>"comment",
				)
		);
		$select->joinLeft(
				array("gru"=>$this->grpTableUsr),
				"gru.acl_users_id=u.id",
				null
		);

		$select->joinLeft(
				array("p"=>$this->tablePrivateInfo),
				"p.userid=u.id",
				array("fio"=>"CONCAT_WS(' ',family,name,otch)")
		);


		$select->where("gru.acl_groups_id= ".$group);
		$select->order("fio ASC");
		$stmt = $db->query($select);
		$result = $stmt->fetchAll();
		return $result;
	}

	/**
	 * создание нового
	 * @param string $login
	 * @param integer $role роль, по умолчанию гость
	 * @return integer ID
	 */
	public function addUserByLogin($loginName,$role=0)
	{
		$data=array('login'=>$loginName,'role'=>$role);

		$this->dbAdapter->insert(array('name'=>$this->table),$data);
		return $this->dbAdapter->lastInsertId();
	}

	/**
	 * создание нового
	 * @param string $login
	 * @param integer $group группа, по умолчанию гости
	 * @return integer ID
	 */
	public function addUserByLogin_v2($loginName,$group=2)
	{

		$data=array();
		$this->dbAdapter->beginTransaction();
		try {
			$data["login"]=$loginName;
			$data["disabled"]=1;
			// добавить запись в acl_users
			$this->dbAdapter->insert($this->table, $data);
			$userid=$this->dbAdapter->lastInsertId($this->table);
			// добавить в acl_groups_has_users
			$dd["acl_groups_id"]=$group;
			$dd["acl_users_id"]=$userid;
			$this->dbAdapter->insert($this->grpTableUsr, $dd);
			// создадим запись в PERSONAL
			$data_p["userid"]=$userid;
// 			$data_p["family"]=$loginName;
// 			$data_p["name"]=$loginName;
// 			$data_p["otch"]=$loginName;
			$this->dbAdapter->insert($this->tablePrivateInfo, $data_p);
			$result["status"]=true;
			$result["userid"]=$userid;
			$this->dbAdapter->commit();

		} catch (Zend_Exception $e) {
			$result["status"]=false;
			$result["errorMsg"]=$e->getMessage();
			$this->dbAdapter->rollback();
		};
		//		$data["userid"]=$userid;
		return $result;

	}

	/**
	 * удалить
	 * @param integer $id
	 */
	public function deleteUser($id)
	{

		$this->dbAdapter->delete($this->table,'id = '.$id);
		// удалить личную инфу
		$this->dbAdapter->delete('personal','userid = '.$id);

		//@TODO проверять надо ли нижеследующее (студент ли это?)
		// @TODO может вообще не удалять пользователей?

		// удалить инфо об образовании
		$this->dbAdapter->delete('students','userid = '.$id);
		// удалить инфо об операциях над субъектом
		$this->dbAdapter->delete('personprocess','userid = '.$id);
		;
	}

	/**
	 * Назначение роли
	 * @param integer $userid
	 * @param integer $newrole
	 * @return unknown
	 */
	public function setRole($userid,$newrole)
	{
		$data=array(
				'role'		=>	$newrole
		);
		return $this->dbAdapter->update($this->table,$data,'id = '.$userid);
	}

	/**
	 * @param array $ids
	 * @param integer $state 0 or 1
	 */
	public function setState($ids,$state) 
	{
		$data=array("disabled"=>$state)
		;
		return $this->dbAdapter->update($this->table,$data,'id IN ( '.implode(',',$ids)." )" );
	}
	
	/**
	 * Перемещение из группы в группу
	 * @param array  $userid
	 * @param integer $group
	 * @param integer $from
	 * @return unknown
	 */
	public function moveUsers($userid,$newgroup,$from)
	{
		// 		UPDATE `academyutf8`.`acl_groups_has_users` SET `acl_groups_id` = '2' WHERE `acl_groups_has_users`.`acl_groups_id` = 15 AND `acl_groups_has_users`.`acl_users_id` IN ( 6498,6491,6492);

		$data=array(
				'acl_groups_id'	=>	$newgroup
		);
		$this->dbAdapter->beginTransaction();
		try {
			$usr=implode (",",$userid);
			$wh=" acl_groups_id=".$from. " AND  acl_users_id IN (".$usr.") ";			
			$affected=$this->dbAdapter->update($this->grpTableUsr,$data,$wh);
			
			$result["status"]=true;
			$result["affected"]=$affected;
			$this->dbAdapter->commit();

		} catch (Zend_Exception $e) {
			$result["status"]=false;
			$result["errorMsg"]=$e->getMessage();
			$this->dbAdapter->rollback();
		};
		//		$data["userid"]=$userid;
		return $result;
	}


	/**
	 * изменение инфо
	 * @param array $d данные из POST
	 */
	public function setInfo($d)
	{
		$id=$d["id"];
		$data=array();

		$data=array(
				'login'		=>	$d['loginName'],
				'pass'		=>	md5($d['pass']),
				'role'		=>	$d['role'],
				'disabled'	=>	$d['disabled'],
				'comment'	=>	$d['comment']
		);
		return $this->dbAdapter->update($this->table,$data,'id = '.$id);

	}

	public function renameLogin($id,$login)
	{
		$data=array(
				'login'	=>	$login
		);
		return  $this->dbAdapter->update($this->table,$data,'id = '.$id);
	}

	/**
	 * включить
	 * @param integer $id
	 */
	public function enableUser($id)
	{
		$data=array();

		$data=array(
				'disabled'	=>	0,
		);
		$this->dbAdapter->update($this->table,$data,'id = '.$id);

	}

	/**
	 * выключить
	 * @param integer $id
	 */
	public function disableUser($id)
	{
		$data=array();

		$data=array(
				'disabled'	=>	1,
		);
		$this->dbAdapter->update($this->table,$data,'id = '.$id);

	}

	/**
	 * возрат данных о пользователе в группе (проверка на наличие в БД)
	 * @param integer $ids
	 * @param integer $from
	 * @return array инфо
	 */
	public function getInfo($ids,$from=false)
	{
		$s=$this->dbAdapter->select();
		$s->from(array("u"=>$this->table));
		if ($from!==false)
		{
			$s->joinLeft(array("gru"=>$this->grpTableUsr), "gru.acl_users_id=u.id",null);
			$s->where("gru.acl_groups_id=".$from);
		}
		$s->joinLeft(array("p"=>$this->tablePrivateInfo), "p.userid=u.id",array("family","name","otch"));
		
		
		$s->where("u.id IN (".implode(",",$ids).")");
		$info=$this->dbAdapter->fetchRow($s);
		return $info;
	}

	/**
	 * список ролей
	 * @return array
	 */
	public function getRolesList()
	{
		$SQL="SELECT id AS `key`, title AS `value` FROM "."acl_roles";
		$rows=$this->dbAdapter->fetchPairs($SQL);
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
// 		$aaa=array();
// 		$aaa["id"]=0;
// 		$aaa["title"]='Гости';
// 		$aaa["comment"]='';
// 		$aaa["disabled"]=0;
// 		$aaa["parent"]=null;
// 		array_unshift($rows,$aaa);
		return $rows;

	}

	/**
	 * получение личной информации о пользователе системы
	 * @param integer $userid
	 * @return array $info
	 */
	public function personalInfoGet($userid)
	{
		$q="SELECT p.*,gen.title AS genderTitle, iden.title AS idenTitle "
		."\n FROM ".$this->tablePrivateInfo." AS p"
		."\n LEFT JOIN gender AS gen"
		."\n ON gen.id=p.id"
		."\n LEFT JOIN identity AS iden"
		."\n ON iden.id=p.identity"
		."\n WHERE userid=".$userid;
		$info=$this->dbAdapter->fetchRow($q);
		// абитур када и кем?
		$q="SELECT abitur_id,abitur_year FROM students WHERE userid=".$userid;
		$abitInfo=$this->dbAdapter->fetchRow($q);
		$info=array_merge($info,$abitInfo);
		return $info;
	}

	/**
	 * Создание личных данных о пользователе
	 * @param integer $userid
	 * @param array $data
	 * @return integer сколько строк вставлено
	 */
	public function personalInfoCreate($userid,$data)
	{
		$data["userid"]=$userid;
		$result=$this->dbAdapter->insert($this->tablePrivateInfo, $data);
		return $result;

	}

	public function setInfoMinimal($id,$loginInfo,$privateInfo,$email='',$privFlag) 
	{
		$this->dbAdapter->beginTransaction();
		try {
			$aff=$this->dbAdapter->update($this->table, $loginInfo,"id=".$id);
			$aff=$this->dbAdapter->update($this->tablePrivateInfo, $privateInfo,"userid=".$id);
			// если нет приватной инфы - создать 
			if ($privFlag===true)
			{
				$this->personalInfoCreate($id, $privateInfo);
			}
			// указан email ?
			if (!empty($email))
			{
				$resEmail=$this->setEmail($email,$id);
			}
			$result["status"]=true;
// 			$result["userid"]=$userid;
			$this->dbAdapter->commit();
		
		} catch (Zend_Exception $e) {
			$result["status"]=false;
			$result["errorMsg"]=$e->getMessage();
			$this->dbAdapter->rollback();
		};
		//		$data["userid"]=$userid;
		return $result;
		
		;
	}
	
	public function setEmail($email,$userid)
	{
		$sql="REPLACE INTO ".$this->tableEmails
		." SET userid=".$userid
		.", email='".$email."'";
		$res=$this->dbAdapter->query($sql);
		return $res;
	}

	public function getEmails($id)
	{
		$s=$this->dbAdapter->select();
		$s->from($this->tableEmails,array("email"))
		->where("userid=".$id);
		return $this->dbAdapter->fetchCol($s);
	}
	
	public function personalInfoChange($userid,$data)
	{
		$aff=$this->dbAdapter->update($this->tablePrivateInfo, $data,"userid=".$userid);
		return $aff;
	}

	public function getInfoForSelectList($table)
	{
		$q="SELECT id AS `key`, title AS `value` FROM ".$table;
		$result=$this->dbAdapter->fetchPairs($q);
		return $result;
	}
	/*
	 * 		$SQL="SELECT id AS `key`, title AS `value` FROM "."acl_roles";
	$rows=$this->dbAdapter->fetchPairs($SQL);
	*/

}