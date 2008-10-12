FormManager = Class.create({
		
	initialize: function(){
		
	},
	
	replicateRow : function(templateRow, number, form){		
		for(var index=0;index < number-1 ;index++){
			tr = $(templateRow.cloneNode(true));
			if(tr.id) tr.id = tr.id+'_'+(index+1);
			var inputs = tr.select('input', 'select', 'textarea');
			inputs.each(function(input){
				var newName = input.getAttribute('name')+'_'+(index+1);
				input.setAttribute('name', newName);
				if(form && Prototype.Browser.IE){form[newName] = input;}
			});
			templateRow.up().insert({bottom:tr});
		}
		templateRow.select('input', 'select', 'textarea').each(function(origInput){
			var newName = origInput.getAttribute('name')+'_0';
			origInput.setAttribute('name', newName);
			if(form && Prototype.Browser.IE){form[newName] = origInput;}
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
			var element = form[realFieldName];
			if(!element)return;
			var nodeName = element.nodeName.toLowerCase();
			switch(nodeName){
				case 'input':
					if(element.getAttribute('type') == "checkbox"){
						if(element.value == value[fieldName]) element.checked = true;
					}else{
						element.value = value[fieldName];
					}
				break;
				case 'select':
					element.select('option').each(function(option){
						if(option.value == value[fieldName]){
							option.selected = true;
						}
					});
				break;
				case 'textarea':
					element.update(value[fieldName]);
					element.value = value[fieldName];
				break;
				default:
				break;
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