 $(function() {
$( "input[type=submit], button" ).button(); 
//.click(function( event ) {
//event.preventDefault();
//});
});

function AclResdetails(resID)
{
//	var roleID=$("#role").val();
	// отправить на север
	var params = {
//			role : roleID,
			resource: resID
		};
		// кто будет обрабатывать - отсчитаем назад ID, EDIT
	var href = "/dostup/resroles/details";
	
	sendPostAdvanced(href,params,function(){
		// покажем/скроем
		$("#formWrapper").togglePopup();
	});
	
}

function roleManageShow()
{
	var id=$("#filterForm #aclgroup").val();
	var params={
			id:id,
	};
	var href = "/dostup/usr/showdetails";
	
	sendPostAdvanced(href,params,function(returned) {
		var resp=$.parseJSON(returned.responseText);
		$("#rolesManageWrapper").togglePopup();
		$("#rolesManage").replaceWith(resp.rolesManage);
		$("#rolesManage #knopka").button();
	});	
}

function roleManageSave ()
{
	var id=$("#rolesManage #aclgroup").val();
	var added = [];
	$("#rolesManage #added option").each(
			function ()
			{
				var vvv=$(this).attr("value");
				added.push(vvv);
			}
			);
	// если не выбрано ничо
	if (added.length<1) 
		{ 
			alert("Выберите хоть одно");
			return;
		}
	var href = "/dostup/usr/editdetails";
	var params={ id:id, added:added	};
	sendPostAdvanced(href,params,function(returned) {
		var resp=$.parseJSON(returned.responseText);
		// и покажем
		$("#formMsg").html(resp.msg);
		$("#msgWrapper").fadeIn('slow');
		// если успех с данными - перезагрузим страницу
		if (resp.OK===true) document.location.reload(true);

	});	

	}

function roleManageLeft() 
{
	$("#rolesManage #other option:selected").each(
			function(){
				$(this).appendTo('#rolesManage #added');
			}
			);
}

function roleManageRight() 
{
	$("#rolesManage #added option:selected").each(
			function(){
				$(this).appendTo('#rolesManage #other');
			}
			);
}

function roleEdit() 
{
	$("#formEditWrapper").togglePopup();
	var id=$("#filter #role").val();
	$("#edit #id").val(id);
}
function roleAdd() 
{
	$("#formAddWrapper").togglePopup();
	var id=$("#filter #role").val();
	$("#add #id").val(id);
}
function roleDel() 
{
	$("#formDelWrapper").togglePopup();
	var id=$("#filter #role").val();
	$("#del #id").val(id);
}
function usrAdd() 
{
	$("#formAddWrapper").togglePopup();
	var id=$("#filterForm #aclgroup").val();
	$("#add #id").val(id);
}
function usrDel(id,titleForDel) 
{
	$("#titleForDel").text(titleForDel);
	$("#formDelWrapper").togglePopup();
	$("#del #id").val(id);
}
function usrCopy(id) 
{
	$("#copyWrapper").togglePopup();
	$("#copy #id").val(id);
}
function usrMove(id) 
{
	$("#moveWrapper").togglePopup();
	$("#move #id").val(id);
}

function grpAdd(id)
{
	$("#formAddWrapper").togglePopup();
	$("#addGroup #id").val(id);
	
	}

function usrState(id,stateNew) 
{
	var params={
			id:id,
			state:stateNew
	};
	var href = "/dostup/usr/state";
	
	sendPostAdvanced(href,params,function(returned) {
		var resp=$.parseJSON(returned.responseText);
		// заменим иконку
		$("#userslist #uid_"+resp.id+ " .state").replaceWith(resp.newbutton);
		// цвет строки
		$("#userslist div#uid_"+resp.id).toggleClass("disabled");
	});	
}

function usrShow(id)
{
	var params={
			id:id,
	};
	var href = "/dostup/usr/show";
	
	sendPostAdvanced(href,params,function(returned) {
		var resp=$.parseJSON(returned.responseText);
		$("#useredit").replaceWith(resp.formEditWrapper);
		$("#formEditWrapper").togglePopup();
	});	
}

function grpShow(id)
{
	
	var params={
			id:id
	};
	var href = "/dostup/groups/show";
	
	sendPostAdvanced(href,params,function(returned) {
		var resp=$.parseJSON(returned.responseText);
		// и покажем
		$("#details").html(resp.details);
		$("#formEditWrapper").togglePopup(); 
	});	
	
}
function grpEdit(id)
{
//	var title=
//	var title_small=
	var params={
			id:id,
			title:$("#formEdit #title").val(),
			title_small:$("#formEdit #title_small").val(),
			paramz:$("#formEdit #paramz").val(),
			comment:$("#formEdit #comment").val(),
			disabled:$("#formEdit #disabled:checkbox:checked").val()
	};
	var href = "/dostup/groups/edit";
	
	sendPostAdvanced(href,params,function(returned) {
		var resp=$.parseJSON(returned.responseText);
		// и покажем
		$("#details").html(resp.details);
		$("#formMsg").html(resp.formMsg);
		$("#msgWrapper").fadeIn('slow');
		// если успех с данными - перезагрузим страницу
		if (resp.OK===true) document.location.reload(true);
		 
	});	
	
}

function grpDel(id,title)
{
	$("#formDelWrapper").togglePopup();
	$("#formDel #id").val(id);
	$("#titleForDel").text(title);	
}