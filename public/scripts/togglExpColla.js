// для обработки класса toggle 
// при Click на классе "toggle" сменить его доп. стиль
// и сделать видимым элемент у соседа в #content
// применялось к структуре 
// <parent1>
//		<span class="toggle"></span> Заголовок
// </parent1>
// <parent2 class="content" style="display:none;">
//		скрытый текст
// </parent2>
$(document).ready(function() {

	$(".toggle").click(function() {
		var chk = $(this).hasClass("expanded");
//		var clases=$(this).attr("class");
//		var funcc =false;
		// поиск среди классов "load_SomeFunctionName(_params_)"
//		var expr = new RegExp('load_([^\s]+)', 'ig');
//		var rezz=expr.exec(clases);
		// если нашли, то запомним
//		alert(rezz);
//		if (rezz!==null) alert(rezz);
		
//		if (ffuu) $(this).removeAttr('onclick');
		// сброс стиля
		$(this).removeClass("expanded");
		$(this).removeClass("collapsed");
		// установка нужного
		if (chk) {
			$(this).addClass("collapsed");
//			скрытие содержимого в #content у соседнего родителя, "двоюрдного брата"			
			$(this).parent().parent().children(".content").hide();
		} else {
			$(this).addClass("expanded");
//			if (rezz!==null) eval(rezz[1]);
			
//			отображение содержимого в #content у соседнего родителя, "двоюрдного брата" 
			$(this).parent().parent().children(".content").show();
			
		}

	});

});
