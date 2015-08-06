<?php
/**
 * Настройка приемной кампании
 * @author zlydden
 *
 */
class Abiturient_ParamsController extends Zend_Controller_Action
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
	private $currentYear;

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
		// 		$ajaxContext ->addActionContext('formchanged', 'json')->initContext('json');

		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/abiturs.js');
		//		Zend_Controller_Action_HelperBroker::addPrefix('My_Helper');
		$this->_author=Zend_Auth::getInstance()->getIdentity();


	}

	public function indexAction ()
	{
// 		echo "<pre>".$this->_request->getBaseUrl()."</pre>";
// 		echo "<pre>".$this->baseLink."</pre>";
		$campaigns=array_keys($this->data->getCampaignList());
		$campaigns=array_combine($campaigns,$campaigns);
		$form=$this->createForm_ChangeYear($campaigns,$this->currentYear);
		if ($this->_request->isPost())
		{
			$params = $this->_request->getParams();
			$chk=$form->isValid($params);
			if ($chk) $this->sessionUpdate($form->getValues());
			else $this->sessionUpdate($this->getFormChangeDefaults());
		}
		$criteria=$this->buildCriteria();
		$form->setDefaults($criteria);

		$this->view->formChange=$form;
		$this->view->formAdd=$this->createForm_AddYear();
		$spec=$this->data->getSpecs();
		$this->view->formDigitAdd=$this->createForm_DigitsAdd($specs, $divisions, $payments);
		return;


	}

	public function yearaddAction()
	{
		if (!$this->_request->isPost()) $this->_redirect($this->redirectLink);
		$form=$this->createForm_AddYear();
		$params = $this->_request->getParams();
		$chk=$form->isValid($params);
		if ($chk) {
			// создадим кампанию
			$res=$this->data->params_createCampaign($params["year"]);
// 			echo "<pre>".print_r($res,true)."</pre>";
			// @TODO проверка на правильность выполнения и вывод ошибки если что
					
			if (method_exists($res, "errorInfo"))
			{
				$this->_redirect($this->redirectLink);
			}
			else 
				$this->view->msg="Ошибка БД: ".$res;
			
		}
		else {
			// неверный год кампании
			$this->view->msg="НЕверно указан год кампании";
		}
		
	}

	public function yearchangeAction()
	{
		if (!$this->_request->isPost()) $this->_redirect($this->redirectLink);
		$campaigns=array_keys($this->data->getCampaignList());
		$campaigns=array_combine($campaigns,$campaigns);
		// 		// узнаем что к нам пришло
		$form=$this->createForm_ChangeYear($campaigns,$this->currentYear);
		$params = $this->_request->getParams();
		$chk=$form->isValid($params);
		if ($chk) {
			// устанновим новый год для кампании
			$res=$this->data->params_setCurrentCampaign($params["year"]);
			if ($res["status"]) $this->_redirect($this->redirectLink);
			else $this->view->msg="Ошибка БД: ".$res["errorMsg"];
		}
		else {
			// неверный год кампании
			$this->view->msg="НЕверно указан год кампании";
		}

	}


	private function createForm_ChangeYear($campaigns,$currentYear)
	{
		$form=new Formochki();
		$form->setAttrib('name','changeYearForm');
		$form->setAttrib('id','changeYearForm');
		$form->setMethod('POST');
		$form->setAction($this->view->baseUrl
				.'/'.$this->view->currentModuleName
				.'/'.$this->_request->getControllerName()
				.'/yearchange'
		);
		$textOptions=array('class'=>'typic_input');
		$_list=$this->hlp->createSelectList("year",$campaigns);
		$form->addElement($_list);
		$form->getElement("year")
		->addValidator("NotEmpty",true)
		->addValidator("Digits",true)
		->addValidator("InArray",true,array(array_keys($campaigns)))
		->addValidator('Date',true,array('locale' => 'ru','format'=>'yyyy'))
		->setDescription("Год приемной кампании")
		;

		$form->addElement("submit","OK",array(
				"class"=>"apply_text"
		));
		$form->getElement("OK")->setName("Сменить");
		return $form;
		;
	}

	private function createForm_AddYear()
	{
		$form=new Formochki();
		$form->setAttrib('name','addYearForm');
		$form->setAttrib('id','addYearForm');
		$form->setMethod('POST');
		$form->setAction($this->view->baseUrl
				.'/'.$this->view->currentModuleName
				.'/'.$this->_request->getControllerName()
				.'/yearadd'
		);
		$textOptions=array('class'=>'typic_input');
		$form->addElement("text","year",$textOptions);
		$form->getElement("year")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		->addValidator("Digits",true)
		->addValidator('Date',true,array('locale' => 'ru','format'=>'yyyy'))
		->setDescription("Год приемной кампании")
		;

		$form->addElement("submit","OK",array(
				"class"=>"apply_text"
		));
		$form->getElement("OK")->setName("Создать");
		return $form;
		;
	}

	private function createForm_DigitsAdd($specs,$divisions,$payments,$subspecs=array())
	{
		$form=new Formochki();
		$form->setAttrib('name','addDigitForm');
		$form->setAttrib('id','addDigitForm');
		$form->setMethod('POST');
		$form->setAction($this->view->baseUrl
				.'/'.$this->view->currentModuleName
				.'/'.$this->_request->getControllerName()
				.'/digitadd'
		);
		$form->addElement("text","digit",array("class"=>"inputSmall"));
		$form->getElement("digit")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		->addValidator("Int",true,array('locale'=>"ru"))
		->setDescription("Количество мест")
		;

		$form->addElement("submit","OK",array(
				"class"=>"apply_text"
		));
		$form->getElement("OK")->setName("Создать");
		return $form;
		;
	}


	/** строит критериый поиска из массива или из данных сессии
	 * @return array
	 */
	private function buildCriteria($in=null)
	{
		if (is_null($in)) $in=$this->session->getIterator();
		$defaults=$this->getFormChangeDefaults();
		$criteria['year']=isset($in['year'])
		?	$in['year']
		:	$defaults['year']
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
				case "year":
					$_value=(int)$value;
					break;

				default:
					$_value=$value;
					break;
			}
			$this->session->$param=$_value;
		}
	}

	private function getFormChangeDefaults()
	{
		$result=array(
				"year"=>$this->currentYear,
		);
		return $result;
	}


}