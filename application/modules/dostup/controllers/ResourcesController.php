<?php
class Dostup_ResourcesController extends Zend_Controller_Action
{

	private $_model;
	private $_navModel;

	//	private $_adminRole=1;
	private $redirectLink;
	private $hlp; // помощник действий Typic
	private $hlpAcl; // помощник действий при работе с ACL
	private $_aclCurrent; // подопытный ACL
	private $filterSession;
	private $baseLink;
	//	private $_acl; // Zend_Acl  текущей роли и всего дерева ресурсов

	public function init()
	{

		// выясним название текущего модуля для путей в ссылках
		$currentModule=$this->_request->getModuleName();
		$this->view->currentModuleName=$currentModule;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->curact = $this->_request->action;
		$this->view->curcont = $this->_request->controller;
		//		$this->redirectLink=$this->view->currentModuleName.'/'.$this->view->curcont;
		$this->redirectLink=$this->_request->getModuleName()."/".$this->_request->getControllerName();
		$this->view->selfLink=$this->view->baseUrl.'/'.$this->redirectLink;
		//		$this->baseLink=$this->_request->getBaseUrl()."/".$this->redirectLink;

		$this->baseLink=$this->_request->getBaseUrl()."/".$currentModule."/".$this->_request->getControllerName();
		$this->hlp=$this->_helper->getHelper('Typic');
		$this->hlpAcl=$this->_helper->getHelper('Acl');

		Zend_Loader::loadClass('Resources');
		Zend_Loader::loadClass('Menus');
		Zend_Loader::loadClass('Formochki');
		$this->_model=new Resources;
		$this->_navModel=new Menus;
		$this->view->title='Управление доступом: ';
		//активируем хэлпер AjaxContext
		//и передаём туда имя action'а, который необходимо интегрировать с AJAX'ом
		$this->view->addHelperPath(APPLICATION_PATH . '/views/helpers', 'My_View_Helper');
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		//		$ajaxContext ->addActionContext('editadvance', 'json')->initContext('json');
		$ajaxContext ->addActionContext('details', 'json')->initContext('json');
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/dostup.js');
		//		$ajaxContext ->addActionContext('formchanged', 'json')->initContext('json');

		//		$ajaxContext ->addActionContext('editadvanceapply', 'json')->initContext('json');

		// путь к помощщнику вида для правильных CHECKBOX и RADIO в формах
		$this->view->addHelperPath('./application/views/helpers/','My_View_Helper');
		Zend_Loader::loadClass('Zend_Session');
		$this->filterSession=new Zend_Session_Namespace('my');

		//

	}

	public function indexAction()
	{
		$logger=Zend_Registry::get("logger");

		$this->view->title.='объекты доступа';
		if ($this->_request->isPost())
		{
			// получим данные из запроса
			$params = $this->_request->getParams();
			// обновим сессию
			$this->sessionUpdate($params);
		}
		$criteria=$this->buildCriteria();

		$this->view->formFilter=$this->createForm_roleFilter($criteria["role"]);
		// дерево ресурсов
		//		$this->view->tree=$this->buildTree();

		//
		// дерево ресурсов
		$resTree=$this->hlpAcl->tree_Resources($this->_model->getResourcesDescription());
		// проверить БД и вычистить то, чего нет среди модулей-контроллеров
		if (count($resTree["res2del"])>0) $this->_model->clean($resTree["res2del"]);
		// добавить в БД новые модули-контроллеры
		if (count($resTree["res2add"])>0) $this->_model->add($resTree["res2add"]);
		$resTree=$resTree["resList"];

		// дерево данной роли
		$roleTree=$this->_model->getRoleTree($criteria["role"]);
		$roleTree=$this->hlpAcl->addGuestToRootsTree($roleTree);
		$roleTree=$this->hlpAcl->treeRolePrepare($roleTree);
		$rList=$this->hlpAcl->rolesListFromTree($roleTree);
		// узнаем привилегии
		$priv=$this->_model->getPrivForResTree($rList,$resTree);
		// полный ACL нашей роли + по умолчанию
		$acl=new Zend_Acl();
		$resTree2dim=$this->hlpAcl->array2dimension($resTree);
		$this->hlpAcl->buildAcl($acl,$roleTree,$resTree2dim,$priv);
		//		$this->_acl=$acl;

		// отправим в ВИД ACL, список ресурсов и роль
		$this->view->resources=$resTree2dim;
		$this->view->restitles=$resTree;
		//		$this->view->role=$criteria["role"];
		//		$this->view->roleAcl=$acl;
		// получим все ACTION ко всем ресурсам
		// получим массив [_MODULE-CONTROLLER_]=>[ACTIONSLIST];
		$actions=array();
		$_all=array("_ALL_"=>'');
		foreach ($resTree as $modCont => $title)
		{
			$path=$this->hlpAcl->getModuleControllerFromRes($modCont);
			$_actions=$this->getActList($path["module"],$path["controller"]);
			if ($_actions)
			{
				$actions[$modCont]=$_actions;
				$actions[$modCont]["_ALL_"]=null;
			}
		}
		//		$this->view->aclActions=$actions;

		// построим массив CSS классов РЕСУРС => (okSign | stopSign | partialSign)
		$accessSigns=array();
//		$nav=array();
		foreach ($resTree2dim as $module=>$childs)
		{
			$signClass=$acl->isAllowed($criteria["role"],$module)
			? "okSign"
			: "stopSign"
			;
			$accessSigns[$module]=$signClass;

			if (is_array($childs) && count($childs)>0)
			{
//				$navSubLvl=array();
				foreach ($childs as $resId=>$resName)
				{
					$_allow=0;

					// проверим на доступ, учитывая доступы к ACTION
					// если есть в перечне ACTION текущий ресурс
					if (isset($actions[$resId]))
					{
						// если все привилегии доступны - доступ полный
						// если ктото нет - значет частичный
						// если везде закрыто - значит доступа нет
						$chkAllow=0; //  сколько разрешенных
						$chkDeny=0;//  сколько запрещенных
						foreach ($actions[$resId] as $priv=> $a)
						{
							$priv=$priv=="_ALL_"?null:$priv;
							if ($acl->isAllowed($criteria["role"],$resId,$priv))
							{
								$chkAllow++;
							}
							else
							{
								$chkDeny++;
							}
							;
						}
						// если кол-во ACTION совпадает с кол-во разрещенных - значит полный доступ
						if ($chkAllow==count($actions[$resId])) $_allow=1;
						// если кол-во ACTION совпадает с кол-во запрещенных - значит полный запрет
						elseif ($chkDeny==count($actions[$resId])) $_allow=0;
						// иначе - частичный
						else $_allow=2;
					}
					else
					{
						$_allow=$acl->isAllowed($criteria["role"],$resId)
						? 1
						: 0
						;
					}
					switch ($_allow)
					{
						case 0:
							$signClass="stopSign";
							break;

						case 1:
							$signClass="okSign";
							break;

						default:
							$signClass="partialSign";;
							break;
					}
					//			$signClass=$_allow ? "okSign": "stopSign"
					$accessSigns[$resId]=$signClass;

					// строим подуровень в навигации
					// если к ресурсу полный доступ или есть хотябы доступ к "INDEX" - то добавим к навигации
					// иначе - ничо
//					if ($this->isAllowForNav($acl,$criteria["role"],$resId) || $signClass==="okSign")
//					{
//						$tmp=explode("-",$resId);
//						//						$module=$tmp[0];
//						$controller=isset($tmp[1])?$tmp[1]:'';
//						$navSubLvl[]=array(
//     						"module"=>$module,
//     						"controller"=>$controller,
//     						"label"=>$resTree[$resId],
//						);
//					}
				}
			}
			// если внутри выстроилоась хоть какаято навигация, то добавим модуль
//			if (count($navSubLvl)>0 && $module!=="default" )
//			{
//				$nav[]=array(
//     				"module"=>$module,
//     				"label"=>$resTree[$module],
//					"pages"=>$navSubLvl
//				);
//			}
				
		}
		
		$this->view->accessSigns=$accessSigns;

		// сохраним навигацию в базу
//		$container = new Zend_Navigation($nav);
//		$this->_model->delMenu($criteria["role"]);
//		$this->_model->putMenu($criteria["role"],$container);
		
		//		$this->hlpAcl->addDefaults($acl);
//										$logger->log(serialize($nav), Zend_Log::INFO);
		//								$logger->log($resTree2dim, Zend_Log::INFO);
		//		$logger->log($actions, Zend_Log::INFO);
		//		$logger->log($this->hlpAcl->array2dimension($resTree), Zend_Log::INFO);
		//				$logger->log($resTree2dim, Zend_Log::INFO);
		//				$logger->log($this->view->aclActions, Zend_Log::INFO);
		// преобразовать ACL в массив строк и передать в ВИД

	}

	/*
	 public function formchangedAction()
	 {
		if (!$this->_request->isPost()) $this->_redirect($this->baseLink);
		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		// узнаем что к нам пришло
		$formData = $this->_request->getPost('formData');
		// прежние данные
		$oldCriteria=$this->buildCriteria($this->filterSession->getIterator());

		}*/
	/*
	 public function addAction()
	 {
		$redirectLink=$this->redirectLink;
		if ($this->_request->isPost())
		{
		$res_id = (int)$this->_request->getPost('res_id',0);
		$role_id = (int)$this->_request->getPost('role',0);
		if ($res_id >0 && $role_id >=0)
		{
		// создадим ЗАПРЕЩАЮЩЕЕ правило для ресурса и данной роли, без ACTION
		// описано ли ресурс-роль-ACTION=null ?
		$check=$this->_model->getInfoResRoleAction($res_id,$role_id,null);
		// еси нет - создадим
		if ($check===false) $this->_model->createPrivilege("0",$role_id,$res_id,null);
		$redirectLink.="/edit/id/".$res_id;
		}
		}
		$this->_redirect($redirectLink);

		}

		public function editadvanceAction()
		{
		$this->view->baseUrl = $this->_request->getBaseUrl();

		// если применили форму
		if ($this->_request->isPost())
		{
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$filter = new Zend_Filter_StripTags();

		// все переменные из POST
		$data=$this->_request->getPost();

		$res_id=intval($data["id"]);
		$role_id=intval($data["role_id"]);
		// если нет ID или role_ID - отбой
		if ($res_id<1 || $role_id<0) return;
		//			$modcont=$data["modcont"][0];
		unset ($data["id"]);
		unset ($data["role_id"]);
		//			unset ($data["modcont"]);

		// 1. привилегии ресурса ID и роли ROLE_ID
		$privileges_in_DB=$this->_model->getPrivileges($role_id,$res_id);
		// информация о ресурсе (выясним модули-контроллер
		//			$infoRes=$this->_model->getResInfo($res_id);
		//  возможны ли ACTION для сочетания МОДУЛЬ-КОНТРОЛЛЕР и какие?
		// список ACTION'ов текущего ресурса
		//			$actionList=$this->getActList($infoRes["module"],$infoRes["controller"]);

		// запросы к БД:
		// 1. узнать что есть относительно текущего ресурса и ACTION
		// 2. INSERT или UPDATE в зависимости от п.1

		//  составим массив для запроса в БД и выполним запрос
		// переберем наши данные
		$prepared_data=array();
		foreach ($data as $name=> $value)
		{
		$d=array();
		// еси массив - берем тока первый элемент
		$value=is_array($value)?$value[0]:$value;
		$d["action"]=$name;
		// если описывается без ACTION
		if ($name==="modcont") $d["action"]=null;
		// если это описаниве параметра к ACTION - следующий
		if (strpos($name,'param')!==false) continue ;
		// есть ли вообще описание параметра?
		if (array_key_exists ($name."_param",$data))
		{
		// отфильтруем от тэгов
		$param= $filter->filter($data[$name."_param"]);
		$params=trim($param);
		// и запомним
		$d["params"]= $params!==""?$params:null;
		}
		// сам доступ
		switch ($value){
		case "1":
		$d["allow"]=1;
		break;

		case "0":
		$d["allow"]=0;
		break;

		case "":
		default:
		$d["allow"]=null;
		break;
		}

		$prepared_data[]=$d;

		}

		$this->view->formRole=$prepared_data;
		// передадим в МОДЕЛЬ - нада в базу заливать
		// переберем полученное
		foreach ($prepared_data as $key=> $priv)
		{
		$action=$priv["action"];
		// 1. узнаем что уже задано к текущему ресурсу, ACTION и роли
		$check=$this->_model->getInfoResRoleAction($res_id,$role_id,$action);
		// если не требуется НАСЛЕДОВАТЬ
		if (!is_null($priv['allow']))
		{
		// если еще не задавали такое - создать
		if ($check===false) $this->_model->createPrivilege($priv['allow'],$role_id,$res_id,$action,$priv['params']);
		// инаве обновить
		else $this->_model->updatePrivilege($priv['allow'],$role_id,$res_id,$action,$priv['params']);
		}

		else // назначить НАСЛЕДОВАТЬ - убрать записть об этой привилегии
		{
		if ($check!==false)
		{

		$this->_model->deletePrivilege($role_id,$res_id,$action);
		}
		}

		}
		return ;
		}

		$id = (int)$this->_request->getParam('id',0);
		$role_id_fromJson=(int)$this->_request->getParam('role_id',0);
		if ($id!=0 && $role_id_fromJson>=0)
		{

		$infoRes=$this->_model->getResInfo($id);
		// список ACTION'ов текущего ресурса
		$actionList=$this->getActList($infoRes["module"],$infoRes["controller"]);

		// все что прописано в БД относительно ЭТОЙ роли и ЭТОГО ресурса
		// отсоритроовано по ACTION, т.е. еси ACTION пуст, то он первый
		$privileges_in_DB=$this->_model->getPrivileges($role_id_fromJson,$id);

		// покажем их
		// форма
		$form=new Formochki;
		$form->setAttrib('name','privilegeAdvancedForm'.$role_id_fromJson);
		$form->setAttrib('id','privilegeAdvancedForm'.$role_id_fromJson);
		$form->setMethod('POST');
		$form->setAction($this->view->selfLink.'/'.$this->view->curact.'/id/'.$id.'/role_id/'.$role_id_fromJson);

		$form->addElement('hidden','id',array('value'=>$id));
		$form->addElement('hidden','role_id',array('value'=>$role_id_fromJson));

		// элемент формы, прямой доступ/запрет к этому ресурсу без учета ACTION
		// создадим его
		$modcont=$form->createElement('radio','modcont');
		$modcont->helper='FormMultiRadioList';
		$modcont
		->setLabel('Доступ')
		->addMultiOptions(
		array(
		'0' => '',
		'1' => '',
		'' => ''
		))
		->setSeparator('');
			
		// запомним то шо взято из БД
		$privilegesList=$privileges_in_DB;
		// изолируем первый элемент
		$grant=array_shift($privilegesList);

		// еси его ACTION пуст, значит он описывает доступ к МОДУЛЬ-КОНТРОЛЛЕР
		if (is_null($grant['action']))
		{
		$aallooww=is_null($grant['allow'])?'':$grant['allow'];
		$modcont->setValue($aallooww);
		}
		// иначе берем исходное - прямого описания доступа к МОДУЛЬ-КОНТРОЛЛЕР нет
		else
		{
		$privilegesList=$privileges_in_DB;
		// выставим флаг в "НАСЛЕДОВАНО"
		$modcont->setValue('');
		}

		// Добавим его к форме
		$form->addElement($modcont);
			
		// сольем со списком ACTION'ов
		//$this->view->formRole="<pre>".print_r($privz,true)."</pre>";
		// переберем полученное если уместно перебирать
		if ($actionList!==false && count($actionList)>0)
		{
		$privz=array_merge($actionList,$privilegesList);
		//				$this->view->formRole="<pre>".print_r($privz,true)."</pre>";
		foreach ($privz as $action =>$priv)
		{
		$resPriv= $form->createElement('radio',$action);
		$resPriv->helper='FormMultiRadioList';

		$resPriv->addMultiOptions(array(
		'0' => '',
		'1' => '',
		'' => ''
		));
		$resPriv->setLabel($action);
		$resPriv->setSeparator('');
		// если к текущему ACTION не задано, значит выставить НАСЛЕДОВАТЬ
		$priv['allow']=is_null($priv['allow'])?'':$priv['allow'];
		$resPriv->setValue($priv['allow']);
		$form->addElement($resPriv);
		$resId= $form->addElement('text',$action."_param",array('value'=>$priv['params'],'class'=>'inputSmall2'));
		}

		}

		$form->setDecorators(array(
		array('ViewScript', array('viewScript' => '_privileges_advance.phtml'))
		));
		$form->addAttribs(array("baseUrl"=>$this->view->baseUrl));


		$this->view->formRole.=$form->render();
		//			$this->view->formRole=$out;
		// @FIXME если перешли по ссылке без AJAX
		return;
		//			$this->_redirect($this->view->selfLink.'/'.'edit'.'/id/'.$id);

		}
		else $this->_redirect($this->redirectLink);

		//		$this->view->title.='объекты доступа';
		//		$this->view->tree=$this->buildTree();
		}
		*/
	//	public function deleteAction()
	//	{
	//		$redirectLink=$this->redirectLink;
	//
	//		$res_id = (int)$this->_request->getParam('res_id',0);
	//		$role_id=(int)$this->_request->getParam('id',0);
	//		if ($res_id>0 && $role_id>=0)
	//		{
	//			$redirectLink.="/edit/id/".$res_id;
	//			$this->_model->deletePrivilege($role_id,$res_id,"FULL_REMOVE");
	//		}
	//		$this->_redirect($redirectLink);
	//
	//	}


	public function saveresAction()
	{
		if (!$this->_request->isPost()) $this->_redirect($this->baseLink);
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$filter = new Zend_Filter_StripTags();

		$resource=$filter->filter($this->_request->getPost("id"));
		$title=$filter->filter($this->_request->getPost("title"));
		$coment=$filter->filter($this->_request->getPost("coment"));
		$paramNames=$filter->filter($this->_request->getPost("paramNames"));
		

		if (empty($resource) || empty($title) ) $this->_redirect($this->baseLink);
		//  проверка есть ли такие модули-контроллеры
		$resInfo=$this->_model->getResInfo($resource);
		//				$logger = Zend_Registry::get('logger');
		//				$logger->log($resInfo, Zend_Log::INFO);
		//				$logger->log($this->resourceExists($resource), Zend_Log::INFO);
		// @TODO проверить есть ли такой ресурс в файловой системе
		//		if ($this->resourceExists($resource) )
		//		{
		// не упоминается в БД?
		if (empty($resInfo)) $this->_model->add(array($resource=>$title),$coment);
		else $this->_model->updateInfo($resInfo["id"],$title,$coment,$paramNames);
		//		}




		$this->_redirect($this->redirectLink);
		;
	}

	public function saveprivAction()
	{
		$logger = Zend_Registry::get('logger');


		if (!$this->_request->isPost()) $this->_redirect($this->baseLink);
		$params = $this->_request->getParams();
		// обновим сессию
		$this->sessionUpdate($params);
		$criteria=$this->buildCriteria();
		$path=$this->hlpAcl->getModuleControllerFromRes($criteria["resource"]);
		$resInfo=$this->_model->getResInfo($criteria["resource"]);

		// всего какие есть ACTION
		$actions=$this->getActList($path["module"],$path["controller"]);
		// Добавим общий на доступ
		$temp=array("_ALL_"=>null);
		if (!$actions) $actions=$temp;
		else {
			$actions=array_merge($actions,$temp);
			ksort($actions);
		}
		$_actions=array();
		// получим данные из формы
		foreach ($actions as $actionName => $temp)
		{
			$temp=(int)array_shift($this->_request->getPost($actionName));
			switch ($temp)
			{
				case 0:
				case 1:
					$_actions[$actionName]=$temp;
					;
					break;

				default:
					break;
			}
		}
		// Убрать все привилегии о данном ресурсе и данной роли
		$this->_model->cleanPriv($criteria["role"],$resInfo["id"]);
		// добавить новое
		foreach ($_actions as $actName => $allow)
		{
			$actName=$actName==="_ALL_"?null:$actName;
			$this->_model->createPrivilege($criteria["role"],$resInfo["id"],$actName,$allow);
		}


		//		$actions=$this->getActList();
		//		$logger->log($actions, Zend_Log::INFO);
		//		$logger->log($_actions, Zend_Log::INFO);
		$this->_redirect($this->redirectLink);
	}

	/** получает инфо о ресурсе и привилегиях к нему данной роли
	 * строит формы для св-в ресурса и назначения привилегий
	 */
	public function detailsAction()
	{
		if (!$this->_request->isPost()) $this->_redirect($this->baseLink);
		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		// узнаем что к нам пришло
		// роль берем из сессии
		// получим данные из запроса
		$params = $this->_request->getParams();
		// обновим сессию
		$this->sessionUpdate($params);
		// наши критерии
		$criteria=$this->buildCriteria();
		//		$logger = Zend_Registry::get('logger');


		// дерево данной роли
		$roleTree=$this->_model->getRoleTree($criteria["role"]);
		$roleTree=$this->hlpAcl->addGuestToRootsTree($roleTree);
		$roleTree=$this->hlpAcl->treeRolePrepare($roleTree);
		// инфо о роли - название и прочее
		$roleInfo=$this->hlp->getLastElem($roleTree);
		//				$logger->log($roleTree, Zend_Log::INFO);
		$rList=$this->hlpAcl->rolesListFromTree($roleTree);
		// дерево данного ресурса
		$resTree=$this->tree_Res($criteria["resource"]);

		// выясним модуль и контроллер
		$path=$this->hlpAcl->getModuleControllerFromRes($criteria["resource"]);

		// узнаем привилегии
		//		$priv=$this->_model->getPrivForResTree($rList,$resTree);
		$priv=$this->_model->getPrivilegiesByResource($criteria["role"],$criteria["resource"]);
		//		$priv=$this->hlp->
		//		$logger = Zend_Registry::get('logger');
		//		$logger->log($priv, Zend_Log::INFO);

		// полный ACL нашей роли + по умолчанию
		//				$acl=new Zend_Acl();
		//				$resTree2dim=$this->hlpAcl->array2dimension($resTree);
		//				$this->hlpAcl->buildAcl($acl,$roleTree,$resTree2dim,$priv);
		//										$logger = Zend_Registry::get('logger');
		//
		//						$logger->log($acl->, Zend_Log::INFO);


		$resInfo=$this->_model->getResInfo($criteria["resource"]);
		// узнать список Action в контроллере
		$actions=$this->getActList($path["module"],$path["controller"]);

		// построить форму редактирования св-в ресурса (название, камент и т.п.)
		$formResDetails=$this->createForm_resDetails($resInfo);
		$this->view->formDetails=$formResDetails;
		$out["formDetailsWrapper"]=$this->view->render($this->_request->getControllerName()."/_formDetails.phtml");
		$formPrivEdit=$this->createForm_privilegies($criteria["role"],$resInfo,$actions,$priv);
		$this->view->roleInfo=$roleInfo;
		$this->view->actions=$actions;
		$this->view->formPrivs=$formPrivEdit;
		$out["formPrivsWrapper"]=$this->view->render($this->_request->getControllerName()."/_formPrivs.phtml");
		$this->view->out=$out;
		// ресурс из AJAX
		//		$resource=$formData["resource"];


	}
	/*
	 public function editAction()
	 {
		$id = (int)$this->_request->getParam('id',0);
		$this->view->res_id.=$id; // это для ссылок и прочего шобы AJAX работал

		$this->view->title.='Свойства объекта доступа';

		// если POST
		if ($this->_request->isPost())
		{
		$id = (int)$this->_request->getPost('id',0);

		// получим и отфильтруем название и комментарий
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$filter = new Zend_Filter_StripTags();
		$title = $filter->filter($this->_request->getPost('title'));
		$title = trim($title);
		$coment = $filter->filter($this->_request->getPost('comment'));
		$coment  = trim($coment);
		// обновим БД
		$this->_model->updateInfo($id,$title,$coment);
		// перейдем сюда же
		$this->_redirect($this->redirectLink.'/'.$this->view->curact.'/id/'.$id);
		}

		// если выбрано чтото не через POST

		if ($id!==0)
		{
		// выясним все про этот ресурс

		// Узнаем информацию - название, камент для формы
		$info=$this->_model->getResInfo($id);



		// построим форму для свойств ресурса (название, комментарий)
		$form=new Formochki;
		$form->setAttrib('name','resProperty');
		$form->setAttrib('id','resProperty');
		$form->setMethod('post');
		$form->setAction($this->view->selfLink.'/'.$this->view->curact.'/id/'.$id);
		$form->addElement('hidden','id',array('value'=>$id));
		$form->addElement('textarea','title',array('class'=>'littleArea', 'value'=>$info["title"]));
		$form->addElement('textarea','comment',array('class'=>'littleArea', 'value'=>$info["coment"]));
		$form->addElement('submit','formSubmit',array('name'=>'Обновить'));
		$this->view->formResProperty=$form;

		$this->view->tree=$this->buildTree();

		// каким ролям чтото задано явно
		$roles=$this->_model->getResRoles($id);
		// если нет явно заданных ролей для этого ресурса
		if (!count($roles)>0) $roles="";

		$this->view->roles=$roles;


		// выясним наследованные привилегии

		// форма для добавления роли
		$formAdd=new Formochki;
		$formAdd->setAttrib('name','formAddRole');
		$formAdd->setAttrib('id','formAddRole');
		$formAdd->setAttrib('style','display:none');
		$formAdd->setMethod('post');
		$formAdd->setAction($this->view->selfLink.'/'.'add');
		$formAdd->addElement('hidden','res_id',array('value'=>$id));
		// список ролей
		// получим роли
		$roleList=$this->_model->getRolesAll();
		// сообразим нужный массив
		$roleList =$this->treeArray($roleList);
		$roleList =$this->rolesForSelect($roleList);

		//			$logger = Zend_Registry::get('logger');
		//
		//			$logger->log($roleList, Zend_Log::INFO);

		Zend_Loader::loadClass('Zend_Form_Element_Select');
		$roleSelect = new Zend_Form_Element_Select('role');
		//				$roleList=array(0=>"гость", 1=>"Адми","sss"=>array(1=>"aaaa",3=>"bbb"));

		foreach ($roleList as $key=>$value)
		{
		$roleSelect ->addMultiOption($key,$value);
		//					if ($key==$info["parent"]) $roleSelect ->setAttrib('class','roles_selected');
		}
		//				$roleSelect ->addMultiOption(0,'Гость (без группы)');

		//				$info["role"]=(is_null($info["role"]))?0:$info["role"];
		// выбранное значение SELECTED
		$roleSelect ->removeDecorator('Label');
		$roleSelect ->removeDecorator('HtmlTag');
		$formAdd->addElement($roleSelect );

		$formAdd->addElement('submit','formSubmit',array('name'=>'Добавить'));
		$this->view->formAddRole=$formAdd;


		}
		// иначе ничего не делать
		else $this->_redirect($this->redirectLink);

		}
		*/

	/**
	 * список ACTION указанного модуля-контроллера
	 *
	 * если не указан контрпрллеор - то возврат FALSE
	 * @param string $module
	 * @param string $controller
	 * @return array|boolean массив ключей без значений или FALSE
	 */

	private function getActList($module,$controller)
	{
		// если не задан контррллер
		if (ord($controller)==0) return false;
		// PATCH шобы под *NIX понимало - первый символ в файле контроллера - заглавный
		$controller=ucfirst($controller);


		// модуль по умолчанию
		$default_module = $this->getFrontController()->getDefaultModule();

		// 1.подключим класс указаного сочетания модуль-контроллер
		$front=$this->getFrontController();
		// путь к контроллеру
		$path=$front->getModuleDirectory($module).DIRECTORY_SEPARATOR.$front->getModuleControllerDirectoryName().DIRECTORY_SEPARATOR;

		// @FIXME Если вообще нет на диске таких модулей-контроллеров - вернуть что ???

		// подключим класс
		include_once ($path.$controller.'Controller.php');
		// 2.переберем классы содержащие в имени Action

		// искомый класс модуль-контроллер
		// для Default исключение
		$ourClass=	$module===$default_module
		?	$controller.'Controller'
		:	$module."_".$controller.'Controller';
		//		echo $ourClass;
		// переберем Actions
		$functions = array();
		foreach (get_class_methods($ourClass) as $f)
		{
			if (strstr($f,"Action") != false)
			{
				$name=substr($f,0,strpos($f,"Action"));
				$functions[$name]=null;
				//				array_push($functions,$name);
			}
		}

		//		echo "<pre>".print_r($functions,true)."</pre>";
		return $functions;


	}

	/**
	 * функция для рекурсии
	 * для переделки из древовидного многоуровневого массива массив для выпадающего списка
	 * @param array $item
	 * @param string $deep показатель глубины
	 * @param array $result
	 */
	/*
	 private function selecElem(&$item,&$deep,&$result,$selected)
	 {
		if (is_array($item))
		{
		$result[$item["id"]]=$deep.$item["title"];
		if ($selected==$item["id"]) $result[$item["id"]].=" <==";
		}
		if (isset($item["child"]))
		{
		// если нет родителей - занчит начало ветки
		if (is_null($item["parent"])) $deep="";

		// переберем "детей"
		$childs=explode(" ",$item["child"]);
		foreach ($childs as $name)
		{
		// если нижеследующий прямой потомок
		if ($item[$name]["parent"]==$item['id']) $dp="|".ltrim($deep."--","|");
		else $dp=$deep;
		$this->selecElem($item[$name],$dp,$result,$selected);
		}


		}

		}
		*/

	/**
	 * делает из древовидного многоуровневого массива массив для выпадающего списка
	 * @param array $rows в виде дерева
	 * @return Array
	 */
	/*
	 private function rolesForSelect($rows,$selected=0)
	 {
		$deep='';
		$result='';
		foreach ($rows as $k=>$item)
		{
		$this->selecElem($item,$deep,$result,$selected);
		}
		return($result);
		}
		*/
	/**
	 * делает из входного массива древовидный
	 * @param array $rows данные из БД
	 * @return array
	 */
	private function treeArray($rows)
	{
		$items=array();
		// верхний цикл
		foreach ($rows as $kk=>$row)
		{
			$aaa=array();
			$aaa["id"]=$row['id'];
			$aaa["title"]=$row['title'];
			$aaa["comment"]=$row['comment'];
			$aaa["disabled"]=$row['disabled'];
			$aaa["parent"]=$row['parent'];

			// это чей-то наследник?
			if (!is_null($row['parent']))
			{
				$this->appendChilds(&$items,$aaa,$row['parent']);
			}
			else
			{
				$items[$row['id']]=$aaa;
			}
		}

		return $items;
	}

	/**
	 * добавляет детей к элементу массива
	 * @param array $inArrayв какой массив добавляется
	 * @param array $ArrayAdd массив, который пристыкуется
	 * @param unknown_type $key "точка монтирования" :)
	 */
	/*
	 private function appendChilds(&$inArray,$ArrayAdd,$key)
	 {
		//				if ($ArrayAdd["id"]==18) echo "<pre>".print_r($inArray,true)."</pre>";
		//				if ($key==0) echo "<pre>".print_r($inArray,true)."</pre>";
		// если это массив - переберем его
		if (is_array($inArray))
		{
		//			if ($inArray["id"]===$key ) $inArray[$key][$ArrayAdd["id"]]=$ArrayAdd;
		//			echo "<pre>".print_r($inArray,true)."</pre>";
		// перебор вот он, значок амперсант важен
		foreach ($inArray as $kk=>&$subArray)
		{
		// если искомое - добавим
		if ($subArray["id"]==$key && is_array($subArray))
		{
		$subArray["child"]=trim($subArray["child"]." ".$ArrayAdd["id"]);
		$subArray[$ArrayAdd["id"]]=$ArrayAdd;
		}
		// если и он массив - то опять ввызов
		if (is_array($subArray))$this->appendChilds($subArray,$ArrayAdd,$key);
		//				else $this->appendChild($subArray,$ArrayAdd,$key);
		}
		}
		//		continue;
		}
		*/
	private function createForm_roleFilter($role)
	{
		//		$logger = Zend_Registry::get('logger');

		$form=new Formochki();
		$form->setAttrib('name','filter');
		$form->setAttrib('id','filter');
		$form->setMethod('POST');
		$form->setAction($this->baseLink);
		// корневые роли
		$roles=$this->_model->getRoles_roots();
		// добавим гостей
		$roles=$this->hlpAcl->addGuestToRootsTree($roles);

		// перестроить список, с учетом родителей
		$roles=$this->hlpAcl->treeRolesPrepare($roles);
		$rList=$this->hlp->createSelectList("role",$roles,'',$role);
		$form->addElement($rList);
		$form->getElement("role")->setAttrib("onChange","$('#filter').submit();");
		// отключим выбранное чтобы в CSS можно было задать оформление
		//		$form->getElement('role')->setAttrib('disable', array($role));
		$form->addElement("submit","OK",array("class"=>"apply_text"));
		$form->getElement("OK")->setName("Сменить");

		//		$logger->log($roles, Zend_Log::INFO);

		return $form;
	}

	/** форма редактирования привилегий ресурса
	 * @param integer $role
	 * @param array $path (module,controller)
	 * @param array $actions может быть FALSE
	 * @param array $priv
	 * @return Formochki
	 */
	private function createForm_privilegies($role,$resInfo,$actions,$priv)
	{

		$form=new Formochki();
		$form->setAttrib('name','formPrivs');
		$form->setAttrib('id','formPrivs');
		$form->setMethod('POST');
		$form->setAction($this->baseLink."/savepriv");

		$form->addElement("hidden","role",array("value"=>$role));
		$form->addElement("hidden","resource",array(
		"value"=>rtrim(implode("-",array($resInfo["module"],$resInfo["controller"])),'-')
		));

		// обязательный - на все
		$value=isset($priv["_ALL_"])
		? $priv["_ALL_"]["allow"]
		: 2 ;
		$radio=$this->createRadio("_ALL_",$value);
		$form->addElement($radio);
		// пройдемся по ACTION
		if ($actions)
		{
			foreach ($actions as $privName=>$a)
			{
				$value=isset($priv[$privName])
				? $priv[$privName]["allow"]
				: 2 ;
				$radio=$this->createRadio($privName,$value);
				//				$radio->setIsArray(false);
				$form->addElement($radio);
			}
		}
		$form->addElement("submit","OK",array("class"=>"apply_text"));
		$form->getElement("OK")->setName("ПРИНЯТЬ");
		$form->addElement("reset","RES",array("class"=>"apply_text"));
		$form->getElement("RES")->setName("Вернуть");

		//		$logger->log($priv, Zend_Log::INFO);

		return $form;
	}

	private function createRadio($elemName,$value)
	{
		$radio=new Zend_Form_Element_Radio($elemName);


		$radio->addMultiOptions(array(
		2=>"Наследовать",
		0=>"Запретить",
		1=>"Разрешить"
		));
		;
		$radio->helper="FormMultiRadioList";
		$radio->removeDecorator('Label');
		$radio->removeDecorator('HtmlTag');
		$radio->setValue($value);
		return $radio;
		;
	}

	/** форма редактирования св-в ресурса
	 * @param array $resInfo
	 * @return Formochki
	 */
	private function createForm_resDetails($resInfo)
	{
		//		$logger = Zend_Registry::get('logger');

		$form=new Formochki();
		$form->setAttrib('name','formDetails');
		$form->setAttrib('id','formDetails');
		$form->setMethod('POST');
		$form->setAction($this->baseLink."/saveres");
		$form->addElement("text","title",array(
		"value"=>$resInfo["title"],
		"class"=>"longinput"
		));
		$form->addElement("textarea","coment",array(
		"value"=>$resInfo["coment"],
		"class"=>"littleArea2"
		));
		$form->addElement("textarea","paramNames",array(
		"value"=>$resInfo["paramNames"],
		"class"=>"littleArea2"
		));

		$form->addElement("hidden","id",array(
		"value"=>rtrim(implode("-",array($resInfo["module"],$resInfo["controller"])),'-')
		));
		$form->addElement("submit","OK",array("class"=>"apply_text"));
		$form->getElement("OK")->setName("Применить");
		return $form;
	}

	private function sessionUpdate($params)
	{
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$filter = new Zend_Filter_StripTags();


		// обновим сессию
		foreach ($params as $param=>$value)
		{
			$_value=$value;
			switch ($param)
			{

				case "resource":
					$_value=$filter->filter($value);

					break;

					// переменная целая
				default:
					$_value=(int)$value;
					break;
			}

			$this->filterSession->$param=$_value;
		}
	}

	private function buildCriteria($in=null)
	{
		if (is_null($in)) $in=$this->filterSession->getIterator();
		$criteria['role']=( !isset($in['role']) || $in['role'] < 0)
		?	0
		:	$in['role']
		;
		$criteria['resource']=( !isset($in['resource']) || $in['resource'] ==='')
		?	'default'
		:	$in['resource']
		;
		return $criteria;
	}


	/** строит дерево ресурса
	 * @param string $resource
	 * @return array
	 */
	private function tree_Res($resource)
	{
		$result=array();
		$temp=explode("-",$resource);

		if (count($temp)>1)
		{
			$result[$temp[0]]='';
			$result[$temp[0]."-".$temp[1]]='';
		}
		else $result[$temp[0]]='';

		return $result;
	}

	/** перечень ВСЕХ ресурсоов на основе модулей-контроллеров и данных в БД
	 * @param array
	 * @return array:
	 */
	/*	private function tree_Resources($resDescribed)
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
		//		$logger->log($res2add, Zend_Log::INFO);
		// общие значения с приоритетом описанных в БД
		$resList=array_intersect_key($resDescribed,$tree);
		// Добавим тех, кого нет в БД
		$resList=array_merge($resList,$res2add);
		// отсортируем
		ksort($resList);

		// проверить БД и вычистить то, чего нет среди модулей-контроллеров
		if (count($res2del)>0) $this->_model->clean($res2del);
		// добавить в БД новые модули-контроллеры
		if (count($res2add)>0) $this->_model->add($res2add);
		// вернем истинный список того, куда можно ходить
		return $resList;
		}
		*/
	private function resourceExists($resource)
	{
		$logger = Zend_Registry::get('logger');


		$path=$this->hlpAcl->getModuleControllerFromRes($resource);
		// array [_MODULE_DIR_] -> _PATH_TO_CONTROLLERS_OF_THIS_MODULE_
		$modules=$this->getFrontController()->getControllerDirectory();
		$logger->log($modules, Zend_Log::INFO);
		$chkModule=array_key_exists($path["module"],$modules);
		// указан контролдлер и он есть такой
		$chkCont=!empty($path["controller"]) && array_search($path["controller"],$modules);
		// еси оба есть - все гуд
		return $chkModule && $chkCont;
		;
	}

	private function isAllowForNav($acl,$role,$res)
	{
		if ($acl->isAllowed($role,$res) || $acl->isAllowed($role,$res,"index")) return true;
		else return false;
	}
}