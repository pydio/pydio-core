FormManager = Class.create({
		
	initialize: function(){
		
	},
	
	replicateRow : function(templateRow, number){		
		for(var index=0;index < number-1 ;index++){
			tr = $(templateRow.cloneNode(true));
			if(tr.id) tr.id = tr.id+'_'+(index+1);
			var inputs = tr.select('input', 'select', 'textarea');
			inputs.each(function(input){
				input.setAttribute('name', input.getAttribute('name')+'_'+(index+1));
			});
			templateRow.up().insert({bottom:tr});
		}
		templateRow.select('input', 'select', 'textarea').each(function(origInput){
			origInput.setAttribute('name', origInput.getAttribute('name')+'_0');
		});
	},
	
	fetchValueToForm : function(form, fields, value, suffix){
		$A(fields).each(function(fieldName){
			if(!value[fieldName]) return;
			if(suffix != null){
				realFieldName = fieldName+'_'+suffix;
			}else{
				realFieldName = fieldName;
			}
			var select = $(form).select('[name="'+realFieldName+'"]');
			if(!select || !select.length) return;
			var element = select[0];			
			if(element.nodeName.toLowerCase() == 'input'){
				if(element.getAttribute('type') == "checkbox"){
					if(element.value == value[fieldName]) element.checked = true;
				}else{
					element.value = value[fieldName];
				}
			}else if(element.nodeName.toLowerCase() == 'select'){
				element.select('option').each(function(option){
					if(option.value == value[fieldName]){
						option.selected = true;
					}
				});
			}else if(element.nodeName.toLowerCase() == 'textarea'){
				element.update(value[fieldName]);
				element.value = value[fieldName];
			}
		});
	},
	
	fetchMultipleValueToForm : function(form, fields, values){
		var index = 0;
		$A(values).each(function(value){
			this.fetchValueToForm(form, fields, value, index);
			index++;
		}.bind(this));
	}
});