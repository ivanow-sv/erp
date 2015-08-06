<?php
class My_Plugin_Auth extends Zend_Controller_Plugin_Abstract
{
	/**
	 * Переменные для хранения сущностей Аутентификации и Управления правами
	 */
	private $_auth;
	private $_acl;
	// храним дерево ресурсов resID=>title
	private $_resTree;
	private $_resNames;
	// работа с БД
	private $_model;
	// помощник действий
	private $hlp;
	// гость по умолчанию
	private $guest_id='2';
	private $guest_Details;
	// неавторизовавшийся сотрудник - для подачи заявок
	private $guestIT_id='4';
	// его инфа в виде stdClass
	private $guestIT_details;
	
	/**
	 * Определение переходов при недопустимости текущей роли и/или аутентификации
	 * естественно, такие экшены у вас должны быть созданы и работать :)
	 */
	protected $_noAuth = array(
 		'module'     => 'default',
 		'controller' => 'auth',
 		'action'     => 'login');
	protected $_noAcl  = array(
 		'module'     => 'default',
 		'controller' => 'error',
 		'action'     => 'erroracl');

	/**
	 * @param resource (Zend_Auth) Объект аутентификации
	 * @param resource (App_Acl) Объект управления правами
	 * @return void
	 */
	public function __construct($auth)
	{
		$this->_auth = $auth;
		//		$this->_acl  = $acl;
		Zend_Loader::loadClass('Aclmodel');
		//		Zend_Loader::loadClass('My_Helper_Typic');
		$this->hlp=new My_Helper_Acl();
		$this->_model=new Aclmodel();

		$this->guestIT_details=new stdClass();
		$this->guestIT_details->id=$this->guestIT_id;
		// @FIXME получение этой инфы из БД
		$this->guestIT_details->login='guest-it';
		$this->guestIT_details->fio='Пользователь IT-услуг';
		
	}

	public function preDispatch( Zend_Controller_Request_Abstract $request)
	{

// 		$logger=Zend_Registry::get("logger");
		/**
		 * Если пользователь авторизирован, то получаем его данные
		 * (хранится в БД вместе с другой инфой и переноситься в сессию при аутентификации (см. выше)
		 * если нет, то он "гость"
		 */
		
// 		$_referer=$request->getHeader('REFERER');
		/* Определяем параметры запрос */
		$controller  = $request->controller;
		$action      = $request->action;
		$module     = $request->module;
		$resource=$module.'-'.$controller;
		// имя хоста 
		$hostname=$request->getServer("SERVER_NAME");
		$subd=explode(".", $hostname);
		
		if( $this->_auth->hasIdentity() )
		{ 
			// если пользователь залогинен - узнаем его ID
			$userid=$this->_auth->getIdentity()->id;
		}
		else
		{
			// зашли по HELP.XXX и это модуль HELPDESK
			if ($module==="helpdesk" && $subd[0]==="help")
			{
				// значит это сотрудник сотрудник не залогиненый,
				$userid=$this->guestIT_id;
				// аутенфикация как сотрудника-пользователя HELPDESK
				$this->_auth->getStorage()->write($this->guestIT_details);
				
			}
			// иначе простой гость
			else $userid=$this->guest_id; 
				
		}
		
// 		$logger->log($this->_auth->getIdentity(),Zend_Log::INFO);
		
		// строим ACL
		$this->_acl=$this->aclBuild($request,$userid);
		
		// если контролер - index выясним аннотацию и запишем её в реестр
		if ($controller==="index")  
		{
			$moduleAnnot=$this->_model->getModuleAnnotation($module);
			Zend_Registry::set("moduleAnnot",$moduleAnnot);
		}		
		
		
		// параметры, это определит привелегию
		$privilege=$action;

		/* @todo шо делть еси не опеределн контроллер как ресурс
		 * @todo пока шо если контррллер не описан в ресурсах, то доступ к нему разрешен
		 *
		 */

		// считается что если есть доступ к ACTION, то и есть доступ к ACTION_ID
		// и ещ пускать если есть доступ к ACTION_ID, но нет к ACTION

		if( !$this->_acl->isAllowed($userid, $resource, $privilege)) 
		{	
		// 	доступа нет - значит нет
			list($module, $controller, $action)  =( !$this->_auth->hasIdentity() ) 
			?  array_values($this->_noAuth) 
			: array_values($this->_noAcl);
		}
		$this->prepareNavigation($userid);
		/* Определяем новые данные запроса */
		$request->setModuleName($module);
		$request->setControllerName($controller);
		$request->setActionName($action);
		
	}

	/** здесь строится ACL в зваисимости кто пришел и куда
	 * @param integer $role
	 * @param Zend_Controller_Request $request
	 * @return Zend_Acl
	 */
	private function aclBuild($request,$userid)
	{
		// *********************
		// ресурсы
		$controller  = $request->controller;
		$module     = $request->module;

		$resource=$module.'-'.$controller;
		//		$action      = $request->action;
		$logger=Zend_Registry::get("logger");
		
		$acl = new Zend_Acl();

		// Группы пользователя
		$_groups=$this->_model->getUserGroups($userid);
		// если групп нету - выключены к примеру?
		if (empty($_groups))
		{
			// будет гостем
			$groupTree[0]=array("groupid"=>2);
		}
		else
		{
			// узнаем роли данной группы
			// 1. пройдемся по дереву группы
			$groupTree=$this->_model->getGroupTree($_groups);
			$groupTree=$this->hlp->treeGroupPrepare($groupTree);
				
		}
		$gList=$this->hlp->groupsListFromTree($groupTree);
// 		$logger->log($groupTree,Zend_Log::INFO);
// 		$logger->log($gList,Zend_Log::INFO);
		
		// 2. роли наши групп
		$rList_v2=$this->_model->getGroupsRoles($gList);
// 		$logger->log($rList_v2,Zend_Log::INFO);
				// переменные окружения для ролей в дереве
		$groupEnv=$this->getGroupParamz($gList);
		// сохраним их в реестре
		Zend_Registry::set('groupEnv',$groupEnv);
				
		// @TODO если раздуется большое дерево ресурсов то чо?
		// полное дерево ресурсов: все, чтобы использовать в NAVIGATION
		$resTree=$this->hlp->tree_Resources($this->_model->getResourcesDescription());
		$resTree=$resTree["resList"];
		$this->_resNames=$resTree;
		// title страницы - resTitle
		Zend_Registry::set('ModuleTitle',$resTree[$module]);
		Zend_Registry::set('ModuleControllerTitle',$resTree[$module."-".$controller]);
		
		$this->_resTree=$this->hlp->array2dimension($resTree);
		// обязательные параметры для данного дерева ресурсов
		$resReqParams=$this->getResReqParamz($module,$controller);
		// сохраним в реестр
		Zend_Registry::set('resReq',$resReqParams);
		
		// узнаем привилегии
// 		$priv=$this->_model->getPrivForResTree($rList,$resTree);
		$priv_v2=$this->_model->getPrivForResTree($rList_v2,$resTree);
// 		$logger->log($rList_v2,Zend_Log::INFO);
// 		$logger->log($priv_v2,Zend_Log::INFO);
// 		$logger->log($this->_resTree,Zend_Log::INFO);
		$this->hlp->buildAcl($acl,$rList_v2,$this->_resTree,$priv_v2,$userid);
// 		$logger->log($acl->inherits("sprav-departments","sprav"),Zend_Log::INFO);
// 		$logger->log($acl->getRoles(),Zend_Log::INFO);
		// сохраним ACL в реестр
		Zend_Registry::set('ACL',$acl);

		return $acl;
	}

	/** получение параметров для ролей
	 * @param array $rolelist
	 * @return array ($param=>$value)
	 */
	private function getRoleParamz($rolelist)
	{
		$_roleEnv=$this->_model->getRolesParamz($rolelist);
		$_roleEnv=array_keys($_roleEnv);
		$_roleEnv=implode(";",$_roleEnv);
		preg_match_all("#([A-Za-z0-9]+)=([A-Za-z0-9]+)#iu",$_roleEnv,$_result);
		$result=array();
		foreach ($_result[1] as $key=>$varName)
		{
			$result[$varName]=$_result[2][$key];
		}
		return $result;
	}

	/** получение параметров для групп
	 * @param array $list
	 * @return array ($param=>$value)
	 */
	private function getGroupParamz($list)
	{
		$_Env=$this->_model->getGroupParamz($list);
		$_Env=array_keys($_Env);
		$_Env=implode(";",$_Env);
		preg_match_all("#([A-Za-z0-9]+)=([A-Za-z0-9]+)#iu",$_Env,$_result);
		$result=array();
		foreach ($_result[1] as $key=>$varName)
		{
			$result[$varName]=$_result[2][$key];
		}
		return $result;
	}

	/** получение имен параметров для ресурсов
	 * @param string $module
	 * @param string $controller
	 * @return array ($raramName1,$paramName2....)
	 */
	private function getResReqParamz($module,$controller)
	{
		$reslist=array(
				$module,
				$module."-".$controller
		);
		$names=$this->_model->getResReqParamz($reslist);
		$result=implode(";",$names);
		$_result=explode(";",$result);
		// переберем, чтобы убрать пустые
		$result=array();
		foreach ($_result as $name) 
		{
			if (!empty($name)) $result[]=$name;
			;
		}
		return $result;
	}

	/** строим навигацию согласно правам доступа
	 * @param integer $role
	 */
	private function prepareNavigation($role)
	{
//		$logger=Zend_Registry::get("logger");

		$resAcl=$this->_acl->getResources();
		// отсортируем на всякий
		// считается, что ресурсы вида "модуль-контроллер" или "модуль"
		sort($resAcl);
		//		$resAcl=$this->hlp->array2dimension($resAcl);
		//		$tree=$this->hlp->array2dimension($this->_resTree);
		$view =  Zend_Controller_Action_HelperBroker::getStaticHelper("viewRenderer")->view;
		//		$pages=array($this->hlp->getDefaultNav($role));
		//		$nav=$this->_model->getMenu($role);
		//		$_pages=unserialize($nav);
		//		$pages=$role==0 ? $pages : $_pages;

		$pages=array($this->hlp->getDefaultNav($role));
		$nav=array();
		foreach ($this->_resTree as $module=> $children)
		{
			$navSubLvl=array();
			$order=2;
			foreach ($children as $resId=>$resName)
			{
				//					 строим подуровень в навигации
				//					 если к ресурсу полный доступ или есть хотябы доступ к "INDEX" - то добавим к навигации
				//					 иначе - ничо
				if ($this->isAllow($role,$resId) )
				{
					$tmp=explode("-",$resId);
					$controller=isset($tmp[1])?$tmp[1]:'';
					if ($controller==="index")
					{
						// то ничего не добавлять
// 						$navSubLvl[]=array(
//      						"module"=>$module,
//      						"controller"=>$controller,
//      						"resource"=>$resId,
//      						"order"=>1,
//      						"class"=>"treeLv2",
//      						"label"=>$this->_resNames[$resId],
// 						);
					}
					else
					{
						$navSubLvl[]=array(
     						"module"=>$module,
     						"controller"=>$controller,
     						"resource"=>$resId,
     						"order"=>$order,
							"class"=>"treeLv2",						
     						"label"=>$this->_resNames[$resId],
						);
					}
					$order++;
				}
					
			}
			// если внутри выстроилоась хоть какаято навигация, то добавим модуль. 
			// URI - SITE/module/ - приведет к INDEX
			if (count($navSubLvl)>0 && $module!=="default" )
			{
				// ссылаемся на первый элемент из нижнего слоя
				// свой class и Label
// 				$_sub=$navSubLvl[0];
// 				$_sub["label"]=$this->_resNames[$module];
// 				$_sub["class"]="treeLv1";
// 				$_sub["pages"]=$navSubLvl;
// 				$_sub["order"]=0;
// 				$nav[]=$_sub;
				
				// ссылаемся на index.php - там покажет и расскажет о чем модуль
				$_sub["module"]=$module;
				$_sub["controller"]="index";
				$_sub["resource"]=$resId;
				$_sub["label"]=$this->_resNames[$module];
				$_sub["class"]="treeLv1";
				$_sub["pages"]=$navSubLvl;
				$_sub["order"]=0;
				$nav[]=$_sub;

				
				
// 				// если доступ разрешен к модулю
// 				if ($this->isAllow($role,$module) )
// 				{
// 					$nav[]=array(
//      					"module"=>$module,
//      					"resource"=>$module,
//      					"label"=>$this->_resNames[$module],
// 						"order"=>0,
// 						"class"=>"treeLv1",					
// 						"pages"=>$navSubLvl
// 					);

// 				}
// 				// не разрешен или доступ к конкретному контроллеру, а index или module запрещены
// 				// тут ссылка не должна быть кликабельной
// 				else
// 				{
// 					// ссылаемся на первый элемент из нижнего слоя
// 					// свой class и Label					
// 					$_sub=$navSubLvl[0];
// 					$_sub["label"]=$this->_resNames[$module];
// 					$_sub["class"]="treeLv1";
// 					$_sub["pages"]=$navSubLvl;
// 					$_sub["order"]=0;
// 					$nav[]=$_sub;
// 				}
			}
			;
		}
		//		$logger->log($nav, Zend_Log::INFO);
		//		$logger->log($this->_resTree, Zend_Log::INFO);

		// для гостей и не залогинившихся - меню дефолтовое
		$pages=$role==0 ? $pages : $nav;
		$container = new Zend_Navigation($pages);
		$view->navigation($container);

		// тут по идее надо юзать встроенный хелпер, шобы отсекать лишние пункты меню
		// , но он не показывает частичный доступ, например разрешено INDEX и запрещено EDIT
		#
		// Store ACL and role in the proxy helper:
		//		$view->navigation()->setAcl($this->_acl)->setRole($role);

		#
		// ...or set default ACL and role statically:
		//		Zend_View_Helper_Navigation_HelperAbstract::setDefaultAcl($this->_acl);
		//		Zend_View_Helper_Navigation_HelperAbstract::setDefaultRole($role);


	}

	private function isAllow($role,$res)
	{
		if ($this->_acl->isAllowed($role,$res) || $this->_acl->isAllowed($role,$res,"index")) return true;
		else return false;
	}

	/** подготвока дерева ролей и возврат в обратном порядке
	 * т.е. на выходе первый элемент - гл.родитель, остальные дети
	 * @param array $tree
	 * @return array:(roleid,parent,title)
	 */
	/*	private function treePrepare($tree)
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
		*/
}