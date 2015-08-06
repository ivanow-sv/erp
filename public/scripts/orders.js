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
		showButtonPanel : true,
		// установим лимиты выбора дат
		// не раньше 1 сентября начала уч. гола
		minDate : new Date(starts, '8', '1'),
		// и не позднее 31 августа конца учебного года
		maxDate : new Date(completes, '7', '31')

	});

}

function getShareDocumentForm(id,elem) {
	var params = {
		id : id
	};
	// кто будет обрабатывать
	var href = "/docus/orders/shareform";
	sendPostAdvanced(href, params, function() {
		var chk = $("#shareFormWrapper").hasClass("hidden");
		if (chk)
			$("#shareFormWrapper").togglePopup();

		// отсчитаем от elem Col_2 и Col_3
		var titNum=$(elem).parent().siblings(".Col_2").text();
		var titDat=$(elem).parent().siblings(".Col_3").text();
		// вставим название В заголовок
		$("#docInfo").text("№"+titNum + " от " + titDat);
		
	});
}

//function getEditForm(id,elem) {
//	location.replace("orders/editform/id/"+id);
//}

function getUsersInRole(elem) {
	var params = {
		role : $(elem).val()
	};
	// кто будет обрабатывать
	var href = "/docus/orders/shareformusers";
	sendPostAdvanced(href, params, function() {
		// с первого раза не срабатывает :O
		$("#popup").alignCenter();
		$("#popup").alignCenter();
		});
}

function del(id,elem) {
	var params = {
			id : id
	};
	// кто будет обрабатывать
	var href = "/docus/orders/del";
	sendPostAdvanced(href, params, function() {
		// с первого раза не срабатывает :O
		$("#popup").alignCenter();
		$("#popup").alignCenter();
	});
}

function moveNode(from, to, elem) {
	var userid = $(elem).children("input").val();
	if (to === false) {
		$(elem).parents("div#granted_" + userid).remove();
	} else {
		var lineSrc=$(elem).parents("div#user_" + userid);
		var line=$("#trTemplate").clone();
		line.children(".tr1").attr("id","granted_" + userid);
		var login=lineSrc.children(".Col_1").html();
		line.children(".tr1").children(".Col_2").text(login);
		line.children(".tr1").children(".Col_3").text(lineSrc.children(".Col_2").html());
		line.find('[name^="userid"]').val(userid);
//		line.find('[name^="userid"]').attr("name",'userid['+userid+']');
		line.find('[name^="priv"]').attr("name",'priv[]');
		$("#usersGranted").append(line.html());
		
	}

}

function shareApply() {
	var userids = new Array();
	var privs = new Array();
	// соберем пользователей
	$("#usersGranted").find('[name^="userid"]').each(
			function(){
				userids[userids.length]=$(this).val();
			}
			);
	// соберем выставленные права
	$("#usersGranted").find('[name^="priv"]').each(
			function(){
				privs[privs.length]=$(this).val();
			}
	);
	// собственно ID документа
	docid=$("#shareForm").children("#id").val();
	
	var params = {
			userid	:	userids,
			priv	: 	privs,
			docid	:	docid
		};
		// кто будет обрабатывать
		var href = "/docus/orders/shareapply";
		sendPostAdvanced(href, params, function() {
			// с первого раза не срабатывает :O
//			$("#popup").alignCenter();
//			$("#popup").alignCenter();
			});
			
}