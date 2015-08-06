<?php
/**
 * @author zlydden
 *
 */
Zend_Loader::loadClass('My_Spravtypic');

class Dostup_GroupsController extends My_Spravtypic
{

	// группы ID <= 10 служебные - не удаляить !!!
	private $table='acl_groups';
	private $_model;  // модель DOSTUP
	protected $data; // модель справочника

	private $baseLink;

	// 	private $title='Справочник: Подразделения предприятия';

	function init()
	{
		parent::setTable($this->table);
		parent::setTitle($this->title);

		// выясним название текущего модуля для путей в ссылках
		$currentModule=$this->_request->getModuleName();
		$this->view->currentModuleName=$currentModule;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->currentController = $this->_request->getControllerName();
		$this->baseLink=$this->_request->getBaseUrl()."/".$currentModule."/".$this->_request->getControllerName();
		$this->view->baseLink=$this->baseLink;
		$this->redirectLink=$this->_request->getModuleName()."/".$this->_request->getControllerName();

		$this->view->icoPath=$this->_request->getBaseUrl()."/public/images/";
		$this->view->confirmWord=$this->confirmWord;
		Zend_Loader::loadClass('Formochki');
		Zend_Loader::loadClass('Sprav');
		Zend_Loader::loadClass('Aclgroups');
		$this->view->title = $this->title;
		$this->data=new Sprav($this->table);
		$this->_model=new Aclgroups();
		$this->hlp=$this->_helper->getHelper('Typic');

		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/sprav.js');
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/dostup.js');

		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		$ajaxContext ->addActionContext('edit', 'json')->initContext('json');
		$ajaxContext ->addActionContext('show', 'json')->initContext('json');

	}

	function indexAction()
	{
		// это для формы добавления
		$this->view->curact = 'add';
		// данные для списка
		$rows=$this->_model->getGroupsAll();

		$this->view->items=$this->treeArray($rows);

		// форма добавления
		$fAdd=$this->createForm_add();
		$fAdd->getElement("id")->setValue(0); // нуль это если новая корневая группа
		$this->view->formAdd=$fAdd;
		// форма удаления
		$this->view->formDel=$this->createForm_del();
		// 		// форма редактирования
		$this->view->formEdit=$this->createForm_edit();
	}

	function addAction()
	{
		if (!$this->_request->isPost()) $this->_redirect($this->redirectLink);

		$form=$this->createForm_add();
		$grps=$this->_model->getGroupsAll();
		$grps[0]=0;
		// 		echo "<pre>".print_r(array_keys($grps),true)."</pre>";
		$form->getElement("id")
		->addValidator("InArray",true,array(array_keys($grps)));
		$params = $this->_request->getParams();

		$chk=$form->isValid($params);

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
		else
		{
			$values=$form->getValues();
			// id != 0 - новая внутри готовой группы
			if ($values["id"] > 0)
			{
				$newid=$this->_model->addGroupByTitle($values["title"],$values["id"]);
			}
			// иначе новая вообще
			else
			{
				$newid=$this->_model->addGroupByTitle($values["title"]);
			}
			$this->_redirect($this->redirectLink);

		}



	}

	function editAction()
	{
		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->redirectLink);
		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();

		$params = $this->_request->getParams();
		$grps=$this->_model->getGroupsAll();

		$form=$this->createForm_edit();
		$form->getElement("id")
		->addValidator("InArray",true,array(array_keys($grps)));

		$chk=$form->isValid($params);
		// форма неправильно заполнена
		if (!$chk)
		{
			$msg=$form->getMessages();
			$_msg="<p class='error'>Ошибка!</p>";
			foreach ($msg as $var=>$text)
			{
				$_msg.="<p>".$form->getElement($var)->getDescription();
				$_msg.=" : ".implode("; ", array_values($text))."</p>";
			}
			$this->view->OK=false;
			$this->view->formMsg=$_msg;
				
		}
		else
		{
			// @FIXME если группа родитель - отключать (DISABLED) все вложенные группы
			$values=$form->getValues();
			$this->_model->saveInfo($values);
			$this->view->OK=true;
			$this->view->formMsg="<p class='ok'>Успешно</p>";
		}
	}

	function showAction()
	{
		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->redirectLink);
		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();

		$params=$this->_request->getParams();
		$id=$params["id"];

		$grps=$this->_model->getGroupsAll();
		if (! array_key_exists($id,$grps)) return;

		$form=$this->createForm_edit();
		$info=$this->_model->getInfo($id);
		$form->populate($info);
		$this->view->info=$info;
		$this->view->formEdit=$form;
		$this->view->details=$this->view->render($this->_request->getControllerName().'/_formEdit.phtml');


	}

	function delAction()
	{
		if (!$this->_request->isPost()) $this->_redirect($this->redirectLink);

		$form=$this->createForm_del();
		$grps=$this->_model->getGroupsAll();
		$_srtict=$this->_model->getRestricted();
		// массивы которые можно удалять
		$_diff=array_diff_key($grps,$_srtict);

		$form->getElement("id")
		->addValidator("InArray",true,array(array_keys($_diff)));
		$params = $this->_request->getParams();

		$chk=$form->isValid($params);

		// форма неправильно заполнена
		if (!$chk)
		{
			$msg=$form->getMessages();
			$_msg="<p class='error'>Ошибка!</p>";
			foreach ($msg as $var=>$text)
			{
				$_msg.="<p>".$form->getElement($var)->getDescription();
				$_msg.=" : ".implode("; ", array_values($text))."</p>";
			}
			$this->view->msg=$_msg;
		}
		else
		{
			$values=$form->getValues();
			// нельзя удалять 1-10 и тех, кто является родителем
			// 1-10 забота валидатора
			// проверим я является ли ID родителем
			if (! $this->_model->hasChild($values["id"])) $this->_model->deleteGroup($values["id"]);
			// 			echo "<pre>".print_r($this->_model->hasChild($values["id"]),true)."</pre>";

			$this->_redirect($this->redirectLink);

		}


	}


	private function createForm_del()
	{
		$form=new Formochki();
		$form->setAttrib('name','formDel');
		$form->setAttrib('id','formDel');
		$form->setMethod('POST');
		$form->setAction($this->view->baseUrl
				.'/'.$this->view->currentModuleName
				.'/'.$this->_request->getControllerName()
				.'/'.'del'
		);
		$textOptions=array('class'=>'typic_input');

		$_restr=$this->_model->getRestricted();
		$form->addElement("hidden","id");
		$form->getElement("id")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		// 		->addValidator("InArray",true,array("haystack"=>array_keys($_restr),"strict"=>true))
		->setDescription("id");

		$form->addElement("text","confirm",$textOptions);
		$form->getElement("confirm")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		->addValidator("Identical",true,array('token'=>$this->confirmWord))
		->setDescription("Слово-подтверджение");

		$form->addElement("submit","OK",array(
				"class"=>"apply_text"
		));
		$form->getElement("OK")->setName("Удалить");
		return $form;


	}

	private function createForm_edit()
	{
		$form=new Formochki();
		$form->setAttrib('name','formEdit');
		$form->setAttrib('id','formEdit');
		// 		$form->setMethod('POST');
		// 		$form->setAction($this->_request->getBaseUrl()
		// 				.'/'.$this->_request->getModuleName()
		// 				.'/'.$this->_request->getControllerName()
		// 				.'/'.'edit'
		// 		);
		$textOptions=array('class'=>'typic_input');

		$form->addElement("hidden","id");
		$form->getElement("id")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		->setDescription("id");

		$form->addElement("checkbox","disabled");
		$form->getElement("disabled")
		->addValidator("NotEmpty",true,array("integer","zero"))
		->addValidator("InArray",true,array(array(0,1)))
		->setValue(0)
		->setCheckedValue(1)
		->setUnCheckedValue(0)
		->setDescription("Отключить");

		$form->addElement("text","title",array("class"=>"longinput"));
		$form->getElement("title")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		->addValidator("Alnum",true,array("allowWhiteSpace"=>true))
		->setDescription("Наименование");

		$form->addElement("textarea","comment",array("class"=>"medinput"));
		$form->getElement("comment")
		// 		->setRequired(true)
		// 		->addValidator("NotEmpty",true)
		->setDescription("Комментарий");


		$form->addElement("text","title_small",$textOptions);
		$form->getElement("title_small")
		->addValidator("Alnum",true,array("allowWhiteSpace"=>true))
		->setDescription("Краткое наименование");

		$form->addElement("textarea","paramz",array("class"=>"medinput"));
		$form->getElement("paramz")
		->setDescription("имя параметра = значение. Каждое отдельной строкой");

		$form->addElement("submit","OK",array(
				"class"=>"apply_text"
		));
		$form->getElement("OK")->setName("ПРИМЕНИТЬ");
		return $form;


	}

	private function createForm_add()
	{
		$form=new Formochki();
		$form->setAttrib('name','addGroup');
		$form->setAttrib('id','addGroup');
		$form->setMethod('POST');
		$form->setAction($this->view->baseUrl
				.'/'.$this->view->currentModuleName
				.'/'.$this->_request->getControllerName()
				.'/'.'add'
		);
		$textOptions=array('class'=>'typic_input');

		// если указано - то создается дочерний
		$form->addElement("hidden","id");
		$form->getElement("id")
		->setRequired(true)
		->addValidator("Digits",true)
		->addValidator("NotEmpty",true,array("integer","zero"))
		->setDescription("id");


		$form->addElement("text","title",array("class"=>"longinput"));
		$form->getElement("title")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		->setDescription("Наименование");

		$form->addElement("submit","OK",array(
				"class"=>"apply_text"
		));
		$form->getElement("OK")->setName("Добавить");
		return $form;

		;
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
			$aaa["title_small"]=$row['title_small'];
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

}