// фильтр заявок подсчет количества выбранных CHECKBOX
$(function()
		{
	$("#filterForm input:checkbox").change(function(){
		// выбрано меньше 1?
		if ($("#filterForm input:checkbox:checked").size()<1) 
			// выделим все
			$("#filterForm input:checkbox").attr("checked","checked");
	});
		}
		);

$(function() {
$( "input[type=submit], button" )
.button();
//.click(function( event ) {
//event.preventDefault();
//});
});
function typeAdd()
{
	$("#formAddWrapper").togglePopup();	
}
function typeEdit(id)
{
	$("#formEditWrapper").togglePopup();
	var tit=$("#item" + id + " .Col_2").text();
	var tble=$("#item" + id + " .Col_3").text();
	$("#editForm #id").val(id);
	$("#editForm #title").val(tit);
	$("#editForm #tbl").val(tble);

}
function typeDel(id)
{
	$("#formDelWrapper").togglePopup();
	$("#delForm #id").val(id);
	
}

function unitAdd() 
{
	$("#addUnitWrapper").togglePopup(); 
}

// запрос и получение формы подачи заявки
function ticketNewForm() 
{
	var params="";
	var href="/helpdesk/ask/newform";
	// send data
	sendPostAdvanced(href,params,function(returned) {
		// return messages
		var resp=$.parseJSON(returned.responseText);
		// show messages
		$("#ticketNewWrapper").replaceWith(resp.formNewR);
		// reRender button
		$("#newForm input[type=submit], button").button();
		$("#ticketNewWrapper").togglePopup();
	});
	
}

// отправка данных формы и получение сообщений
function ticketNewSend() 
{
	// collect data
	var params ={
			fio:		$("#newForm #fio").val(),
			dep:		$("#newForm #dep").val(),
			place:		$("#newForm #place").val(),			
			typeid:		$("#newForm #typeid").val(),			
			subject:	$("#newForm #subject").val(),			
			problem:	$("#newForm #problem").val()			
	};
	var href="/helpdesk/ask/new";
	// send data
	sendPostAdvanced(href,params,function(returned) {
		// return messages
		var resp=$.parseJSON(returned.responseText);
		// show messages
		$("#newForm").replaceWith(resp.formNewR);
		// reRender button
		$("#newForm input[type=submit], button").button();
		$("#popup #formMsg").html(resp.formMsg).fadeIn();
		// form is accepted 
		if (resp.status>0)
			{
			setTimeout(function() {document.location.reload(true);},1800);
			}
	});
	
}


function ticketView(id)
{
	var params = {
			id : id
		};	
	var href = "/helpdesk/ask/view/id/"+id;	
	sendPostAdvanced(href,params,function(returned) {
		var resp=$.parseJSON(returned.responseText);
		// и покажем
		$("#ticketDetail").html(resp.details);
		$("#ticketDetailWrapper").togglePopup(); 
	});
	
}

function toggleWrong(id) 
{
	var params = {
			id : id
		};	
	var href = "/helpdesk/ask/wrongticket/id/"+id;	
	sendPostAdvanced(href,params,function(returned) {
		var resp=$.parseJSON(returned.responseText);
		// и покажем
		var trashClass=resp.trashClass;
		var trashMsg=resp.trashMsg;
		$("#trashMsg").html(trashMsg);
		$("#trashButton").attr("class",trashClass);
		// обновить список заявок
		// если успех с данными - перезагрузим страницу
		document.location.reload(true);		
		
	});
	
}

function toggleClose(id) 
{
	var params = {
			id : id
	};	
	var href = "/helpdesk/ask/close/id/"+id;	
	sendPostAdvanced(href,params,function(returned) {
		var resp=$.parseJSON(returned.responseText);
		// и покажем
		
		var formMsg=resp.formMsg;
		$("#formMsg").html(formMsg).fadeIn('fast');
		$("#msg input[type=submit], button").button();		
		// обновить список заявок
		// если успех с данными - перезагрузим страницу
		// form is accepted 
		if (resp.status>0)
			{
			setTimeout(function() {document.location.reload(true);},1800);
			}

//		document.location.reload(true);		
		
	});
	
}

function ticketVerdictAdd() 
{
	var params ={
			id:			$("#verdictForm #id").val(),
			verdict:	$("#verdictForm #verdict").val()			
	};
//	var href=$("#verdictForm").attr("action");
	var href="/helpdesk/ask/verdictadd";
	
	sendPostAdvanced(href,params,function(returned) {
		var resp=$.parseJSON(returned.responseText);
		// и покажем
		$("#verdicts").html(resp.verdicts);
		$("#verdictFormWrapper").toggleClass('hidden'); 
	});
	

}

function showUnit(id)
{
	var unitid="unitid_"+id;
	// проверка - отображается ли уже
	var tt=$("#"+unitid+"_detail").attr("style");
	if (tt==="display: table-row;")
		{
			//очистим и скроем
			$("#"+unitid+"_detail"+" td").html("");
			$("#"+unitid+"_detail").fadeOut(0);
		}
	else
		{
	// если нет - отобразим	
	var href = "/helpdesk/departments/show/id/"+id;
	var params = {
			id : id
		};	
	sendPostAdvanced(href,params,function(returned) {
		var resp=$.parseJSON(returned.responseText);
		// и покажем
		$("#"+unitid+"_detail"+" td").html(resp.details);
		$("#tabs_"+id).tabs();
		$("#"+unitid+"_detail").fadeIn(0);
	});
	
		}
}

function unitAssignForm(id)
{
	var href="/helpdesk/ask/unitassign/id/"+id;
//	var unit=$("#assignForm #unit").val();
	var params={
			id:id
	};
	// скрыто? подгрузим
	if ($("#assignFormWrapper").hasClass("hidden"))
		{
		sendPostAdvanced(href,params,function(returned) {
			var resp=$.parseJSON(returned.responseText);
			// и покажем
			$("#assignFormWrapper").replaceWith(resp.assignFormWrapper);
			$("#assignFormWrapper").toggleClass("hidden");
		});
		
		}
	// иначе спрячем
	else {
		
		$("#assignFormWrapper").html("");
		$("#assignFormWrapper").toggleClass("hidden");
	}

}
function unitAssign()
{
	var id=$("#assignForm #id").val();
	var unit=$("#assignForm #unit").val();
	var params={
			id:id,
			unit:unit
	};
	var href="/helpdesk/ask/unitassign/id/"+id;
//	// скрыто? подгрузим
//	if ($("#assignFormWrapper").hasClass("hidden"))
//	{
		sendPostAdvanced(href,params,function(returned) {
			var resp=$.parseJSON(returned.responseText);
//			// и покажем
			$("#assignInfo").replaceWith(resp.assignInfo);
			$("#assignFormWrapper").toggleClass("hidden");
		});
//		
//	}
//	// иначе спрячем
//	else {
//		
//		$("#assignFormWrapper").html("");
//		$("#assignFormWrapper").toggleClass("hidden");
//	}
	
}

function moveUnit(selected,id)
{
	$("#moveUnitWrapper").togglePopup(); 
	// перенос выбранных
	if (selected)
		{
		// получим выбранные ID
		// уберем в форме hidden'ы
		$("#MoveUnit input:hidden").remove();
		$('#unitslist .unitid input:checked').each(function(){
			var elem=$(this).clone();
			// переделаем в HIDDEN
			elem.removeAttr("type");
			elem.removeAttr("checked");
			elem.attr("type","hidden");
			// добавим в форму
			elem.appendTo("#MoveUnit");
		});
		
		}
	// перенос текущей позиции
	else
		{
		// т.к. #MoveUnit - Это копия внутри opaco - То просто установим значение
			$("#MoveUnit #id").val(id);
		}
	}
