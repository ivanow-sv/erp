function picker(elem) {
	// выясним интервал текущего учебного года
	// без этих двух - работать не будет нормально
	var starts = $("#studyYearStart").val();
	// var completes=$("#studyYearEnd").text();
	var completes = Number(starts) + 1;
	// alert (completes);
	$(elem).datepicker({
		showOtherMonths : true,
		selectOtherMonths : true,
		regional : 'ru',
		dateFormat : 'dd-mm-yy',
		// установим лимиты выбора дат
		// не раньше 1 сентября начала уч. гола
		minDate : new Date(starts, '8', '1'),
		// и не позднее 31 августа конца учебного года
		maxDate : new Date(completes, '7', '31')

	});

}
function sendStudentsToLesson() {
	var ids = new Array();
	$("#peopleList").find("input[name^='id']:checkbox:checked").each(
			function() {
				ids.push($(this).val());
			});
	var untilDate = $("#untilDate").val();
	var time = $("#para").val();
	var discipline = $("#discipline").val();
	var params = {
		ids : ids,
		time : time,
		discipline : discipline,
		untilDate : untilDate
	};
	// кто будет обрабатывать
	var href = "/dekanat/studyprocess/studentprocess";
	// теперь их отправим
	sendPostAdvanced(href, params);
}

function pickerTime(elem) {
	$(elem).timepicker({
		timeOnlyTitle : 'Выберите время',
		timeText : 'Время',
		hourText : 'Часы',
		minuteText : 'Минуты',
		secondText : 'Секунды',
		currentText : 'Сейчас'
	});
}

// получение формы создания листа
function attListFormAdd()
{
	var group = $("#filterForm #group").val();
	if (group > 0)
	{
		var params={
				group:group
		};
		var href="/dekanat/attendance/formnewlist";
		sendPostAdvanced(href,params,function(returned){
			var resp=$.parseJSON(returned.responseText);
			var form=resp.out.form;
			// если есть форма уже на странице
			if ($("#formAddWListrapper"))
			{
				$("#formAddWListrapper").remove();
				$('#workspace').after(form);
			}
			else $('#workspace').after(form);
			// отобразим форму
			$("#formAddWListrapper").togglePopup();
			// проставим группу и курс в форму
			var kurs = $("#filterForm #kurs").val();
			var group = $("#filterForm #group option:selected").val();

			// скорректируем форму, подставим курс и группу
			$("#addForm #group").val(group);
			$("#addForm #kurs").val(kurs);
			
		});
//		location.replace("attendance");		
	}

	else
		alert("Выберите группу");
	
}
// получение формы создания выходного контроля
function ocontrolFormAdd()
{
	var group = $("#filterForm #group").val();
	var kurs = $("#filterForm #kurs").val();
	var ids = new Array();
	$("#peopleList #id:checked").each(
			function(){
				ids.push($(this).val());				
			});
	if ( ids.length > 0 )
	{
		var params={
				group:group,
				kurs:kurs
		};
		var href="/dekanat/outcontrol/formadd";
		sendPostAdvanced(href,params,function(returned){
			var resp=$.parseJSON(returned.responseText);
			var form=resp.out.form;
			// если есть форма уже на странице
			if ($("#formAddWrapper"))
			{
				$("#formAddWrapper").remove();
				$('#workspace').after(form);
			}
			else $('#workspace').after(form);
			// скорректируем форму, подставим курс и группу
			$("#addForm #group").val(group);
			$("#addForm #kurs").val(kurs);
			// добавим пользователей
			// с каждым выбранным
			$("#peopleList #id:checked").each(
					function(){
						// возьмем его
						var elem=$(this).clone();
						// уберем чекбокс
						$(elem).removeAttr("checkbox");
						// сделаем скрытым
						$(elem).attr("type","hidden");
						$(elem).removeAttr("id");
						$(elem).attr("name","userid[]");
						// добавим в форму
						$("#addForm #kurs").after( $(elem));
					}
					);
			// отобразим форму
			$("#formAddWrapper").togglePopup();
			
			
		});
//		location.replace("attendance");		
	}
	
	else
		alert("Требуется выбрать студентов");
	
}
function ocontrol_listDekanChange(id)
{
	var params={id:id};
	var href = "/dekanat/outcontrol/approvechange";
	sendPostAdvanced(href, params, function(returned) {
		var resp=$.parseJSON(returned.responseText);
		$("#stateList").replaceWith(resp.elem);
		$("#formEdit #state").val(resp.listState.state);
	});

	}


// форма создания док. вых. контроля. при смене семестра подгружать нужные дисциплины плана	
function ocontrolsemChanged(semestr)
{
	//semestrchanged
	var semestr = $("#addForm #semestr").val();
	var kurs = $("#filterForm #kurs").val();
	var group = $("#filterForm #group").val();

	// отправить на север
	var params = {
			semestr : semestr,
		kurs : kurs,
		group: group
	};
	// кто будет обрабатывать 
	var href = "/dekanat/outcontrol/semestrchanged";

	sendPostAdvanced(href, params, function(returned) {
		var resp=$.parseJSON(returned.responseText);
		$("#addForm #discipline").replaceWith(resp.disciplines);
	});
	
	
	}
// форма создания док. вых. контроля. при смене семестра подгружать нужные дисциплины плана	
function ocontroldisChanged()
{
	//semestrchanged
	var discipline = $("#addForm #discipline").val();
	
	// отправить на север
	var params = {
			discipline : discipline 
	};
	// кто будет обрабатывать 
	var href = "/dekanat/outcontrol/discipchanged";
	
	sendPostAdvanced(href, params, function(returned) {
		var resp=$.parseJSON(returned.responseText);
		$("#addForm #teacher").replaceWith(resp.teacher);
//		alert(resp.disciplines);
	});
	
	
}

function personLog(id)
{
	
	// отправить на север
	var params = {
			id : id
	};
	// кто будет обрабатывать 
	var href = "/dekanat/personal/personlog";
	
	sendPostAdvanced(href, params, function(returned) {
		var resp=$.parseJSON(returned.responseText);
		$("#personProcess div.content").replaceWith(resp.personProcess);
	});
	
	
}

// проверка выбрана ли группп и отсылает к списку аттестационных листов группы
function attendance() {
	var group = $("#filterForm #group").val();
	if (group > 0)
		location.replace("attendance");
	else
		alert("Выберите группу");
	// http://erp/dekanat/attendance

}
// проверка выбрана ли группп и отсылает к списку документов выходного контроля группы
function outcontrol() {
	var group = $("#filterForm #group").val();
	if (group > 0)
		location.replace("outcontrol");
	else
		alert("Выберите группу");
	// http://erp/dekanat/attendance
	
}

function ocontrolDel(id, title) {
	$("#formDeleteWarp").togglePopup();
	$("#formDelete #id").val(id);
	$("#titleForDel").text(title);
}

function attendanceDel(id, title) {
	$("#formDeleteWarp").togglePopup();
	$("#formDelete #id").val(id);
	$("#titleForDel").text(title);
}

function attendanceEditDate(id) {
	$("#formEditDateWrapper").togglePopup();
	$("#editDateForm #id").val(id);
}

function attendanceStateForm(userid, disID) {
	$("#formStateChangeWrapper").togglePopup();
	// var state=elem.text();
	// покажем у кого меняем и какую дисц.
	var fio = $("#fio" + userid).text();
	$("#stateChangeFio").text(fio);
	var disTitle = $("#disTitleID_" + disID).text();
	$("#stateChangeDis").text(disTitle);
	var comment = $("#d" + disID + "u" + userid + " small").text();

	// скорректируем форму
	$("#formStateChange #discipline").val(disID);
	$("#formStateChange #userid").val(userid);
	$("#formStateChange #comment").val(comment);

}

function attendanceStateMassForm(userid, disID) {
	$("#formStateChangeMassWrapper").togglePopup();

	if (userid > 0) {
		var fio = $("#fio" + userid).text();
		$("#stateChangeMassFio").text(fio);
		$("#stateChangeMassDis").text("Все");
	}
	if (disID > 0) {
		var disTitle = $("#disTitleID_" + disID).text();
		$("#stateChangeMassDis").text(disTitle);
		$("#stateChangeMassFio").text("Все");
	}

	// скорректируем форму
	$("#formStateChangeMass #discipline").val(disID);
	$("#formStateChangeMass #userid").val(userid);

}

function attendanceState() {
	var id = $("#formStateChange #id").val();
	var discipline = $("#formStateChange #discipline").val();
	var state = $("#formStateChange #state").val();
	var userid = $("#formStateChange #userid").val();
	var comment = $("#formStateChange #comment").val();

	// отправить на север
	var params = {
		id : id,
		discipline : discipline,
		state : state,
		userid : userid,
		comment : comment
	};
	// кто будет обрабатывать - отсчитаем назад ID, EDIT
	var href = "/dekanat/attendance/personedit";

	sendPostAdvanced(href, params, function() {
		// скрыть форму
		$("#formStateChangeWrapper").togglePopup();
	});

}
function attendanceStateMass() {
	var id = $("#formStateChangeMass #id").val();
	var discipline = $("#formStateChangeMass #discipline").val();
	var state = $("#formStateChangeMass #state").val();
	var userid = $("#formStateChangeMass #userid").val();

	// отправить на север
	var params = {
		id : id,
		discipline : discipline,
		state : state,
		userid : userid
	};
	// кто будет обрабатывать - отсчитаем назад ID, EDIT
	var href = "/dekanat/attendance/personmassedit";

	sendPostAdvanced(href, params, function() {
		// скрыть форму
		$("#formStateChangeMassWrapper").togglePopup();
	});

}

function attendanceAdd() {
	var kurs = $("#filterForm #kurs").val();
	var group = $("#filterForm #group option:selected").val();

	$("#formAddWrapper").togglePopup();
	// скорректируем форму, подставим курс и группу
	$("#addForm #group").val(group);
	$("#addForm #kurs").val(kurs);
}

function groupAdd() {
	$("#formAddWarp").togglePopup();
	var spec = $("#filterForm #spec").val();
	var kurs = $("#filterForm #kurs").val();
	$("#addForm #spec").val(spec);
	$("#addForm #kurs").val(kurs);
}
function groupDel(id, t) {
	$("#formDeleteGroupWarp").togglePopup();
	$("#titleForDel").text(t);
	$("#deleteFormGroup #id").val(id);
}
function subGroupDel(id, t) {
	$("#formDeleteSubGroupWarp").togglePopup();
	$("#titleForDel").text(t);
	$("#deleteFormSubGroup #id").val(id);
}
function subGroupRename(id, t) {
	$("#formRenameSubGroupWarp").togglePopup();
	$("#newTitle").val(t);
	$("#renameFormSubGroup #id").val(id);
}
function groupRename(id, t) {
	$("#formRenameGroupWarp").togglePopup();
	$("#newTitle").val(t);
	$("#renameFormGroup #id").val(id);
}

function freeList() {
	var subgroup = $("#filterForm #subgroup").val();
	subgroup = Number(subgroup);
	if (subgroup <= 0) {
		alert('Неверно указана подгруппа');
		return;
	}
	var params = {
		subgroup : subgroup
	};
	// кто будет обрабатывать
	var href = "/dekanat/personal/freelist";
	// href=document.location.href+'/freelist',

	sendPostAdvanced(href, params, function() {
		$("#studentsFree").togglePopup();
	});

	// return false;
}

function pastedImport() {

	// найдем цель
	var subgroup = $("#filterForm #subgroup").val();
	if (subgroup <= 0) {
		alert('Неверно указана подгруппа');
		return;
	}
	$("#formClipboardImportWarp").togglePopup();
	// скорректируем форму
	$("#formClipboardImport #subgroupTo").val(subgroup);
}

function moveStudentsForm() {
	var idz = new Array();
	$("#peopleList #id:checked").each(function() {
		idz.push($(this).val());
	});
	// выбранные специальность, форма, курс, галка "весь фак."
	var spec = $("#filterForm #spec").val();
	var osnov = $("#filterForm #osnov").val();
	var kurs = $("#filterForm #kurs").val();
	var allfacult = $("#filterForm input:checkbox#allfacult:checked").val();

	var params = {
		ids : idz,
		spec : spec,
		osnov : osnov,
		kurs : kurs,
		allfacult : allfacult
	};

	// кто будет обрабатывать
	var href = "/dekanat/personal/move";
	// еси не выбрано
	if (idz.length <= 0 || allfacult > 0) {
		alert("Не выбраны студенты или неверны остальные критерии");
		return;
	} else
		sendPostAdvanced(href, params, function() {
			$("#formMoveWarp").togglePopup();
		});
}

function editDiscipline(planid, disciplineID) {
	$("#formEditDisciplineWarp").togglePopup();
	$("#formEditDis #id").val(planid);
	// alert (typeof(disciplineID)) ;
	if (typeof (disciplineID) == "number" || disciplineID != 0) {
		$("#formEditDis #discipline_old").val(disciplineID);
		$("#formEditDis #discipline").val(disciplineID);
		// найдем кол-во каждого вых. контроля
		var container = "p" + planid + "d" + disciplineID;
		$("#" + container + " [id^=contrCount]").each(function() {
			var n = $(this).attr("id");
			var v = $(this).html();
			if (v == '-' || v === "0000-00-00" || v === "00-00-0000")
				v = '';
			// и перенесем в форму редактирования
			$("#formEditDis #" + n).val(v);
			// contrCount10
		});
		// даты экзаменов
		$("#" + container + " [id^=examdate]").each(function() {
			var n = $(this).attr("id");
			var v = $(this).html();
			if (v == '')
				v = '';
			// и перенесем в форму редактирования
			$("#formEditDis #" + n).val(v);
			// contrCount10
		});
		// $("")
	}
	else $("#formEditDis #discipline_old").val(0);
	;
}

function removeDiscipline(planid, disciplineID) {
	$("#formDisDeleteWarp").togglePopup();
	$("#formDisDelete #id").val(planid);
	$("#formDisDelete #discipline").val(disciplineID);
}

function addSession(kurs, sesNumber, elem) {

}

function getPrivateForm(userid) {
	var params = {
		id : userid
	};

	// кто будет обрабатывать
	var href = "/dekanat/personal/privateview";
	// еси не выбрано
	/*
	 * if (id.length <=0 ) { alert("Не выбраны студенты или неверны остальные
	 * критерии"); return; } else
	 */
	sendPostAdvanced(href, params, function() {
		$("#privateDetailsWrap").togglePopup();
		// создадим табы Jquery-UI-tabs
		$("#tabs").tabs();
		$("#popup").alignCenter();
		
	});
}

/**
 * @param buttonObj DOM-эелемент по которому кликнули
 */
function savePrivateForm(buttonObj) {

	var formData = new Object();
	var formObj=buttonObj.parents("#formPrivateInfo");
//	alert ($(formObj).find("select").length);
	// соберем данные со всех SELECTED
//	$("#formPrivateInfo select").each(
	$(formObj).find("select").each(
			function(){
				formData[$(this).attr("name")]=$(this).find('option:selected').val();
			}
			);
	// соберем все данные с текстовых полей
//	$("#formPrivateInfo input:text").each(
	$(formObj).find("input:text").each(
			function(){
				formData[$(this).attr("name")]=$(this).val();
			}
			);
	// соберем все данные с HIDDEN
//	$("#formPrivateInfo input:hidden").each(
	$(formObj).find("input:hidden").each(
			function(){
				formData[$(this).attr("name")]=$(this).val();
			}
	);
	// соберем все данные TEXTAREA
//	$("#formPrivateInfo textarea").each(
	$(formObj).find("textarea").each(
			function(){
				var aaa=$(this).val();
//				alert($(this).attr("name") + " = " + aaa);
				formData[$(this).attr("name")]=aaa;
			}
	);
	
	var params = {
			formData : formData
	};
	
	// кто будет обрабатывать
	// @FIXME не будет скорее всего работать при расположениие http://DOMAIN/PATH/path/_MODULE_/_cont_
	var href = "/dekanat/personal/privatesave";
	// еси не выбрано
	/*
	 * if (id.length <=0 ) { alert("Не выбраны студенты или неверны остальные
	 * критерии"); return; } else
	 */
	sendPostAdvanced(href, params
	, function(returned) {
		$("#formMsg").show(1);
		// если скрипт сказал что все нормуль - то перезагрузим анкету
		var resp=$.parseJSON(returned.responseText);
		if (resp.ok==true){
			$("#formMsg").delay(800).fadeOut('slow');
//			location.reload(true);
		}
	});
}

function newStudentForm()
{
	// соберем данные из фильтра
	var subgroup = $("#filterForm #subgroup").val();
	subgroup = Number(subgroup);
	if (subgroup <= 0) {
		alert('Неверно указана подгруппа');
		return;
	}
	var group= Number($("#filterForm #group").val());
	var kurs= Number($("#filterForm #kurs").val());
	var gosparam= Number($("#filterForm #gosparam").val());
	var osnov= Number($("#filterForm #osnov").val());
	
	// покажем форму
	$("#formNewStudWarp").togglePopup();
	
	// заполним скрытые поля данными из фильтра 
	$("#newStudForm #subgroup").val(subgroup);
	$("#newStudForm #group").val(group);
	$("#newStudForm #kurs").val(kurs);
	$("#newStudForm #osnov").val(osnov);
	$("#newStudForm #gosparam").val(gosparam);
	
}

