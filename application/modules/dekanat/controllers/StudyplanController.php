<?php
class Dekanat_StudyplanController extends Zend_Controller_Action
{
	protected $gosParams;
	protected $gosParamsDef;

	protected  $currentFacultId;
	protected  $currentDivision		=	1;
	protected  $session;
	protected  $baseLink;
	protected  $redirectLink;
	private 	$confirmWord;
	private 	$data;
	private 	$studyYear_now;
	private 	$osnovs;

	// условные сроки семестров, после добавим,к началу год
	private $semStarts=array(
	1=>"-09-01" , // год в начало допишем
	2=>"-02-01"  // год = начало +1
	);
	private $semEnds=array(
	1=>"-01-30", // год = начало +1
	2=>"-07-01" // год = начало +1
	);
	// для обработки XLS
	private 	$rowStart=8;
	// имена столбцов - лат. буквы
	const 	xlsNUM		=	"A";	// порядковый номер
	const 	xlsDIV		=	"C";	// отделение
	const 	xlsKURS		=	"G"; 	// курс
	const 	xlsSEM		=	"H"; 	// семестр
	const 	xlsDIS		=	"K"; 	// название дисциплины
	// количество на студента
	const 	xlsZACH		=	"AF"; 	// зачеты
	const 	xlsEKZ		=	"AG"; 	// экзамены
	const 	xlsKR		=	"AH"; 	// К.П. ??? курсовая работа
	const 	xlsKP		=	"AI"; 	// К.Р. ??? курсовой проект
	const 	xlsRGR		=	"AJ"; 	// расчетно-графическая
	const 	xlsKONTR	=	"AK"; 	// контроллььная
	const 	xlsREF		=	"AL"; 	// рефераты
	const 	xlsKCH		=	"AM"; 	// доп. кон. час ???
	// ID выходных контролей
	// @TODO временно, ID в базе могут поменяться
	/*
	1 	курсовая работа
	2 	контрольная работа
	7 	экзамен
	9 	курсовой проект
	10 	зачет
	11 	расчетно-графическая работа
	12 	реферат
	*/
	const	KR		=	1;
	const	KONTR	=	2;
	const	EKZ		=	7;
	const	KP		=	9;
	const	ZACH	=	10;
	const	RGR		=	11;
	const	REF		=	12;

	public function init()
	{
		// выясним название текущего модуля для путей в ссылках
		$currentModule=$this->_request->getModuleName();
		$this->view->currentModuleName=$currentModule;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->iconpath= $this->view->baseUrl."/"."public"."/"."images"."/";

		$this->view->currentController = $this->_request->getControllerName();
		$this->baseLink=$this->_request->getBaseUrl()."/".$currentModule."/".$this->_request->getControllerName();
		$this->view->baseLink=$this->baseLink;
		$this->redirectLink=$this->_request->getModuleName()."/".$this->_request->getControllerName();

		$this->confirmWord="УДАЛИТЬ";
		// установим учебный год, который сейчас происходит

		Zend_Loader::loadClass('Dekanat');
		$groupEnv=Zend_Registry::get("groupEnv");
		$this->currentFacultId=$groupEnv['currentFacult'];
		$this->data=new Dekanat($this->currentFacultId);
		//		$this->currentDivision=$this->data->getDivisionId();

		$this->studyYear_now=$this->data->getStudyYear_Now();
		$this->semStarts[1]=$this->studyYear_now["start"].$this->semStarts[1];
		$this->semEnds[1]=$this->studyYear_now["end"].$this->semEnds[1];
		$this->semStarts[2]=$this->studyYear_now["end"].$this->semStarts[2];
		$this->semEnds[2]=$this->studyYear_now["end"].$this->semEnds[2];

		$this->gosParams=$this->data->getGosParams();
		$this->gosParamsDef=$this->data->getGosParamsDef();

		$this->osnovs=$this->data->getInfoForSelectList("osnov");

		$this->view->facultInfo=$this->data->getFacultInfo($this->currentFacultId);
		$moduleTitle=Zend_Registry::get("ModuleTitle");
		$modContrTitle=Zend_Registry::get("ModuleControllerTitle");
		$this->view->title=$moduleTitle
		.'. Факультет - '.$this->view->facultInfo['title']
		.". ".$modContrTitle.'. ';
		//		$this->view->addHelperPath('./application/views/helpers/','My_View_Helper');
		//		$logger=Zend_Registry::get("logger");
		//
		//		$aa=$this->view->getHelperPaths();
		//$logger->log($aa, Zend_Log::INFO);

		Zend_Loader::loadClass('Zend_Session');
		Zend_Loader::loadClass('Zend_Form');
		Zend_Loader::loadClass('Formochki');
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$this->session=new Zend_Session_Namespace('my');
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		$ajaxContext ->addActionContext('formchanged', 'json')->initContext('json');
		//		$ajaxContext ->addActionContext('details', 'json')->initContext('json');
		//		$ajaxContext ->addActionContext('plansave', 'json')->initContext('json');
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/dekanat.js');
		//		$this->view->Addons();
		//		$ajaxContext ->addActionContext('checkboxes', 'json')->initContext('json');



	}

	public function indexAction ()
	{
		if ($this->_request->isPost())
		{
			// получим данные из запроса
			$params = $this->_request->getParams();
			// обновим сессию
			$this->sessionUpdate($params);
		}
		$criteria=$this->buildCriteria();
		// форма фильтра
		$this->view->form=$this->createForm_Filter($criteria);

		$this->view->studyYearStart=$criteria["studyYearStart"];
		$this->view->studyYearEnd=($criteria["studyYearStart"]+1);

		// сколько существует выходных контролей
		$outControls=$this->data->getInfoForSelectList("studoutcontrols"," 1 ORDER BY title ASC");
		$this->view->outControls=$outControls;

		// форма добавления нового плана, изначально скрыта
		$formNew=$this->createForm_NewPlan($criteria["gosparam"],$criteria["osnov"],$criteria["studyYearStart"],$criteria["studyYearStart"]);
		$this->view->formNew=$formNew;

		// форма правки сроков
		$formDates=$this->createForm_editPlanDates();
		$this->view->formEditDates=$formDates;

		// форма-пустышка добавления/редактирования дисциплины
		$this->view->formEditDis=$this->createForm_editDiscipline("disciplineedit");
		$this->view->formDisDelete=$this->createForm_deleteDiscipline();

		// форма удаления - шаблон, шо именно удаляться будет - определит JS
		$formDelete=new Formochki();
		$formDelete->setAttrib('name','formDelete');
		$formDelete->setAttrib('id','formDelete');
		$formDelete->setMethod('POST');
		$formDelete->setAction($this->baseLink."/"."plandelete");
		$formDelete->addElement("hidden","id");
		$formDelete->addElement("text","confirmWord",array("class"=>"typic_input"));
		$formDelete->addElement("submit","OK",array("class"=>"apply_text"));
		$formDelete->getElement("OK")->setName("подтвердить");
		$this->view->confirmWord=$this->confirmWord;
		$this->view->formDelete=$formDelete;

		$this->view->importxlskostromaForm=
		$this->createForm_importxlskostroma($criteria["gosparam"],$criteria["osnov"]);
			
		//return;
		// построим планы
		$List=array();
		$List=$this->data
		->studyPlans_getList($criteria["studyYearStart"],$criteria["gosparam"],$criteria["osnov"]);
		$List=$this->preparePlan($List);
		// получился массив:
		// курс => семестр => детали семестра => дисциплины => форма выходного контроля => детали
		$this->view->list=$List;
	}

	public function importxlskostromaAction()
	{
		if (!$this->_request->isPost()) $this->_redirect($this->redirectLink);
		Zend_Loader::loadClass('Xls_Reader');
		$params = $this->_request->getParams();
		// обновим сессию
		$this->sessionUpdate($params);
		$criteria=$this->buildCriteria();

		// @FIXME имеем ли право мы работать с ДАННОЙ специальностью

		$form=$this->createForm_importxlskostroma($criteria["gosparam"],$criteria["osnov"]);
		//		$params = $this->_request->getParams();
		$chk=$form->isValid($params);
		if ($chk)
		{
			// получим файл
			//			$form->file->recieve();
			//			$values= $form->file->getValue();
			$_info=$form->file->getFileInfo();
			$_info=$_info["file"];
			$info=array(
			"name"=>$_info["name"],
			"size"=>$_info["size"],
			);

			// парсинг XLS
			$XLS=new Xls_Reader($_info["tmp_name"],false,"UTF-8");
			$numrows=$XLS->rowcount();
			$disciplines=array();
			$plans=array();
			// пойдем построчно
			// сделаем массив разбитый по семестрам
			for ($row = $this->rowStart; $row <= $numrows; $row++)
			{
				$_val=$XLS->val($row,self::xlsNUM);
				// если ячейка пуста - харош парсить
				if (empty($_val)) break(1);
				else {


					$_sem=(int)$XLS->val($row,self::xlsSEM);

					$_kurs=(int)$XLS->val($row,self::xlsKURS);
					$_id=$_kurs."-".$_sem;
					$plans[$_id]["kurs"]=$_kurs;
					$plans[$_id]["semestr"]=$_sem;
					$plans[$_id]["gosParams"]=$criteria["gosparam"];
// 					$plans[$_id]["spec"]=$criteria["spec"];
// 					$plans[$_id]["division"]=$this->currentDivision;
					$plans[$_id]["osnov"]=$criteria["osnov"];
					$plans[$_id]["studyYearStart"]=$criteria["studyYearStart"];
					$plans[$_id]["begins"]=$this->semStarts[$_sem];
					$plans[$_id]["ends"]=$this->semEnds[$_sem];

					$_dis=iconv("windows-1251", "utf-8", $XLS->val($row,self::xlsDIS));
					//					$disciplines[$_id][$_dis]["title"]=$_dis;
					$disciplines[$_id][$_dis][self::ZACH]=(int)$XLS->val($row,self::xlsZACH);
					$disciplines[$_id][$_dis][self::EKZ]=(int)$XLS->val($row,self::xlsEKZ);
					$disciplines[$_id][$_dis][self::KP]=(int)$XLS->val($row,self::xlsKP);
					$disciplines[$_id][$_dis][self::KR]=(int)$XLS->val($row,self::xlsKR);
					$disciplines[$_id][$_dis][self::RGR]=(int)$XLS->val($row,self::xlsRGR);
					$disciplines[$_id][$_dis][self::KONTR]=(int)$XLS->val($row,self::xlsKONTR);
					$disciplines[$_id][$_dis][self::REF]=(int)$XLS->val($row,self::xlsREF);
				}
			}
			// 1. создать уч. план
			// 1.1. есть ли уже такой для спец., отдел., курса, семестра, формы обуч. на этот год ?
			// 1.2. если есть - пропустить - сообщить об этом
			// 2. заполнить дисциплинами
			// 2.1. выяснить типы выходного контроля
			// 2.2. сопоставить с тем, что есть в XLS
			// 2.3. внести для каждого нужное кол-во
			/*
			* 				"planid"=>$planId,
			*				"discipline"=>$discipline,
			*				"outControl"=>$coID,
			*				"contrCount"=>$_param
			*/
			$log=array();

			if (count($plans)>0)
			{
				foreach ($plans as $key=>$plan)
				{
					// есть ли план уже ?
// 					$criteria["division"]=$this->currentDivision;
					$_planId=$this->data->studyPlans_findPlan(
					$criteria,
					$criteria["studyYearStart"],
					$plan["kurs"],
					$plan["semestr"]
					);
					if (!empty($_planId))
					{
						$log[$key]["status"]=false;
						$log[$key]["msg"]="Уже есть";
						continue;
					}
// 					echo "<pre>".print_r($plan,true)."</pre>";
					//					unset($planId);
					$planId=$this->data->studyPlans_newPlan($plan);
					// если не получилось создать план - скажем и след.
					if ($planId===false)
					{
						$log[$key]["status"]=false;
						$log[$key]["msg"]="Ошибка БД";
						continue;
					}
					// план создан - идем дальше
					$log[$key]["status"]=true;
					$ids[]=$plan;
					foreach ($disciplines[$key] as $title=>$d)
					{
						// найти ID дисциплины
						$disId=$this->data->getDiciplineId($title);
						if (empty($disId))
						{
							$log[$key][$title]["status"]=false;
							$log[$key][$title]["msg"]="Нет подходящей дисциплины";
							continue;
						}
						// все виды выходного контроля данной дисциплины и их кол-во
						unset($_disData);
						foreach ($d AS $ocontr=>$count)
						{
							//							if ($count<1) continue;
							$_disData[$ocontr]=array(
							"planid"=>$planId,
							"discipline"=>$disId,
							"outControl"=>$ocontr,
							"contrCount"=>$count
							);

						}
						// внесем
						$rez=$this->data->studyPlans_refreshPlanDiscipline($planId, $_disData,0);
						if ($rez!==true)
						{
							$log[$key][$title]["status"]=false;
							$log[$key][$title]["msg"]="Ошибка БД: ".$rez;
						}
						else {
							$log[$key][$title]["status"]=true;
						}
						;
					}
				}
			}
			$this->view->plans=$plans;
			//			$this->view->dicsiplines=$disciplines;
			$this->view->log=$log;
			//			$this->view->ids=$ids;
			$this->view->info=$info;
			//			$this->view->info2=$_info;
		}
		else
		{
			$msg=$form->getMessages();
			$this->view->msg=$msg["file"];
		}


		//		$form->


		//		Zend_Loader::loadClass('Xls_Reader');
		//		$XLS=new Xls_Reader('aah_1kurs.xls',false);

		//		$aaa=$XLS->val(8,'BC');
		//		echo $aaa;

	}

	public function formchangedAction()
	{
		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->iconpath= $this->view->baseUrl."/"."public"."/"."images"."/";
		// сколько существует выходных контролей
		$outControls=$this->data->getInfoForSelectList("studoutcontrols"," 1 ORDER BY title ASC");
		$this->view->outControls=$outControls;

		//		$logger=Zend_Registry::get("logger");
		//
		//		$aa=$this->view->getHelperPaths();
		//$logger->log($aa, Zend_Log::INFO);
		if (!$this->_request->isPost()) 		$this->_redirect($this->redirectLink);


		//
		// узнаем что к нам пришло
		$formData = $this->_request->getPost('formData');
		$oldData=$this->session->getIterator();
		// обновим сессию
		$this->sessionUpdate($formData );
		$criteria=$this->buildCriteria();

		$form=new Formochki();


		//		$osnov=intval($this->session->osnov)<=0?1:intval($this->session->osnov);
		//		$studyYearStart=intval($this->session->studyYearStart)<=0?$this->studyYear_now["start"]:intval($this->session->studyYearStart);

		// отрисовать заново выбор годов и выставим выбранный
		$yearz=$this->data->studyPlans_getYearInterval($criteria["studyYearStart"]);
		$yearList=$this->data->studyPlans_buildYearList($yearz);
		$yearSelect=$this->createSelectList("studyYearStart",$yearList,"",$criteria["studyYearStart"]);
		$form->addElement($yearSelect);
		$out["studyYearStart"]=$form->getElement("studyYearStart")->render();

		$out["studyYearEnd"]='<span id="studyYearEnd">'.($criteria["studyYearStart"]+1)."</span>";

		// отрисовать заново выбор форм обучения
		$osnovs=$this->data->getInfoForSelectList("osnov","");
		$osnovList=$this->createSelectList("osnov",$osnovs,"",$criteria["osnov"]);
		$form->addElement($osnovList);
		$out["osnov"]=$form->getElement("osnov")->render();
		$List=$this->data->studyPlans_getList($criteria["studyYearStart"],$criteria["gosparam"],$criteria["osnov"]);
		$List=$this->preparePlan($List);
		$this->view->list=$List;
		$out["List"]=$this->view->render($this->_request->getControllerName().'/_List.phtml');

		// форма добавления нового плана, изначально скрыта
		$this->view->studyYearStart=$criteria["studyYearStart"];
		$this->view->studyYearEnd=($criteria["studyYearStart"]+1);

		//		$formNew=$this->createForm_NewSes($spec,$osnov,$studyYearStart);
		$formNew=$this->createForm_NewPlan(
		$criteria["gosparam"],
		$criteria["osnov"],
		$criteria["studyYearStart"],
		$criteria["studyYearStart"]);
		$this->view->formNew=$formNew;
		$out["formNewWarp"]=$this->view->render($this->_request->getControllerName().'/_AddForm.phtml');

		$formImport=$this->createForm_importxlskostroma(
		$criteria["gosparam"],
		$criteria["osnov"]);
		$this->view->importxlskostromaForm=$formImport;
		$out["importxlskostromaWarp"]=$this->view->render($this->_request->getControllerName().'/_importxlskostromaForm.phtml');

		$this->view->out=$out;

		//		$this->_forward('index');

	}

	/*
	 public function detailsAction()
	 {
	 // очистим вывод
	 $this->view->clearVars();
	 $this->view->baseLink=$this->baseLink;
	 $this->view->baseUrl = $this->_request->getBaseUrl();
	 $this->view->iconpath= $this->view->baseUrl."/"."public"."/"."images"."/";

	 // узнаем что к нам пришло
	 //		$data = $this->_request->getPost('editPlanData');
	 $planId = (int)$this->_request->getPost('id');

	 if ($planId <1)
	 {
	 $this->view->out='';
	 return;
	 }
	 // узнаем детали плана
	 $rows=$this->data->studyPlans_getDisciplines($planId);
	 $info=$this->data->studyPlans_getInfo($planId);

	 $disciplines=$this->data->getDiscipliesStudOrderedByKaf();

	 //		die();
	 //		$this->view->formEdit=$form;
	 $formEdit=new Formochki();
	 $formEdit->setAttrib('name','formEdit');
	 $formEdit->setAttrib('id','formEdit');
	 //		$formEdit->setAttrib('style','display:none');
	 $formEdit->setMethod('POST');
	 $formEdit->setAction($this->baseLink);
	 $formEdit->addElement("hidden","id",array("value"=>$planId));
	 //		$formEdit->addElement("hidden","kurs");
	 $formEdit->addElement("text","begins",array(
	 "class"=>"typic_input",
	 "value"=>$info["begins"],
	 "onMouseDown"=>'picker($(this));',
	 "onChange"=>'$("#buttonApply_'.$planId.'").attr("class", "buttonApplyActive");'
	 ));
	 $formEdit->getElement("begins")->setAttrib("id","begins".$planId);
	 //		$formEdit->getElement("begins")->setValue($info["begins"]);
	 $formEdit->addElement("text","ends",array(
	 "class"=>"typic_input",
	 "value"=>$info["ends"],
	 "onMouseDown"=>'picker($(this));',
	 "onChange"=>'$("#buttonApply_'.$planId.'").attr("class", "buttonApplyActive");'
	 ));
	 $formEdit->getElement("ends")->setAttrib("id","ends".$planId);
	 if (count ($rows)<1)
	 // создадим пустые дисциплины
	 {
	 $rows=array();
	 $rows[0]=array(
	 "id"=>'',
	 "planid"=>$planId,
	 "discipline"=>"",
	 "zachetCount"=>"",
	 "examCount"=>'',
	 "kontrCount"=>'',
	 "kursWorkCount"=>'',
	 "kursProjCount"=>'',
	 "disTitle"=>'',
	 );
	 }

	 $i=0;
	 foreach ($rows as $key=>$r)
	 {
	 $varname="discipline".$i;
	 $disciplinesList=$this->createSelectList($varname,$disciplines,"",$r["discipline"]);
	 $formEdit->addElement($disciplinesList);
	 $this->formElementToArray($formEdit,$varname,"discipline");
	 //				$formEdit->getElement($varname)->setName("discipline")->setIsArray(true);

	 $varname="zachetCount".$i;
	 $formEdit->addElement("text",$varname,array("class"=>"inputSmall","value"=>$r["zachetCount"]));
	 $this->formElementToArray($formEdit,$varname,"zachetCount");
	 $varname="examCount".$i;
	 $formEdit->addElement("text",$varname,array("class"=>"inputSmall","value"=>$r["examCount"]));
	 $this->formElementToArray($formEdit,$varname,"examCount");
	 $varname="kontrCount".$i;
	 $formEdit->addElement("text",$varname,array("class"=>"inputSmall","value"=>$r["kontrCount"]));
	 $this->formElementToArray($formEdit,$varname,"kontrCount");
	 $varname="kursWorkCount".$i;
	 $formEdit->addElement("text",$varname,array("class"=>"inputSmall","value"=>$r["kursWorkCount"]));
	 $this->formElementToArray($formEdit,$varname,"kursWorkCount");
	 $varname="kursProjCount".$i;
	 $formEdit->addElement("text",$varname,array("class"=>"inputSmall","value"=>$r["kursProjCount"]));
	 $this->formElementToArray($formEdit,$varname,"kursProjCount");

	 $i++;
	 }

	 $this->view->disciplines=$rows;

	 $this->view->formEdit=$formEdit;
	 $out["formEditWrap".$planId]=$this->view->render($this->_request->getControllerName().'/_EditForm.phtml');

	 $this->view->out=$out;

	 }
	 */
	public function plandeleteAction()
	{
		$filter = new Zend_Filter_StripTags();
		$planid= (int)$this->_request->getPost('id');
		$confirmWord=$this->_request->getPost('confirmWord');
		$confirmWord=$filter->filter($confirmWord);

		$planInfo=$this->data->studyPlans_getInfo($planid);
		// наш план? - совпадают отделени и спец.
		$chkPlan=(array_key_exists($planInfo["gosparam"],$this->gosParams) );

		// есть дисциплины ? вернет FALSE если нету дисциплин
		$chkDis=$this->data->studyPlans_getDisciplines($planid);

		if ($confirmWord === $this->confirmWord && $planid>1 && $chkPlan && !$chkDis)
		{
			$this->data->studyPlans_deletePlan($planid);
		}

		$this->_redirect($this->redirectLink);
	}

	public function plannewAction()
	{
		$filter = new Zend_Filter_StripTags();
		$kurs= (int)$this->_request->getPost('kurs');
		$kList=$this->kursy();
		$kurs=($kurs <1 || !array_search($kurs,$kList))
		?	1
		:	$kurs;
		$semestr= (int)$this->_request->getPost('semestr');
		$semestr=($semestr>2||$semestr<1)
		?	1
		:	$semestr;
		$gosparam=intval($this->session->gosparam)<=0?$this->gosParamsDef["id"]:intval($this->session->gosparam);
		$osnov=intval($this->session->osnov)<=0?1:intval($this->session->osnov);
		$studyYearStart=intval($this->session->studyYearStart)<=0?$this->studyYear_now["start"]:intval($this->session->studyYearStart);
		$studyYearEnd=$studyYearStart+1;
// 		$division=$this->currentDivision;

		// @TODO проверка инетрвалов дат и их корректности
		// @FIXME проверка еси срок окончания ДО срока начала
		$begins = $filter->filter($this->_request->getPost('begins'));
		$begins=$this->date_DMY2array($begins);
		$ends= $filter->filter($this->_request->getPost('ends'));
		$ends=$this->date_DMY2array($ends);
		// лень было переделывать на новое :)
		$beginMonth = $begins["month"];
		$endMonth = $ends["month"];
		$beginDay= $begins["day"];
		$endDay= $ends["month"];
		$beginYear= $begins["year"];
		$endYear= $ends["year"];

		// некоторые элементарные проверки на корректность
		// @TODO заюзать функцию checkdate
		if ($beginYear < $studyYearStart || $beginYear>$studyYearEnd) $beginYear=$studyYearStart;
		if ($endYear < $studyYearStart || $endYear>$studyYearEnd) $endYear=$studyYearStart;
		if ($beginMonth< 1 || $beginMonth>12) $beginMonth=1;
		if ($endMonth< 1 || $endMonth>12) $endMonth=1;
		if ($beginDay< 1 || $beginDay>31) $beginDay=1;
		if ($endDay< 1 || $endDay>31) $endDay=1;
		// сколько дней в месяце
		$days_numBegin=cal_days_in_month(CAL_GREGORIAN,$beginMonth,$studyYearStart);
		$days_numEnd=cal_days_in_month(CAL_GREGORIAN,$endMonth,$studyYearEnd);

		$beginDay=$beginDay>$days_numBegin?$days_numBegin:$beginDay;
		$endDay=$endDay>$days_numEnd?$days_numEnd:$endDay;

		// @FIXME проверка есть ли уже на этом курсе планы?
		// если есть на данном семестре, курсе, спец., отдел. форме обуч
		// сообщить и фигушки
		$check=$this->data->studyPlans_findPlanCount($kurs,$gosparam,$semestr,$osnov,$studyYearStart);
		if ($check>0)
		{
			$this->_redirect($this->redirectLink);
		}
		else
		{
			// вот теперь бум добавлять запись :)
			$begins=$beginYear."-".$beginMonth."-".$beginDay;
			$ends=$endYear."-".$endMonth."-".$endDay;
			$data=array(
		"begins"=>$begins,
		"ends"=>$ends,
		"kurs"=>$kurs,
		"semestr"=>$semestr,
		"gosParams"=>$gosparam,
// 		"spec"=>$spec,
// 		"division"=>$division,
		"osnov"=>$osnov,
		"studyYearStart"=>$studyYearStart		
			);
			$id=$this->data->studyPlans_newPlan($data);
			$this->_redirect($this->redirectLink);



		}
	}

	public function disciplinedelAction()
	{
		$filter = new Zend_Filter_StripTags();
		$confirmWord=$this->_request->getPost('confirmWord');
		$confirmWord=$filter->filter($confirmWord);

		$planId = (int)$this->_request->getPost('id',0);
		$planInfo=$this->data->studyPlans_getInfo($planId);
		// наш план? - совпадают отделени и спец.
		$chkPlan=(array_key_exists($planInfo["gosparam"],$this->gosParams) );
		// дисциплина
		$discipline = (int)$this->_request->getPost('discipline',0);

		// если план наш и указана дисциплина
		if ($chkPlan && $discipline>0 && $confirmWord === $this->confirmWord)
		{
			$this->data->studyPlans_deletePlanDiscipline($planId,$discipline);
		}
		$this->_redirect($this->redirectLink);

	}

	public function disciplineeditAction()
	{
		$filter = new Zend_Filter_StripTags();
		$planId = (int)$this->_request->getPost('id',0);
		$planInfo=$this->data->studyPlans_getInfo($planId);
		// наш план? - совпадают отделени и спец.
		$chkPlan=(array_key_exists($planInfo["gosparam"],$this->gosParams) );
		// дисциплина выбранная из списка
		$discipline = (int)$this->_request->getPost('discipline',0);
		// дисциплина которую меняют
		$discipline_old = (int)$this->_request->getPost('discipline_old',0);

		$filter2 = new Zend_Filter_LocalizedToNormalized(array("date_format"=>"d-M-Y"));

		//		for ($i = 1; $i <= 3; $i++)
		//		{
		//			$_date=$filter2->filter($this->_request->getPost('examdate'.$i));
		//			$chkDate=isset($_date["month"]) && checkdate($_date["month"],$_date["day"],$_date["year"]);
		//			if ($chkDate)$examdate[$i]=$_date;
		//		}

		//	если уже есть в БД и описана данная дисциплина - все её данные заменятся
		//	ибо сначала удалится все что относится к дисциплине, а потом добавится
		//	предупреждение выдавать будет JS


		// если план наш и указана дисциплина
		if ($chkPlan && $discipline>0)
		{
			// все формы вых. контроля
			$controls=$this->data->getInfoForSelectList("studoutcontrols","1 ORDER BY title");
			//			$contrCount=array();
			$data=array();
			foreach ($controls as $coID => $title)
			{
				$_param= (int)$this->_request->getPost('contrCount'.$coID,0);
				if ($_param>0 && !is_null($_param))
				{
					//					$contrCount[$coID]=$_param;
					$data[$coID]=array(
					"planid"=>$planId,
					"discipline"=>$discipline,
					"outControl"=>$coID,
					"contrCount"=>$_param
					);
					// еси экзамен и указаны даты
					//					if ($coID==7 && !empty($examdate))
					//					{
					//						foreach ($examdate as $k=>$date)
					//						{
					//							$_data["eventdate".$k]=$date["year"]."-".$date["month"]."-".$date["day"];
					//						}
					//						$data[$coID]=array_merge($data[$coID],$_data);
					//					}
				}
			}
			//			$logger->log($data, Zend_Log::INFO);
			//			echo "<pre>".print_r($data,true)."</pre>";

			if(count($data)>0) $affectd=$this->data->studyPlans_refreshPlanDiscipline($planId,$data,$discipline_old);
		}
		$this->_redirect($this->redirectLink);

		//		$logger=Zend_Registry::get("logger");
		//				$logger->log($affectd, Zend_Log::INFO);
		//		$logger->log($data, Zend_Log::INFO);
		//				$logger->log($examdate3, Zend_Log::INFO);
		;
	}

	public function editdatesAction()
	{
		//										$logger=Zend_Registry::get("logger");
		$filter = new Zend_Filter_StripTags();
		$begins = $filter->filter($this->_request->getPost('begins'));
		//		var_dump($begins);
		$begins=$this->date_DMY2array($begins);
		$ends= $filter->filter($this->_request->getPost('ends'));
		$ends=$this->date_DMY2array($ends);
		// корректны даты?
		$chkBegin=checkdate($begins["month"],$begins["day"],$begins["year"]);
		$chkEnd=checkdate($ends["month"],$ends["day"],$ends["year"]);
		//								$logger->log($begins, Zend_Log::INFO);
		//								$logger->log($ends, Zend_Log::INFO);
		$planId = (int)$this->_request->getPost('id');

		$planInfo=$this->data->studyPlans_getInfo($planId);
		// наш план? - совпадают отделени и спец.
		$chkPlan=(array_key_exists($planInfo["gosparam"],$this->gosParams) );
		// элеметнарщина верна?
		if ($chkBegin && $chkEnd && $chkPlan)
		{
			$dates=array(
			"begins"=>$begins["year"]."-".$begins["month"]."-".$begins["day"],
			"ends"=>$ends["year"]."-".$ends["month"]."-".$ends["day"]			
			);
			//			$logger->log($dates, Zend_Log::INFO);
			//			$logger->log($dates, Zend_Log::INFO);
			//			var_dump($dates);
			$this->data->studyPlans_editPlanDates($planId,$dates);

		}

		$this->_redirect($this->redirectLink);


	}
	/*
	 public function plansaveAction()
	 {
	 // очистим вывод
	 $this->view->clearVars();
	 $this->view->baseLink=$this->baseLink;
	 $this->view->baseUrl = $this->_request->getBaseUrl();
	 $this->view->iconpath= $this->view->baseUrl."/"."public"."/"."images"."/";
	 $filter = new Zend_Filter_StripTags();

	 $planId = (int)$this->_request->getPost('id');
	 // @TODO проверка инетрвалов дат и их корректности
	 $begins = $filter->filter($this->_request->getPost('begins'));
	 $begins=$this->date_DMY2YMD($begins);
	 $ends= $filter->filter($this->_request->getPost('ends'));
	 $ends=$this->date_DMY2YMD($ends);
	 $disciplines = $this->_request->getPost('disciplines');
	 $zachetCounts = $this->_request->getPost('zachetCounts');
	 $examCounts = $this->_request->getPost('examCounts');
	 $kontrCounts= $this->_request->getPost('kontrCounts');
	 $kursProjCounts = $this->_request->getPost('kursProjCounts');
	 $kursWorkCounts = $this->_request->getPost('kursWorkCounts');
	 if ($planId<1) return;
	 // узнаем детали плана
	 $info=$this->data->studyPlans_getInfo($planId);
	 // если такого нету - ничаво
	 if (count($info)<1) return;
	 // подготовим массив для базы
	 $discDetails=array();
	 $k=0;
	 foreach ($disciplines as $d)
	 {
	 if ($d<1) continue;
	 $discDetails[]=array(
	 "planid"=>$planId,
	 "discipline"=>(int)$d,
	 "zachetCount"=>(int)$zachetCounts[$k],
	 "examCount"=>(int)$examCounts[$k],
	 "kontrCount"=>(int)$kontrCounts[$k],
	 "kursProjCount"=>(int)$kursProjCounts[$k],
	 "kursWorkCount"=>(int)$kursWorkCounts[$k],
	 );
	 $k++;
	 }
	 $this->data->studyPlans_savePlan($planId,$discDetails,array("begins"=>$begins,"ends"=>$ends));
	 //		$this->view->disc=$discDetails;
	 // передать это все модели

	 }
	 */
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

	/** создает массив со значениями от $start до $end
	 * @param integer $start
	 * @param integer $end
	 * @return array
	 */
	private function arrayFilled($start,$end)
	{
		$result=array();
		for ($i = $start; $i <= $end; $i++)
		{
			$result[$i]=$i;
		}
		return $result;
	}
	/**
	 * построить список выбора
	 * @param string $elemName
	 * @param array $src data ID=>value
	 * @param integer $defaultValue selected option
	 * @return Zend_Form_Element_Select
	 */
	private function createSelectList($elemName,$src,$nullTitle="Выберите",$selected=false)
	{
		Zend_Loader::loadClass('Zend_Form_Element_Select');
		$result = new Zend_Form_Element_Select($elemName);
		$result	->setOptions(array("multiple"=>""));
		if ($nullTitle!=='') $result->addMultiOption(0,$nullTitle);


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

	private function sessionUpdate($params)
	{
		$filter = new Zend_Filter_StripTags();
		// обновим сессию
		foreach ($params as $param=>$value)
		{
			// отфильтруем
			switch ($param)
			{
				case "studyYearStart":
				case "gosparam":
				case "osnov":
					$_value=$value;
					if (is_null($_value)) $_value=0;
					$_value=intval($_value);
					break;

				default:
					$_value=$value;
					break;
			}
			$this->session->$param=$_value;
		}
	}


	private function formElementToArray(&$form,$varname,$newName)
	{
		$form->getElement($varname)->setName($newName)->setIsArray(true);

	}

	private function buildCriteria($in=null)
	{
		if (is_null($in)) $in=$this->session->getIterator();
		$criteria['studyYearStart']=( !isset($in['studyYearStart']) || $in['studyYearStart'] <= 0)
		?	$this->studyYear_now["start"]
		:	$in['studyYearStart']
		;

		//		$criteria["studyYearStart"]=;

		$criteria['gosparam']=( !isset($in['gosparam']) || $in['gosparam'] <= 0)
		?	$this->gosParamsDef["id"]
		:	$in['gosparam'];

		//		$criteria['kurs']=$in['kurs']<1?1:$in['kurs'];

		$osnDef=$this->getFirstElem($this->osnovs);
		$criteria['osnov']=(!isset($in['osnov']) || $in['osnov'] <= 0 )
		?	$osnDef["key"]
		:	$in['osnov'];

		return $criteria;
	}
	/*
	 private function preparePlan_OldVersion($list)
	 {
	 if (count($list)<1) return;
	 //		$cur=0;
	 $result=array();
	 foreach ($list as $key => $row)
	 {
	 $result[$row["kurs"]][$row["semestr"]]=$row;

	 }
	 return $result;

	 }
	 */
	private function preparePlan($list)
	{
		if (count($list)<1) return;
		$result=array();
		foreach ($list as $key => $row)
		{
			$result[$row["kurs"]][$row["semestr"]]=$row;
			$dis=$this->data->studyPlans_getDisciplines($row["id"]);
			if ($dis)
			{
				$disList=$this->prepareDisciplines($dis);
				$result[$row["kurs"]][$row["semestr"]]["disciplines"]=$disList;
			}
		}
		return $result;

	}

	/**
	 * @param array $list
	 * @return array [disciplineId][outCountolID]=details
	 * считается что у одной дисциплины несколько форм выходного контроля
	 */
	private function prepareDisciplines($list)
	{
		$result=array();
		foreach ($list as $item)
		{
			$result[$item["disTitle"]][$item["outControl"]]=$item;
		}
		return $result;
	}

	private function createForm_Filter($criteria)
	{
		$form=new Formochki();
		$textOptions=array('class'=>'inputSmall');

		$form->setAttrib('name','filterForm');
		$form->setAttrib('id','filterForm');
		$form->setMethod('POST');
		$form->setAction($this->baseLink);

		unset($_goss);
		foreach ($this->gosParams as $key => $info) {
			$_goss[$key]=$info["gosTitle"];
		}
		$specsList=$this->createSelectList("gosparam",$_goss,"",$criteria['gosparam']);
		//		$form->addElement($specsList);
		$form->addElement($specsList);

		$yearz=$this->data->studyPlans_getYearInterval($criteria["studyYearStart"]);
		$yearList=$this->data->studyPlans_buildYearList($yearz);
		$yearSelect=$this->createSelectList("studyYearStart",$yearList,"",$criteria["studyYearStart"]);
		$form->addElement($yearSelect);

		//		$osnovs=$this->data->getInfoForSelectList("osnov","");
		$osnovList=$this->createSelectList("osnov",$this->osnovs,"",$criteria["osnov"]);
		$form->addElement($osnovList);
		return $form;

	}


	private function createForm_editPlanDates()
	{
		$form=new Formochki();
		$form->setAttrib('name','formEditDates');
		$form->setAttrib('id','formEditDates');
		$form->setMethod('POST');
		$form->setAction($this->baseLink."/"."editdates");
		$form->addElement("hidden","id",array("value"=>0));
		$form->addElement("text","begins",array(
		"class"=>"typic_input",
		//		"value"=>$info["begins"],
		"onMouseDown"=>'picker($(this));'		
		));
		//		$form->getElement("begins")->setAttrib("id","beginsNew");
		//		$formEdit->getElement("begins")->setValue($info["begins"]);
		$form->addElement("text","ends",array(
		"class"=>"typic_input",
		//		"value"=>$info["ends"],
		"onMouseDown"=>'picker($(this));'
		));
		//		$form->getElement("ends")->setAttrib("id","endsNew");
		$form->addElement("submit","OK",array("class"=>"apply_text"));
		$form->getElement("OK")->setName("Подтвердить");

		return $form;
	}

	private function createForm_editDiscipline($action)
	{
		$form=new Formochki();
		$form->setAttrib('name','formEditDis');
		$form->setAttrib('id','formEditDis');
		$form->setMethod('POST');
		$form->setAction($this->baseLink."/".$action);
		$form->addElement("hidden","id",array("value"=>0));
		$form->addElement("hidden","discipline_old",array("value"=>0));

		// выбор дисциплины
		$disciplines=$this->data->getDiscipliesStudOrderedByKaf();
		$disList=$this->createSelectList("discipline",$disciplines,"Выберите дисциплину");
		$form->addElement($disList);
		// выбор количество для каждой формы вых. контроля
		$controls=$this->data->getInfoForSelectList("studoutcontrols","1 ORDER BY title");
		foreach ($controls as $coID => $title)
		{
			//			$_list=$this->createSelectList("outContol".$coID,);
			//			$form->addElement("text","outContol".$coID,array("class"=>"inputSmall"));
			$form->addElement("text","contrCount".$coID,array("class"=>"inputSmall"));
		}
		// поля - количество для данной формы вых. контроля

		//		// даты для экзаменов
		//		$form->addElement("text","examdate1",array(
		//		"class"=>"typic_input",
		//		//		"value"=>$info["begins"],
		//		"onMouseDown"=>'picker($(this));'
		//		));
		//		$form->addElement("text","examdate2",array(
		//		"class"=>"typic_input",
		//		//		"value"=>$info["begins"],
		//		"onMouseDown"=>'picker($(this));'
		//		));
		//		$form->addElement("text","examdate3",array(
		//		"class"=>"typic_input",
		//		//		"value"=>$info["begins"],
		//		"onMouseDown"=>'picker($(this));'
		//		));


		$form->addElement("submit","OK",array("class"=>"apply_text"));
		$form->getElement("OK")->setName("Добавить");

		return $form;
	}

	private function createForm_deleteDiscipline()
	{
		$form=new Formochki();
		$form->setAttrib('name','formDisDelete');
		$form->setAttrib('id','formDisDelete');
		$form->setMethod('POST');
		$form->setAction($this->baseLink."/disciplinedel");
		$form->addElement("hidden","id",array("value"=>0));
		$form->addElement("hidden","discipline",array("value"=>0));
		$form->addElement("text","confirmWord",array("class"=>"typic_input"));

		$form->addElement("submit","OK",array("class"=>"apply_text"));
		$form->getElement("OK")->setName("Подтвердить");

		return $form;
	}

	private function createForm_NewPlan($gos,$osnov,$studyYearStart,$semestr)
	{
		// форма добавления нового плана, изначально скрыта
		$formNew=new Formochki();
		$formNew->setAttrib('name','formNew');
		$formNew->setAttrib('id','formNew');
		$formNew->setMethod('POST');
		$formNew->setAction($this->baseLink."/"."plannew");
		// пользователь выбирает тока курс и время действия плана
		// остальное все в HIDDEN и берется из сессии
		$formNew->addElement("hidden","gosparam",array("value"=>$gos));
		$formNew->addElement("hidden","osnov",array("value"=>$osnov));
		$kursy=$this->kursy();
		$kursList=$this->createSelectList("kurs",$kursy,"Задайте курс");
		$formNew->addElement($kursList);

		$semestrList=$this->createSelectList("semestr",array("1"=>1,"2"=>2),"");
		$formNew->addElement($semestrList);

		$formNew->addElement("text","begins",array(
		"class"=>"typic_input",
		//		"value"=>$info["begins"],
		"onMouseDown"=>'picker($(this));'		
		));
		$formNew->getElement("begins")->setAttrib("id","beginsNew");
		//		$formEdit->getElement("begins")->setValue($info["begins"]);
		$formNew->addElement("text","ends",array(
		"class"=>"typic_input",
		//		"value"=>$info["ends"],
		"onMouseDown"=>'picker($(this));'
		));
		$formNew->getElement("ends")->setAttrib("id","endsNew");


		//		$studyYearStart
		//array_merge()
		//		$formNew->addElement("text","begins",array("class"=>"typic_input"));
		//		$formNew->addElement("text","ends",array("class"=>"typic_input"));
		$formNew->addElement("submit","OK",array("class"=>"apply_text"));
		$formNew->getElement("OK")->setName("добавить");
		return $formNew;
	}

	private function createForm_importxlskostroma($gos,$osnov)
	{
		// форма добавления нового плана, изначально скрыта
		$formNew=new Formochki();
		$formNew->setAttrib('name','importxlskostromaForm');
		$formNew->setAttrib('id','importxlskostromaForm');
		$formNew->setAttrib('enctype', 'multipart/form-data');
		$formNew->setMethod('POST');
		$formNew->setAction($this->baseLink."/"."importxlskostroma");
		// пользователь выбирает тока курс и время действия плана
		// остальное все в HIDDEN и берется из сессии

		$formNew->addElement("file","file",array("label"=>"Загрузка XLS файла"));
		$formNew->getElement("file")
		->addValidator('Extension', false, 'xls')
		->addValidator('Count', false, 1)
		->setRequired(true)
		->addValidator('NotEmpty');

		$formNew->addElement("hidden","gosparam",array("value"=>$gos));
		$formNew->addElement("hidden","osnov",array("value"=>$osnov));


		$formNew->addElement("submit","OK",array("class"=>"apply_text"));
		$formNew->getElement("OK")->setName("Загрузить");
		return $formNew;
	}

	/**
	 * преобразование даты из ГОД-МЕСЯЦ-ДЕНЬ в ДЕНЬ-МЕСЯЦ-ГОД
	 *
	 * @param string $text
	 * @return string
	 */
	private function date_YMD2DMY($text)
	{

		preg_match ( "|(\d{4}).{1}(\d{2}).{1}(\d{2})|Ui", $text, $edu_d );
		$out = $edu_d [3] . "-" . $edu_d [2] . "-" . $edu_d [1];
		return $out;
	}
	/**
	 * преобразование даты из ДЕНЬ-МЕСЯЦ-ГОД в ГОД-МЕСЯЦ-ДЕНЬ
	 *
	 * @param string $text
	 * @return string
	 */
	private function date_DMY2YMD($text)
	{
		// дата выдача док. об обазовании - переобразуем в день-месяц-год
		preg_match ( "|(\d{2}).{1}(\d{2}).{1}(\d{4})|Ui", $text, $edu_d );
		$out = $edu_d [3] . "-" . $edu_d [2] . "-" . $edu_d [1];
		return $out;
	}

	private function date_YMD2array($text)
	{
		preg_match ( "|(\d{4}).{1}(\d{2}).{1}(\d{2})|Ui", $text, $edu_d );
		$out["year"]=$edu_d [3];
		$out["month"]=$edu_d [2];
		$out["day"]=$edu_d [1];
		return $out;
	}

	private function date_DMY2array($text)
	{
		preg_match ( "|(\d{2}).{1}(\d{2}).{1}(\d{4})|Ui", $text, $edu_d );
		$out["year"]=$edu_d [3];
		$out["month"]=$edu_d [2];
		$out["day"]=$edu_d [1];
		return $out;
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

}