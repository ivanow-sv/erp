<?php
Zend_Loader::loadClass('My_Spravtypic');

class Sprav_GosparamsController extends My_Spravtypic
{
	private $table='gos_params';	 	// основная таблица
	// 	private $tblSpecs='specs'; 			// таблица направлений подготовки
	private $tblDivs='division'; 		// таблица отделений
	private $tblFacs='facult'; 		// таблица факультетов
	private $title='Справочник: Параметры ГОСов';
	private $hlp; 						// помощник действий Typic
	private		$session;
	private 	$criteria; // критерии фильтра
	private		$filter; // фильтр Zend_Form

	private $specs;			// перечень направлений подготовки
	private $divisions;		//перечень отделений

	private $selfLink;

	function init()
	{
		parent::setTable($this->table);
		parent::setTitle($this->title);
		parent::init();
		$this->hlp=$this->_helper->getHelper('Typic');
		Zend_Loader::loadClass('Zend_Form');
		Zend_Loader::loadClass('Formochki');
		Zend_Loader::loadClass('Zend_Session');
		$this->session=new Zend_Session_Namespace('my');
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		$ajaxContext ->addActionContext('formchanged', 'json')->initContext('json');

		$this->divisions=$this->data->getListForSelectList($this->tblDivs);
		$this->specs=$this->data->getSpecsForSelectList();
		$this->selfLink=$this->_request->getBaseUrl()."/".$this->redirectLink;
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/sprav.js');
	}

	public function indexAction()
	{

		$this->filter=$this->createForm_Filter();
		$formData = $this->_request->getPost('formData');
		if ($this->_request->isPost())
		{
			// обновим сессию
			$this->sessionUpdate($formData );
		}
		$this->buildCriteria();
		$this->view->formFilter=$this->filter;

		// данные для списка
		$this->view->entries = $this->data->gosparamsList($this->criteria["spec"]);
		$form=$this->createForm_edit();
		$this->view->formEdit=$form;

	}

	public function formchangedAction()
	{
		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->selfLink);
		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->selfLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->selfLink= $this->_request->getBaseUrl()."/".$this->redirectLink;

		$this->filter=$this->createForm_Filter();
		$formData = $this->_request->getPost('formData');
		$this->sessionUpdate($formData );
		$this->buildCriteria();
		$this->view->entries=$this->data->gosparamsList($this->criteria["spec"]);
		$out["list"]=$this->view->render($this->_request->getControllerName().'/_list.phtml');
		$this->view->out=$out;

	}

	public function addAction()
	{
		$this->view->title = $this->view->title. ' - Добавлена запись';

		if ($this->_request->isPost()) {
			$form=$this->createForm_edit();
			if ($form->isValid($_POST))
			{
				$values=$form->getValues();
				
				// год окончания больше года начала
				// а если не так, значит конечная дата открыта и установить в NULL
				if (!isset($values["yearLast"]) || $values["yearStart"]>=$values["yearLast"] ) $values["yearLast"]=null;
					$data=$values;
					unset($data["id"]);
						
					$this->data->otherAdd($data);
					$this->view->err="OK";
					// 				}
					// 				else
						// 				{
					// 					$this->view->err="Ошибка";
					// 					$this->view->m=array("Некорретность"=> array("Год окончания меньше года начала"));
					// 				}

			}
			else
			{
				$this->view->err="Ошибка";
				$this->view->m=$form->getMessages();
			}

		}

	}

	public function editAction()
	{
		$this->view->title = $this->view->title. ' - Изменения записи';
		if ($this->_request->isPost()) {
			$form=$this->createForm_edit();
			if ($form->isValid($_POST))
			{
				$values=$form->getValues();
				if ( $values["id"]>0)
				{
					// год окончания больше года начала
					// а если не так, значит конечная дата открыта и установить в NULL
								
					if (!isset($values["yearLast"]) || $values["yearStart"]>=$values["yearLast"] ) $values["yearLast"]=null;
					$data=$values;
					unset($data["id"]);
					$this->data->otherChange($values["id"], $data);
					$this->view->err="OK";
				}
				else
				{
					$values["yearLast"]=0;
					$this->view->err="Ошибка";
					$this->view->m=array("Некорретность"=> array("Недостаточно данных"));
				}

			}
			else
			{
				$this->view->err="Ошибка";
				$this->view->m=$form->getMessages();
			}


		}
		// 		$this->_redirect($this->redirectLink);

	}

	public function delAction()
	{
		parent::delAction();
	}

	/**
	 * @FIXME добавить валидатора по массиву $this->specs
	 * @return Formochki
	 */
	private function createForm_Filter()
	{
		// фильтр по курсу, спеец., отделению, форме обучения, дисциплине, группе
		$form=new Formochki();
		$textOptions=array('class'=>'inputSmall');

		$form->setAttrib('name','filterForm');
		$form->setAttrib('id','filterForm');
		$form->setMethod('POST');
		$form->setAction($this->selfLink);

		// 		$divList=$this->hlp->createSelectList("division",$this->divisions);
		// 		$form->addElement($divList);
		// 		$form->getElement("division")
		// 		//		->s
		// 		//		->setDescription("Отделение")
		// 		->addValidator("NotEmpty",true)
		// 		->addValidator("digits",true);

		$specsList=$this->hlp->createSelectList("spec",$this->specs);
		$form->addElement($specsList);
		$form->getElement("spec")
		//		->setDescription("Направление подготовки")
		->addValidator("NotEmpty",true)
		->addValidator("Digits",true);
		// 		->addValidator("InArray",true,array_keys($this->specs));
		// 		print_r(array_keys($this->specs));

		// красивая кнопка-картинка "ИСКАТЬ"
		//		$form->addElement('submit','OK',array('title'=>'ИСКАТЬ','class'=>"search_button_large"));
		//		$form->getElement('OK')->setValue('');


		return $form;

	}
	/**
	 * @FIXME добавить валидатора по массиву $this->specs
	 * @return Formochki
	 */
	private function createForm_edit()
	{
		$form=new Formochki();
		$form->setAttrib('name','editForm');
		$form->setAttrib('id','editForm');
		$textOptions=array('class'=>'inputSmall');

		$form->setMethod('POST');
		$form->setAction($this->selfLink."/"."edit");

		$form->addElement("hidden","id");
		$form->getElement("id")
// 		->setRequired(true);
		->addValidator('NotEmpty');

		$form->addElement("hidden","spec");
		$form->getElement("spec")
		->setValue($this->criteria["spec"])
		->setRequired(true)
		->addValidator('NotEmpty');

		$divs=$this->hlp->createSelectList("division",$this->divisions);
		$form->addElement($divs);
		$form->getElement("division")
		->setRequired(true)
		->addValidator('NotEmpty');

		$facList=$this->data->getListForSelectList($this->tblFacs);
		$facults=$this->hlp->createSelectList("facult",$facList);
		$form->addElement($facults);
		$form->getElement("facult")
		->setRequired(true)
		->addValidator('NotEmpty');

		$form->addElement("text","years2study",$textOptions);
		$form->getElement("years2study")
		->setDescription("Срок обучения")
		->setRequired(true)
		->addValidator('NotEmpty',true)
		->addValidator('Digits',true)
		->addValidator("GreaterThan",true,array("min"=>0))
		;

		$form->addElement("text","yearStart",array("class"=>"inputSmall2"));
		$form->getElement("yearStart")
		->setRequired(true)
		->addValidator('NotEmpty')
		->addValidator('Date',true,array('locale' => 'ru','format'=>'yyyy'))
		->addValidator("GreaterThan",true,array("min"=>1900));
		;

		$form->addElement("text","yearLast",array("class"=>"inputSmall2"));
		$form->getElement("yearLast")
// 		->setRequired(true)
// 		->addValidator('NotEmpty')
// 		->addValidator('Date',true,array('locale' => 'ru','format'=>'yyyy'))
// 		->addValidator("GreaterThan",true,array("min"=>1999));
		;

		$form->addElement("submit","OK",array("class"=>"apply_text"));
		$form->getElement("OK")->setName("ПРИМЕНИТЬ");


		return $form;
		;
	}

	/**
	 * Обновляет данные в сессии
	 */
	private function sessionUpdate($params)
	{
		// получим данные из запроса

		foreach ($params as $p=>$value)
		{
			$this->session->$p=$value;
			;
		}
	}


	private function buildCriteria()
	{
		$_params=$this->session->getIterator();
		//		$this->filter->populate($_params);
		//		;
		$validValues=$this->filter->getValidValues($_params);
		$values=$this->filter->getValues();
		// если правильных значений меньше, то заполнить значениями по умолчанию
		//		$diff=array_diff_key($values, $validValues);
		//		if (count($diff)>0)
		//		{
		//			foreach ($_params as $key)
		foreach ($values as $key=>$val)
		{
			switch ($key)
			{

				case "spec":
					$_spec=$this->filter->getElement("spec")->getMultiOptions();

					$_spec=$this->hlp->getFirstElem($_spec);
					//						echo "<pre>".print_r ($_spec,true)."</pre>";
					//						die();
					// @FIXME - вытащить первое значение
					$validValues["spec"]=isset($validValues[$key])
					? $validValues[$key]
					: key($_spec);
					break;

					// в остальном - убрать лишнее
				default:
					unset($validValues[$key]);
					break;
			};
		}
		//		}
		$this->criteria=$validValues;
	}

}