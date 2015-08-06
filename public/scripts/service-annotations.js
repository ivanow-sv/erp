function showAn(name) {

	var params = {
		id : name
	};
	// кто будет обрабатывать
	var href = "/service/annotations/show";
	// теперь их отправим
	sendPostAdvanced(href,params,function(returned) {
		
		var resp=$.parseJSON(returned.responseText);
		$("#contentWrapper").html(resp.details);
		// и покажем
		$("#contentWrapper").togglePopup();

		// есть ли уже CKEditor?
		var instance = CKEDITOR.instances["editor1"];
		if(instance)
		{
		    CKEDITOR.remove(instance);
		}
		CKEDITOR.replace("editor1");
	});

}

function saveAn()
{
	var instance = CKEDITOR.instances["editor1"];
	var editor_data='';
	if(instance)
	{
		var editor_data = CKEDITOR.instances.editor1.getData();
	    
	};
	var name = $("#editForm #id").val();
	// зохаваем форму и содержимое CKEditor
	var params = {
			id : name,
			editor1: editor_data
		};
	
	// отправим
	var href = "/service/annotations/edit"; 
	sendPostAdvanced(href,params,function(returned) {
		var resp=$.parseJSON(returned.responseText);
		
//		$("#msgWrapper").html(resp.msg);
		// и покажем
//		$("#msgWrapper").togglePopup();
		
	});
	
	}
