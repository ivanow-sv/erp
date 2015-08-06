<?php
/**
 * Передача дел в деканат
 * @author zlydden
 *
 */
class Abiturient_TransferController extends Zend_Controller_Action
{
	protected 	$session;
	protected 	$baseLink; // ссылка на модуль-контроллер начиная от базы
	protected	$redirectLink; // ссылка в этот модуль/контроллер
	protected	$studyYear_now;
	private 	$data;
	private 	$specDefault;
	private 	$osnovs;
	private $hlp; // помощник действий Typic
	private $_author; // пользователь шо щаз залогинен
	// @FIXME откуда-то брать это значение года
	private $currentYear; // текущий год кампании

	public function init()
	{
		Zend_Loader::loadClass('Zend_Session');
		Zend_Loader::loadClass('Zend_Form');
		Zend_Loader::loadClass('Formochki');
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		Zend_Loader::loadClass('Abiturs');

		$this->data=new Abiturs();
		$this->currentYear=$this->data->getYear();


		// выясним название текущего модуля для путей в ссылках
		$currentModule=$this->_request->getModuleName();
		$this->view->currentModuleName=$currentModule;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->currentController = $this->_request->getControllerName();
		$this->baseLink=$this->_request->getBaseUrl()."/".$currentModule."/".$this->_request->getControllerName();
		$this->view->baseLink=$this->baseLink;
		$this->redirectLink=$this->_request->getModuleName()."/".$this->_request->getControllerName();
		$this->hlp=$this->_helper->getHelper('Typic');
		$moduleTitle=Zend_Registry::get("ModuleTitle");
		$modContrTitle=Zend_Registry::get("ModuleControllerTitle");
		$this->view->title=$moduleTitle
		.". ".$modContrTitle.'. ';
		$this->view->addHelperPath('./application/views/helpers/','My_View_Helper');
		$this->session=new Zend_Session_Namespace('my');
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		$ajaxContext ->addActionContext('formchanged', 'json')->initContext('json');

		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/abiturs.js');
		//		Zend_Controller_Action_HelperBroker::addPrefix('My_Helper');
		$this->_author=Zend_Auth::getInstance()->getIdentity();


	}

	// 1. показать специальности - вып. список
	// 2. показать список приказов
	// 2.1. показать список протоколов
	// 3. показать новичков, зачисленных протоколом
	// 4. выбрать людей
	// 5. выбрать приказ
	// 6. нажать "передать"
	// 7. транзакция:
	// 7.1 создать записи в STUDENTS с подгруппой NULL
	// 7.2. указать в PERSONPROCESS документ, автора, дату, и пользователя



	public function indexAction ()
	{
		$aa=array("0"=>"любое значение");

		$prots=$this->data->getProtList();
		$specs=$this->data->getSpecs();
		$specs=$aa+$specs;
		$prots=$aa+$prots;
		//		array_unshift($prots,"выбрать");
		$formFilter=$this->createForm_Filter($prots,$specs);
		if ($this->_request->isPost())
		{
			$params = $this->_request->getParams();
			$chk=$formFilter->isValid($params);
			if ($chk) $this->sessionUpdate($formFilter->getValues());
			else $this->sessionUpdate($this->getFormFilterDefaults());
		}
		$criteria=$this->buildCriteria();
		$formFilter->setDefaults($criteria);

		$list=$this->data->getNewbies($criteria["prot_id"],$criteria["spec"]);
		$this->view->list=$list;

		$this->view->formFilter=$formFilter;
		$this->view->formOrders=$this->createForm_OrdersList();
		return;


	}

	public function formchangedAction()
	{
		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->redirectLink);

		//		if (!$this->_request->isPost()) $this->_redirect($this->redirectLink);
		// очистим вывод
		unset ($out);
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		// узнаем что к нам пришло
		$formData = $this->_request->getPost('formData');
		// обновим сессию
		$this->sessionUpdate($formData );
		//		$this->view->a3=$this->session->allfacult;
		$criteria=$this->buildCriteria();
		$list=$this->data->getNewbies($criteria["prot_id"],$criteria["spec"]);
		$this->view->list=$list;

		$this->view->formOrders=$this->createForm_OrdersList();

		$out["abiturList"]=$this->view->render(
		$this->_request->getControllerName().'/_abiturList.phtml'
		);

		$this->view->out=$out;

	}

	// 7. транзакции:
	// 7.1 создать записи в STUDENTS с подгруппой NULL
	// 7.2. указать в PERSONPROCESS документ, автора, дату, и пользователя
	/**
	* абитур -> студент, запись в журнале с указанием приказа
	*/
	public function applyorderAction()
	{
		if (!$this->_request->isPost()) $this->_redirect($this->redirectLink);
		// получим данные
		$params = $this->_request->getParams();
		$docid=(int)$params["docid"];
		$ids=$this->hlp->arrayINTEGER($params["userid"]);
		unset($params);
		$msg=array();
		// проверим их
		if ($docid<1 || count($ids)<1)
		{
			$msg[]="Неверно задан приказ или абитуриенты";
		}
		$docInfo=$this->data->getOrderInfo($docid);
		if (empty($docInfo))
		{
			$msg[]="Нет подходящего приказа";
		}
		// данные годны
		else
		{
			$info=$this->data->getUserInfo_pack($ids);
//			echo "<pre>".print_r($info,true)."</pre>";
			// обработаем
			$aff=0;
			foreach ($info as $userinfo)
			{
				$userinfo["zach"]=$this->currentYear."-".$userinfo["regNo"];
				$res=$this->data->applyorder($userinfo,$docid,$this->_author->id);
				if ($res===true)
				{
					$aff++;
					
				}
				else 
				{
					$_info="Рег.№".$userinfo["regNo"]." ".$userinfo["family"]." ".$userinfo["name"]." ".$userinfo["otch"];
					$msg[]="Ошибка. ".$_info." - ".$res."";
				}
			}
		}
		$this->view->msg=$msg;
		$this->view->aff=$aff;
		$this->view->req=count($ids);
		

	}

	private function createForm_Filter($prots,$specs)
	{
		$form=new Formochki();
		$form->setAttrib('name','filterForm');
		$form->setAttrib('id','filterForm');
		$form->setMethod('POST');
		$form->setAction($this->view->baseUrl.'/'.$this->view->currentModuleName.'/'.$this->_request->getControllerName());
		$textOptions=array('class'=>'typic_input');
		$prot_list=$this->hlp->createSelectList("prot_id",$prots);
		$specsList=$this->hlp->createSelectList("spec",$specs);
		$form->addElement($prot_list);
		$form->getElement("prot_id")
		->addValidator("Digits")
		->setDescription("Номер протокола комисии")
		;
		$form->addElement($specsList);
		$form->getElement("spec")
		->addValidator("Digits")
		->setDescription("Специальность")
		;
		$form->addElement("submit","OK",array(
		"class"=>"apply_text"		
		));
		$form->getElement("OK")->setName("Искать");
		return $form;
		;
	}

	private function createForm_OrdersList()
	{
		$orders=$this->data->getOrders();
		$form=new Formochki();
		$form->setAttrib('name','orderForm');
		$form->setAttrib('id','orderForm');
		$form->setMethod('POST');
		$form->setAction($this->view->baseUrl.'/'
		.$this->_request->getModuleName().'/'
		.$this->_request->getControllerName()."/"
		."applyorder"
		);
		$list= $this->hlp->createSelectList("docid",$orders);
		$form->addElement($list);
		$form->addElement("hidden","spec");
		$form->addElement("hidden","prot_id");
		$form->getElement("prot_id")
		->addValidator("Digits")
		->setDescription("Номер протокола комисии")
		;
		$form->getElement("spec")
		->addValidator("Digits")
		->setDescription("Специальность")
		;
		$form->getElement("docid")
		->addValidator("Digits")
		->setDescription("Приказ")
		;

		return $form;
	}

	/** строит критериый поиска из массива или из данных сессии
	 * @return array
	 */
	private function buildCriteria($in=null)
	{
		if (is_null($in)) $in=$this->session->getIterator();
		$defaults=$this->getFormFilterDefaults();
		$criteria['prot_id']=isset($in['prot_id'])
		?	$in['prot_id']
		:	$defaults['prot_id']
		;
		$criteria['docid']=isset($in['docid'])
		?	$in['docid']
		:	$defaults['docid']
		;
		$criteria['spec']=isset($in['spec'])
		?	$in['spec']
		:	$defaults['spec']
		;
		return $criteria;
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
				case "prot_id":
				case "docid":
				case "spec":
					$_value=(int)$value;
					break;

				default:
					$_value=$value;
					break;
			}
			$this->session->$param=$_value;
		}
	}

	private function getFormFilterDefaults()
	{
		$result=array(
		"prot_id"=>0,
		"docid"=>0,
		"spec"=>0 
		);
		return $result;
	}


}