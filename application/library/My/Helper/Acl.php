<?php
class My_Helper_Acl extends Zend_Controller_Action_Helper_Abstract
{
	private $_defAclModule="default";
	private $_adminRole=1;
	private $_adminGroup=1;
	private $_guestRole=2;
	private $_guestGroup=2;

	private $defRes=array(
			"default"=>array(
					"index",
					"auth",
					"error"
			)
	);

	private $defaultNav=array(
			array(
					'module'=>'default',
					'controller'=>'index',
					'label'=>'Главная',
					'pages'=>array
					(
							array(
									"label"=>"Вход",
									'module'=>'default',
									'controller'=>'auth',
									'action'=>'login',
							),
							array(
									"label"=>"Выход",
									'module'=>'default',
									'controller'=>'auth',
									'action'=>'logout',
							)
					)
			),

	);

	public function getDefaultNav($role)
	{
		$guest=array(
				"label"=>"Вход",
				'module'=>'default',
				'controller'=>'auth',
				'action'=>'login',
		);
		$nonguest=array(
				"label"=>"Выход",
				'module'=>'default',
				'controller'=>'auth',
				'action'=>'logout',
		);
		if ($role==0) return $guest;
		else return $nonguest;
		//		return ;
	}

	public function getDefAclModule()
	{
		return $this->_defAclModule;
	}

	public function getDefAclResTree()
	{
		$module=$this->getDefAclModule();
		$result[$module]=$module;
		foreach ($this->defRes as $moduleName=> $childs)
		{
			foreach ($childs as $contrName)
			{
				$result[$moduleName."-".$contrName]=$moduleName."-".$contrName;
			}
		}
		return $result;
	}

	/**
	 * список "родителей" роли
	 * @param unknown_type $role
	 * @return array
	 */
	public function getParents($role)
	{
		$acl=Zend_Registry::get("ACL");
		$parents = array();
		foreach ($acl->getRoles() as $inherit)
		{
			if ($acl->inheritsRole($role, $inherit))
			{
				$parents[] = $inherit;
			}
		}
		return $parents;
		;
	}

	public function getAdminRole()
	{
		return $this->_adminRole;
	}

	public function getGuestRole()
	{
		return $this->_guestRole;
	}

	public function getAdminGroup()
	{
		return $this->_adminGroup;
	}

	public function getGuestGroup()
	{
		return $this->_guestGroup;
	}


	/** подготвока дерева ролей ЗАДАННОЙ РОЛИ и возврат в обратном порядке
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

	/** подготвока дерева групп ЗАДАННОЙ группы и возврат в обратном порядке
	 * т.е. на выходе первый элемент - гл.родитель, остальные дети
	 * @param array $tree
	 * @return array:(groupid,parent,title)
	 */
	public function treeGroupPrepare($big_tree)
	{
		$result=array();
		foreach ($big_tree as $key => $tree) 
		{
			$numz=$tree["level"];
			for($i=0;$i<$numz;$i++)
			{
				$temp=array();
				$temp["groupid"]=$tree[$i.".id"];
				$temp["parent"]=$tree[$i.".parent"];
				$temp["title"]=$tree[$i.".text"];
				$result[]=$temp;
			}
			;
		}

		return (array_reverse ($result));
	}

	/**
	 * Добавление гостевых ролей к списку корневых
	 */
	public function addGuestToRootsTree($roles)
	{
		$guest=array(
				"level"=>1,
				"0.id"=>$this->getGuestRole(),
				"0.parent"=>null,
				"0.text"=>"Гости"
		);
		if (empty($roles)) $roles=$guest;
		else array_unshift($roles,$guest);
		return $roles;
	}

	/** перестройка списка КОРНЕВЫХ ролей для выпадающего списка
	 * @param array $roles все корневые роли с потомками
	 * @return array ID => название
	 */
	public function treeRolesPrepare($roles)
	{
		$result=array();
		foreach ($roles as $tree)
		{
			$numz=$tree["level"];
			$pref='';
			for ($i=0;$i<$numz;$i++)
			{
				$result[$tree[$i.".id"]]=$pref.$tree[$i.".text"];
				if ($i==0) $pref =" |";
				$pref=rtrim($pref)."-- ";
			}
		}
		return $result;
	}


	public function addDefaultRes(&$acl)
	{
		foreach ($this->defRes as $moduleName => $item)
		{
			$acl ->addResource(new Zend_Acl_Resource($moduleName));
			if (is_array($item)&&count($item)>0)
			{
				foreach ($item as $controllerName)
				{
					$resName=$moduleName."-".$controllerName;
					$acl ->addResource(new Zend_Acl_Resource($resName),$moduleName);
					;
				}
			}
		}
		// общие ресурсы - обазательно
		//		$acl ->addResource(new Zend_Acl_Resource('default'));
		//		$acl ->addResource(new Zend_Acl_Resource('default-index'),'default');
		//		$acl ->addResource(new Zend_Acl_Resource('default-auth'),'default');
		//		$acl ->addResource(new Zend_Acl_Resource('default-error'),'default');
	}

	public function addDefaultRoles(&$acl)
	{
		// админы и гости
		$adminRole=$this->getAdminRole();
		$guestRole=$this->getGuestRole();
		$acl ->addRole(new Zend_Acl_Role($adminRole)); // суперадмины
		$acl ->addRole(new Zend_Acl_Role($guestRole)); // гости
	}

	public function addDefaultPrivs(&$acl)
	{
		$acl ->allow($this->getAdminRole(),null,null);
		// всем можно на дефолтовое
		$acl ->allow(null,$this->getDefAclModule(),null);
	}

	/** Извлечение перечня ролей из дерева
	 * @param array $tree
	 * @return array
	 */
	public function rolesListFromTree($tree)
	{
		$result=array();
		foreach ($tree as $item)
		{
			$result[]=$item["roleid"];
		}
		return $result;
	}

	/** Извлечение перечня групп из дерева
	 * @param array $tree
	 * @return array
	 */
	public function groupsListFromTree($tree)
	{
		$result=array();
		foreach ($tree as $item)
		{
			$result[]=$item["groupid"];
		}
		return $result;
	}


	/**
	 * построение ACL для редактора прав ролей
	 * @param Zend_Acl $acl
	 * @param array $roleTree
	 * @param array $resTree двумерный вида [module]=>(module-controller_1,module-controler_2)
	 * @param array $priv
	 */
	public function buildAclRoles(&$acl,$roleTree,$resTree,$priv)
	{
		// ресурсы
		// ресурсы по умолчанию
		// @TODO может добавлять их потом, проверяя, не заданы ли уже
		$this->addDefaultRes($acl);

		// остальные ресурсы
		foreach ($resTree as $module=>$childs)
		{
			if ($module!==$this->getDefAclModule())
			{
				// ресурс модуля
				$acl->addResource(new Zend_Acl_Resource($module));
				// ресурс "модуль-контроллер", наследник "модуль"
				if (is_array($childs) && count($childs)>0)
					foreach ($childs as $modContResource=>$title)
					{
						$acl->addResource(new Zend_Acl_Resource($modContResource),$module);
					}
			}
		}

		// роли по умолчанию
		$this->addDefaultRoles($acl);

		$adminRole=$this->getAdminRole();
		$guestRole=$this->getGuestRole();
		//		$roleList=array($adminRole,$guestRole); // перечень ролей для поиска привилегий
		foreach ($roleTree as $node)
		{
			// пропустим одминов и гостей - их уже назначили
			if ($node["roleid"]==$adminRole || $node["roleid"]==$guestRole) continue;
			if (is_null($node["parent"])) $acl ->addRole(new Zend_Acl_Role($node["roleid"]));
			else $acl ->addRole(new Zend_Acl_Role($node["roleid"]),$node["parent"]);
			//			$roleList[]=$node["roleid"]; // добавим еще
			;
		}

		// привилегии
		// привилении по умолчанию - там админы и гости
		$this->addDefaultPrivs($acl);
		// 		$logger=Zend_Registry::get("logger");
		// 		$logger->log($roleTree, Zend_Log::INFO);
		//		$privilegies=$this->_model->getPrivilegies($roleList,$module,$controller);
		if (is_array($priv) && count($priv)>0)
		{
			foreach ($priv as $v)
			{
				if ($v["allow"]==1) $acl ->allow($v["role"],$v["resName"],$v["action"], new My_Assert());
				else $acl ->deny($v["role"],$v["resName"],$v["action"]);
			}
		}

	}

	/**
	 * построение ACL для авторизации с учетом группы
	 * @param Zend_Acl $acl
	 * @param array $roleList роли данной группы
	 * @param array $resTree двумерный вида [module]=>(module-controller_1,module-controler_2)
	 * @param array $priv
	 */
	public function buildAcl(&$acl,$roleList,$resTree,$priv,$userid)
	{
		// ресурсы
		// ресурсы по умолчанию
		// @TODO может добавлять их потом, проверяя, не заданы ли уже
		$this->addDefaultRes($acl);

		// остальные ресурсы
		foreach ($resTree as $module=>$childs)
		{
			if ($module!==$this->getDefAclModule())
			{
				// ресурс модуля
				$acl->addResource(new Zend_Acl_Resource($module));
				// ресурс "модуль-контроллер", наследник "модуль"
				if (is_array($childs) && count($childs)>0)
					foreach ($childs as $modContResource=>$title)
					{
						$acl->addResource(new Zend_Acl_Resource($modContResource),$module);
					}
			}
		}

		// роли по умолчанию
		$this->addDefaultRoles($acl);

		// добавим если её ыще нет, а может она быть если это гость или админ
		// 		if (!$acl->hasRole($group)) $acl ->addRole(new Zend_Acl_Role($group));
		if (!$acl->hasRole($userid)) $acl ->addRole(new Zend_Acl_Role($userid));

		// привилегии
		// привилении по умолчанию - там админы и гости
		$this->addDefaultPrivs($acl);

		// есть ли у данной группы админская роль ?
		$chkAdmin=array_search($this->getAdminRole(),$roleList);
		if ($chkAdmin!==false)  $acl ->allow($userid,null,null);
		// 		$chkGuest=array_search($this->getGuestRole(),$roleList);
		// 		$logger=Zend_Registry::get("logger");
		//  		$logger->log($roleList,Zend_Log::INFO);
		//  		$logger->log($chkAdmin,Zend_Log::INFO);
		//  		$logger->log($chkGuest,Zend_Log::INFO);

		
		if (is_array($priv) && count($priv)>0)
		{
			foreach ($priv as $v)
			{
				if ($v["allow"]==1) 
				{ 
					$acl ->allow($userid,$v["resName"],$v["action"], new My_Assert());
					
					// разрешить доступ к контроллеру "index" - это описание модуля
					// если конечто есть хоть куда-то доступ внутри модуля
					// ===============================
					// контроллер INDEX должен быть без ACTION's вообще
					// ===============================
					$_c=$this->getModuleControllerFromRes($v["resName"]);
					// если имя ресурса из МОДУЛЬ-КОНТРОЛЛЕР, значит разрещим досуп к МОДУЛЬ-INDEX
					if (!empty($_c["controller"]) && $_c["module"]!=='default' && $userid!==2) 
						$acl ->allow($userid,$_c["module"]."-index",$v["action"], new My_Assert());
					
				}
				else $acl ->deny($userid,$v["resName"],$v["action"]);
			}
		}

	}

	public function getModuleControllerFromRes($resource)
	{
		$result=array();
		$temp=explode("-",$resource);

		$result["module"]=$temp[0];
		$result["controller"]=count($temp)>1
		? $temp[1]
		: ""
		;
		return $result;
	}

	/** преобразование массива в двуменрный, на входе (module=>title, module-controller=>title)
	 * @param array $array
	 * @return array двумерный вида [module]=>(module-controller_1,module-controler_2)
	 */
	public function array2dimension($array)
	{
		$result=array();
		foreach ($array as $key=>$elem)
		{
			$temp=explode("-",$key);
			if (count($temp)>1) $result[$temp[0]][$key]=$elem;
			else $result[$temp[0]]=array();
			;
		}
		return $result;
	}

	/** получение имен всех ACTION  для ресурсов вида МОДУЛЬ-КОНТРОЛЛЕР
	 *  создание массива _МОДУЛЬ-КОНТРОЛЛЕР_ => array(action1=>action1,action2=>action2)
	 * @param array $priv получен из models/Resources :: getPrivForResTree()
	 * @return array
	 */
	public function actionsFromPrivs($priv)
	{
		$result=array();
		foreach ($priv as $p)
		{
			// пропустим модули
			if (strpos($p["resName"],"-")===false) continue;
			$actionName=is_null($p["action"])
			? "_ALL_"
			: $p["action"]
			;
			$result[$p["resName"]][$actionName]=$actionName;
		}
		return $result;
		;
	}

	/** перечень ВСЕХ ресурсоов на основе модулей-контроллеров и данных в БД
	 * @param array $resDescribed ресурсы описанные в БД
	 * @return array
	 */
	public function tree_Resources($resDescribed)
	{
		// ресурсы описанные в БД
		//		$resDescribed=$this->_model->getResourcesDescription();

		// построим два массива [_resName_]=[_TITLE_] где
		// _resName_ = MODULE || MODULE-CONTROLLER
		// _TITLE_ = описание из БД
		// один массив - то шо в файдовой системе, другой то шо в БД
		// при слиянии получится список ресурсов и их названий

		// узнаем контроллеры наших модулей
		// получим все модули/контроллены
		// array [_MODULE_DIR_] -> _PATH_TO_CONTROLLERS_OF_THIS_MODULE_
		$modules=$this->getFrontController()->getControllerDirectory();
		ksort($modules);
		$tree=array();
		foreach ($modules as $modulName => $pathToControllers)
		{
			$tree[$modulName]=$modulName;
			$filelist=scandir($pathToControllers);
			foreach ($filelist AS $name)
			{
				// еси это у нас файл контроллера
				if (is_file($pathToControllers.DIRECTORY_SEPARATOR.$name)&& (stristr($name,'Controller')!=false))
				{
					// имя текущего контроллера
					preg_match("|(.*)Controller|Ui",$name,$cont);
					$cont=strtolower($cont[1]);
					$tree[$modulName."-".$cont]=$modulName."-".$cont;
				}
			}
		}
		$logger=Zend_Registry::get("logger");

		// лишнее, описанное в БД - удалить
		$res2del=array_diff_key($resDescribed,$tree);
		//		$logger->log($res2del, Zend_Log::INFO);
		// этого в БД нет  - добавить
		$res2add=array_diff_key($tree,$resDescribed);
		//		echo "<pre>".print_r($res2add,true)."</pre>";
		//		$logger->log($res2add, Zend_Log::INFO);
		// общие значения с приоритетом описанных в БД
		$resList=array_intersect_key($resDescribed,$tree);
		// Добавим тех, кого нет в БД
		$resList=array_merge($resList,$res2add);
		// отсортируем
		ksort($resList);

		// проверить БД и вычистить то, чего нет среди модулей-контроллеров
		//		if (count($res2del)>0) $this->_model->clean($res2del);
		// добавить в БД новые модули-контроллеры
		//		if (count($res2add)>0) $this->_model->add($res2add);
		// вернем истинный список того, куда можно ходить
		return array("resList"=>$resList,"res2del"=>$res2del,"res2add"=>$res2add);
	}
}