/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */

/**
 * An simple form generator 
 */
Class.create("FormManager", {
		
	/**
	 * Constructor
	 */
	initialize: function(){
		
	},

    parseParameters : function (xmlDocument, query){
        var res = $A();
        $A(XPathSelectNodes(xmlDocument, query)).each(function(node){
            res.push(this.parameterNodeToHash(node));
        }.bind(this));
        return res;
    },

	parameterNodeToHash : function(paramNode){
		var paramsAtts = $A(['name', 'group', 'type', 'label', 'description', 'default', 'mandatory', 'choices']);
		var paramsHash = new Hash();
		paramsAtts.each(function(attName){
			paramsHash.set(attName, (XPathGetSingleNodeText(paramNode, '@'+attName) || ''));
		});
		return paramsHash;
	},
	
	createParametersInputs : function(form, parametersDefinitions, showTip, values, disabled, skipAccordion, addFieldCheckbox){
        var b=document.body;
        var groupDivs = $H({});
		parametersDefinitions.each(function(param){		
			var label = param.get('label');
			if(param.get('labelId')){
				label = MessageHash[param.get('labelId')];
			}
			var name = param.get('name');
			var type = param.get('type');
			var desc = param.get('description');
			if(param.get('descriptionId')){
				desc = MessageHash[param.get('descriptionId')];
			}
            var group = param.get('group') || MessageHash[439];
            if(param.get('groupId')){
                group = MessageHash[param.get('groupId')];
            }
			var mandatory = false;
			if(param.get('mandatory') && param.get('mandatory')=='true') mandatory = true;
			var defaultValue = (values?'':(param.get('default') || ""));
			if(values && values.get(name)){
				defaultValue = values.get(name);
			}
			var element;
			var disabledString = (disabled?' disabled="true" ':'');
			if(type == 'string' || type == 'integer' || type == 'array'){
				element = '<input type="text" ajxp_type="'+type+'" ajxp_mandatory="'+(mandatory?'true':'false')+'" name="'+name+'" value="'+defaultValue+'"'+disabledString+' class="SF_input">';
            }else if(type == 'textarea'){
                if(defaultValue) defaultValue = defaultValue.replace(new RegExp("__LBR__", "g"), "\n");
                element = '<textarea class="SF_input" style="height:70px;" ajxp_type="'+type+'" ajxp_mandatory="'+(mandatory?'true':'false')+'" name="'+name+'"'+disabledString+'>'+defaultValue+'</textarea>'
		    }else if(type == 'password'){
				element = '<input type="password" autocomplete="off" ajxp_type="'+type+'" ajxp_mandatory="'+(mandatory?'true':'false')+'" name="'+name+'" value="'+defaultValue+'"'+disabledString+' class="SF_input">';
			}else if(type == 'boolean'){
				var selectTrue, selectFalse;
				if(defaultValue){
					if(defaultValue == "true" || defaultValue == "1") selectTrue = true;
					if(defaultValue == "false" || defaultValue == "0") selectFalse = true;
				}
				element = '<input type="radio" ajxp_type="'+type+'" class="SF_box" name="'+name+'" value="true" '+(selectTrue?'checked':'')+''+disabledString+'> Yes';
				element = element + '<input type="radio" ajxp_type="'+type+'" class="SF_box" name="'+name+'" '+(selectFalse?'checked':'')+' value="false"'+disabledString+'> No';
				element = '<div class="SF_input">'+element+'</div>';
			}else if(type == 'select' && param.get('choices')){
                var choices = param.get('choices').split(",");
                element = '<select class="SF_input" name="'+name+'" ajxp_mandatory="'+(mandatory?'true':'false')+'" >';
                if(!mandatory) element += '<option value=""></option>';
                for(var k=0;k<choices.length;k++){
                    var cLabel, cValue;
                    var cSplit = choices[k].split("|");
                    cValue = cSplit[0];
                    if(cSplit.length > 1 ) cLabel = cSplit[1];
                    else cLabel = cValue;
                    var selectedString = (defaultValue == cValue ? ' selected' : '');
                    element += '<option value="'+cValue+'"'+selectedString+'>'+cLabel+'</option>';
                }
                element += '</select>';
            }
            var cBox = '';
            if(addFieldCheckbox){
                cBox = '<input type="checkbox" class="SF_fieldCheckBox" name="SFCB_'+name+'" '+(defaultValue?'checked':'')+'/>';
            }
			var div = new Element('div', {className:"SF_element" + (addFieldCheckbox?" SF_elementWithCheckbox":"")}).update('<div class="SF_label">'+label+(mandatory?'*':'')+' :</div>'+ cBox + element);
			if(desc){
				modal.simpleTooltip(div.select('.SF_label')[0], '<div class="simple_tooltip_title">'+label+'</div>'+desc);
			}
            if(skipAccordion){
			    form.insert({'bottom':div});
            }else{
                var gDiv = groupDivs.get(group) || new Element('div', {className:'accordion_content'});
                b.insert(div);
                var lab = div.down('.SF_label');
                lab.setStyle({width:parseInt(39*(Prototype.Browser.IE?340:320)/100)+'px'});
                if( parseInt(lab.getHeight()) > 30){
                    lab.next().setStyle({marginTop:'20px'});
                }
                lab.setStyle({width:'39%'});
                gDiv.insert(div);
                groupDivs.set(group, gDiv);
            }
		});
        if(!groupDivs.size()) return;
        var firstGroup = true;
        groupDivs.each(function(pair){
            var title = new Element('div',{className:'accordion_toggle'}).update(pair.key);
            form.insert(title);
            form.insert(pair.value);
        });
        form.SF_accordion = new accordion(form, {
            classNames : {
                toggle : 'accordion_toggle',
                toggleActive : 'accordion_toggle_active',
                content : 'accordion_content'
            },
            defaultSize : {
                width : '360px',
                height: null
            },
            direction : 'vertical'
        });
        form.SF_accordion.activate(form.down('div.accordion_toggle'));
        if(addFieldCheckbox){
            form.select("input.SF_fieldCheckBox").each(function(cb){
                cb.observe("click", function(event){
                    var cbox = event.target;
                    var state = !cbox.checked;
                    var fElement = cbox.next("input.SF_input,select.SF_input,div.SF_input");
                    var fElements;
                    if(fElement && fElement.nodeName.toLowerCase() == "div") {
                        fElements = fElement.select("input");
                    }else{
                        fElements = $A([fElement]);
                    }
                    fElements.invoke((state?"disable":"enable"));
                    if(state) cbox.previous("div.SF_label").addClassName("SF_disabled");
                    else cbox.previous("div.SF_label").removeClassName("SF_disabled");
                });
                if(!cb.checked){
                    cb.checked = true;
                    cb.click();                	
                }
            });
        }
	},
	
	serializeParametersInputs : function(form, parametersHash, prefix, skipMandatoryWarning){
		prefix = prefix || '';
		var missingMandatory = $A();
        var checkboxesActive = false;
		form.select('input,textarea').each(function(el){
			if(el.type == "text" || el.type == "password" || el.nodeName.toLowerCase() == 'textarea'){
				if(el.getAttribute('ajxp_mandatory') == 'true' && el.value == '' && !el.disabled){
					missingMandatory.push(el);
				}
				parametersHash.set(prefix+el.name, el.value);				
			}
			else if(el.type=="radio" && el.checked){
				parametersHash.set(prefix+el.name, el.value)
			};
			if(el.getAttribute('ajxp_type')){
				parametersHash.set(prefix+el.name+'_ajxptype', el.getAttribute('ajxp_type'));
			}
            if(form.down('[name="SFCB_'+el.name+'"]')){
                checkboxesActive = true;
                parametersHash.set(prefix+el.name+'_checkbox', form.down('[name="SFCB_'+el.name+'"]').checked?'checked':'unchecked');
            }
		});		
		form.select('select').each(function(el){
			if(el.getAttribute("ajxp_mandatory") == 'true' && el.getValue() == '' && !el.disabled){
				missingMandatory.push(el);
			}
			parametersHash.set(prefix+el.name, el.getValue());
            if(form.down('[name="SFCB_'+el.name+'"]')){
                checkboxesActive = true;
                parametersHash.set(prefix+el.name+'_checkbox', form.down('[name="SFCB_'+el.name+'"]').checked?'checked':'unchecked');
            }
		});
        if(checkboxesActive){
            parametersHash.set("sf_checkboxes_active", "true");
        }
        if(!skipMandatoryWarning){
	        missingMandatory.each(function(el){
	        	el.addClassName("SF_failed");
	        	if(form.SF_accordion){
	        		el.up('div.accordion_content').previous('div.accordion_toggle').addClassName('accordion_toggle_failed');
	        	}
	        });
        }
		return missingMandatory.size();
	},		
	
	/**
	 * Replicate a template row
	 * @param templateRow HTMLElement
	 * @param number Integer
	 * @param form HTMLForm
	 */
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
	
	/**
	 * @param form HTMLForm
	 * @param fields Array
	 * @param value Object
	 * @param suffix String
	 */
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
	
	/**
	 * @param form HTMLForm
	 * @param fields Object
	 * @param values Array
	 */
	fetchMultipleValueToForm : function(form, fields, values){
		var index = 0;
		$A(values).each(function(value){
			this.fetchValueToForm(form, fields, value, index);
			index++;
		}.bind(this));
	}
});