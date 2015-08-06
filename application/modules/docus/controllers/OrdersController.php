<?php
//@FIXME работа после выбора фильтра - неадекватное поведение
class Docus_OrdersController extends Zend_Controller_Action
{

	protected 	$session;
	protected 	$baseLink; // ссылка на модуль-контроллер начиная от базы
	protected	$redirectLink; // ссылка в этот модуль/контроллер
	protected	$studyYear_now;
	private 	$data;
	private 	$specDefault; // специальность по умолчанию
	private 	$osnovs;
	private $hlp; // помощник действий Typic
	private $hlpAcl; // помощник действий Acl

	private $_num_len=3; // кол-во знаков в № зачетки
	private $_list1="|";
	private $_listFil="--";
	private $_author; // пользователь шо щаз залогинен
	private $_isAdmin; // костыль для суперадминов
	//	private $data;

	public function init()
	{
		// выясним название текущего модуля для путей в ссылках
		$currentModule=$this->_request->getModuleName();
		$this->view->currentModuleName=$currentModule;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->currentController = $this->_request->getControllerName();
		$this->baseLink=$this->_request->getBaseUrl()."/".$currentModule."/".$this->_request->getControllerName();
		$this->view->baseLink=$this->baseLink;
		$this->redirectLink=$this->_request->getModuleName()."/".$this->_request->getControllerName();
		$this->hlp=$this->_helper->getHelper('Typic');
		$this->hlpAcl=$this->_helper->getHelper('Acl');


		Zend_Loader::loadClass('Orders');

		// @TODO из реестра взять с каким факультетом работает данный пользователь
		// @TODO а еси может со всем факультетами работать? админ например

		$groupEnv=Zend_Registry::get("groupEnv");
		$this->data=new Orders();
		$this->studyYear_now=$this->data->getStudyYear_Now();

		$this->osnovs=$this->data->getInfoForSelectList("osnov");
		$moduleTitle=Zend_Registry::get("ModuleTitle");
		$modContrTitle=Zend_Registry::get("ModuleControllerTitle");
		$this->view->addHelperPath('./application/views/helpers/','My_View_Helper');

		Zend_Loader::loadClass('Zend_Session');
		Zend_Loader::loadClass('Zend_Form');
		Zend_Loader::loadClass('Formochki');
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$this->session=new Zend_Session_Namespace('my');
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		$ajaxContext ->addActionContext('shareform', 'json')->initContext('json');
		$ajaxContext ->addActionContext('shareformusers', 'json')->initContext('json');
		$ajaxContext ->addActionContext('shareapply', 'json')->initContext('json');
		$ajaxContext ->addActionContext('del', 'json')->initContext('json');
		if ($this->_request->isXmlHttpRequest()) $ajaxContext ->addActionContext('docslist', 'json')->initContext('json');
		//		$ajaxContext ->addActionContext('freelist', 'json')->initContext('json');
		//		$ajaxContext ->addActionContext('zachchange', 'json')->initContext('json');
		//		$ajaxContext ->addActionContext('move', 'json')->initContext('json');
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/orders.js');

		//		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/styles/dekanat.css');
		//		Zend_Controller_Action_HelperBroker::addPrefix('My_Helper');
		$this->_author=Zend_Auth::getInstance()->getIdentity();
		$this->_isAdmin=$this->_author->role==1?true:false;

	}

	public function indexAction ()
	{
		$logger=Zend_Registry::get("logger");
		//		$acl=Zend_Registry::get("ACL");
		//		$roles=$acl->getRoles();
		// получить роли: текущая и потомки
		//		$roles=$this->data->getRoleChildrenTree($this->_author->role);
		//		$roles=$this->hlpAcl->treeRolePrepare($roles);
		//		$roles=$this->hlpAcl->rolesListFromTree($roles);
		//		$logger->log($roles ,Zend_Log::INFO);
		// вот ЧО
		/**
		* 1. если POST - строим критерии из POST
		* 2. если не пост и есть данные в сессии - Брать из сессии
		* 3. еси сессия пуста - и нет POST - берем дефолтовые
		*/
		// список приказов по студентам
		// фильтр
		$formFilter=$this->createForm_Filter();
		$this->view->form=$formFilter;
		if ($this->_request->isPost())
		{
			$params = $this->_request->getParams();
			$chk=$formFilter->isValid($params);
			if ($chk) $this->sessionUpdate($formFilter->getValues());
			else $this->sessionUpdate($this->getFormFilterDefaults());
		}
		$criteria=$this->buildCriteria();
		$formFilter->setDefaults($criteria);
		// найти все документы, к которым у этих ролей есть доступ
		// писок документов
		$list=$this->data->getOrdersList($this->hlp->dates2YMD($criteria),$this->_author->id,$this->_isAdmin);
		$this->view->list=$list;
		$this->view->newDocumentForm=$this->createForm_newDocument();
	}

	/** @TODO не доделано
	 * перечень документов, разрешенных на просмотр пользователю за последние полгода
	 */
	public function docslistAction()
	{
		// если AJAX
		if ($this->_request->isXmlHttpRequest())
		{
			// очистим вывод
			$this->view->clearVars();
			$this->view->baseLink=$this->baseLink;
			$this->view->baseUrl = $this->_request->getBaseUrl();
			// подбор документов за последние 14 мес
			$criteria=array(
			"titleLetter"=>"с",
			"titleNum"=>0,
			'titleDate1'=>date("Y-m-d",mktime()-(30*14*$this->hlp->getSecondsInDay())),
			'titleDate2'=>date("Y-m-d"),
			'createDate1'=>date("Y-m-d",mktime()-(30*14*$this->hlp->getSecondsInDay())),
			'createDate2'=>date("Y-m-d")
			);

			//			$_docs=$this->data->getOrdersList4Select($criteria, $this->_author->id);
			//			$this->view->docInfo=$this->hlp->createSelectList("docid",$_docs);
			//			$out["docInfo"]=$this->view->render($this->_request->getControllerName().'/_docList.phtml');

			$this->view->out=$out;
		}

	}

	public function newAction()
	{
		if (!$this->_request->isPost()) $this->_redirect($this->redirectLink);
		$form=$this->createForm_newDocument();
		$params = $this->_request->getParams();

		$chk=$form->isValid($params );
		// форма неправильно заполнена
		if (!$chk)
		{
			$msg=$form->getMessages();
			$_msg="<p class='error'>Ошибка заполнения!</p>";
			foreach ($msg as $var=>$text)
			{
				$_msg.="<p>".$form->getElement($var)->getDescription();
				$_msg.=" : ".implode("; ", array_values($text))."</p>";
			}
			$this->view->msg=$_msg;
		}
		// все нормуль
		else
		{
			$values=$form->getValues();
			// создадим приказ
			$values["titleDate"]=$this->hlp->date_DMY2YMD($values["titleDate"]);
			$id=$this->data->createOrder($this->_author->id, $values);
			if ($id!==false)
			{
				$this->view->msg="<p>Успешно создан приказ</p>";
				// дадим права владельцу
				//				$aff=$this->data->addPrivileges($id,$this->_author->id,"FULL",1);
				//				if ($aff===false) $this->view->msg.="<p class='error'>Сбой при работе с БД при назначении прав доступа. Документ будет недоступен</p>";
				//				else
				//				{
				//					$this->view->msg.="<p>Успешно назначены права доступа.</p>";
				//					$this->view->msg.="<p>Вы можете сменить права доступа или опубликовать документ нажав соовтетвующую кнопку напротив приказа.</p>";
				//					$this->view->ok=true;
				//					//					$this->view->msg.="<p>Те.</p>";
				//				}

			}
			else $this->view->msg="<p class='error'>Сбой при работе с БД при создании приказа</p>";

		}
		//		echo $chk;

		;
	}

	public function shareformAction()
	{
		// если НЕ AJAX - идет лесом
		if (!$this->_request->isXmlHttpRequest()) return;
		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();

		// форма раздачи прав
		$form=$this->createForm_share();

		// из формы нас интересует тока ID ибо остальное обрабатывается JS
		$values=$form->getValidValues($_POST);
		// если не то пришло
		if (!isset($values["id"])) return ;
		// есть ли права у этого пользователя к этому документу
		$privs=$this->data->getDocumentPrivs4User($values["id"],$this->_author->id);
		// @TODO сообщить о том что недостаточно прав
		$_wrongPriv=$privs["allow"]<1 || $privs["action"]<100;
		if ($_wrongPriv && !$this->_isAdmin) return;


		// список пользователей из той же группы что и текущей
		$usersInRole=$this->data->getUsersInRole($this->_author->role);
		$this->view->usersInRole=$usersInRole;

		$this->view->shareForm=$form;

		// найдем и покажем кому что прописано к этому документу
		$this->view->usersGranted=$this->data->getDocumentPrivs($values["id"]);
		$privsAll=$this->data->getPrivilegesList();
		$privsAll=array_flip($privsAll);
		$this->view->privSelList=$this->hlp->createSelectList("priv",$privsAll);
		//		$this->view->privSelList->isArray(true);
		// расшифровка прав доступа
		$this->view->privList=$this->data->getPrivilegesList();
		$this->view->privLList=$this->data->getPrivilegesListTitles();

		$out["shareFormWrapper"]=$this->view->render($this->_request->getControllerName().'/_shareForm.phtml');
		$this->view->out=$out;
		;
	}

	public function delAction()
	{
		// если НЕ AJAX - идет лесом
		if (!$this->_request->isXmlHttpRequest()) return;
		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		
		$id=(int)$this->_request->getParam("id",0);
		if ($id<1) return;
		// узнаем инфо о приказе
		$info = $this->data->getOrderInfo($id);
		// если не найдено ничаго - посыл
		if (empty($info))  {
			$this->view->msg='<p class="error">Не найдено</p>';
			return; 
		}
		// если не админ, то нах
		if (! $this->_isAdmin) return;

		$aff=$this->data->delOrder($id);
		if ($aff===false) $this->view->msg='<p class="error">Ошибка при работе с БД</p>';
		else $this->view->msg='<p >Успешно</p>';
		$out["delWrapper"]=$this->view->render($this->_request->getControllerName().'/_del.phtml');
		$this->view->out=$out;
		

	}

	public function editAction()
	{
		$id=(int)$this->_request->getParam("id",0);
		if ($id<1) $this->_redirect($this->redirectLink);
		//		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/ckeditor/ckeditor.js');

		// узнаем инфо о приказе
		$info = $this->data->getOrderInfo($id);
		// если не найдено ничаго - посыл
		if (empty($info)) $this->_redirect($this->redirectLink);

		// @FIXME автора приказа = пользователю ? или его начальнику
		// родители роли пользователя
		//		$parents=$this->hlpAcl->getParents($this->_author->role);

		//		$acl=Zend_Acl_Role_Registry;
		//		$this->_author->role
		//		if ($this->_author->id === $info["author"] || array_search($this->_author->role, $parents)===true) $this->_redirect($this->redirectLink);
		//		$_changable=($this->_author->id === $info["author"] || array_search($this->_author->role, $parents)===true);
		//		var_dump($_changable);
		//		var_dump(array_search($this->_author->role, $parents)===true);
		//		var_dump($parents);
		//		var_dump($parents);

		$this->view->title.=" Приказ №".$info["titleNum"]."-".$info["titleLetter"]." от ".$info["titleDate"];
		$this->view->prikazCreated=$info["createtime"];
		// создадим форму
		$form=$this->createForm_newDocument("edit");
		// добавим ID, его там не было
		$form->addElement("hidden","id");
		$form->getElement("id")
		->setRequired(true)
		->addValidator("NotEmpty",true,array("all"))
		->addValidator("Digits",true)
		;

		// узнаем что пришло
		$params= $this->_request->getParams();
		$values=$form->getValidValues($params);
		// данные идентичны?
		$chk=$values["id"]===$info["id"]
		&& $values["titleLetter"]===$info["titleLetter"]
		&& $values["titleNum"]===$info["titleNum"]
		&& $values["titleDate"]===$info["titleDate"]
		&& $values["comment"]===$info["comment"];


		// если отправляли форму и были изменения
		if ($this->_request->isPost() && $form->isValid($params) && !$chk)
		{
			// @FIXME права на изменение
			// пока менять могут тока одмины и автор
			$_changable=($this->_author->id === $info["author"] || $this->_isAdmin);
			if ($_changable)
			{
				$values["titleDate"]=$this->hlp->date_DMY2YMD($values["titleDate"]);
				// внесем их
				$data=array(
				"comment"=>$values["comment"],
				"titleNum"=>$values["titleNum"],
				"titleDate"=>$values["titleDate"],
				"titleLetter"=>$values["titleLetter"]
				);
				$this->data->updateOrder($values, $id);
			}
			// вернемся к форме
			$this->_redirect($this->redirectLink."/".$this->_request->getActionName()."/id/".$id);
		}
		else
		{
			// еси нет - просто отобразить
			$form->setDefaults($info);
			// Добавим "ПРИНЯТЬ"
			$form->addElement("submit","OK",array(
			"class"=>"apply_text"		
			));
			$form->getElement("OK")->setName("Применить");
			// Добавим "вернуть"
			$form->addElement("reset","RESET",array(
			"class"=>"apply_text"		
			));
			$form->getElement("RESET")->setName("Вернуть");

			$this->view->editDocumentForm=$form;

		}
			
		;
		return;
	}

	public function shareformusersAction()
	{
		// если НЕ AJAX - идет лесом
		if (!$this->_request->isXmlHttpRequest()) return;
		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$role=(int)$this->_request->getPost("role");
		if ($role<0) return;
		$users=$this->data->getUsersInRole($role);
		$this->view->usersInRole=$users;
		$out["usersList"]=$this->view->render($this->_request->getControllerName().'/_usersInRole.phtml');
		$this->view->out=$out;
	}

	public function shareapplyAction()
	{
		// если НЕ AJAX - идет лесом
		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->baseLink);
		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();

		// получим данные формы
		$DOCID=$this->_request->getPost("docid");
		$users=$this->_request->getPost("userid");
		$privs=$this->_request->getPost("priv");
		// есть ли вообще данные
		if ($DOCID<0 ) return;
		//		$this->view->u=empty($users);
		//		$this->view->p=$privs;
		//
		$privs=empty($privs)
		? 	NULL
		:	$this->hlp->arrayINTEGER($privs);
		$users=empty($users)
		?	null
		:	$this->hlp->arrayINTEGER($users);


		$privsDefaults=$this->data->getPrivilegesList();
		$privsDefaults_T=array_flip($privsDefaults);
		//$this->view->cc=$privsDefaults_T;
		// разрешено ли зашедшему пользователю назначать права на документ $DOCID
		$privs4doc=$this->data->getDocumentPrivs4User($DOCID, $this->_author->id);
		// как минимум допуск SHARE
		//		// @TODO сообщить ему шо он не прав
		if ($privs4doc["action"] < $privsDefaults["SHARE"] && !$this->_isAdmin) return;

		// добавим права, с учетом того, что самого себя не трогаем
		if (count($users)>0)
		{
			// @FIXME очистить все привилегии к документу, исключая текущего пользователя
			$this->data->delDocumentPrivs($DOCID,array(" userid <> ".$this->_author->id));
			foreach ($users as $key=>$userid)
			{
				if ($userid==$this->_author->id) continue;
				// если некорретная привилегия
				if (array_search($privs[$key], $privsDefaults)===false) continue;
				$this->data->addPrivileges($DOCID, $userid, $privsDefaults_T[$privs[$key]], 1);
			}
		}
		// значит убрать всех кроме "себя"
		else
		{
			// привилегии к документу вообще
			//			$privs4doc=$this->data->getDocumentPrivs($DOCID,array(" priv.userid <> ".$this->_author->id));
			//			$this->view->xcxc=$privs4doc;
			// удалим все записи о правах, которые не принадлежат текущему пользователю
			$this->data->delDocumentPrivs($DOCID,array(" userid <> ".$this->_author->id));
		}
		;
	}

	private function kursy()
	{
		// список курсов
		$kursy=array();
		for ($i = 1; $i <= 6; $i++)
		{
			$kursy[$i]=$i;
		}
		return $kursy;
	}
	/**
	 * построить список выбора
	 * @param string $elemName
	 * @param array $src data ID=>value
	 * @param integer $defaultValue selected option
	 * @return Zend_Form_Element_Select
	 */
	private function createSelectList($elemName,$src,$nullTitle="",$selected=false)
	{
		Zend_Loader::loadClass('Zend_Form_Element_Select');
		// ОБЛОМ! зенд тянект тока двухуровневое дерево :(

		$result = new Zend_Form_Element_Select($elemName);
		if ($nullTitle!=='') $result->addMultiOption(0,$nullTitle);

		//		$result->addMultiOptions($src);
		foreach ($src as $key=>$value)
		{
			$result ->addMultiOption($key,$value);
		}
		// выбранное значение SELECTED
		if ($selected !==false) $result  ->setValue($selected);
		$result  ->removeDecorator('Label');
		$result  ->removeDecorator('HtmlTag');
		return $result;
	}

	private function createRadioList($elemName,$src,$nullTitle="",$selected=false)
	{
		Zend_Loader::loadClass('Zend_Form_Element_Radio');
		// ОБЛОМ! OPTGROUP тянект тока двухуровневое дерево :(

		$result = new Zend_Form_Element_Radio($elemName);
		if ($nullTitle!=='') $result->addMultiOption(0,$nullTitle);

		$result->addMultiOptions($src);
		// выбранное значение SELECTED
		if ($selected !==false) $result  ->setValue($selected);
		$result  ->removeDecorator('Label');
		$result  ->removeDecorator('HtmlTag');
		return $result;
	}

	private function getFormFilterDefaults()
	{
		$today=$this->hlp->getTodayDate();
		$weekInSec=7*$this->hlp->getSecondsInDay();
		$monthInSec=30*$this->hlp->getSecondsInDay();
		$result=array(
		"createDate1"=>date("d-m-Y",(time()-$weekInSec)),
		"createDate2"=>$today,
		"titleDate1"=>date("d-m-Y",(time()-$monthInSec)),
		"titleDate2"=>date("d-m-Y"),
		"titleNum"=>'',
		"titleLetter"=>'с', // русская "с" ( эс ) 
		);
		return $result;
	}

	private function sessionUpdate($params)
	{
		$defaults=$this->getFormFilterDefaults();

		$filter = new Zend_Filter_Alpha();
		// обновим сессию
		foreach ($params as $param=>$value)
		{
			// отфильтруем
			switch ($param)
			{
				case "createDate1":
				case "createDate2":
				case "titleDate1":
				case "titleDate2":
					$_dt=$this->hlp->date_DMY2array($value);
					$_value=checkdate($_dt["month"],$_dt["day"],$_dt["year"])
					?	$value
					:	$defaults[$param]
					;
					break;

				case "titleNum":
					$_value=(int)$value;
					break;

				case "titleLetter":
					$_value=$filter->filter($value);
					break;

				default:
					$_value=$value;
					break;
			}
			$this->session->$param=$_value;
		}
	}



	/** строит критериый поиска из массива или из данных сессии
	 * @return array
	 */
	private function buildCriteria($in=null)
	{
		if (is_null($in)) $in=$this->session->getIterator();
		$defaults=$this->getFormFilterDefaults();
		$criteria['titleNum']=isset($in['titleNum'])
		?	$in['titleNum']
		:	$defaults['titleNum']
		;
		$criteria['titleLetter']=isset($in['titleLetter'])
		?	$in['titleLetter']
		:	$defaults['titleLetter']
		;
		$criteria['titleDate1']=isset($in['titleDate1'])
		?	$in['titleDate1']
		:	$defaults['titleDate1'];
		$criteria['titleDate2']=isset($in['titleDate2'])
		?	$in['titleDate2']
		:	$defaults['titleDate2']
		;
		$criteria['createDate1']=isset($in['createDate1'])
		?	$in['createDate1']
		:	$defaults['createDate1']
		;
		$criteria['createDate2']=isset($in['createDate2'])
		?	$in['createDate2']
		:	$defaults['createDate2']
		;
		return $criteria;
	}


	/** возвращает первый элемент массива
	 * @param array $array
	 * @return array:
	 */
	private function getFirstElem($array)
	{
		$_ar=$array;
		reset($_ar);
		$result=each($_ar);
		return $result;
	}

	private function createForm_share()
	{
		$form=new Formochki();
		$form->setAttrib('name','shareForm');
		$form->setAttrib('id','shareForm');
		$form->setMethod('POST');
		// перенаправленине на себя, чтобы обновлять список пользователей
		$form->setAction($this->baseLink."/shareform");
		$form->addElement("hidden","id");
		$form->getElement("id")
		->addValidator("NotEmpty",true)
		->addValidator("digits",true)
		->setRequired(true)
		->setDescription("ID документа")
		;
		//		$list=$this->createSelectList("privilege", $this->data->getPrivilegesList());
		//		$form->addElement($list);
		//		$form->getElement("privilege")
		//		->addValidator("Digits",true)
		//		->setRequired(true)
		//		->setDescription("Права")
		;
		// выбор группы (роли)
		// корневые роли
		$roles=$this->data->getRoles_roots();
		// добавим гостей
		$roles=$this->hlpAcl->addGuestToRootsTree($roles);

		// перестроить список, с учетом родителей
		$roles=$this->hlpAcl->treeRolesPrepare($roles);
		$rList=$this->hlp->createSelectList("role",$roles,'',$this->_author->role);
		$form->addElement($rList);
		$form->getElement("role")
		->addValidator("digits",true)
		->setAttrib("onChange", "getUsersInRole(this);");

		//		$list=$this->data->getRoleChildrenTree($this->_author->role);

		// выбор пользователей - чекбоксы

		// при нажатии вызывается JS скрипт, который отправляет форму
		$form->addElement("button","OK",array(
		"class"=>"apply_text",
		// нужный скрипт
		'onClick'=>'shareApply();',
		));
		$form->getElement("OK")->setName("ПРИМЕНИТЬ");

		return $form;

	}

	//	private function createForm_editDocument($id)
	//	{
	//		$form=new Formochki();
	//		$form->setAttrib('name','editDocumentForm');
	//		$form->setAttrib('id','editDocumentForm');
	//		$form->setMethod('POST');
	//		$form->setAction($this->baseLink."/editapply");
	//		$form->addElement("hidden","id");
	//		$form->getElement("id")
	//		->setValue($id)
	//		->addValidator("NotEmpty",true)
	//		->addValidator("digits",true)
	//		->setRequired(true)
	//		->setDescription("ID документа")
	//		;
	//		$form->addElement("textarea","editor1");
	//		return $form;
	//	}

	private function createForm_newDocument($act="new")
	{
		$form=new Formochki();
		$form->setAttrib('name','newDocumentForm');
		$form->setAttrib('id','newDocumentForm');
		$form->setMethod('POST');
		$form->setAction($this->baseLink."/".$act);

		$form->addElement("text","titleDate",array("class"=>"typic_input","onMouseDown"=>'picker($(this));'));
		$form->getElement("titleDate")
		->addValidator("NotEmpty",true)
		->addValidator("Date",true,array('format'=>'dd-MM-yyyy','locale'=>'ru'))
		->setRequired(true)
		->setDescription("Дата в приказе")
		;
		$form->addElement("text","titleNum",array("class"=>"inputSmall2"));
		$form->getElement("titleNum")
		->setRequired(true)
		->addValidator("NotEmpty",true,array("all"))
		->addValidator("Digits",true)
		->setDescription("Номер приказа")
		;
		$form->addElement("text","titleLetter",array("class"=>"inputSmall"));
		$form->getElement("titleLetter")
		->setValue("с") // "с" русская
		->setRequired(true)
		->addValidator("NotEmpty",true,array("all"))
//		->addValidator("Alpha",true,array('allowWhiteSpace' => false))
		->setDescription("Буква в номере приказа")
		;
		$form->addElement("textarea","comment",array("class"=>"littleArea"));
		$form->getElement("comment")
		->addValidator("Alnum",true,array('allowWhiteSpace' => true))
		->setDescription("Комментарий")
		;
		$form->addElement("submit","OK",array(
		"class"=>"apply_text"		
		));
		$form->getElement("OK")->setName("Создать");

		return $form;
		;
	}

	private function createForm_Filter()
	{
		//		echo "preved";die();
		// форма поиска студня
		$form=new Formochki();
		$form->setAttrib('name','filterForm');
		$form->setAttrib('id','filterForm');
		$form->setMethod('POST');
		$form->setAction($this->view->baseUrl.'/'.$this->view->currentModuleName.'/'.$this->_request->getControllerName());
		$textOptions=array('class'=>'typic_input');

		// фильтр приказов
		// дата создания: от  и до
		// шапка приказа - дата ( от и до ) , номер, буква
		$form->addElement("text","createDate1",array("class"=>"typic_input","onMouseDown"=>'picker($(this));'));
		$form->getElement("createDate1")
		->addValidator("Date",true,array('format'=>'dd-MM-yyyy','locale'=>'ru'))
		->setDescription("Создан в интервале 'с' ")
		;
		$form->addElement("text","createDate2",array("class"=>"typic_input","onMouseDown"=>'picker($(this));'));
		$form->getElement("createDate2")
		->addValidator("Date",true,array('format'=>'dd-MM-yyyy','locale'=>'ru'))
		->setDescription("Создан в интервале 'по' ")
		;
		// шапка
		$form->addElement("text","titleDate1",array("class"=>"typic_input","onMouseDown"=>'picker($(this));'));
		$form->getElement("titleDate1")
		->addValidator("Date",true,array('format'=>'dd-MM-yyyy','locale'=>'ru'))
		->setDescription("Шапка приказа - начало интервала")
		;
		$form->addElement("text","titleDate2",array("class"=>"typic_input","onMouseDown"=>'picker($(this));'));
		$form->getElement("titleDate2")
		->addValidator("Date",true,array('format'=>'dd-MM-yyyy','locale'=>'ru'))
		->setDescription("Шапка приказа - конец интервала")
		;
		$form->addElement("text","titleNum",array("class"=>"inputSmall2"));
		$form->getElement("titleNum")
		->addValidator("Digits")
		->setDescription("Номер приказа")
		;
		$form->addElement("text","titleLetter",array("class"=>"inputSmall"));
		$form->getElement("titleLetter")
		->addValidator("Alpha",true,array('allowWhiteSpace' => false))
		->setDescription("Буква в номере приказа")
		;
		$form->addElement("submit","OK",array(
		"class"=>"apply_text"		
		));
		$form->getElement("OK")->setName("Искать");


		return $form;

	}


}