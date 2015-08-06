<?php
Zend_Loader::loadClass('Typic');

class Orders extends Typic
{
	var $priv=array(
	'VIEW'=>'100', // просмотр
	'EDIT'=>'200', // просмотр + изменения 
	'SHARE'=>'400', // просмотр + изменения + публикация + смена привилегий
	'FULL'=>'999', // полный
	);
	var $privTitles=array(
	'VIEW'=>'Просмотр', // просмотр
	'EDIT'=>'Просмотр + изменения', // просмотр + изменения 
	'SHARE'=>'Просмотр + изменения + публикация + смена привилегий', // просмотр + изменения + публикация + смена привилегий
	'FULL'=>'Полный доступ', // полный
	);

	//	const VIEW	=	100; // просмотр
	//	const EDIT	=	200; // изменение
	////	const PRIV	=	300; // смена привилегий
	//	const SHARE	=	400; // публикация и смена привилегий
	//	const FULL	=	999; // полный, даже удаление

	private $_acl;
	private $_aclModel;
	private $_tbl="docus";
	//	private $db;
	//	private $dekanat;

	public function __construct()
	{
// 		parent::__construct($facultId);

		$this->_aclModel=new Aclmodel();		;
	}


	public function getPrivilegesList()
	{
		return $this->priv;
	}

	public function getPrivilegesListTitles()
	{
		return $this->privTitles;
	}

	public function getRoleChildrenTree($role,$direction="DOWN")
	{
		return $this->_aclModel->getRoleTree($role,$direction);
		//		return $this->_aclModel;
		//		$this->person;
	}

	public function getRoles_roots()
	{
		return $this->_aclModel->getRoles_roots();
	}

	public function getUsersInRole($role)
	{
		return $this->_acl_user->getListInRole($role);
		;
	}

	/**
	 * Список привилегий к документу
	 * @param integer $id документ
	 * @return array
	 */
	public function getDocumentPrivs($id,$where=array())
	{
		$db = $this->getDefaultAdapter();
		$select=$db->select()
		->from(array("priv"=> "docus_priv"),
		array("document"=>"document",
		"userid"=>"userid",
		"action",
	 	"allow") );

		$select->joinLeft(
		array("u"=>"acl_users"),
		"u.id=priv.userid",
		array("u.login")
		);
		$select->joinLeft(
		array("p"=>"personal"),
		"p.userid=u.id",
		array("fio"=>"CONCAT_WS(' ',p.family,p.name,p.otch)")
		);


		$select->where("document = ".$id);

		if (!empty($where))
		{
			foreach ($where as $wh)
			{
				$select->where($wh);
			}
		}

		$select->order("fio ASC");

		$stmt = $db->query($select);
		$result = $stmt->fetchAll();
		return $result;
	}

	/**
	 * Список привилегий к документу
	 * @param integer $id документ
	 * @return array
	 */
	public function getDocumentPrivs4User($id,$userid)
	{
		$db = $this->getDefaultAdapter();
		$select=$db->select()
		->from(array("priv"=> "docus_priv"),
		array("document"=>"document",
		"userid"=>"userid",
		"action",
	 	"allow") );
		$select->where("document = ".$id);
		$select->where("userid = ".$userid);

		$stmt = $db->query($select);
		$result = $stmt->fetch();
		return $result;
	}

    public function getOrderInfo($id)
    {
    	$db = $this->getDefaultAdapter();
		$select=$db->select()
		->from(array("d"=> $this->_tbl),
		array("id"=>"id",
		"createtime"=>"DATE_FORMAT(d.createtime,'%d-%m-%Y %H:%i:%s')",
		"comment",
	 	"type", 	
	 	"author", 	
	 	"titleNum", 	
	 	"titleLetter", 	
	 	"titleDate"=>"DATE_FORMAT(d.titleDate,'%d-%m-%Y')"));

		$where=array();
		$where[]=	"id = ".$id;
		$where[]=	"type = 2"; // тип - приказ
				foreach ($where AS $wh)
		{
			$select->where($wh);
		}
		$stmt = $db->query($select);
		$result = $stmt->fetch();
		return $result;
		
    	
    }	

	public function getOrdersList($criteria,$users,$admin=false)
	{
		$db = $this->getDefaultAdapter();
		$users=is_array($users)?$users:array($users);


		$select=$db->select()
		->from(array("d"=> $this->_tbl),
		array("id"=>"id",
		"createtime"=>"DATE_FORMAT(d.createtime,'%d-%m-%Y %H:%i:%s')",
		"comment",
	 	"type", 	
	 	"author", 	
	 	"titleNum", 	
	 	"titleLetter", 	
	 	"titleDate"=>"DATE_FORMAT(d.titleDate,'%d-%m-%Y')"));

		$where=array();
		$where[]=	"titleLetter LIKE '%".$criteria["titleLetter"]."%'";
		if ($criteria["titleNum"]!=0) $where[]=	"titleNum = ".$criteria["titleNum"];
		$where[]=	"createtime BETWEEN '".$criteria["createDate1"]." 00:00:00' AND '".$criteria["createDate2"]." 23:59:59'";
		$where[]=	"titleDate BETWEEN '".$criteria["titleDate1"]."' AND '".$criteria["titleDate2"]."'";
		$where[]=	"type = 2"; // тип - приказ
		foreach ($where AS $wh)
		{
			$select->where($wh);
		}

//		$select->joinLeft(
//		array("priv"=>"docus_priv"),
//		"priv.document=d.id",
//		array("allow"=>"allow","action"=>"action","userid"=>"userid")
//		);
//		if (!$admin){
//			$where=array();
//			$where[]="priv.action >= ".$this->priv["VIEW"]; // привилегия как минимум на чтение
//			$where[]="priv.allow = 1"; // разрешено
//			//		$where[]="priv.role IN (".implode(",", $roles).")"; // роль в интервале
//			$where[]="priv.userid IN (".implode(",", $users).")"; // роль в интервале
//			foreach ($where AS $wh)
//			{
//				$select->where($wh);
//			}
//		}
		//		$select->joinLeft(
		//		array("r"=>"acl_roles"),
		//		"r.id=priv.role",
		//		array("roleTitle"=>"title")
		//		);
		$select->joinLeft(
		array("u"=>"acl_users"),
		"u.id=d.author",
		array("login"=>"login")
		);
		$select->joinLeft(
		array("p"=>"personal"),
		"p.userid=d.author",
		array("fio"=>"CONCAT_WS(' ',p.family,p.name,p.otch)")
		);

		$select->group("d.id");
		$select->order("d.titleNum DESC");
		//		$or[]=	"author =".$role;
		//		$select->orWhere($cond)

		$stmt = $db->query($select);
		$result = $stmt->fetchAll();
		return $result;
	}

	public function getOrdersList4Select($criteria,$users,$admin=false)
	{
		$db = $this->getDefaultAdapter();
		$users=is_array($users)?$users:array($users);
		$q="SELECT `d`.`id` AS `key`"
		."\n , CONCAT('№ ',titleNum,'-',titleLetter,' от ',DATE_FORMAT(d.titleDate,'%d-%m-%Y')) AS `value`"
		."\n FROM `".$this->_tbl."` AS `d`"
//		."\n LEFT JOIN `docus_priv` AS `priv` ON priv.document=d.id"
		;
		$where=array();
		$where[]=	" d.titleLetter LIKE '%".$criteria["titleLetter"]."%'";
		if ($criteria["titleNum"]!=0) $where[]=	" d.titleNum = ".$criteria["titleNum"];
		$where[]=	" d.createtime BETWEEN '".$criteria["createDate1"]." 00:00:00' AND '".$criteria["createDate2"]." 23:59:59'";
		$where[]=	" d.titleDate BETWEEN '".$criteria["titleDate1"]."' AND '".$criteria["titleDate2"]."'";
		$where[]=	" d.type = 2"; // тип - приказ
//		if (!$admin){
//			$where[]=" priv.action >= ".$this->priv["VIEW"]; // привилегия как минимум на чтение
//			$where[]=" priv.allow = 1"; // разрешено
//			$where[]=" priv.userid IN (".implode(",", $users).")"; // роль в интервале
//		}
		$_wh=implode(" AND ", $where);
		$q=$q."\n WHERE". $_wh;
		$q.="\n GROUP BY `d`.`id` "
		."\n ORDER BY `d`.`titleNum` DESC"
		;
		$result=$db->fetchPairs($q);
		return $result;
	}

	public function delDocumentPrivs($documentid,$where)
	{
		$_where=array();
		$_where[]=" document = ".$documentid;
		if (!is_array($where)) $where=array($where);
		$_where=array_merge($_where,$where);
		$_where=implode(" AND ", $_where);
		$db=$this->getDefaultAdapter();
		$aff=$db->delete("docus_priv",$_where);
		//		if ($aff>0)return $db->lastInsertId("docus_priv");
		return $aff;
	}

	public function addPrivileges($documentid,$userid,$priv,$allow)
	{
		$data=array(
		"document"=>$documentid,
		"userid"=>$userid,
		"action"=>$this->priv[$priv],
		"allow"=>$allow
		);
		$db=$this->getDefaultAdapter();
		$aff=$db->insert("docus_priv",$data);
		if ($aff>0)return $db->lastInsertId("docus_priv");
		else return false;
	}

	public function updateOrder($data,$id)
	{
		
		$db=$this->getDefaultAdapter();
		$aff=$db->update($this->_tbl,$data,"id=".$id);
	}
	
	public function createOrder($author,$data)
	{

		$data["createtime"]=date("Y-m-d H:i:s");
		$data["type"]=2;
		$data["author"]=$author;
		$db=$this->getDefaultAdapter();
		$aff=$db->insert($this->_tbl,$data);
		if ($aff>0)return $db->lastInsertId($this->_tbl);
		else return false;
	}
	
	public function delOrder($id) 
	{
		$db=$this->getDefaultAdapter();
		try {
			$aff=$db->delete($this->_tbl,"id=".$id);
			return $aff;
		} 
		catch (Exception $e) 
		{
			return false;
			
		}
		
		;
	}
	
}