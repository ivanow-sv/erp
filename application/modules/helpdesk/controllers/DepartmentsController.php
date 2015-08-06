<?php
class Helpdesk_DepartmentsController extends Zend_Controller_Action
{
	protected 	$session;
	protected 	$baseLink;
	protected	$redirectLink; 	// ссылка в этот модуль/контроллер
	private 	$_model;
	private 	$hlp; 			// помощник действий Typic
	private 	$_author; 		// пользователь шо щаз залогинен
	private 	$deptsGr=3; 	// ACL группа с перечнем подразделений (группа "Сотрудники")


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

		$groupEnv=Zend_Registry::get("groupEnv");
		//		$this->currentFacultId=$roleEnv['currentFacult'];
		$this->_model=new Helpdesk();
		$moduleTitle=Zend_Registry::get("ModuleTitle");
		$modContrTitle=Zend_Registry::get("ModuleControllerTitle");
		$this->view->title=$moduleTitle
		.". ".$modContrTitle.'. ';
		$this->view->addHelperPath('./application/views/helpers/','My_View_Helper');

		Zend_Loader::loadClass('Zend_Session');
		Zend_Loader::loadClass('Zend_Form');
		Zend_Loader::loadClass('Formochki');
		Zend_Loader::loadClass('Zend_Dom_Query');
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$this->session=new Zend_Session_Namespace('my');
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		$ajaxContext ->addActionContext('show', 'json')->initContext('json');
		// 				$ajaxContext ->addActionContext('formchanged', 'json')->initContext('json');
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/helpdesk.js');
		// 		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/jquery.dataTables.min.js');
		// 				$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/styles/jquery.dataTables.css');
		$this->_author=Zend_Auth::getInstance()->getIdentity();
	}


	function indexAction()
	{
		// отделы - многомомерный массив
		$_deps=$this->_model->getDepartments($this->deptsGr);
		// переделаем в нужный вид
		$deps=$this->treePrepare($_deps);
		// 		echo "<pre>".print_r($deps,true)."</pre>";
		$this->view->deps=$deps;
	}

	public function listAction()
	{
		$id=(int)$this->_request->getParam("id");
		$sortBy=(int)$this->_request->getParam("sortBy");

		$_deps=$this->_model->getDepartments($this->deptsGr);
		$deps=$this->treePrepare($_deps);

		if (!array_key_exists($id, $deps))  $this->_redirect($this->redirectLink);

		// информация об отделе
		$info=$this->_model->getDepInfo($id);
		$this->view->depInfo=$info;

		// ссылка на перечень подразделений
		$this->view->backLink=$this->view->baseLink."/list";
		// перечень техники
		$this->view->depUnits=$this->_model->getDepUnitsList($id);
		// форма добавления новой единицы
		$utypes=$this->_model->getUTypes(true);
		$formAdd=$this->createForm_AddUnit($utypes,$deps);
		$formAdd->getElement("department")->setValue($id);
		$this->view->formAddUnit=$formAdd;
		$this->view->formMoveUnit=$this->createForm_MoveUnit($deps,$id);

	}

	function dublicateAction()
	{
		$id= (int)$this->_request->getParam("id");

		$info=$this->_model->getUnitInfo($id);
		if (empty($info)) $this->_redirect($this->redirectLink);

		// есть такая позиция - действуем
		$details=$this->_model->getUnitInfoDetails($id, $info["tbl"]);
		$res=$this->_model->unitDublicate($info,$details,$this->_author->id);
		// все нормуль? перейдем к новому
		if ($res["status"] ) $this->_redirect($this->redirectLink."/edit/id/".$res["inserted"]);
		// 				иначе ругнем
		else $this->view->msg="Ошибка БД: ".$res["msg"]."<br>".$_rez["msg"];
	}

	function moveAction()
	{
		if (!$this->_request->isPost())  $this->_redirect($this->redirectLink);
		// проверить форму переноса - верны ли ID и подразделение назначения
		$deps=$this->_model->getDepartments($this->deptsGr);
		$deps=$this->treePrepare($deps);

		$form=$this->createForm_MoveUnit($deps);
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

			// название подразделения "куда"
			$detTitle=$deps[$values["department"]];

			$res=$this->_model->unitsMove($values["id"],$values["department"],$this->_author->id,$detTitle);
			// 			все нормуль - перейдем к правке
			if ($res["status"])  {
				$this->view->msg="Успешно";
				$this->view->status=true;
				$this->view->nextLink=$this->baseLink."/list/id/".$values["department"];

			}
			// 			иначе ругнем
			else $this->view->msg="Ошибка БД: ".$res["msg"];
		}


	}



	public function editAction()
	{
		$id=(int)$this->_request->getParam("id");

		// выясним где хранятся подробности
		$info=$this->_model->getUnitInfo($id);
		// запись не найдена
		if (empty($info)) $this->_redirect($this->redirectLink);
		$this->view->info=$info;
		// выясним подробности
		$detail=$this->_model->getUnitInfoDetails($id,$info["tbl"]);
		// узнаем имена полей и их описания (комментарии к столбцам таблицы)
		$fieldNames=$this->_model->getColumnsComments($info["tbl"]);
		// из всего этого устром форму
		$formDetail=$this->createForm_unitDetails($fieldNames);
		$formDetail->getElement("id")->setValue($id);
		$this->view->formDetail=$formDetail;
		// передадим названия полей
		$this->view->fieldNames=$fieldNames;
		// ссылка на перечень  техники в подразделении
		$this->view->backLink=$this->view->baseLink."/list/id/".$info["department"];

		// если просто открыли
		if (!$this->_request->isPost())
		{
			// впишем данные
			$this->view->formDetail->populate($detail);
			// получим журнал и выведем
			$this->view->journal=$this->_model->unitsJournalGet($id);
			// заявки
			$this->view->tickets=$this->_model->unitTicketsGet($id);
		}
		// значит отправлялась форма
		else
		{
			// проверим форму
			$params = $this->_request->getPost();
			// 			unset($params["lshwfile"]);
			// 				echo $formDetail->lshwfile->isUploaded();
			$chk=$formDetail->isValid($params);
			// была загрузка LSHW ?
			if (isset($formDetail->lshwfile) &&  $formDetail->lshwfile->isUploaded())
			{
				// распарсить
				$_file=$formDetail->lshwfile->getFileInfo();
				$_file=$_file["lshwfile"];
				$file=array(
						"name"=>$_file["name"],
						"size"=>$_file["size"],
				);
				$fcontent=file_get_contents($_file["tmp_name"]);
					
				// @TODO проверка, тот ли формат

				// распарсим
				$_values=$this->parseLSHW($fcontent);
					
				// @TODO гденить сохранить и сделать привязку

				// перезапишем данные формы
				$params=array_merge($params,$_values);
				// еще ращз проверим
				$chk=$formDetail->isValid($params);
					
			}
			// 			echo "<pre>".print_r($params,true)."</pre>";

			// форма неправильно заполнена
			if (!$chk)
			{
				$msg=$formDetail->getMessages();
				$_msg="<p class='error'>Ошибка заполнения!</p>";
				foreach ($msg as $var=>$text)
				{
					$_msg.="<p>".$formDetail->getElement($var)->getDescription();
					$_msg.=" : ".implode("; ", array_values($text))."</p>";
				}
				$this->view->msg=$_msg;
			}
			// Заполнена правильно
			else
			{
				$values=$formDetail->getValues();
				// @TODO этот блок как нить переделать
				// @FIXME если инвентарник не менялся - в журнал не писать

				// если меняли инвентарник или примечания
				$_comment_log="";
				$_d=array();
				if($info["inumb"]!==$values["inumb"])
				{
					$_comment_log.="Назн.инв.№: ".$values["inumb"].". ";
					$_d["inumb"]=$values["inumb"];
				}
				if($info["comment"]!==$values["comment"])
				{
					$_comment_log.="Комментарий: ".$values["comment"];
					$_d["comment"]=$values["comment"];
				}
				// если были изменения
				if (!empty($_d)) $_rez=$this->_model->unitInfoChange($_d, $id,$this->_author->id,$_comment_log);

				unset($values["inumb"]);
				unset($values["comment"]);
				unset($values["lshwfile"]);
				$_comment_log="";

				// детальная информация
				foreach ($values as $key => $_value)
				{
					// пропустим ключ ID или если значение не сменилось
					if ($key==="id" || $_value===$detail[$key]) continue;
					$_comment_log.=$key."=".$_value."; ";
				}
				// если коментарий не пуст, то были изменения
				if (!empty($_comment_log))
				{
					$_comment_log="Изменения: ".$_comment_log;
					// внесем
					$res=$this->_model->unitDetailsChange($values,$info["tbl"],$this->_author->id,$_comment_log);
					// 				все нормуль - перейдем к правке
					if ($res["status"] || $_rez["status"]) $this->_redirect($this->redirectLink."/edit/id/".$id);
					// 				иначе ругнем
					else $this->view->msg="Ошибка БД: ".$res["msg"]."<br>".$_rez["msg"];

				}
				else $this->_redirect($this->redirectLink."/edit/id/".$id);

				// 				print_r($res);

			}


		}
	}

	public function addAction()
	{
		if (!$this->_request->isPost())  $this->_redirect($this->redirectLink);
			

		$utypes=$this->_model->getUTypes(true);
		$deps=$this->_model->getDepartments($this->deptsGr);
		$deps=$this->treePrepare($deps);

		$form=$this->createForm_AddUnit($utypes,$deps);
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
			// узнаем таблицу деталей для данного типа
			$_tbl=$this->_model->getUTypeTbl($values["utype"]);
			// название подразделения
			$depTitle=$deps[$values["department"]];
			$res=$this->_model->unitAdd($values,$_tbl,$depTitle,$this->_author->id);
			// все нормуль - перейдем к правке
			if ($res["status"])  $this->_redirect($this->redirectLink."/edit/id/".$res["inserted"]);
			// иначе ругнем
			else $this->view->msg="Ошибка БД: ".$res["msg"];
		}
	}

	private  function createForm_AddUnit($utypes,$deps)
	{
		$form=new Formochki();
		$form->setAttrib('name','AddUnit');
		$form->setAttrib('id','AddUnit');
		$form->setMethod('POST');
		$form->setAction($this->view->baseUrl
				.'/'.$this->view->currentModuleName
				.'/'.$this->_request->getControllerName()
				.'/'.'add'
		);
		$textOptions=array('class'=>'typic_input');

		$utypesList=$this->hlp->createSelectList("utype",$utypes);
		$form->addElement($utypesList);
		$form->getElement("utype")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		->addValidator("Digits",true)
		->addValidator("InArray",true,array(array_keys($utypes)))
		->setDescription("Тип оборудования")
		;

		$form->addElement("textarea","comment",array("class"=>"medinput"));
		$form->getElement("comment")
		->setDescription("Комментарий");

		$form->addElement("text","inumb",$textOptions);
		$form->getElement("inumb")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		->setDescription("Инвентарный номер");

		$form->addElement("hidden","department");
		$form->getElement("department")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		->addValidator("Digits",true)
		->addValidator("InArray",true,array(array_keys($deps)))
		->setDescription("Подразделение");

		$form->addElement("submit","OK",array(
				"class"=>"apply_text"
		));
		$form->getElement("OK")->setName("Добавить");
		return $form;

		;
	}

	/**
	 * @param array $deps список подразделений
	 * @param integer $current ID текущего, если =0 то значит все
	 * @return Formochki
	 */
	private  function createForm_MoveUnit($deps,$current=0)
	{
		if ($current>0) unset($deps[$current]);
		$form=new Formochki();
		$form->setAttrib('name','MoveUnit');
		$form->setAttrib('id','MoveUnit');
		$form->setMethod('POST');
		$form->setAction($this->view->baseUrl
				.'/'.$this->view->currentModuleName
				.'/'.$this->_request->getControllerName()
				.'/'.'move'
		);
		$textOptions=array('class'=>'typic_input');

		$form->addElement("hidden","id");
		$form->getElement("id")
		->setRequired(true)
		->setIsArray(true)
		->addValidator("NotEmpty",true)
		->addValidator("Digits",true);

		$depslist=$this->hlp->createSelectList("department",$deps);
		$form->addElement($depslist);
		$form->getElement("department")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		->addValidator("Digits",true)
		->addValidator("InArray",true,array(array_keys($deps)))
		->setDescription("Подразделение")
		;

		$form->addElement("submit","OK",array(
				"class"=>"apply_text"
		));
		$form->getElement("OK")->setName("Применить");
		return $form;
	}

	private function createForm_unitDetails($fieldName)
	{
		$form=new Formochki();
		$form->setAttrib('name','EditUnit');
		$form->setAttrib('id','EditUnit');
		$form->setAttrib('enctype', 'multipart/form-data');
		$form->setMethod('POST');
		$form->setAction($this->view->baseUrl
				.'/'.$this->view->currentModuleName
				.'/'.$this->_request->getControllerName()
				.'/'.'edit'
		);
		$textOptions=array('class'=>'medinput');

		$form->addElement("hidden","id");
		$form->getElement("id")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		->addValidator("Digits",true);

		// камент
		$form->addElement("textarea","comment",$textOptions);
		$form->getElement("comment")
		->setDescription("Примечания")
		;

		// инвентарник
		$form->addElement("text","inumb",array('class'=>'typic_input'));
		$form->getElement("inumb")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		->setDescription("Инвентарный номер")
		;

		// переберем поля
		foreach ($fieldName as $name => $title)
		{
			// Если софт
			if($name=="software")
			{
				$form->addElement("textarea",$name,$textOptions);
				$form->getElement($name)
				->setRequired(true)
				->addValidator("NotEmpty",true)
				->setDescription($title)
				;
			}
			else
			{
				$form->addElement("text",$name,$textOptions);
				$form->getElement($name)
				->setRequired(true)
				->addValidator("NotEmpty",true)
				->setDescription($title)
				;
			}

		}

		// с процессором? значит системный блок и можно импортировать LSHW
		if (isset($fieldName["processor"]))
		{
			// загрузка LSHW файла
			$form->addElement("file","lshwfile",$textOptions);
			$form->getElement("lshwfile")
			->setDescription("XML-файл формата LSHW")
			->addValidator('Extension', false, 'xml')
			->addValidator('Count', false, 2);
		}
		$form->addElement("submit","OK",array(
				"class"=>"apply_text"
		));
		$form->getElement("OK")->setName("Применить");
		return $form;

		;
	}

	public function showAction()
	{
		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->redirectLink);
		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();

		$id=(int)$this->_request->getParam("id");

		// выясним где хранятся подробности
		$info=$this->_model->getUnitInfo($id);
		// запись не найдена
		if (empty($info)) $this->_redirect($this->redirectLink);
		// выясним подробности
		$detail=$this->_model->getUnitInfoDetails($id,$info["tbl"]);
		// узнаем имена полей и их описания (комментарии к столбцам таблицы)
		$fieldNames=$this->_model->getColumnsComments($info["tbl"]);
		// из всего этого устром форму
		$formDetail=$this->createForm_unitDetails($fieldNames);
		$formDetail->getElement("id")->setValue($id);
		$formDetail->populate($detail);

		// уберем кнопку
		$formDetail->removeElement("OK");
		// уберем lshw
		$formDetail->removeElement("lshwfile");
		// уберем ACTION
		$formDetail->removeAttrib("action");
		// сделаем поля READONLY
		// 		$_elems=$formDetail->getElements() ;
		foreach ($formDetail->getElements() as $elemname=> $value)
		{
			// узнаем текущие
			$_attr=$value->getAttribs();
			// добавим новые
			$_attr=array_merge($_attr,array("readonly"=>"readonly"));
			// заменим в форме
			$formDetail->getElement($elemname)->setAttribs($_attr);
			;
		}

		// передадим названия полей
		$this->view->fieldNames=$fieldNames;
		$this->view->formDetail=$formDetail;
		$this->view->journal=$this->_model->unitsJournalGet($id);
		$this->view->tickets=$this->_model->unitTicketsGet($id);
		$this->view->details=$this->view->render($this->_request->getControllerName().'/_details.phtml');

	}

	/**
	 * @ TODO вынести в отдельную либу
	 * @param string $xml
	 * @return array
	 */
	private function parseLSHW($xml)
	{
		$DOM=new Zend_Dom_Query();
		$DOM->setDocumentXml($xml,"UTF-8");

		// процессор
		$cpu=$DOM->query("#cpu .processor > product")->current()->nodeValue;
		$cpu.=" ".round($DOM->query("#cpu .processor > size")->current()->nodeValue/1000/1000/1000,2)." GHz";
		$data["processor"]=$cpu;
			
		// Материнская плата
		$mb=$DOM->query("#core > vendor")->current()->nodeValue;
		$mb.=" ". $DOM->query("#core > product")->current()->nodeValue;
		$data["board"]=$mb;

		// память
		$memory=$DOM->query("#memory .memory > size");
		$data["memory"]=$memory->current()->nodeValue;
		$data["memory"]=round($data["memory"]/1024/1024/1024,2);
		$data["memory"].=" Gb";

		// видео
		$vga=$DOM->query("#display > product")->current()->nodeValue;
		$data["videocard"]=$vga;

		// жесткие диски
		$_hdd="";
		$hdd_desc=$DOM->query('node[id*="disk"] .disk > description');
		$hdd_vendors=$DOM->query('node[id*="disk"] .disk > vendor');
		$hdd_products=$DOM->query('node[id*="disk"] .disk > product');
		$hdd_sizes=$DOM->query('node[id*="disk"] .disk > size');
		$_count=$hdd_desc->count();
		for ($i = 0; $i < $_count; $i++)
		{
			$_hdd.=$hdd_vendors->current()->nodeValue;
			$_hdd.=" ". $hdd_products->current()->nodeValue;
			$_s=$hdd_sizes->current()->nodeValue;
			// доведем до гигабайт и округлим
			$_s=round($_s/1024/1024/1024,2) . " Gb ";
			$_hdd.=" ". $_s."; ";
			$hdd_vendors->next();
			$hdd_products->next();
			$hdd_sizes->next();
		}
		$data["hdd"]=$_hdd;
		// + vendor, size

		// Оптический привод - один
		$cdrom=$DOM->query('#cdrom .disk > description')->current()->nodeValue;
		$cdrom.=" ". $DOM->query('#cdrom .disk > vendor')->current()->nodeValue;
		$cdrom.=" ".$DOM->query('#cdrom .disk > product')->current()->nodeValue;
		$data["optical"]=$cdrom;
		return $data;
	}

	private function treePrepare($big_tree)
	{
		$result=array();
		foreach ($big_tree as $key => $tree)
		{
			$a="";
			$b="";
			$numz=$tree["level"];
			// начинем с 1-ой ступени, а не с нулеовой
			for($i=1;$i<$numz;$i++)
			{
				if($i>1)
				{
					$a="|";
					$b=$b."—";
				}
				else
				{
					$a="";
					$b="";
				}
				$tit.=$a.$b.$tree[$i.".text"];
				$result[$tree[$i.".id"]]=$tit;
				$tit="";
			}
			;
		}

		return ($result);
	}

	// 	public function formchangedAction()
	// 	{
	// 		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->redirectLink);		// очистим вывод
	// 		$this->view->clearVars();
	// 		$this->view->baseLink=$this->baseLink;
	// 		$this->view->baseUrl = $this->_request->getBaseUrl();


	// 	}


	// 	private function createForm_Filter($utypes,$deps)
	// 	{
	// 		$form=new Formochki();
	// 		$form->setAttrib('name','filterForm');
	// 		$form->setAttrib('id','filterForm');
	// 		$form->setMethod('POST');
	// 		$form->setAction($this->view->baseUrl.'/'.$this->view->currentModuleName.'/'.$this->_request->getControllerName());
	// 		$textOptions=array('class'=>'typic_input');

	// 		$depsList=$this->hlp->createSelectList("department",$deps);
	// 		$form->addElement($depsList);
	// 		$form->getElement("department")
	// 		->addValidator("Digits")
	// 		->addValidator("InArray",true,array($deps))
	// 		->setDescription("Подразделение")
	// 		;

	// 		$utypeList=$this->hlp->createSelectList("utype",$utypes);
	// 		$form->addElement($utypeList);
	// 		$form->getElement("utype")
	// 		->addValidator("Digits")
	// 		->addValidator("InArray",true,array($utypes))
	// 		->setDescription("Тип оборудования")
	// 		;

	// 		$form->addElement("submit","OK",array(
	// 		"class"=>"apply_text"
	// 		));
	// 		$form->getElement("OK")->setName("Искать");
	// 		return $form;
	// 		;
	// 	}
	/** строит критериый поиска из массива или из данных сессии
	 * @return array
	 */
	// 	private function buildCriteria($in=null)
	// 	{
	// 		if (is_null($in)) $in=$this->session->getIterator();
	// 		$defaults=$this->getFormFilterDefaults();
	// 		$criteria['utype']=isset($in['utype'])
	// 		?	$in['utype']
	// 		:	$defaults['utype']
	// 		;
	// 		return $criteria;
	// 	}

	// 	private function sessionUpdate($params)
	// 	{
	// 		$defaults=$this->getFormFilterDefaults();

	// 		$filter = new Zend_Filter_Alpha();
	// 		// обновим сессию
	// 		foreach ($params as $param=>$value)
	// 		{
	// 			// отфильтруем
	// 			switch ($param)
	// 			{
	// 				case "utype":
	// 					$_value=(int)$value;
	// 					break;

	// 				default:
	// 					$_value=$value;
	// 					break;
	// 			}
	// 			$this->session->$param=$_value;
	// 		}
	// 	}

	// 	private function getFormFilterDefaults()
	// 	{
	// 		$result=array(
	// 				"utype"=>1,
	// 		);
	// 		return $result;
	// 	}

}