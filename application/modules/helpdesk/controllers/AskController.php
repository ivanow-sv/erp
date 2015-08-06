<?php
/**
 * @author zlydden
 * раздел заявителя - подача заявки, вывод списка
 */
class Helpdesk_AskController extends Zend_Controller_Action
{
	protected 	$session;
	protected 	$baseLink;
	protected	$redirectLink; // ссылка в этот модуль/контроллер
	private 	$_model;
	private 	$hlp; // помощник действий Typic
	private 	$_author; // пользователь шо щаз залогинен
	private 	$filterState=999; // выбранное состояние в фильтра поиска
	private 	$filterDay=3; // выбранно дней в  фильтра поиска
	private 	$filterType=array(); // выбранный тип заявки в  фильтра поиска
	private 	$ticketTypes;

	// статус заявки
	private		$states=array(
			999=>"Все",
			0=>"Открыта",
			1=>"закрыта"
	);

	
	// заявки поданы за последние N дней
	private		$dayInterval=array(
			3=>"3 дня",
// 			7=>"7 дней",
			30=>"30 дней",
			365=>"365 дней",
// 			"-1"=>"старше 7 дней",
	);


	function init()
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
		Zend_Loader::loadClass('Helpdesk');

		// 		$_acl=Zend_Registry::get("ACL");

		$this->_model=new Helpdesk();
		$moduleTitle=Zend_Registry::get("ModuleTitle");
		$modContrTitle=Zend_Registry::get("ModuleControllerTitle");
		$this->view->title=$moduleTitle
		.". ".$modContrTitle.'. ';
		$this->view->addHelperPath('./application/views/helpers/','My_View_Helper');

		Zend_Loader::loadClass('Zend_Session');
		Zend_Loader::loadClass('Zend_Form');
		Zend_Loader::loadClass('Formochki');
		// 		Zend_Loader::loadClass('Zend_Dom_Query');
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$this->session=new Zend_Session_Namespace('my');
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		$ajaxContext ->addActionContext('verdictadd', 'json')->initContext('json');
		$ajaxContext ->addActionContext('unitassign', 'json')->initContext('json');
		$ajaxContext ->addActionContext('formchanged', 'json')->initContext('json');
		$ajaxContext ->addActionContext('view', 'json')->initContext('json');
		$ajaxContext ->addActionContext('new', 'json')->initContext('json');
		$ajaxContext ->addActionContext('newform', 'json')->initContext('json');
		$ajaxContext ->addActionContext('wrongticket', 'json')->initContext('json');
		$ajaxContext ->addActionContext('close', 'json')->initContext('json');
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/helpdesk.js');
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/jquery.dataTables.min.js');
		$this->view->headLink()	->	appendStylesheet($this->_request->getBaseUrl().'/public/styles/jquery.dataTables.erp.css');
		$this->_author=Zend_Auth::getInstance()->getIdentity();
		$this->ticketTypes=$this->_model->getTicketTypes();
		$this->filterType=array_keys($this->ticketTypes);

	}


	public function indexAction()
	{
		// список открытых заявок текущего пользователя
		$filterForm=$this->createForm_filter();

		// @TODO иначе при пустой сессии вываливает ошибку
		$sessData=$this->session->getIterator();
		$oldData["days"]=$sessData["days"];
		$oldData["state"]=$sessData["state"];
		$oldData["type"]=$sessData["type"];
		// в сесси корреткные данные?
		if ($filterForm->isValid($oldData))
		{
			$values=$filterForm->getValues();
			$this->sessionUpdate($values);
		}
		// ощибка - создадим форму заново со значениями по умолчанию
		else
		{
			$filterForm=$this->createForm_filter();
		}
		// заявки
		$list=$this->_model->tickets_GetList($this->filterDay,$this->filterState,$this->filterType);
		$this->view->list=$list;

		$this->view->formFilter=$filterForm;
		$this->view->author=$this->_author->id;

		// форма подачи

		$formNew=$this->createForm_New();
		$this->view->formNew=$formNew;

		// форма заключений специалиста
		$formVerdict=$this->createForm_Verdictadd(0);
		$this->view->formVerdict=$formVerdict;

		
		// гостевой шаблон еси гостевой логин
		if ($this->_author->id==4) $this->_helper->layout->setLayout("hlpdskguest-main");
	}

	public function formchangedAction()
	{
		if (!$this->_request->isXmlHttpRequest())  $this->_redirect($this->redirectLink);

		// очистим вывод
		$this->view->clearVars();
		//
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->currentModuleName=$this->_request->getModuleName();
		$this->view->author=$this->_author->id;

		// узнаем что к нам пришло
		$formData = $this->_request->getPost('formData');
		$filterForm=$this->createForm_filter();
		if ($filterForm->isValid($formData))
		{
			// обновим сессию и свойства класса
			$values=$filterForm->getValues();
			$this->sessionUpdate($values);
		}
		else
		{
			// создадим форму со значениями по умолчанию
			$filterForm=$this->createForm_filter();
		}

		// выведем форму заново
		$this->view->formFilter=$filterForm;

		// найдем перечень
		$list=$this->_model->tickets_GetList($this->filterDay,$this->filterState,$this->filterType);
		$this->view->list=$list;
		// выведем его
		$out=array();
		$out["ticketsList"]=$this->view->render($this->_request->getControllerName().'/_list.phtml');
		$this->view->out=$out;
// 				$logger=Zend_Registry::get("logger");
// 				$logger->log($filterForm->getValue("type"), Zend_Log::INFO);
// 				$logger->log($this->_request->getPost("type"), Zend_Log::INFO);
		


	}

	/**
	 * Генерация формы подачи зявки и вывод в VIEW через AJAX
	 */
	public function newformAction()
	{
		// еси не AJAX
		if (!$this->_request->isXmlHttpRequest())  $this->_redirect($this->redirectLink);
		// очистим вывод
		
		$this->view->clearVars();
		// перезапишем типовые переменные
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->currentModuleName=$this->_request->getModuleName();
		$this->view->currentController=$this->_request->getControllerName();
		// форма подачи
// 		$this->ticketTypes=$this->_model->getTicketTypes();
		$formNew=$this->createForm_New();
		$this->view->formNew=$formNew;
		$this->view->formNewR=$this->view->render($this->_request->getControllerName().'/_formNewWrapper.phtml');

	}

	// создать новую
	public function newAction()
	{
		// еси не AJAX
		if (!$this->_request->isXmlHttpRequest())  $this->_redirect($this->redirectLink);
		// очистим вывод
		$this->view->clearVars();
		// перезапишем типовые переменные
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->currentModuleName=$this->_request->getModuleName();
		$this->view->currentController=$this->_request->getControllerName();

		// 		if (!$this->_request->isPost())  $this->_redirect($this->redirectLink);

// 		$this->ticketTypes=$this->_model->getTicketTypes();
		$form=$this->createForm_New();

		$chk=$form->isValid($_POST);
		// проверки и вывод ошибок
		if ($chk)
		{
			$values=$form->getValues();
			$res=$this->_model->tickets_AddNew($values,$this->_author->id);
			// данные внесены
			if ($res["status"])
			{
				$this->view->msg="Ваша заявка принята";//. Номер заявки ".$res["inserted"];
				$this->view->msg.="\n<br>Страница перезагрузится через 2 секунды.";
				$this->view->status=1;
				$this->view->linkCont=$this->baseLink;
				$this->view->formNewR=$this->view->render('_buttonOK.phtml');

			}
			// ошибка БД
			else
			{
				$this->view->msg="Ошибка при работе с БД. Данные не внесены.<br>Попробуйте попозже или свяжитесь с тех. специалистами.";
				$this->view->msg.="\n<br>Страница перезагрузится через 2 секунды.";
				$this->view->status=2;
				$this->view->linkCont=$this->baseLink;
				$this->view->formNewR=$this->view->render('_buttonOK.phtml');

			}
		}
		// форма заполнена неверно
		else
		{
			$this->view->status=-1;
			$this->view->msg="Заполните форму правильно.";
			$this->view->formNew=$form;
			$this->view->formNewR=$this->view->render($this->_request->getControllerName().'/_formNew.phtml');
		}

		$this->view->formMsg=$this->view->render('_formMsgContent.phtml');
	}

	// посмотреть заявку
	public function viewAction()
	{
		if (!$this->_request->isXmlHttpRequest())  $this->_redirect($this->redirectLink);
		$id = (int)$this->_request->getPost("id");
		if ($id<1) $this->_redirect($this->redirectLink);
		// выясним
		$info=$this->_model->tickets_getInfo($id,0);
		// @FIXME отображать ошибки
		if (empty($info))
		{
			$msg="не обнаружено";
			$formVerdict=$this->createForm_Verdictadd(0);
		}
			
		else
		{
			$this->view->info=$info;
			$this->view->ticketlog=$this->_model->tickets_getLog($id);
			// форма заключений специалиста
			$formVerdict=$this->createForm_Verdictadd($id);
		}
		$this->view->formVerdict=$formVerdict;

		// если неверно назначена
		if ($info["isWrong"]!=0)
		{
			$this->view->trashClass="trashFullMedium";
			$this->view->trashMsg='<span class="warningIcoSmall"></span>'.'<span class="warning">'."Заявка закрыта как неправильно оформленная"."</span>";
		}
		else
		{
			$this->view->trashClass="trashEmptyMedium";
			$this->view->trashMsg="";
		}

		// рендер инфо
		$this->view->details=$this->view->render($this->_request->getControllerName().'/_details.phtml');

	}

	public function verdictaddAction()
	{
		if (!$this->_request->isXmlHttpRequest())  $this->_redirect($this->redirectLink);
		// очистим вывод
		$this->view->clearVars();
		//
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->currentModuleName=$this->_request->getModuleName();
		// 		$this->view->author=$this->_author->id;

		$id = (int)$this->_request->getPost("id");
		$form=$this->createForm_Verdictadd($id);

		$info=$this->_model->tickets_getInfo($id);
		if (empty($info))
		{
			if (!$this->_request->isXmlHttpRequest())  $this->_redirect($this->redirectLink);
		}
		else
		{
			// @TODO проверка правильности заполнения
			$form->populate($_POST);
			$verdict=$form->getValue("verdict");
			$this->view->res=$this->_model->tickets_verdictAdd($id,$verdict,$this->_author->id);
			// получим список заново

			$this->view->ticketlog=$this->_model->tickets_getLog($id);
			$this->view->verdicts=$this->view->render($this->_request->getControllerName().'/_verdictList.phtml');

		}
	}

	/**
	 * установка метки "неверно оформленная заявка"
	 */
	public function wrongticketAction()
	{
		if (!$this->_request->isXmlHttpRequest())  $this->_redirect($this->redirectLink);
		$ticket = (int)$this->_request->getPost("id");
		$info=$this->_model->tickets_getInfo($ticket);
		if (empty($info))
		{
			return;
		}
		else
		{
			// уже неправильная?
			if ($info["isWrong"]!=0)
			{
				// сменим
				$r=$this->_model->tickets_wrongToggle($ticket,0,0);
				$info["isWrong"]=0;
			}
			else
			{
				// поставим как неправильную
				$r=$this->_model->tickets_wrongToggle($ticket);
				$info["isWrong"]=1;
			}

			// иконки и сообщения
			if ($info["isWrong"]!=0)
			{
				$this->view->trashClass="trashFullMedium";
				$this->view->trashMsg='<span class="warningIcoSmall"></span>'.'<span class="warning">'."Заявка закрыта как неправильно оформленная"."</span>";
			}
			else
			{
				$this->view->trashClass="trashEmptyMedium";
				$this->view->trashMsg="";
			}

		}
	}

	/**
	 * привязка техники к заявке
	 */
	public function unitassignAction()
	{
		if (!$this->_request->isXmlHttpRequest())  $this->_redirect($this->redirectLink);
		$ticket = (int)$this->_request->getPost("id");
		$info=$this->_model->tickets_getInfo($ticket);
		if (empty($info))
		{
			return;
		}
		else
		{

			// если передали ID техники - значит хотим сделать привязку
			$unit = (int)$this->_request->getPost("unit");
			$u_info=$this->_model->getUnitInfo($unit);
			// 			$this->view->u_info=$u_info;
			// есть такая в базе
			if (!empty($u_info))
			{
				// привяжем
				$res=$this->_model->unitAssignTicket($unit, $ticket);
				// покажем результат
				if ($res["status"])
				{
					// обновим инфо о заявке
					$this->view->info=$this->_model->tickets_getInfo($ticket);;
					// рендер
					$this->view->assignInfo=$this->view->render($this->_request->getControllerName().'/_assignInfo.phtml');
					$this->view->msg="OK";
				}
				else
				{
					$this->view->msg="Ошибка БД.";
				}
				// 				$this->view->res=$res;

			}
			// иначе покажем форму переченя техники
			else
			{
				// группы пользователя подавшего заявку
				$groups=$this->_model->getGroup($info["author"]);
				// оборудование в группах
				$units=$this->unitsListPrepare($this->_model->getDepUnitsList($groups));
				// есть ли вообще техника в отделе?
				if (!empty($units))
				{
					$this->view->formAssign=$this->createForm_unitAssign($ticket, $units);
				}
				else
				{
					$this->view->formAssign=false;
				}
				$this->view->assignFormWrapper=$this->view->render($this->_request->getControllerName().'/_formAssignUnit.phtml');

			}

		}

	}

	public function closeAction()
	{

		if (!$this->_request->isXmlHttpRequest())  $this->_redirect($this->redirectLink);
		$ticket = (int)$this->_request->getPost("id");
		$info=$this->_model->tickets_getInfo($ticket);
		// заявка не обнаружена
		if (empty($info))
		{
			return;
		}
		// значит есть такая заявка
		else
		{
			// найдем заключения по данной заявке
			$verdicts=$this->_model->tickets_getLog($ticket);
			// если они есть и не считается "неправильно оформленной" - закрываем
			if (!empty($verdicts) && $info["isWrong"]==0)
			{
				// дата закрытия - если открыта - то FALSE, иначе открыта
				$_t=$info["closed"]==="0000-00-00 00:00:00" ? false :"0000-00-00 00:00:00";
				// применим к БД
				$r=$this->_model->tickets_close($ticket,$_t);
				// БД без ошибок?
				if ($r["status"])
				{
					$this->view->status=1;
					$this->view->msg=$_t===false ? "Заявка закрыта." : "Заявка в работе.";
				}
				else 
				{
					$this->view->status=2;
					$this->view->msg="Ошибка БД";
				}
				$this->view->msg.="\n<br>Страница перезагрузится через 2 секунды.";				
			}
			// если нет - то сообщяем что следует сделать заключение по заявке
			else
			{
				$this->view->status=-1;
				$this->view->msg="Нельзя закрыть. Заявка без заключения инженера или неверно оформлена";
			}
			// кнопка ПРОДОЛЖИТЬ чтобы обновилось все
// 			$this->view->linkCont=$this->baseLink;
// 			$butt=$this->view->render('_buttonOK.phtml');
// 			$this->view->msg.=$butt;
			$this->view->formMsg=$this->view->render('_formMsgContent.phtml');
		}

	}


	public function odtAction()
	{
		// отключить вывод
		$this->_helper->layout->disableLayout();
		$this->_helper->viewRenderer->setNoRender(true);

		$id = (int)$this->_request->getParam("id");

		// есть ли такое?
		$info=$this->_model->tickets_getInfo($id);
		if (empty($info)) $this->_redirect($this->redirectLink);
		$_d=explode(" ",$info["created"]);
		$info["created"]=$_d[0];
		// 		$info["dloaded"]=date("Y-m-d");
		$info["dloaded"]=date("d.m.Y");

		// ФИО введено в форме?
		if (!empty($info["tfio"]))
		{
			// тогда перезабьем ФИО и подраздаление указанне в БД
			$info["fio"]=$info["tfio"];
			$info["depTitle"]=$info["tdep"];
		}


		$verdicts=$this->_model->tickets_getLog($id,"DESC");
		// 		echo "<pre>".print_r($info,true)."</pre>";
		// 		echo "<pre>".print_r($verdicts,true)."</pre>";
		// 		return;

		// подготовим вердикты
		// берем тока последнй
		$verdicts=$verdicts[0];
		// 		print_r($info);
		// 		print_r($verdicts);
		// 		die();
		// 		print_r($info);
		// 		foreach ($verdicts as $key => &$v)
		// 		{
		// 			$_v=explode(" ", $v["created"]);
		// 			$v["created"]=$_v[0];
		// 			;
		// 		}

		$TBS=new Tbs_Tbs;
		$template='helpdesk_act.odt';

		$TBS->LoadTemplateUtf8($template);
		$TBS->MergeBlock('info', array($info));
		$TBS->MergeBlock('verdict', array($verdicts));
		// 		$file_name = str_replace('.','_'.date('Y-m-d').'.',$template);
		$file_name = "hlpdsk_act_".$info["id"]."_".date('Y-m-d');
		$TBS->Show(OPENTBS_DOWNLOAD, $file_name);
	}

	// форма фильтр поиск заявок
	private function createForm_filter()
	{
		// фильтр закрытые/открытые, поданы в последние N дней
		$form=new Formochki();
		$textOptions=array('class'=>'inputSmall');

		$form->setAttrib('name','filterForm');
		$form->setAttrib('id','filterForm');
		$form->setMethod('POST');
		$form->setAction($this->baseLink);

		$_state=$this->hlp->createSelectList("state",$this->states);
		$form->addElement($_state);
		$form->getElement("state")
		->setRequired(true)
		->addValidator('NotEmpty',true,array("integer","zero"))
		->addValidator("InArray",true,array(array_keys($this->states)))
		->setDescription("Статус")
		;

		$el = new Zend_Form_Element_MultiCheckbox("type", array('disableLoadDefaultDecorators' => true, 'required' => false));
		$el->addMultiOptions($this->ticketTypes);
		// для LABEL справа от CHECKBOX'a
		$el->setSeparator("&nbsp;");
		$el->setDecorators(
				array(
						'ViewHelper',
						array('Description', array('escape' => false, 'tag' => 'span')), //escape false because I want html output
						array(array('w' => 'HtmlTag'), array('tag' => 'div', 'class' => 'question-answer-variant'))
				)
		)->setDescription("Тип заявки");
		$form->addElement($el);
		$form->getElement("type")
		->setRequired(true)
		->addValidator('NotEmpty',true,array("integer","zero"))
		->setDescription("Тип заявки")
		;

		$_dayInterval=$this->hlp->createSelectList("days",$this->dayInterval);
		$form->addElement($_dayInterval);
		$form->getElement("days")
		->setRequired(true)
		->addValidator("InArray",true,array(array_keys($this->dayInterval)))
		->addValidator('NotEmpty',true,array("integer"))
		->setDescription("Подано за последние")
		;

		
		// заполним по умолчаниюя, т.к. два параметра, то можно и вручную
		$form->getElement("state")->setValue($this->filterState);
		$form->getElement("days")->setValue($this->filterDay);
		$form->getElement("type")->setValue($this->filterType);

		return $form;		;
	}

	// форма новой заявки
	private function createForm_New()
	{
		$form=new Formochki();
		$textOptions=array('class'=>'inputSmall');

		$form->setAttrib('name','newForm');
		$form->setAttrib('id','newForm');
		$form->setMethod('POST');
		$form->setAction($this->baseLink."/new");


		$form->addElement("textarea","subject",array("class"=>"longinput2"));
		$form->getElement("subject")
		->setRequired(true)
		->addValidator('NotEmpty',true)
		->setDescription("Оборудование")
		;

		$form->addElement("text","fio",array("class"=>"longinput"));
		$form->getElement("fio")
		->setRequired(true)
		->addValidator('NotEmpty',true)
		->setDescription("Ф.И.О.")
		;

		$form->addElement("text","dep",array("class"=>"longinput"));
		$form->getElement("dep")
		->setRequired(true)
		->addValidator('NotEmpty',true)
		->setDescription("Подразделение")
		;

		$form->addElement("text","place",array("class"=>"longinput"));
		$form->getElement("place")
		->setRequired(true)
		->addValidator('NotEmpty',true)
		->setDescription("Кабинет")
		;

		$_types=array("0"=>"не задано");
		$_types=$_types + $this->ticketTypes;
		$_tickettype=$this->hlp->createSelectList("typeid",$_types);
		$form->addElement($_tickettype);
		$form->getElement("typeid")
		->setRequired(true)
		->addValidator('NotEmpty',true,array("integer","zero"))
		->addValidator("InArray",true,array(array_keys($this->ticketTypes)))
		->setErrorMessages(array("0"=>"Тип заявки задан неверно"))
		->setDescription("Тип заявки")
		;

		$form->addElement("textarea","problem",array("class"=>"longinput2"));
		$form->getElement("problem")
		->setRequired(true)
		->addValidator('NotEmpty',true)
		->setDescription("признаки неисправности")
		;

		$form->addElement("button","OK",array("onclick"=>"ticketNewSend();"));
		$form->getElement("OK")->setName("Отправить");

		return $form;		;
	}

	private function createForm_unitAssign($ticket,$units)
	{
		$form=new Formochki();
		$form->setAttrib('name','assignForm');
		$form->setAttrib('id','assignForm');
		$form->setMethod('POST');
		// 		$form->setAction($this->baseLink."/verdictadd");

		$form->addElement("hidden","id");
		$form->getElement("id")
		->setRequired(true)
		->setValue($ticket)
		->addValidator("NotEmpty",true)
		->addValidator("Digits",true)
		// 		->addValidator("InArray",true,array(array_keys($deps)))
		->setDescription("Заявка");



		$_units=$this->hlp->createSelectList("unit",$units);
		$form->addElement($_units);
		$form->getElement("unit")
		->setRequired(true)
		->addValidator("InArray",true,array(array_keys($units)))
		->addValidator('NotEmpty',true,array("integer"))
		->setDescription("Единица техники")
		;


		$form->addElement("submit","OK",array("class"=>"button"));
		$form->getElement("OK")->setName("Применить");

		return $form;		;
	}

	// форма "заключение инженера"
	private function createForm_Verdictadd($id)
	{
		$form=new Formochki();
		$textOptions=array('class'=>'inputSmall');

		$form->setAttrib('name','verdictForm');
		$form->setAttrib('id','verdictForm');
		$form->setMethod('POST');
		$form->setAction($this->baseLink."/verdictadd");

		$form->addElement("hidden","id");
		$form->getElement("id")
		->setRequired(true)
		->setValue($id)
		->addValidator("NotEmpty",true)
		->addValidator("Digits",true)
		// 		->addValidator("InArray",true,array(array_keys($deps)))
		->setDescription("Заявка");

		$form->addElement("textarea","verdict",array("class"=>"longinput2"));
		$form->getElement("verdict")
		->setRequired(true)
		->addValidator('NotEmpty',true)
		->addValidator("Alnum",true,array('allowWhiteSpace' => true))
		->setDescription("Коментарий специалиста")
		;

		$form->addElement("submit","OK",array("class"=>"button"));
		$form->getElement("OK")->setName("Добавить");

		return $form;		;
	}


	private function sessionUpdate($params)
	{
		$this->session->days=$params["days"];
		$this->session->state=$params["state"];
		$this->session->type=$params["type"];
		$this->filterDay=$params["days"];
		$this->filterState=$params["state"];
		$this->filterType=$params["type"];

	}

	/**
	 * подготовка массива к списку
	 * @param array $list
	 * @return array
	 */
	private function unitsListPrepare($list)
	{
		foreach ($list as $key => $info)
		{
			$result[$info["depTitle"]][$info["id"]]=$info["typeTitle"].". Инв. № ".$info["inumb"];
		}
		return $result
		;
	}



}