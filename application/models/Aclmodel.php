<?php
/**
 * @author zlydden
 * работает тока с Bootstrap
 * адаптер получает оттуда же
 */
class Aclmodel extends Zend_Db_Table
{
	protected $db;
	protected $_depth=8;// глубина дерева ролей
	protected $tbl_roles="acl_roles";
	protected $tbl_rolesEnv="acl_rolesEnv";
	protected $tbl_users="acl_users";
	protected $tbl_res="acl_resources";
	protected $tbl_res_annot="acl_res_annot";
	//	protected $tbl_resReq="acl_resReq";
	protected $tbl_priv="acl_privilege";
	protected $t_grpRoles="acl_groups_has_roles";
	protected $t_usrGroups="acl_groups_has_users";
	protected $t_groups="acl_groups";
	//	protected $_adminRole=1;
	//	protected $_guestRole=0;

	public function __construct()
	{
		$db = $this->getDefaultAdapter();
		$this->db=$db;
		//		$this->table='acl_roles';

	}

	public function getDepth()
	{
		return $this->_depth;
	}

	//	public function getAdminRole()
	//	{
	//		return $this->_adminRole;
	//	}
	//
	//	public function getGuestRole()
	//	{
	//		return $this->_guestRole;
	//	}

	/** дерево заданной роли
	 * @param integer $roleid
	 * @param integer $direction UP - роль и родители, DOWN - роль и потомки (пока только UP проверяется)
	 */
	public function getRoleTree($roleid,$direction="UP")
	{
		// Составляем большой SQL-запрос.
		$fields = array();  // куски после SELECT (имена полей выборки)
		$joins = array();   // JOIN-ы
		$wheres = array();  // WHERE-условия
		$levels = array();  // инкременты для level
		for ($i=0; $i<$this->_depth; $i++) {
			// Алиасы для таблицы.
			$alias     =       "t".sprintf("%04d", $i);
			$aliasPrev = $i>0? "t".sprintf("%04d", $i-1) : null;
			// Список полей для конкретного алиаса.
			$fields[] = join(", ", array(
      		"$alias.id as `$i.id`",
      		"$alias.parent as `$i.parent`",
      		"$alias.title as `$i.text`"
			));
			// LEFT JOIN только для второй и далее таблиц!
			if ($aliasPrev)
			// узнаются все кто выше начиная с $roleid роли включая её
			$joins[] = $direction==="UP"
			?	"LEFT JOIN $this->tbl_roles $alias ON ($alias.id=$aliasPrev.parent)"
			:	"LEFT JOIN $this->tbl_roles $alias ON ($alias.parent=$aliasPrev.id)";
			else
			$joins[] = "$this->tbl_roles $alias";
			// Привязываемся к корню (если поиск от корня).
			//			if (!$aliasPrev && substr($mask, 0,1)=='/') $wheres[] = "$alias.parent=0";
			if (!$aliasPrev ) {
				$wheres[] = "$alias.disabled=0";
				$wheres[] = "$alias.id=".$roleid;
			}

			// Условия поиска.

			//			if (isset($parts[$i])) $wheres[] = "$alias.text LIKE '".addslashes($parts[$i])."'";
			// Инкремент level-а.
			$levels[] = "IF($alias.id IS NOT NULL,1,0)";
		}
		$sql = implode("\n", array(
	    "SELECT",
    	 "\t(" .  implode("+", $levels) . ") as level,",
      	"\t" . implode(",\n\t", $fields),
    	"FROM",
      	"\t" . implode("\n\t", $joins),
    	"WHERE",
      	"\t" . implode(" AND \n\t", $wheres)
		#    "ORDER BY level DESC" . "\n" .
		));
		//		echo $this->db;
		//		die();
		$row=$this->db->fetchRow($sql);
		return $row;
		//		$logger=Zend_Registry::get("logger");
		//		$logger->log($row, Zend_Log::INFO);
	}
	
	/**
	 * Дерево заданной группы
	 * @param array $groupid
	 * @param string $direction
	 * @return mixed
	 */
	public function getGroupTree($groupid,$direction="UP")
	{
		$_groups=implode(",",$groupid);
		// Составляем большой SQL-запрос.
		$fields = array();  // куски после SELECT (имена полей выборки)
		$joins = array();   // JOIN-ы
		$wheres = array();  // WHERE-условия
		$levels = array();  // инкременты для level
		for ($i=0; $i<$this->_depth; $i++) {
			// Алиасы для таблицы.
			$alias     =       "t".sprintf("%04d", $i);
			$aliasPrev = $i>0? "t".sprintf("%04d", $i-1) : null;
			// Список полей для конкретного алиаса.
			$fields[] = join(", ", array(
      		"$alias.id as `$i.id`",
      		"$alias.parent as `$i.parent`",
      		"$alias.title as `$i.text`"
			));
			// LEFT JOIN только для второй и далее таблиц!
			if ($aliasPrev)
			// узнаются все кто выше начиная с $groupid роли включая её
			$joins[] = $direction==="UP"
			?	"LEFT JOIN $this->t_groups $alias ON ($alias.id=$aliasPrev.parent)"
			:	"LEFT JOIN $this->t_groups $alias ON ($alias.parent=$aliasPrev.id)";
			else
			$joins[] = "$this->t_groups $alias";
			// Привязываемся к корню (если поиск от корня).
			//			if (!$aliasPrev && substr($mask, 0,1)=='/') $wheres[] = "$alias.parent=0";
			if (!$aliasPrev ) {
				$wheres[] = "$alias.disabled=0";
				$wheres[] = "$alias.id IN (".$_groups.")";
			}

			// Условия поиска.

			//			if (isset($parts[$i])) $wheres[] = "$alias.text LIKE '".addslashes($parts[$i])."'";
			// Инкремент level-а.
			$levels[] = "IF($alias.id IS NOT NULL,1,0)";
		}
		$sql = implode("\n", array(
	    "SELECT",
    	 "\t(" .  implode("+", $levels) . ") as level,",
      	"\t" . implode(",\n\t", $fields),
    	"FROM",
      	"\t" . implode("\n\t", $joins),
    	"WHERE",
      	"\t" . implode(" AND \n\t", $wheres)
		#    "ORDER BY level DESC" . "\n" .
		));
		//		echo $this->db;
		//		die();
		$row=$this->db->fetchAll($sql);
		return $row;
		//		$logger=Zend_Registry::get("logger");
		//		$logger->log($row, Zend_Log::INFO);
	}

	/**
	 * 	 * узнаем роли, которые имеют указанные группы
	 * @param array $list
	 * @return array
	 */
	public function getGroupsRoles($list) 
	{
		$s=$this->db->select();
		$_list=implode(",",$list);
		$s->from($this->t_grpRoles,array("role"=>"acl_roles_id"));
		$s->where("acl_groups_id IN (".$_list.")");
		$s->order(" FIELD(acl_groups_id,".$_list.")");
		$result=$this->db->fetchCol($s);
		return $result; 
		;
	}
	
	/**
	 * 	 * узнаем роли, которые имеют указанные группы
	 * @param array $list
	 * @return array
	 */
	public function getGroupsRoles2($list) 
	{
		$s=$this->db->select();
		$_list=implode(",",$list);
		$s->from($this->t_grpRoles,array("roleid"=>"acl_roles_id"));
		$s->where("acl_groups_id IN (".$_list.")");
		$s->order(" FIELD(acl_groups_id,".$_list.")");
		$result=$this->db->fetchAll($s);
		return $result; 
		;
	}
	
	/**
	 * Получаем роли с указанными параметрами окружения Env
	 * @param string $string строка в столбце paramz acl_rolesEnv
	 * @return array
	 */
	public function getRolesByParam($string)
	{
		$s=$this->db->select();
		$s->from("acl_rolesEnv",array("key"=>"roleid","value"=>"paramz"));
		$s->where("paramz LIKE '%".$string."%'");
		return $this->db->fetchPairs($s);
		;
	}

	/** подготвока дерева ролей ЗАДАННОГО РЕСУРСА и возврат в обратном порядке
	 * т.е. на выходе первый элемент - гл.родитель, остальные дети
	 * @param array $tree
	 * @return array:(roleid,parent,title)
	 */

	public function treeRolePrepare($tree)
	{
		$numz=$tree["level"];
		$result=array();
		for($i=0;$i<$numz;$i++)
		{
			$temp=array();
			$temp["roleid"]=$tree[$i.".id"];
			$temp["parent"]=$tree[$i.".parent"];
			$temp["title"]=$tree[$i.".text"];
			$result[]=$temp;
		}
		return (array_reverse ($result));
	}


	public function getUserGroups($userid)
	{
		$s=$this->db->select();
		$s->from(array("gru"=>$this->t_usrGroups),array("acl_groups_id"));
		$s->where("gru.acl_users_id=".$userid);
		// исключим отключенные группы
		$s->join(array("gr"=>$this->t_groups), "gr.id=gru.acl_groups_id",null);
		$s->where("gr.disabled = 0");
		$s->order("gru.prior DESC");
		return $this->db->fetchCol($s);
		
	}
	
	public function getRoles() 
	{
		$s=$this->db->select();
		$s->from($this->tbl_roles,array("key"=>"id","value"=>"title"));
		$s->order("title ASC");
		return $this->db->fetchPairs($s);
	}

	/**
	 * уже назначенные группе роли
	 * @return Ambigous <multitype:, multitype:mixed >
	 */
	public function getGroupRoles($group) 
	{
		$s=$this->db->select();
		$s->from(array("grr"=>$this->t_grpRoles),null);
		$s->joinLeft(array("r"=>$this->tbl_roles), "r.id=grr.acl_roles_id",array("key"=>"id","value"=>"title"));
		$s->where("grr.acl_groups_id=".$group);
		$s->order("title ASC");
		return $this->db->fetchPairs($s);
	}
	
	/**
	 * список корневых ролей
	 * @return array
	 */
	public function getRoles_roots()
	{
		// Составляем большой SQL-запрос.
		$fields = array();  // куски после SELECT (имена полей выборки)
		$joins = array();   // JOIN-ы
		$wheres = array();  // WHERE-условия
		$levels = array();  // инкременты для level
		for ($i=0; $i<$this->_depth; $i++) {
			// Алиасы для таблицы.
			$alias     =       "t".sprintf("%04d", $i);
			$aliasPrev = $i>0? "t".sprintf("%04d", $i-1) : null;
			// Список полей для конкретного алиаса.
			$fields[] = join(", ", array(
      		"$alias.id as `$i.id`",
      		"$alias.parent as `$i.parent`",
      		"$alias.title as `$i.text`"
			));
			// LEFT JOIN только для второй и далее таблиц!
			if ($aliasPrev)
			// узнаются все кто выше начиная с $roleid роли включая её
			$joins[] = "LEFT JOIN $this->tbl_roles $alias ON ($aliasPrev.id=$alias.parent)";
			else
			$joins[] = "$this->tbl_roles $alias";
			// Привязываемся к корню (если поиск от корня).
			if (!$aliasPrev ) $wheres[] = "$alias.parent is null";
			//			if (!$aliasPrev ) {
			//				$wheres[] = "$alias.disabled=0";
			//				$wheres[] = "$alias.id=".$roleid;
			//			}

			// Условия поиска.

			//			if (isset($parts[$i])) $wheres[] = "$alias.text LIKE '".addslashes($parts[$i])."'";
			// Инкремент level-а.
			$levels[] = "IF($alias.id IS NOT NULL,1,0)";
		}
		$sql = implode("\n", array(
	    "SELECT",
    	 "\t(" .  implode("+", $levels) . ") as level,",
      	"\t" . implode(",\n\t", $fields),
    	"FROM",
      	"\t" . implode("\n\t", $joins),
    	"WHERE ",
      	"\t" . implode(" AND \n\t", $wheres)
		#    "ORDER BY level DESC" . "\n" .
		));
		//		echo $sql;die();
		$row=$this->db->fetchAll($sql);
		return $row;

	}


	
	/** привилегии заданных ролей к заданномму модулю и контроллеру
	 * @param array $roles
	 * @param unknown_type $module
	 * @param unknown_type $controller
	 * @return array
	 */
	public function getPrivilegies($roles,$module,$controller)
	{
		$q="SELECT priv.*,res.module, res.controller"
		." , TRIM('-' FROM CONCAT(res.module,'-',res.controller)) AS resName"
		."\n FROM ".$this->tbl_priv." AS priv"
		."\n LEFT JOIN ".$this->tbl_res." AS res"
		."\n ON res.id=priv.resource"
		."\n WHERE priv.role IN (".implode(',',$roles).")"
		."\n AND ( "
		// чисто модуль
		."\n (res.module LIKE '".$module."' AND res.controller like '' )"
		// модуль с контроллером
		."\n OR (res.module LIKE '".$module."' AND res.controller like '".$controller."')"
		."\n )"
		;
		//		echo $q;
		//		die();
		$rows=$this->db->fetchAll($q);
		return $rows;
	}

	/** привилегии для роли по ресурсу МОДУЛЬ-КОНТРОЛЛЕР
	 * @param integer $role
	 * @param string $resID
	 * @return array ключ = Action
	 */
	public function getPrivilegiesByResource($role,$resID)
	{
		$q="SELECT IFNULL(priv.action,'_ALL_') AS action" // если NULL
		."\n ,priv.params,priv.allow,priv.comment"
		."\n ,res.module, res.controller"
		." , TRIM('-' FROM CONCAT(res.module,'-',res.controller)) AS resName"
		."\n FROM ".$this->tbl_priv." AS priv"
		."\n LEFT JOIN ".$this->tbl_res." AS res"
		."\n ON res.id=priv.resource"
		."\n WHERE priv.role =".$role
		."\n AND TRIM('-' FROM CONCAT(res.module,'-',res.controller)) LIKE '".$resID."'";
		;

		$result=$this->db->fetchAssoc($q);
		return $result;
		;
	}

	/** привилегии списка ролей заданного дерева ресурсов
	 * @param array $rList (roleid,roleid...)
	 * @param array $resTree (resId=>resTitle)
	 * @return array
	 */
	public function getPrivForResTree($rList,$resTree)
	{
		$resources=array_keys($resTree);
		foreach ($resources as &$res)
		{
			$res="'".$res."'";
			;
		}
		//		$rows=implode(',',$resources);
		$q="SELECT priv.*,res.module, res.controller"
		." , TRIM('-' FROM CONCAT(res.module,'-',res.controller)) AS resName"
		."\n FROM ".$this->tbl_priv." AS priv"
		."\n LEFT JOIN ".$this->tbl_res." AS res"
		."\n ON res.id=priv.resource"
		."\n WHERE priv.role IN (".implode(',',$rList).")"
		."\n AND TRIM('-' FROM CONCAT(res.module,'-',res.controller)) IN (".implode(",",$resources).")"
		;
		$q.="\n ORDER BY FIELD(priv.role, ".implode(',',$rList).") ASC";
// 		$s->order(" FIELD(acl_groups_id,".$_list.")");
		//		echo $q;
		//		die();
		$rows=$this->db->fetchAll($q);
		return $rows;

	}

	/** описанные в БД ресурсы, КЛЮЧ - НАЗВАНИЕ
	 * @return array
	 */
	public function getResourcesDescription()
	{
		$q="SELECT TRIM('-' FROM CONCAT(module,'-',controller)) AS `key`"
		."\n,  title AS `value`"
		."\n FROM ".$this->tbl_res
		."\n ORDER BY `key` ASC"; // очень важно
		//		echo $q;
		//		die();
		$rows=$this->db->fetchPairs($q);
		return $rows;
	}

	/*
	* получение аннотаций к модулям
	* @param string имя модуля
	* @return string
	*/
	public function getModuleAnnotation($module)
	{
		$select=$this->db->select()->from(array("res"=> $this->tbl_res),null);
		$select->joinLeft
		(
				array("annot"=>$this->tbl_res_annot),
				"res.id=annot.resid","annotation"
		);
		// указанный модуль
		$select->where("res.module LIKE '".$module."'");
		// контроллер на задан
		$select->where("res.controller =''");
	
		$stmt = $this->db->query($select);
		$result = $stmt->fetchColumn();
		return $result;
	}
	
	
	/** все ресурсы для указанных ролей для которых описаны привилегии
	 * @param array $rList ($roleid,$roleid)
	 * @return array
	 */
	public function getResourcesPrivilegied($rList)
	{
		$q="SELECT priv.*,res.module, res.controller,res.title"
		." , TRIM('-' FROM CONCAT(res.module,'-',res.controller)) AS resName"
		."\n FROM ".$this->tbl_priv." AS priv"
		."\n LEFT JOIN ".$this->tbl_res." AS res"
		."\n ON res.id=priv.resource"
		."\n WHERE priv.role IN (".implode(',',$rList).")"
		."\n GROUP BY resName"
		//		."\n AND TRIM('-' FROM CONCAT(res.module,'-',res.controller)) IN (".implode(",",$resources).")"
		;
		$rows=$this->db->fetchAll($q);
		return $rows;
			
	}

	public function putMenu($role,$nav)
	{
		$_nav=$nav->toArray();
		$data=array(
		"roleid"=>$role,
		"nav"=>serialize($_nav)
		);
		$this->db->insert("nav_items2",$data);
		;
	}

	public function delMenu($role)
	{
		$where=" roleid=".$role;
		$this->db->delete('nav_items2',$where);

		;
	}

	public function getMenu($role)
	{
		$where=" roleid=".$role;
		$q="SELECT nav FROM nav_items2 WHERE ".$where;
		$nav=$this->db->fetchOne($q);
		return $nav;

		;
	}

	/** получение параметров для ролей
	 * @param array  $rolelist
	 * @return unknown
	 */
	public function getRolesParamz($rolelist)
	{
		$sql="SELECT paramz FROM ".$this->tbl_rolesEnv
		."\n WHERE roleid IN (".implode(",",$rolelist).")";
		$info=$this->db->fetchAssoc($sql);
		return $info;
	}

	/** получение параметров для групп
	 * @param array  $list
	 * @return unknown
	 */
	public function getGroupParamz($list)
	{
		$sql="SELECT paramz FROM ".$this->t_groups
		."\n WHERE id IN (".implode(",",$list).")";
		$info=$this->db->fetchAssoc($sql);
		return $info;
	}

	/** получение имен параметров для ресурсов
	 * @param array $reslist
	 */
	public function getResReqParamz($reslist)
	{
		foreach ($reslist as &$res)
		{
			$res="'".$res."'";
			;
		}
		$sql="SELECT TRIM('-' FROM CONCAT(module,'-',controller)) AS `key`"
		."\n , paramNames AS `value`"
		."\n FROM ".$this->tbl_res
		."\n WHERE TRIM('-' FROM CONCAT(module,'-',controller)) IN (".implode(",",$reslist).")"
		."\n ORDER BY `key`";
		//		echo $sql;die();
		$info=$this->db->fetchPairs($sql);
		return $info;
	}

	public function setRoles2Group($group,$roles) 
	{
		$this->db->beginTransaction();
		try {
			// 1. убрать все роли от этой группы
			$this->db->delete($this->t_grpRoles,"acl_groups_id=".$group);
			// 2. назначить новое
			foreach ($roles as $r) 
			{
				$this->db->insert($this->t_grpRoles, array("acl_groups_id"=>$group,"acl_roles_id"=>$r));
			}
			$result["status"]=true;
			$this->db->commit();
		
		} catch (Zend_Exception $e) {
			$result["status"]=false;
			$result["errorMsg"]=$e->getMessage();

			$this->db->rollback();
		};
		//		$data["userid"]=$userid;
		return $result;
		
		
		;
	}

}
