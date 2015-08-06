function teacherAdd(id)
{
	$("#addForm #discipline").val(id);
	$("#addFormWrapper").togglePopup();
	
	}
function teacherDel(user,dis)
{
	$("#delForm #discipline").val(dis);
	$("#delForm #teacher").val(user);
	$("#delFormWrapper").togglePopup();
	
	}
function listApproveChange(id)
{
	var params={id:id};
	var href = "/kafedra/ocontrol/approvechange";
	sendPostAdvanced(href, params, function(returned) {
		var resp=$.parseJSON(returned.responseText);
		$("#stateList").replaceWith(resp.elem);
		$("#formEdit #state").val(resp.listState.state);
	});

	}

function listSave(formName)
{
	var state=$("#"+formName+" #state").val();
	var approved=$("#"+formName+" #approved").val();
//	alert (approved);
	if (state==1 || approved==1) {
		alert('Документ уже подписан.');
	}
	else
		{
		$("#"+formName).submit();
		}
	}