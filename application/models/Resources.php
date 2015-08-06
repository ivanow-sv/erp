<?php

class Resources extends Aclmodel
{

	/** создает привилегию
	 * @param integer $role
	 * @param array $resID_db ID ресурса в БД
	 * @param string $action может быть и _ALL_
	 * @param integer $allow 1 или 0
	 */
	public function createPrivilege($role,$resID_db,$action,$allow)
	{
		$data=array(
		'role'=>$role,
		'resource'=>$resID_db,
		'action'=>$action,
//		'params'=>$params,
		'allow'=>$allow
//		'comment'=>$comment,
		);

		$this->db->insert($this->tbl_priv,$data);
		return $this->db->lastInsertId();
	}

	/**
	 * обновляет привилеги. заданной ролью, ресурсом и ACTION
	 * @param integer $allow
	 * @param integer $role
	 * @param integer $resource
	 * @param string $action
	 * @param string $params
	 * @param string $comment
	 */
	public function updatePrivilege($allow,$role,$resource,$action,$params=null,$comment='')
	{
		$where=" role=".$role;
		$where.="\n AND resource=".$resource;
		$where.=is_null($action)?"\n AND action is null":"\n AND action like '".$action."'";
		$data=array(
		'params'=>$params,
		'allow'=>$allow,
		'comment'=>$comment,
		);
		$this->db->update(array('name'=>'acl_privilege'),$data,$where);
	}

	/**
	 *  удалить привилегию
	 * @param integer $role
	 * @param integer $resource
	 * @param string $action
	 */
	public function deletePrivilege($role,$resource,$action)
	{

		$where=" role=".$role;
		$where.="\n AND resource=".$resource;
		switch ($action)
		{
			case NULL:
				$where.="\n AND action is null";
				break;

			case "FULL_REMOVE":
				break;

			default:
				$where.="\n AND action like '".$action."'";
				break;
		}
		//		$where.=is_null($action)?"\n AND action is null":"\n AND action like '".$action."'";

		$this->db->delete('acl_privilege',$where);

	}

	/**
	 * @param string $modulName
	 * @param string $controllerName
	 * @param string $title
	 * @param integer $parent
	 * @param string $coment
	 */
	public function createResource($modulName,$title,$controllerName='',$parent=0,$coment='')
	{
		$data=array(
		'module'=>$modulName,
		'controller'=>$controllerName,
		'title'=>$title,
		'parent'=>$parent,
		'coment'=>$coment,
		);

		$this->db->insert(array('name'=>$this->tbl_res),$data);
		return $this->db->lastInsertId();
	}


	/**
	 * возвращает ID и название ресурса заданного парой модуль-модель
	 * @param string $module
	 * @param string $controller
	 * @return array
	 */
	public function getTitle($module,$controller)
	{
		$q="SELECT id,title,module,controller FROM ".$this->tbl_res." WHERE module LIKE '".$module."' ";
		$q.="AND controller LIKE '".$controller."'";
		$title=$this->db->fetchRow($q);
		//		echo $q."<hr>";
		return $title;
	}

	/**
	 * возрат данных о ресурсе
	 * @param integer $id
	 * @return array инфо
	 */
	public function getInfo($id)
	{
		$q="SELECT res.*,priv.role,roles.title AS roleTitle,roles.parent AS roleParent,roles.comment AS roleComment, priv.action, priv.params, priv.allow, priv.comment AS privComment";
		$q.="\n FROM `acl_resources` AS res";
		$q.="\n LEFT JOIN acl_privilege AS priv ON res.id=priv.resource";
		$q.="\n LEFT JOIN acl_roles AS roles ON roles.id=priv.role";
		$q.="\n WHERE res.id=".$id;
		$q.="\n ORDER BY priv.role ASC";
		//		echo $q;
		$info=$this->db->fetchAll($q);

		return $info;
	}

	/**
	 * возврат строки привилегий по ресурсу, роли и ACTION
	 * @param integer $res_id
	 * @param integer $role_id
	 * @param string $action
	 * @return array
	 */
	public function getInfoResRoleAction($res_id,$role_id,$action)
	{
		$q="SELECT * FROM acl_privilege";
		$q.="\n WHERE role=".$role_id;
		$q.="\n AND resource=".$res_id;
		$q.=is_null($action)?"\n AND action is null":"\n AND action LIKE '".$action."'";
		$info=$this->db->fetchRow($q);
		return $info;
	}

	/**
	 * возврат всех привилегий заданных ресурсу и роли
	 * @param integer $role_id
	 * @param integer $res_id
	 */
	public function getPrivileges($role_id,$res_id)
	{
		$q="select action ,params,allow,comment from acl_privilege"
		."\n where role=".$role_id
		."\n and resource=".$res_id
		."\n  ORDER BY action ASC"; // ЭТО ОЧЕНЬ ВАЖНО
		//		echo $q;
		$list=$this->db->fetchAssoc($q);
		return $list;

	}

	/**
	 * возврат список ролей заданных ресурсом
	 * @param integer $res_id
	 * @return array
	 */
	public function getResRoles($res_id)
	{
		$q="select priv.role,r.title AS roleTitle from acl_privilege AS priv";
		$q.="\n LEFT JOIN acl_roles AS r";
		$q.="\n ON r.id=priv.role";
		$q.="\n where priv.resource=".$res_id;
		$q.="\n group by role";
		$list=$this->db->fetchAll($q);
		return $list;

	}

	/**
	 * информация о ресурсе
	 * @param string $id ресурс
	 * @return array инфо о ресурсе
	 */
	public function getResInfo($resID)
	{
		$q="SELECT res.* FROM ".$this->tbl_res." AS res";
//		$q.="\n LEFT JOIN ".$this->tbl_resReq." AS resReq";
//		$q.="\n ON resReq.resource LIKE TRIM('-' FROM CONCAT(res.module,'-',res.controller))";
		$q.="\n WHERE TRIM('-' FROM CONCAT(res.module,'-',res.controller)) LIKE '".$resID."'";
//		echo $q;die();
		$info=$this->db->fetchRow($q);
		return $info;
	}

	public function updateInfo($resID,$title,$comment,$paramNames)
	{
		$data=array(
		'title'=>$title,
		'coment'=>$comment,
		'paramNames'=>$paramNames,
		);
		$where=" id=".$resID;
		$this->db->update($this->tbl_res,$data,$where);
		// 

	}

	/** удалить все связанное с указанными ресурсами
	 * @param array $list (_MODULE-CONTROLLER_ => _TITLE_)
	 */
	public function clean($list)
	{
		$logger=Zend_Registry::get("logger");

		$resources=array_keys($list);
		// добавим кавчки
		foreach ($resources as &$res)
		{
			$res="'".$res."'";
		}
		// найдем все связанные привилегии
		$q="SELECT priv.id FROM ".$this->tbl_priv." AS priv"
		."\n LEFT JOIN ".$this->tbl_res." AS res"
		."\n ON priv.resource=res.id"
		."\n WHERE TRIM('-' FROM CONCAT(res.module,'-',res.controller)) IN (".implode(",",$resources).")"
		;
		$privIDs=$this->db->fetchAssoc($q);
		$privIDs=array_keys($privIDs);
		if (count($privIDs)>0)
		{
			// удалим упоминания о них
			$this->db->delete($this->tbl_priv," id IN (".implode(",",$privIDs).")");
		}
		// удалим ресурсы
		$this->db
		->delete(
		$this->tbl_res,
		" TRIM('-' FROM CONCAT(module,'-',controller)) IN (".implode(",",$resources).")"
		);
	}

	/** удаляет привилегии связанные с ролью и ID ресурса (ID в БД)
	 * @param integer $role
	 * @param integer $resDB_id
	 */
	public function cleanPriv($role,$resDB_id)
	{
		$where[] = $this->db->quoteInto('role = ?', $role);
		$where[] = $this->db->quoteInto('resource = ?', $resDB_id);
		$this->db->delete($this->tbl_priv,$where);

		;
	}
	/** добаим недостающие ресурсы
	 * @param array $list (_MODULE-CONTROLLER_ => _MODULE-CONTROLLER_)
	 */
	public function add($list,$comment='')
	{
		//		$resources=array_keys($list);
		// добавим кавчки
		foreach ($list as $resource=>$title)
		{
			$temp=explode("-",$resource);

			if (count($temp)>1)
			{
				$module=$temp[0];
				$controller=$temp[1];

			}
			else
			{
				$module=$temp[0];
				$controller='';
					
			}

			$data=array(
			"module"=>$module,
			"controller"=>$controller,
			"title"=>$title,
			"coment"=>$comment
			);
			$this->db->insert($this->tbl_res,$data);
		}
	}

	/**
	 * список ролей
	 * @return array
	 */
	public function getRolesList()
	{
		$SQL="SELECT id AS `key`, title AS `value` FROM "."acl_roles";
		$rows=$this->db->fetchPairs($SQL);
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
		$rows=$this->db->fetchAssoc($q);
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

	
}