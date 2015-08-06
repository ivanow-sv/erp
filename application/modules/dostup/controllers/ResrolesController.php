<?php
class Dostup_ResrolesController extends Zend_Controller_Action
{

	private $_model;
	private $_rolemodel;
	private $_navModel;

	//	private $_adminRole=1;
	private $redirectLink;
	private $hlp; // помощник действий Typic
	private $hlpAcl; // помощник действий при работе с ACL
	private $hlpFrms; // помощник действий при работе с формами
	private $_aclCurrent; // подопытный ACL
	private $filterSession;
	private $baseLink;

	private $rolesAll;
	private $rolesRestr;
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
		$this->hlpFrms=$this->_helper->getHelper('Forms');

		Zend_Loader::loadClass('Resources');
		Zend_Loader::loadClass('Roles');
		Zend_Loader::loadClass('Menus');
		Zend_Loader::loadClass('Formochki');
		$this->_model=new Resources;
		$this->_rolemodel=new Roles();
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
		$this->rolesAll=$this->_model->getRoles();		
		$this->rolesRestr=$this->_rolemodel->getRestricted();

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
		$this->hlpAcl->buildAclRoles($acl,$roleTree,$resTree2dim,$priv);

		// отправим в ВИД ACL, список ресурсов и роль
		$this->view->resources=$resTree2dim;
		$this->view->restitles=$resTree;
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
		foreach ($resTree2dim as $module=>$childs)
		{
			$signClass=$acl->isAllowed($criteria["role"],$module)
			? "okSign"
			: "stopSign" ;
			$accessSigns[$module]=$signClass;

			if (is_array($childs) && count($childs)>0)
			{
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
					$accessSigns[$resId]=$signClass;
				}
			}
				
		}
		
		// добавление новой роли
		$this->view->formAdd=$this->createForm_add();

		// удаление роли
		$this->view->formDel=$this->createForm_del();
		$this->view->formDel->getElement("id")->setValue($criteria["role"]);

		// переименование (правка) роли
		$formEdit=$this->createForm_edit();
		$rInfo=$this->_rolemodel->getInfo($criteria["role"]);
		$formEdit->getElement("id")->setValue($criteria["role"]);		
		$formEdit->getElement("title")->setValue($this->rolesAll[$criteria["role"]]);
		$formEdit->getElement("comment")->setValue($rInfo["comment"]);
		$this->view->formEdit=$formEdit;
		$this->view->roleInfo=$rInfo;

		$this->view->accessSigns=$accessSigns;

	}

	 public function addAction()
	 {
		$redirectLink=$this->redirectLink;
		
		if (!$this->_request->isPost()) $this->_redirect($redirectLink);
	
		$form=$this->createForm_add();
		
		$params=$this->_request->getParams();
		$chk=$form->isValid($params);
		
		if ($chk)
		{
			$v=$form->getValues();
			$id=$this->_rolemodel->addRoleByTitle($v["title"]);
			$this->sessionUpdate(array("role"=>$id));
			$this->_redirect($redirectLink);
		}
		else
		{
			$this->view->msg="Неверно заполнена";
			$this->view->formAdd=$form;
		}	
	}
	
	 public function editAction()
	 {
		$redirectLink=$this->redirectLink;
		
		if (!$this->_request->isPost()) $this->_redirect($redirectLink);
	
		$form=$this->createForm_edit();
		
		$params=$this->_request->getParams();
		$chk=$form->isValid($params);
		
		if ($chk)
		{
			$v=$form->getValues();
			$data["title"]=$v["title"];
			$data["comment"]=$v["comment"];
			
			$this->_rolemodel->setInfo_v2($data, $v["id"]);
			
// 			$id=$this->_rolemodel->addRoleByTitle($v["title"]);
// 			$this->sessionUpdate(array("role"=>$id));
			$this->_redirect($redirectLink);
		}
		else
		{
			$this->view->msg="Неверно заполнена";
			$this->view->formEdit=$form;
		}	
	}
	
	public function delAction() 
	{
		if (!$this->_request->isPost()) $this->_redirect($this->redirectLink);
		
		
		$form=$this->createForm_del();
		$params=$this->_request->getParams();
		$chk=$form->isValid($params);
		if ($chk)
		{
			$v=$form->getValues();
			$r=$this->_rolemodel->deleteRole($v["id"]);
			$this->_redirect($this->redirectLink);
		}
		else
		{
			$this->view->msg="Неверно заполнена";
			$this->view->formDel=$form;
				
		}
		
	}


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


	private function createForm_roleFilter($role)
	{
		$form=new Formochki();
		$form->setAttrib('name','filter');
		$form->setAttrib('id','filter');
		$form->setMethod('POST');
		$form->setAction($this->baseLink);
		// роли
// 		$roles=$this->_model->getRoles();
		$rList=$this->hlp->createSelectList("role",$this->rolesAll,'',$role);
		$form->addElement($rList);
		$form->getElement("role")
		->setAttrib("onChange","$('#filter').submit();")
		->setValue($role)
		->addValidator("InArray",array_keys($this->rolesAll));
		;
		
		$form->addElement("submit","OK",array("class"=>"apply_text"));
		$form->getElement("OK")->setName("Сменить");
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
		
		$_defRole=$this->hlp->getFirstElemKey($this->rolesAll);
		
		$criteria['role']=( isset($in['role']) && array_key_exists($in['role'], $this->rolesAll) )
		?	$in['role']
		:	$_defRole
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
	
	private function createForm_add()
	{
		$formAdd=$this->hlpFrms->add();
		$formAdd->setAction($this->view->baseUrl
				.'/'.$this->view->currentModuleName
				.'/'.$this->_request->getControllerName()
				.'/'.'add'
		);
		$formAdd->getElement("title")->addValidator("Alnum",true,array("allowWhiteSpace"=>true));
		$formAdd->removeElement("id");
		return $formAdd;
		
	}
	private function createForm_del()
	{
		$formDel=$this->hlpFrms->del();
		$formDel->setAction($this->view->baseUrl
				.'/'.$this->view->currentModuleName
				.'/'.$this->_request->getControllerName()
				.'/'.'del'
		);
		$_roles=$this->rolesAll;
		// запрещенные роли низя удалять
		$restr=$this->_rolemodel->getRestricted();
		// а вот этот список можно
		$roles=array_diff_key($_roles,$restr);
		
		$formDel->getElement("id")->addValidator("InArray",true,array(array_keys($roles)));
		return $formDel;
		
	}
	private function createForm_edit()
	{
		$formEdit=$this->hlpFrms->add();
		$formEdit->setAttrib('name','edit');
		$formEdit->setAttrib('id','edit');
		$formEdit->setAction($this->view->baseUrl
				.'/'.$this->view->currentModuleName
				.'/'.$this->_request->getControllerName()
				.'/'.'edit'
		);
		$roles=$this->rolesAll;
		$formEdit->getElement("id")->addValidator("InArray",true,array(array_keys($roles)));
		
		$formEdit->addElement("textarea","comment",array("class"=>"medinput"));
		$formEdit->getElement("title")->addValidator("Alnum",true,array("allowWhiteSpace"=>true));
		$formEdit->getElement("comment")->addValidator("Alnum",true,array("allowWhiteSpace"=>true))
		->setDescription("Описание");
				
		$formEdit->getElement("OK")->setName("ПРИМЕНИТЬ");
		return $formEdit;
	}
}