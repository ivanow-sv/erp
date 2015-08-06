$("#formEditWrapper")
.ready(function(){
	$("#formEdit #col_collector").sortable({
		cursor: "move",
		axis: "y",
			placeholder: "sortable-placeholder"
			});;
	
})

function applyorder()
{
//	// выбранные специальность, протокол
//	var spec = $("#filterForm #spec").val();
//	var prot_id = $("#filterForm #prot_id").val();
//	// вставить данные в форму
//	$("#orderForm #spec").val(spec);
//	$("#orderForm #prot_id").val(prot_id);
	
	var idz = new Array();
	$("#abiturList #userid:checked").each(function() {
		idz.push($(this).val());
	});

	if (idz.length <= 0 ) {
		alert("Не выбраны студенты или неверны остальные критерии");
		
	} else
		{
			$("#orderForm").submit();
		}
	
	}

function yearAdd()
{
	$("#formAddWrapper").togglePopup();	
}

function filters_saveAsNew()
{
	if (filters_chk()) 
	{
		$("#formEdit #listid").val(0);
		$("#formEdit").submit();
	}
	else alert("Требуется задать как минимум: один столбец, выбрать в 'условиях' направление подготовки и отделение");
}

function filters_save()
{
	if (filters_chk()) $("#formEdit").submit();
	else alert("Требуется задать как минимум: один столбец, выбрать в 'условиях' направление подготовки и отделение");
}

// проверим заданы ли обязательные параметры
function filters_chk()
{
	// количество отделений в условиях 
	var a=$("#formEdit #cond_collector select#cond option:selected[value='division']").length;
	// напр. подготовки
	var b=$("#formEdit #cond_collector select#cond option:selected[value='spec']").length;
	// количество выбранных колонок
	var c=$("#formEdit #col_collector select#col").length;
	
	if (a <1 || b<1 || c<1) return false;
	else return true;
	
	}

function filters_addCol()
{
	var p = "<p id='item_wrapper'></p>";
	var item=$("#items_src #col").clone().removeClass("hidden");
	var remBut=$("#items_src #removeBut").clone().removeClass("hidden");
	p=$(p).append(remBut).append(item);
	
	$("#formEdit #col_collector").append(p)
	.sortable({
		cursor: "move",
		axis: "y",
			placeholder: "sortable-placeholder"
			});;
	
}

// добавление поля условий выбора
function filters_addCond()
{
	var item=$("#items_src #cond").clone().removeClass("hidden");
	// пустышка
	var item_val=$("#items_src #cond_val").clone();
	var sign=$("#items_src #sign").clone().removeClass("hidden");
	var remBut=$("#items_src #removeBut").clone().removeClass("hidden");	
	// узнаем, выбиралось ли уже в предыдущих позициях
	// переберем собранные позиции
	$("#cond_collector select#cond option:selected").each(function(){
		// имя параметра
		var rmName=$(this).val();
		// убрать из нашего списка
		$(item).children("option[value='"+rmName+"']").remove();
				});
	
	var p = "<p id='item_wrapper'></p>";
	p=$(p).append(remBut).append(item).append(sign).append(item_val);
	$("#formEdit #cond_collector").append(p).sortable({
		cursor: "move",
		axis: "y",
		placeholder: "sortable-placeholder" 
			});

}

// добавление значений условия выбора
function filters_CondValues(el)
{
	// узнаем что выбрано
	var varname=el.val();
	// найдем среди перечней
	var var_elem=$("#items_src #"+varname+"_val").clone().removeClass("hidden");
	// если нету таких, то добавим скрытый пустой элемент, 
	// кол-во и порядок cond_val[] должны соответствовать cond[]
	if (var_elem.length <1)	
	{
		var_elem=$("#items_src #cond_val").clone();
	}
	else
		{
			$(var_elem).attr("name","cond_val[]");
			$(var_elem).attr("id","cond_val");
		}
	// уберем что было до этого
	$(el).next("select#sign").next("[id$='_val']").remove();
	// добавим после знака условия
	$(el).next("#sign").after(var_elem);
}

// уберем указанное
function filters_remove(el)
{
	el.parent("p").remove();
	}

function filters_del()
{
	var id=$("#filterForm #listid option:selected").val();
	
	}