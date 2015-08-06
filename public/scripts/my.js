function privilegesApply(formID) {

	var options = {
		// beforeSubmit: showRequest, // функция, вызываемая перед передачей
//		 success: showResponse, // функция, вызываемая при получении ответа
		// timeout : 3000,
		dataType : 'json'
	};
	$("#" + formID).ajaxSubmit(options);
}

function showResponse(responseText, statusText, xhr, $form)
{
	  alert(responseText['formRole']); 
}

function privilegesAll(formID, myValue) {
	// / найдем все RADIO со значением myValue внутри формы formID
	$("input[value='" + myValue + "']:radio", "#" + formID).click() // кликнем
	;
}
function formpPivileges(role_id, res_id) {

	$.getJSON("../../editadvance/id/" + res_id + "/role_id/" + role_id,
			function(data) {
				$('#form_role' + role_id).html(data.formRole);
			});
	return false;
}

function formEditMenuModuleChanged() {
	element = document.getElementById('module');
	elementB = document.getElementById('id').value;
	// alert (elementB);
	// получим параметры формы и подгрузим новый список контрроллеров
	$.getJSON("./" + elementB, {
		selectedModule : element.value
	}, function(data) {
		$('#controllerList').html(data.controllersList);
		$('#actionList').html(data.actionList);
	});
	return false;
}

function formEditMenuControllerChanged() {
	elementA = document.getElementById('module');
	elementB = document.getElementById('controller');
	elementC = document.getElementById('id').value;
	// получим параметры формы и подгрузим новый список контрроллеров
	$.getJSON("./" + elementC, {
		selectedModule : elementA.value,
		selectedContr : elementB.value
	}, function(datas) {
		$('#actionList').html(datas.actionList);
	});
	return false;
}

function formEditMenuModContrChanged() {
	module = document.getElementById('module').value;
	controller = document.getElementById('controller').value;
	id = document.getElementById('id').value;
	// получим параметры формы и подгрузим новый список контрроллеров
	$.getJSON("./" + id, {
		selectedModule : module,
		selectedContr : controller
	}, function(datas) {
		$('#actionList').html(datas.actionList);
	});
	return false;
}

function showElement(elementID) {
	$("#" + elementID).animate( {
		height : "show"
	}, 300);
}
function cleanElement(elementID) {
	$("#" + elementID).animate( {
		height : "hide"
	}, 300).text('');
}
function removeHideParentElement(elementID) {
	$("#" + elementID).parent().animate( {
		height : "hide"
	}, 300);
	$("#" + elementID).remove();
}

function toggleShowElement(elementID) {
	element = document.getElementById(elementID);
	if (element.style.display == 'none') {
		$("#" + elementID).animate( {
			height : "show"
		}, 300);
		/*
		 * $("#" + elementID).css('z-index','999'); $("#" +
		 * elementID).css('position','absolute'); $("#" +
		 * elementID).css('background-color','white'); $("#" +
		 * elementID).css('border','darkgreen solid 1px');
		 */
	} else
		$("#" + elementID).animate( {
			height : "hide"
		}, 300);
	;
}

/*
 * function showSpravChangeForm(controller, id) {
	element = document.getElementById('SpravChangeForm');
	element.style.display = 'block';
	element2 = document.getElementById('formOnly');

	element2.innerHTML = element2.innerHTML + '<form action="/sprav/'
			+ controller + '/' + 'edit/' + id + '"' + ' method="post">'
			+ '<input type="hidden" name="id" value="' + id + '">'
			+ 'Введите новое значение' + '<TEXTAREA name="title"></TEXTAREA>'
			+ '<input type="submit" value="Применить" class="apply_text" />'
			+ '</form>';
}
*/
function showSpravChangeForm(elemid, id,text) {
	$("#"+elemid).togglePopup();
	$("#SpravChangeForm #id").val(id);
	$("#SpravChangeForm textarea").val(text);
}
function showSpravChangeForm2(controller, id) {
	element = document.getElementById('SpravChangeForm2');
	element.style.display = 'block';
	element2 = document.getElementById('formOnly');

	element2.innerHTML = element2.innerHTML + '<form action="/sprav/'
			+ controller + '/' + 'edit/' + id + '"' + ' method="post">'
			+ '<input type="hidden" name="id" value="' + id + '">'
			+ 'Введите новое значение<br>'
			+ 'Шифр:&nbsp;<INPUT TYPE="text" name="numeric_title">'
			+ '<br>Наименование:<br>' + '<TEXTAREA name="title"></TEXTAREA>'
			+ '<input type="submit" value="Применить" class="apply_text" />'
			+ '</form>';
}
function showSpravChangeForm3(controller, id) {
	element = document.getElementById('SpravChangeForm3');
	element.style.display = 'block';
	element2 = document.getElementById('formOnly');

	element2.innerHTML = element2.innerHTML
			+ '<form action="/sprav/'
			+ controller
			+ '/'
			+ 'edit/'
			+ id
			+ '"'
			+ ' method="post">'
			+ '<input type="hidden" name="id" value="'
			+ id
			+ '">'
			+ 'Введите новые значения<br>'
			+ '<br>Табельный номер:<br><INPUT class="medinput" TYPE="text" name="tabel">'
			+ '<br>Фамилия:<br><INPUT class="medinput" TYPE="text" name="family">'
			+ '<br>Имя:<br><INPUT class="medinput" TYPE="text" name="name">'
			+ '<br>Отчество:<br><INPUT class="medinput" TYPE="text" name="otch">'
			+ '<br><br><input type="submit" value="Применить" class="apply_text" />'
			+ '</form>';
}
function FacultAddSpecForm() {
	element = document.getElementById('FacultAddSpecForm');
	element.style.display = 'block';
}
function hideFacultAddSpecForm() {
	element = document.getElementById('FacultAddSpecForm');
	element.style.display = 'none';

}
function hideSpravChangeForm() {
	element2 = document.getElementById('formOnly');
	element2.innerHTML = '';
	element = document.getElementById('SpravChangeForm');
	element.style.display = 'none';

}
function hideSpravChangeForm2() {
	element2 = document.getElementById('formOnly');
	element2.innerHTML = '';
	element = document.getElementById('SpravChangeForm2');
	element.style.display = 'none';

}
function hideSpravChangeForm3() {
	element2 = document.getElementById('formOnly');
	element2.innerHTML = '';
	element = document.getElementById('SpravChangeForm3');
	element.style.display = 'none';

}

/**
 * "Отметить все" или "снять отметки" чекбоксов
 * 
 * @param ID
 *            формы
 * @param имя
 *            checkbox
 * @param true
 *            шобы отметить или false шобы снять
 * @return
 */
function markCheckBoxes(oFormName, cbName, checked) {
	oForm = document.getElementById(oFormName);
	for ( var i = 0; i < oForm[cbName].length; i++)
		oForm[cbName][i].checked = checked;
}
// события для формирования групп
(function($) {
	$(function()
			{
				//если выбирали специальность
				$('form#groupsmanage').delegate('#spec', 'change', function() {
				specSelected=$(this).children('option:selected').val() ;
			    	$.ajax({
			    	    url: document.location.href + "/ajax/",
			    	   	type: 'post',
//			    	   	dataType: '_default',
			    	   	data: {specSelected:specSelected},
			    	   	beforeSend: function (){
			    	   	// подгрузка картинки ожидания
			    	        $("#ajaxLoader").show();
			    	   	},
			    	   	success: function(data){
			    	   		// покажем "свободных" студней
			    	   		$('#studentsFreeList').html(data.studsFreeList);
			    	   		// очистка списка групп
			    	   		$('#group').replaceWith(data.grouplist);
			    	   		// покажем курсы
			    	   		$('#kurs').replaceWith(data.kursList);
			    	   		// очистка подгрупп
			    	   		$('#subgroup').replaceWith(data.subgrouplist);
							// очистка студней из списка привязвнных
							$('#studentsAssignedList').html('');
							// тулбар скроем
			    	   		$('#groupsmanage div.studentsAssigned div.toolbar').hide();						
			    	   		
			    	   	},
			    	   	complete: function(data){
			    	              // удаление картинки ожидания
			    	   		$("#ajaxLoader").hide();
			    	   	}
			    	   });
			    	return false;
				})
			}
	);

	$(function()
			{
		//если выбирали курс
		$('form#groupsmanage').delegate('#kurs', 'change', function() {
			kursSelected=$(this).children('option:selected').val() ;
			specSelected=$("form#groupsmanage #spec option:selected").val() ;
			
			$.ajax({
				url: "groupmanage/getgroups/",
				type: 'post',
//			    	   	dataType: '_default',
				data: {specSelected:specSelected,kursSelected:kursSelected},
				beforeSend: function (){
					// подгрузка картинки ожидания
					$("#ajaxLoader").show();
				},
				success: function(data){
					// перестроим список групп
					$('#group').replaceWith(data.grouplist);
//					 очистка подгрупп
					$('#subgroup').replaceWith(data.subgrouplist);			    	   		
					// очистка студней из списка привязвнных
					$('#studentsAssignedList').html('');
					// тулбар скроем
	    	   		$('#groupsmanage div.studentsAssigned div.toolbar').hide();						
					
				},
				complete: function(data){
					// удаление картинки ожидания
					$("#ajaxLoader").hide();
				}
			});
			return false;
		})
			}
	);
	
	// выбирали группу	
	$(function() {
	  $('div.studentsAssigned').delegate('#group', 'change', function() {
		// выбрали группу
		groupSelected=$(this).children('option:selected').val() ;
		// еси выбрано "добавить" value=-1;
	    if (groupSelected== "-1")
	    {
	    	// отправить запрос на создание группы
	    	kursSelected=$("form#groupsmanage #kurs option:selected").val() ;
			specSelected=$("form#groupsmanage #spec option:selected").val() ;
	    	$.ajax({
	    	    url: "groupmanage/creategroup/",
	    	   	type: 'post',
//	    	   	dataType: '_default',
	    	   	data: {kursSelected:kursSelected,specSelected:specSelected},
	    	   	beforeSend: function (){
	    	   	// подгрузка картинки ожидания
	    	        $("#ajaxLoader").show();
	    	   	},
	    	   	success: function(data){
	    	   		//обновить список групп
	    	   		$('#group').replaceWith(data.grouplist);
	    	   		// обновить список подгрупп - пусто
	    	   		$('#subgroup').replaceWith(data.subgrouplist);
	    	   		// обновить список студней - пусто
	    	   		$('#studentsAssignedList').html(data.studsAssingedList);
					// тулбар скроем
	    	   		$('#groupsmanage div.studentsAssigned div.toolbar').hide();						
	    	   		
	    	   	},
	    	   	complete: function(){
	    	              // удаление картинки ожидания
	    	   		$("#ajaxLoader").hide();
	    	   	}
	    	   });
	    	return false;
	    }
	    // иначе надо отправить Ajax, получить подгруппы и воткнуть в нужное место 
	    else
	    {
	    	// ну отправим наш запрос
	    	$.ajax({
	    	    url: "groupmanage/getsubgroups/",
	    	   	type: 'post',
//	    	   	dataType: '_default',
	    	   	data: {groupSelected:groupSelected},
	    	   	beforeSend: function (){
	    	   	// подгрузка картинки ожидания
	    	        $("#ajaxLoader").show();
	    	   	},
	    	   	success: function(data){
	    	   		$('#subgroup').replaceWith(data.subgrouplist);
	    	   		$('#studentsAssignedList').html(data.studsAssingedList);
					// тулбар скроем
	    	   		$('#groupsmanage div.studentsAssigned div.toolbar').hide();						
	    	   		
	    	   	},
	    	   	complete: function(){
	    	              // удаление картинки ожидания
	    	   		$("#ajaxLoader").hide();
	    	   	}
	    	   });
	    	return false;
	    }
	  })
	
	}
	);

	$(function()
			{
		//если выбирали подгруппу
		$('form#groupsmanage').delegate('#subgroup', 'change', function() {
			subgroupSelected=$(this).children('option:selected').val() ;
//			groupSelected=$(this).children('option:selected').val() ;
			groupSelected=$("form#groupsmanage #group option:selected").val() ;
		    if (subgroupSelected== "-1")
		    {
		    	// отправить запрос на создание подгруппы
		    	$.ajax({
		    	    url: "groupmanage/createsubgroup/",
		    	   	type: 'post',
//		    	   	dataType: '_default',
		    	   	data: {groupSelected:groupSelected},
		    	   	beforeSend: function (){
		    	   	// подгрузка картинки ожидания
		    	        $("#ajaxLoader").show();
		    	   	},
		    	   	success: function(data){
		    	   		// обновить список подгрупп 
		    	   		$('#subgroup').replaceWith(data.subgrouplist);
		    	   		// обновить список студней - содержимоей всей группы
		    	   		$('#studentsAssignedList').html(data.studsAssingedList);
		    	   		// скрыть TOOLBARы
		    	   		$('#groupsmanage div.studentsAssigned div.toolbar').hide();
		    	   	},
		    	   	complete: function(){
		    	              // удаление картинки ожидания
		    	   		$("#ajaxLoader").hide();
		    	   	}
		    	   });
		    	return false;
		    	
		    }
		    else
		    {
			
			$.ajax({
				url: "groupmanage/getstuds/",
				type: 'post',
//			    	   	dataType: '_default',
				data: {groupSelected:groupSelected,subgroupSelected:subgroupSelected},
				beforeSend: function (){
					// подгрузка картинки ожидания
					$("#ajaxLoader").show();
				},
				success: function(data){
					// студни
	    	   		$('#studentsAssignedList').html(data.studsAssingedList);
	    	   		// Отобразить TOOLBARы
	    	   		$('#groupsmanage div.studentsAssigned div.toolbar').show();
	    	   		// КНОПКА убрать из подгруппы
	    	   		// кнопки "ВЫДЕЛИТЬ ВСЕ" снять выбор
				},
				complete: function(data){
					// удаление картинки ожидания
					$("#ajaxLoader").hide();
				}
			});
			return false;
		    }
		})
			}
	);	
	$(function()
			{
				//если нажали ДОБАВИТЬ
				$('#groupsmanage .toolbar').delegate('#add', 'click', function() {
					subgroupSelected=$("form#groupsmanage #subgroup option:selected").val() ;
					//собрать всех выбранных свободных
					var idz = new Object();
					n=0;
					$('#studentsFreeList input.userid:checkbox:checked').each(function(){
						idz[n]=this.value;
						n=n+1;
//						alert(this.value);
					});
					dataz= new Object();
					dataz['ids']=idz;
					dataz['subgroupSelected']=subgroupSelected;
					
//					freeids=$("form#groupsmanage #studentsFreeList option:selected").val() ;
				$.ajax({
			    	    url: "groupmanage/assign2subgroup/",
			    	   	type: 'post',
//			    	   	dataType: '_default',
			    	   	data: dataz,
			    	   	beforeSend: function (){
			    	   	// подгрузка картинки ожидания
			    	        $("#ajaxLoader").show();
			    	   	},
			    	   	success: function(data){
			    	   		// покажем "свободных" студней
			    	   		$('#studentsFreeList').html(data.studsFreeList);
							// студни из списка привязвнных
							$('#studentsAssignedList').html(data.studsAssingedList);
							// снимем галки
							$('#studentsFreeList input.userid:checkbox:checked').each(function(){
								$(this).removeAttr("checked");
							});
							$('#studentsAssignedList input.userid:checkbox:checked').each(function(){
								$(this).removeAttr("checked");
							});
			    	   		
			    	   	},
			    	   	complete: function(data){
			    	              // удаление картинки ожидания
			    	   		$("#ajaxLoader").hide();
			    	   	}
			    	   });
			    	return false;
			    	
				})
			}
	);

	$(function()
			{
				//если нажали УБРАТЬ
				$('#groupsmanage .toolbar').delegate('#remove', 'click', function() {
					subgroupSelected=$("form#groupsmanage #subgroup option:selected").val() ;
					//собрать всех выбранных свободных
					var idz = new Object();
					n=0;
					$('#studentsAssignedList input.userid:checkbox:checked').each(function(){
						idz[n]=this.value;
						n=n+1;
//						alert(this.value);
					});
					dataz= new Object();
					dataz['ids']=idz;
					dataz['subgroupSelected']=subgroupSelected;
					
//					freeids=$("form#groupsmanage #studentsFreeList option:selected").val() ;
				$.ajax({
			    	    url: "groupmanage/unassignsubgroup/",
			    	   	type: 'post',
//			    	   	dataType: '_default',
			    	   	data: dataz,
			    	   	beforeSend: function (){
			    	   	// подгрузка картинки ожидания
			    	        $("#ajaxLoader").show();
			    	   	},
			    	   	success: function(data){
			    	   		// покажем "свободных" студней
			    	   		$('#studentsFreeList').html(data.studsFreeList);
							// студни из списка привязвнных
							$('#studentsAssignedList').html(data.studsAssingedList);
							// снимем галки
							$('#studentsFreeList input.userid:checkbox:checked').each(function(){
								$(this).removeAttr("checked");
							});
							$('#studentsAssignedList input.userid:checkbox:checked').each(function(){
								$(this).removeAttr("checked");
							});
			    	   		
			    	   	},
			    	   	complete: function(data){
			    	              // удаление картинки ожидания
			    	   		$("#ajaxLoader").hide();
			    	   	}
			    	   });
			    	return false;
			    	
				})
			}
	);	

	$(function()
			{
				//если нажали ВЫБРАТЬ ВСЕ свободные
				$('#groupsmanage #studentsFreeList').delegate('#checkAll', 'click', function() {
					// если там установлено - то значит нада все галки поставить
					if ($(this).attr("checked"))
					{
						$('#studentsFreeList input.userid:checkbox').each(function(){
							$(this).attr("checked","checked");
						});						
						
					}
					// иначе снять
					else
					{
						$('#studentsFreeList input.userid:checkbox:checked').each(function(){
							$(this).removeAttr("checked");
						});						
						
					}
				})
			}
	);		
	$(function()
			{
		//если нажали ВЫБРАТЬ ВСЕ привязанные
		$('#groupsmanage #studentsAssignedList').delegate('#checkAll', 'click', function() {

			// если там установлено - то значит нада все галки поставить
			if ($(this).attr("checked"))
			{
				$('#studentsAssignedList input.userid:checkbox').each(function(){
					$(this).attr("checked","checked");
				});						
				
			}
			// иначе снять
			else
			{
				$('#studentsAssignedList input.userid:checkbox:checked').each(function(){
					$(this).removeAttr("checked");
				});						
				
			}

			
		})
			}
	);		

	// функции, для отправки запроса и получения результата в виде списков
	// уинифицированные версии предыдущих
	
		$(
		function()
		{
			// если выбирали выпадающий список внутри #filterForm
			$('#filterForm').delegate('select, input:checkbox', 'change', function() 
			{
				var formData = new Object();
				// соберем данные со всех SELECTED
				$("#filterForm select").each(
						function(){
							formData[$(this).attr("name")]=$(this).find('option:selected').val();
						}
						);
				// соберем все данные с текстовых полей
				$("#filterForm input:text").each(
						function(){
							formData[$(this).attr("name")]=$(this).val();
						}
						);
				
				// данные со всех checkbox
				$("#filterForm input:checkbox:checked").each(
						function(){
							// checkbox's name
							// WARN - all checkboxes is in NAME[] format
							var nn=$(this).attr("name").substr(0,$(this).attr("name").length-2);
							// array is set?
							if (formData[nn] !== undefined) 
								formData[nn].push($(this).val());
							else
								{
									formData[nn]=[];
									formData[nn].push($(this).val());
								}
						}
						);
				
		    	// отправим запрос и получим новый список
				$.ajax({
		    	    url: document.location.href + "/formchanged/",
		    	   	type: 'post',
//		    	   	dataType: '_default',
		    	   	data: {formData:formData},
		    	   	beforeSend: function (){
		    	   	// подгрузка картинки ожидания
		    	        $("#ajaxLoader").show();
		    	   	},
		    	   	success: function(data){
		    	   		// заменим контейнеры вновь прибывшими
		    	   		dataNeeded=data["out"];
		    	   		for (var id in dataNeeded)
		    	   		{
		    	   			$("#"+id).replaceWith(dataNeeded[id]);
//		    	   			alert(data[id]);
		    	   		}
		    	   		
		    	   	},
		    	   	complete: function(data){
		    	              // удаление картинки ожидания
		    	   		$("#ajaxLoader").hide();
		    	   	}
		    	   });
		    	return false;
		    	});
		}
		);		
	
	
	})(jQuery)

function markUnmarkAll(elemClassClicked,elemClass) {
	if ($("." + elemClassClicked).attr('checked'))
		{
			$(this).attr('checked','checked');
			$("." + elemClass).attr('checked','cheched');
		;
		}
	else
	{
		$(this).removeAttr('checked');
		$("." + elemClass).removeAttr('checked');
		
	}		
}

function markAllClass(elemClass) {
	$("." + elemClass).attr('checked','checked');
}

function unmarkAllClass(elemClass) {
	$("." + elemClass).removeAttr('checked');
}
function markAllClassFromOne(elemClass) {
	var k=1;
	$("input:checkbox:even" + "." + elemClass).each(function(){
		$(this).attr('checked','checked');		
	})
}

function sendCheckBoxes(containerId,resultContainerId,flag)
{
	var checkBoxes = new Object();

	var k=0;
	$("#"+containerId+" input:checkbox:checked").each(function()
			{
				var aa = new Object();
				aa[$(this).attr('name')]=$(this).val();
				checkBoxes[k]=aa;
//				alert($(this).attr('name')+"="+$(this).val());
				k++;
			})
	// отправим запрос и получим данные
	$.ajax({
		url: document.location.href + "/checkboxes/",
		type: 'post',
//	    	   	dataType: '_default',
		data: {checkBoxes :checkBoxes },
		beforeSend: function (){
			// подгрузка картинки ожидания
			$("#ajaxLoader").show();
			},
		success: function(data){
				// заменим содержимео контейнера вновь прибывшими
	    	   	dataNeeded=data["out"];
	    	   	$("#"+resultContainerId).html(dataNeeded);
	    	   	// покажем его
	    	   	if (flag==1) $("#"+resultContainerId).togglePopup(); 
//	    	   	$("#"+resultContainerId).show();
	    	   		
	    	   	},
	    complete: function(data){
	    	   	// удаление картинки ожидания
	    	   	$("#ajaxLoader").hide();
	    	   	}
	});
}
/*
function sendDataAndRecieve(containerId,controller,resultContainer,flag)
{
	var params = new Object();

	var k=0;
	$("#"+containerId+" input:text").each(function()
			{
				var aa = new Object();
				aa[$(this).attr('name')]=$(this).val();
				params[k]=aa;
//				alert($(this).attr('name')+"="+$(this).val());
				k++;
			})
	
	// отправим запрос и получим данные
	$.ajax({
		url: document.location.href + "/checkboxes/",
		type: 'post',
//	    	   	dataType: '_default',
		data: {params :params },
		beforeSend: function (){
			// подгрузка картинки ожидания
			$("#ajaxLoader").show();
			},
		success: function(data){
				// заменим содержимео контейнера вновь прибывшими
	    	   	dataNeeded=data["out"];
	    	   	$("#"+resultContainerId).html(dataNeeded);
	    	   	// покажем его
	    	   	if (flag==1) $("#"+resultContainerId).togglePopup(); 
//	    	   	$("#"+resultContainerId).show();
	    	   		
	    	   	},
	    complete: function(data){
	    	   	// удаление картинки ожидания
	    	   	$("#ajaxLoader").hide();
	    	   	}
	});
}
*/

function sendData(containerId,names,controller)
{
	var params = new Object();
	// найдем нужные данные внутри контейнера
	for(key in names)
	  {
		varname=names[key];
		varvalue=$("#"+containerId+" [name|="+varname+"]").val();
		params[varname]=varvalue;
	  }
	// отправим запрос и получим данные
	$.ajax({
		url: document.location.href+'/'+controller,
		type: 'post',
//	    	   	dataType: '_default',
		data: {params :params },
		beforeSend: function (){
			// подгрузка картинки ожидания
			$("#ajaxLoader").show();
			},
		success: function(data){
	    	   	},
	    complete: function(data){
	    	   	// удаление картинки ожидания
	    	   	$("#ajaxLoader").hide();
//	    	   	// редирект
	    	   	window.location=document.location.href;

	    	   	}
	});
}
function sendPost(controller,params)
{
	var returned;
	// отправим запрос и получим данные
	$.ajax({
		url: document.location.href+'/'+controller,
		type: 'post',
//	    	   	dataType: '_default',
		data: params,
		beforeSend: function (){
			// подгрузка картинки ожидания
			$("#ajaxLoader").show();
		},
		success: function(data){
	   		dataNeeded=data["out"];
	   		for (var id in dataNeeded)
	   		{
	   			$("#"+id).html("");
	   			$("#"+id).html(dataNeeded[id]);
//	   			alert(data[id]);
	   		}
	   		returned=data;

		},
		complete: function(data){
			// удаление картинки ожидания
			$("#ajaxLoader").hide();
//	    	   	// редирект
			//window.location=document.location.href;
			
		}
	});
//	alert (returned.iconpath);
	return returned;
}
function sendPostAdvanced(href,params,funcc)
{
//	var returned;
	// отправим запрос и получим данные
	$.ajax({
		url: my_baseUrl + href,
		type: 'post',
//	    	   	dataType: '_default',
		data: params,
		beforeSend: function (){
		// подгрузка картинки ожидания
		$("#ajaxLoader").show();
	},
	success: function(data,textStatus){
		dataNeeded=data["out"];
		for (var id in dataNeeded)
		{
			$("#"+id).html("");
			$("#"+id).html(dataNeeded[id]);
//	   			alert(data[id]);
		}
//		alert(textStatus);
//		returned=data;
		
	},
	complete: function(data){
		// удаление картинки ожидания
		$("#ajaxLoader").hide();
		if (typeof(funcc)=="function") funcc(data);
		
//	    	   	// редирект
		//window.location=document.location.href;
		
	}
	});
//	alert (returned.iconpath);
//	return returned;
}


function disableSelect(containerId,names)
{
	var params = new Object();
	// найдем нужные данные внутри контейнера
	var k=1;
	for(key in names)
	  {
		varname=names[key];
		node=$("#"+containerId+" [name|="+varname+"]");
		if (k==1) needattr=node.attr('disabled');
		if (needattr===true) node.removeAttr('disabled');
		else node.attr('disabled', 'disabled');
//		params[varname]=varvalue;
		k++;
	  }
	
}

function correctForm(containerId,data)
{
	for(var key in data)
	{
		var varvalue=data[key];
		node=$("#"+containerId+" #"+key+"");
		node.attr("value",varvalue);
//		alert(node.attr("value"));
	}
}

// выбор всех checkboxов
// @param id переменной
// @param внутри чего расположена
// @param контейнер-ориентир checkbox (на который нажали)
function toggleCheck(varname,container,flagElem)
{
	var attribb=flagElem.attr("checked");
	if (attribb==="checked")
		{
		var node=$("#"+container).find("input[name^='"+varname+"']");
		node.each(function()
				{
				$(this).attr("checked","checked");
				});
		}
	else 
		{
		var node=$("#"+container).find("input[name^='"+varname+"']");
		node.each(function()
				{
				$(this).removeAttr("checked");
				});
		}
}