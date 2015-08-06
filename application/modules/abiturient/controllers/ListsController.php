<?php
class Abiturient_ListsController extends Zend_Controller_Action
{
	protected 	$session;
	protected 	$baseLink;
	protected	$redirectLink; // ссылка в этот модуль/контроллер
	private 	$_model;
	private 	$hlp; // помощник действий Typic
	private 	$_author; // пользователь шо щаз залогинен
	private		$cfgfile;
	private		$cfg; 		// конфиг для полей и условий  [key] => Array(column,title,join,where)
	private		$select_begin="";
	private 	$UserLists;
	private		$cond_signs=array(
			0	=>	"=",
			1	=>	"<>",
			2	=>	">",
			3	=>	">=",
			4	=>	"<",
			5	=>	"<=");


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
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/abiturs.js');

		$this->hlp=$this->_helper->getHelper('Typic');
		Zend_Loader::loadClass('Abiturs');
		$this->_model=new Abiturs();
		Zend_Loader::loadClass('Zend_Session');
		Zend_Loader::loadClass('Zend_Form');
		Zend_Loader::loadClass('Formochki');
		$this->session=new Zend_Session_Namespace('my');
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		$ajaxContext ->addActionContext('formchanged', 'json')->initContext('json');
		$this->_author=Zend_Auth::getInstance()->getIdentity();
		$this->cfgfile=APPLICATION_PATH . '/configs/'."abitur_lists.csv";
		$this->knowcfg();
		// 		$this->select_begin=""
		$this->UserLists=$this->_model->lists_getFilters($this->_author->id);

	}

	public function indexAction ()
	{
		$changeFilter=$this->createForm_filter($this->UserLists);
		if ($this->_request->isPost())
		{
			$params = $this->_request->getParams();
			$chk=$changeFilter->isValid($params);
			if ($chk) $this->sessionUpdate($changeFilter->getValues());
			else $this->sessionUpdate($this->getFormChangeDefaults());
		}
		$criteria=$this->buildCriteria();
		$changeFilter->setDefaults($criteria);

		$this->view->formNew=$this->createForm_new();
		$vals=$this->createForm_valuestitles();
		$this->view->valz=$vals;
		
		$list_params=$this->_model->lists_getFilterParams($criteria["listid"]);
// 		echo "<pre>".print_r($list_params,true)."</pre>";
		$p=$this->listParamsPrepare($list_params);
		// 
		$abiturs=$this->_model->lists_getAbiturs($p["cols"],$p["joins"],$p["wheres"]);
		$this->view->abiturs=$abiturs;
		$this->view->hdrs=$p["hdrs"];

		$this->view->formEdit=$this->createForm_edit($criteria["listid"], $list_params,clone $vals);
		$this->view->formFilter=$changeFilter;
	}


	public function formchangedAction()
	{
		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->redirectLink);

		// очистим вывод
		unset ($out);
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		// узнаем что к нам пришло
		$formData = $this->_request->getPost('formData');
		// обновим сессию
		$this->sessionUpdate($formData );
		$criteria=$this->buildCriteria();

		$list_params=$this->_model->lists_getFilterParams($criteria["listid"]);
		$p=$this->listParamsPrepare($list_params);
		$abiturs=$this->_model->lists_getAbiturs($p["cols"],$p["joins"],$p["wheres"]);
		$this->view->abiturs=$abiturs;
		$this->view->hdrs=$p["hdrs"];
		$out["abitursList"]=$this->view->render(
				$this->_request->getControllerName().'/_abitursList.phtml'
		);

		$this->view->formNew=$this->createForm_new();
		$vals=$this->createForm_valuestitles();
		$this->view->valz=$vals;
		$this->view->formEdit=$this->createForm_edit($criteria["listid"], $list_params,clone $vals);
		
// 		$this->view->formNew=$this->createForm_new();
		$out["formEditWrapper"]=$this->view->render(
				$this->_request->getControllerName().'/_formEdit.phtml'
		);
		
		$this->view->out=$out;

	}

	public function editAction()
	{
// 		$logger=Zend_Registry::get("logger");
		if (!$this->_request->isPost()) $this->_redirect($this->redirectLink);
		$form=$this->createForm_new();
		$params = $this->_request->getParams();
		$chk=$form->isValid($params);
		// данные формы
		$params=$form->getValues();
// 		$logger->log($params,Zend_Log::ALERT);
		// эталонные значения из БД
		$valz=$this->createForm_valuestitles();
		// @TODO как-то в отдельную функцию		
		// проверим в условиях наличие направления подготовки и отделение
		$chk_s=array_search("spec", $params["cond"]);
		$chk_d=array_search("division", $params["cond"]);
		// отделение и (напр. или профиль) обязательны, если их нет? то добавить
		if ($chk_d===false) 
		{
			$params["cond"][]="division";
			$params["sign"][]=0;
			// первый ключ
			$_v=$valz->getElement("division_val")->options;
			$params["cond_val"][]=key($_v);			
		}
		if ($chk_s===false) 
		{
			$params["cond"][]="spec";
			$params["sign"][]=0;
			// первый ключ
			$_v=$valz->getElement("spec_val")->options;
			$params["cond_val"][]=key($_v);				
		}
// 		$logger->log($params,Zend_Log::INFO);
		if (count($params["cond"])>=1 && $chk)
		{
			$cond=array();
			$sign=array();
			$cond_value=array();
			$i=1;
			foreach ($params["cond"] as $key => $value)
			{
				// пары условия-значение - отсеять где значение меньше 0
				// @TODO отрегулировать отрицательное значение считающимся неверным
				if ($params["cond_val"][$key] <0 ) continue;
				// есть ли данное значение из "эталонных"
				$_tmpl=array_keys($valz->getElement($value."_val")->options);
// 				$logger->log(in_array($params["cond_val"][$key], $_tmpl),Zend_Log::WARN);;
				// пропускаем если нет
				if (!in_array($params["cond_val"][$key], $_tmpl)) continue;
				$cond[$i]=$params["cond"][$key];
				// знаки - получить из $this->cond_signs;
				$sign[$i]=$this->cond_signs[$params["sign"][$key]];
				$cond_value[$i]=$params["cond_val"][$key];
				$i++;
			}
			// правка существующего 
			if ($params["listid"]>0)
			{
				$res=$this->_model->lists_updateFilter($params["listid"],$params["listname"],$params["col"],$cond,$sign,$cond_value);
			}
			// добавим новый
			else $res=$this->_model->lists_addFilter($this->_author->id,$params["listname"],$params["col"],$cond,$sign,$cond_value);
			// ошибка БД
			if ($res["status"]==true) 
			{
				$this->sessionUpdate(array("listid"=>$res["id"]));
				$this->_redirect($this->redirectLink);				
			}
			else echo $res["mgs"];

		}
		// данные неверные
		else
		{
			// 			$this->view->formNew=$form;
			// 			$this->view->formVal=$this->createForm_valuestitles();
		}

	}


	/**
	 * получение конфига полей и условий
	 */
	private function knowcfg()
	{
		$row=1;
		$handler=fopen($this->cfgfile,"r");

		if (($handle = fopen($this->cfgfile, "r")) !== FALSE)
		{

			while (($data = fgetcsv($handle, 1000, ";")) !== FALSE)
			{
				// первая строка пропускается
				if ($row!=1)
				{
					$this->cfg[$data[0]]["col"]=$data[2];
					$this->cfg[$data[0]]["tit_table"]=$data[1];
					$this->cfg[$data[0]]["tit"]=$data[3];
					// TABLE AS ALIAS ON CONTIDION
					$j=explode(" ",$data[4]);
					$this->cfg[$data[0]]["join"]["tbl"]=$j[0]; 	// TABLE
					$this->cfg[$data[0]]["join"]["alias"]=$j[2];// ALIAS
					$this->cfg[$data[0]]["join"]["cond"]=$j[4]; // CONDITION
					$this->cfg[$data[0]]["where"]=$data[5];
				}
				$row++;
			}
			fclose($handle);
		}

	}


	private function createForm_filter($_fltUser)
	{
		// фильтр закрытые/открытые, поданы в последние N дней
		$form=new Formochki();
		$textOptions=array('class'=>'inputSmall');

		$form->setAttrib('name','filterForm');
		$form->setAttrib('id','filterForm');
		$form->setMethod('POST');
		$form->setAction($this->baseLink);

		$listid=$this->hlp->createSelectList("listid",$_fltUser);
		$form->addElement($listid);
		$form->getElement("listid")
		->setRequired(true)
		->addValidator('NotEmpty',true,array("integer","zero"))
		->addValidator("InArray",true,array(array_keys($_fltUser)))
		->setDescription("Настроенный список")
		;
		return $form;		;
	}

	/**
	 * списки справочных параметров и их названий
	 * @return array of Zend_Form_Element
	 */
	private function createForm_valuestitles()
	{
		$form=new Formochki();
		$form->setAttrib('name','valz');
		$form->setAttrib('id','valz');
		$form->setMethod('POST');
		
		$elems=array();
		foreach ($this->cfg as $key => $value)
		{
			// если должно сравниваться с БД - узнаем возможные значение и создадим элемент
			if (!empty($value["tit_table"]))
			{
				// для направлений подготовки и профиля отдельный подход
				switch ($key) 
				{
					case "spec":
					$_list=$this->_model->getSpecsTreeCampaign();
					break;

					case "subspec":
						$_list=$this->_model->getSubSpecsTreeCampaign();
					break;
					
					default:
						$_list=$this->_model->sprav_getList($value["tit_table"]);
						
					break;
				}
				$_elem=$this->hlp->createSelectList($key."_val",$_list);
				$_elem->setOptions(array("isArray"=>true))
				->setAttrib("class", "hidden");
				$elems[]=$_elem;
				
			}
		}
		$form->addElements($elems);
		return $form;
	}

	private function createForm_edit($listid, $params,$valz)
	{
		$form=new Formochki();
		$form->setAttrib('name','formEdit');
		$form->setAttrib('id','formEdit');
		$form->setMethod('POST');
		$form->setAction($this->baseLink."/edit");
		$textOptions=array('class'=>'medinput');
		$form->addElement("hidden","listid",array("value"=>$listid));
		$_listname=empty($params["info"]["title"])?"Новый":$params["info"]["title"];
		$form->addElement("text","listname",array("class"=>"medinput","value"=>$_listname));

		// узнаем имена колонок из того что задано в БД
		$cols		= array_map( function($row) {
			return $row['keyname'];
		}, $params["cols"] );
		// узнаем имена условий из того что задано в БД
		$conds		= array_map( function($row) {
			return $row['keyname'];
		}, $params["cond"] );
		// узнаем ID знаков условий из того что задано в БД
		$signs		= array_map( function($row) {
			return $row['cond'];
		}, $params["cond"] );
		// узнаем значения условий из того что задано в БД		
		$cond_vals	= array_map( function($row) {
			return $row['cond_val'];
		}, $params["cond"] );
// 		print_r($conds);
		// названия по ключам 
		$list=array();
		foreach ($this->cfg as $key => $value)
		{
			$list[$key]=$value["tit"];
		}
		// добавим элементы - колонки и зададим значения
		foreach ($cols as $i=>$v)
		{
			$item=$this->hlp->createSelectList("col".$i,$list);
			$form->addElement($item);
			$form->getElement("col".$i)
			->setName("col")
			->setValue($v)
			->setIsArray(true)
			->addValidator("InArray",true,array(array_keys($this->cfg)))
			->addValidator('NotEmpty',true)
			;
		}
		
		// проверим в условиях наличие направления подготовки и отделение
		$chk_s=array_search("spec", $conds);
		$chk_d=array_search("division", $conds);
		// отделение и (напр. или профиль) обязательны, если их нет? то добавить
		if ($chk_d===false) $conds[]="division";		
		if ($chk_s===false) $conds[]="spec";		
		// элементы-условия, знаки и значения
		foreach ($conds as $i=>$v)
		{
			$item=$this->hlp->createSelectList("cond".$i,$list);
			$form->addElement($item);
			$form->getElement("cond".$i)
			->setName("cond")
			->setValue($v)
			->setIsArray(true)
			->setAttrib("onChange", "filters_CondValues($(this));")
			->addValidator("InArray",true,array(array_keys($this->cfg)))
			->addValidator('NotEmpty',true)
			;
			
			$item=$this->hlp->createSelectList("sign".$i,$this->cond_signs);
			$form->addElement($item);
			$form->getElement("sign".$i)
			->setName("sign")
			->setIsArray(true)
			->setValue(array_search($signs[$i], $this->cond_signs))
			->addValidator('NotEmpty',true,array("integer","zero"))
			;

			// выпадающий список по имени ключа 
			foreach ($valz->getElements() as $key => $value) 
			{
				// @FIXME если было два одинаковых условия-ключа, то значение не подставляется
				// и в форме не будет соотв. выпадающий список
				if ($value->getName()==$v."_val") 
				{
// 					unset($item);
// 					echo $value->getName()."|";
					$item=$value;
					$item->setName("cond_val".$i);
					$form->addElement($item);
					$form->getElement("cond_val".$i)
					->setName("cond_val")
					->setIsArray(true)
					->setAttrib("class", "")
					->setValue($cond_vals[$i])
					->addValidator('NotEmpty',true,array("integer","zero"))
					;
// 					$form->addElement($item);
				}
			}
		}
		$form->addElement("button","OK",array(
				"id"=>"btnSave",
				"onClick"=>"filters_save()",
				"class"=>"apply_text"
		));
		$form->getElement("OK")->setName("Сохранить");		
		return $form;
	}

	private function createForm_new()
	{
		$form=new Formochki();
		$form->setAttrib('name','formNew');
		$form->setAttrib('id','formNew');
		$form->setMethod('POST');
		$form->setAction($this->baseLink."/add");
		$form->addElement("hidden","listid",array("value"=>$listid));
		$form->getElement("listid")
		->addValidator('NotEmpty',true,array("integer","zero"))
		->addValidator("Int",true);
		;
		$textOptions=array('class'=>'medinput');
		$form->addElement("text","listname");
		$form->getElement("listname")
		->setOptions($textOptions)
		// @FIXME считает валидным при пустом значении
		->addValidator("NotEmpty",true)
		->addValidator("Alnum",true,array('allowWhiteSpace' => true))
		;

		$list=array();
		foreach ($this->cfg as $key => $value)
		{
			$list[$key]=$value["tit"];
		}
		$item=$this->hlp->createSelectList("col",$list);
		$form->addElement($item);
		$form->getElement("col")
		->setIsArray(true)
		->setAttrib("class", "hidden")
		->addValidator("InArray",true,array(array_keys($this->cfg)))
		->addValidator('NotEmpty',true)
		;

		$item=$this->hlp->createSelectList("cond",$list);
		$form->addElement($item);
		$form->getElement("cond")
		->setOptions(array("isArray"=>true))
		->setAttrib("class", "hidden")
		->setAttrib("onChange", "filters_CondValues($(this));")
		->addValidator("InArray",true,array(array_keys($this->cfg)))
		->addValidator('NotEmpty',true)
		;
		$signs=$this->hlp->createSelectList("sign",$this->cond_signs);
		$form->addElement($signs);
		$form->getElement("sign")
		->setOptions(array("isArray"=>true))
		->setAttrib("class", "hidden")
		->addValidator("InArray",true,array(array_keys($this->cond_signs)))
		->addValidator('NotEmpty',true,array("integer","zero"))
		;
		$form->addElement("hidden","cond_val");
		$form->getElement("cond_val")
		->setOptions(array("isArray"=>true))
		->setAttrib("class", "hidden")
		->setValue(-9)
		->addValidator('NotEmpty',true,array("integer","zero"))
		;
		$form->addElement("button","OK",array(
				"id"=>"btnSave",
				"class"=>"apply_text",
				"onClick"=>"filters_save()"
		));
		$form->getElement("OK")->setName("Сохранить");
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
		$criteria['listid']=isset($in['listid'])
		?	$in['listid']
		:	$defaults['listid']
		;
		return $criteria;
	}


	private function sessionUpdate($params)
	{
		$defaults=$this->getFormChangeDefaults();

		// 		$filter = new Zend_Filter_Alpha();
		// обновим сессию
		foreach ($params as $param=>$value)
		{
			// отфильтруем
			switch ($param)
			{
				case "listid":
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
		$f=array_keys($this->UserLists);
		$f=array_shift($f);
		$f=empty($f)?0:$f;
		$result=array(
				"listid"=>$f,
		);
		return $result;
	}

	/** подготовка трех массивов для модели
	 * @param array $list_params параметры списка полученные из БД
	 * @return array (COLS,JOINS,WHERES)
	 */
	private function listParamsPrepare($list_params)
	{
		$cols=array();
		$joins=array();
		$wheres=array();
		$hdrs=array();
		// по именам параметров узнаем что и как искать
		foreach ($list_params["cols"] as $i => $p)
		{
			$cols[]=$this->cfg[$p["keyname"]]["col"]." AS ".$p["keyname"];
			if (!empty($this->cfg[$p["keyname"]]["join"]))
			{
				$joins[]=$this->cfg[$p["keyname"]]["join"];
			}
			$hdrs[$p["keyname"]]=$this->cfg[$p["keyname"]]["tit"];
		}
		foreach ($list_params["cond"] as $i => $p)
		{
			$wheres[]=$this->cfg[$p["keyname"]]["where"].$p["cond"].$p["cond_val"];
			if (!empty($this->cfg[$p["keyname"]]["join"]))
			{
				$joins[]=$this->cfg[$p["keyname"]]["join"];
			}
		}
		return $result=array("cols"=>$cols,"joins"=>$joins,"wheres"=>$wheres,"hdrs"=>$hdrs);
	}

	private function array_col($colname)
	{
		return $element[$colname];

	}
}