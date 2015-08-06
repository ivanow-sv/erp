function logShow(patch) {

	var params = {
		patch : patch
	};
	// кто будет обрабатывать
	var href = "/service/bdpatches/logshow";
	// теперь их отправим
	sendPostAdvanced(href, params, function() {
		// выполним чонить
		 var te=$("#"+"log_"+patch).html();
		 $("#logWrapper .logContent").html(te);
		 $("#logWrapper").togglePopup();
		

	});

}
