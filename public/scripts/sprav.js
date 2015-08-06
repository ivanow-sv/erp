function facultspecAdd()
{
	// найти все узлы классом "link"
	links=$("div#link");
	// узнаем скока их
	elCount= links.size();
	// последний
	last=links.last();
	// добаим еще один после последнего - копия последнего 
	inserted=last.clone().insertAfter(last);
	// поменяем класс - шобы зебра была
	lastClass=last.attr("class");
	if (lastClass === "tr1")
	{
		inserted.attr("class","tr2");
	}
	else
	{
		inserted.attr("class","tr1");
	}
	;
	// найдем внутри SELECT и выберем пусто
	inserted.contents().find("select").val("0");
	
	// Найдем кнопку применить, и поменяем её стиль
	$("#buttonApply_").attr("class", "buttonApplyActive");
}

function facultspecDel(elem)
{
	// шобы не удалить последний
	links=$("div#link");
	elCount=links.size();
	if(elCount<=1) return;
	// само удаление
	$(elem).parents("div#link").remove();
}
function renameSubspec(title,id)
{
	$("#formRenWrapper").togglePopup();
	$("#SpravRenameForm #title").val(title);
	$("#SpravRenameForm #id").val(id);
	
}
function deleteSubspec(title,id)
{
	$("#formDelWrapper").togglePopup();
	$("#SpravDeleteForm #title").html(title);
	$("#SpravDeleteForm #id").val(id);
	
}
function pickerTime(elem) {
	$(elem).timepicker({
		timeOnlyTitle: 'Выберите время',
		timeText: 'Время',
		hourText: 'Часы',
		minuteText: 'Минуты',
		secondText: 'Секунды',
		currentText: 'Сейчас'
	});
}

function editBells(id)
{
	$("#formEditWrapper").togglePopup(); 
	$("#editForm #id").val(id);
	$("#editForm #starts").val($("#starts_"+id).text());
	$("#editForm #ends").val($("#ends_"+id).text());
	
}
function deleteBells(id)
{
	$("#formDelWrapper").togglePopup(); 
	$("#delForm #id").val(id);
}

function showSpravChangeKafForm(elemid, id,text,title_small)
{
	$("#"+elemid).togglePopup();
	$("#SpravChangeForm #id").val(id);
	$("#SpravChangeForm #title_small").val(title_small);
	$("#SpravChangeForm textarea").val(text);
	}

function spravGosChangeForm(id,division,facult,fl)
{
	$("#formWrapper").togglePopup();
	$("#editForm #id").val(id);
	var spec=$("#filterForm #spec").val();
	$("#editForm #spec").val(spec);
	$("#editForm #division").val(division);
	$("#editForm #facult").val(facult);
	var attrr=$("#editForm").attr("action");
	
	if (fl === true) // Если редактируем - пробросим значения в форму
		{
		var value=$("#row_" + id +" .Col_3").text();
		$("#editForm #years2study").val(value);
		var value=$("#row_" + id +" .Col_4").text();
		$("#editForm #yearStart").val(value);
		var value=$("#row_" + id +" .Col_5").text();
		$("#editForm #yearLast").val(value);
		attrr = attrr.replace("add","edit"); // смена ACTION 		
		}
	else 
		{
		attrr = attrr.replace("edit","add"); // смена ACTION 
		}
	$("#editForm").attr("action",attrr);
	
	}