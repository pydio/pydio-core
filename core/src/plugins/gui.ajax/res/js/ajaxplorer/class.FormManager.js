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

    modalParent : null,
	/**
	 * Constructor
	 */
	initialize: function(modalParent){
        if(modalParent) this.modalParent = modalParent;
	},

    parseParameters : function (xmlDocument, query){
        var res = $A();
        $A(XPathSelectNodes(xmlDocument, query)).each(function(node){
            res.push(this.parameterNodeToHash(node));
        }.bind(this));
        return res;
    },

	parameterNodeToHash : function(paramNode){
        var paramsAtts = paramNode.attributes;
		var paramsHash = new Hash();
        for(var i=0; i<paramsAtts.length; i++){
            var attName = paramsAtts.item(i).nodeName;
            var value = paramsAtts.item(i).nodeValue;
            if( (attName == "label" || attName == "description" || attName == "group") && MessageHash[value] ){
                value = MessageHash[value];
            }
			paramsHash.set(attName, value);
		}
        paramsHash.set("xmlNode", paramNode);
		return paramsHash;
	},
	
	createParametersInputs : function(form, parametersDefinitions, showTip, values, disabled, skipAccordion, addFieldCheckbox){
        var b=document.body;
        var groupDivs = $H({});
        var replicableGroups = $H({});
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
			var disabledString = (disabled || param.get('readonly')?' disabled="true" ':'');
            var commonAttributes = {
                'name'                  : name,
                'data-ajxp_type'        : type,
                'data-ajxp_mandatory'   : (mandatory?'true':'false')
            };
            if(disabled || param.get('readonly')){
                commonAttributes['disabled'] = 'true';
            }
			if(type == 'string' || type == 'integer' || type == 'array'){
                element = new Element('input', Object.extend({type:'text', className:'SF_input', value:defaultValue}, commonAttributes));
				//element = '<input type="text" data-ajxp_type="'+type+'" data-ajxp_mandatory="'+(mandatory?'true':'false')+'" name="'+name+'" value="'+defaultValue+'"'+disabledString+' class="SF_input">';
            }else if(type == 'textarea'){
                if(defaultValue) defaultValue = defaultValue.replace(new RegExp("__LBR__", "g"), "\n");
                element = '<textarea class="SF_input" style="height:70px;" data-ajxp_type="'+type+'" data-ajxp_mandatory="'+(mandatory?'true':'false')+'" name="'+name+'"'+disabledString+'>'+defaultValue+'</textarea>'
		    }else if(type == 'password'){
				element = '<input type="password" autocomplete="off" data-ajxp_type="'+type+'" data-ajxp_mandatory="'+(mandatory?'true':'false')+'" name="'+name+'" value="'+defaultValue+'"'+disabledString+' class="SF_input">';
			}else if(type == 'boolean'){
				var selectTrue, selectFalse;
				if(defaultValue){
					if(defaultValue == "true" || defaultValue == "1") selectTrue = true;
					if(defaultValue == "false" || defaultValue == "0") selectFalse = true;
				}
				element = '<input type="radio" data-ajxp_type="'+type+'" class="SF_box" name="'+name+'" value="true" '+(selectTrue?'checked':'')+''+disabledString+'> '+MessageHash[440];
				element = element + '<input type="radio" data-ajxp_type="'+type+'" class="SF_box" name="'+name+'" '+(selectFalse?'checked':'')+' value="false"'+disabledString+'> '+MessageHash[441];
				element = '<div class="SF_input">'+element+'</div>';
			}else if(type == 'select'){
                var choices, json_list;
                if(Object.isString(param.get("choices"))){
                    if(param.get("choices").startsWith("json_list:")){
                        choices = ["loading|Loading..."];
                        json_list = param.get("choices").split(":")[1];
                    }else if(param.get("choices") == "AJXP_AVAILABLE_LANGUAGES"){
                        var object = window.ajxpBootstrap.parameters.get("availableLanguages");
                        choices = [];
                        for(var key in object){
                            choices.push(key + "|" + object[key]);
                        }
                    }else{
                        choices = param.get('choices').split(",");
                    }
                }else{
                    choices = param.get("choices");
                }
                if(!choices) choices = [];
                var multiple = param.get("multiple") ? "multiple='true'":"";
                element = '<select class="SF_input" name="'+name+'" data-ajxp_mandatory="'+(mandatory?'true':'false')+'" '+multiple+'>';
                if(!mandatory && !multiple) element += '<option value=""></option>';
                for(var k=0;k<choices.length;k++){
                    var cLabel, cValue;
                    var cSplit = choices[k].split("|");
                    cValue = cSplit[0];
                    if(cSplit.length > 1 ) cLabel = cSplit[1];
                    else cLabel = cValue;
                    var selectedString = '';
                    if(param.get("multiple")){
                        $A(defaultValue.split(",")).each(function(defV){
                            if(defV == cValue) selectedString = ' selected';
                        });
                    }else{
                        selectedString = (defaultValue == cValue ? ' selected' : '');
                    }
                    element += '<option value="'+cValue+'"'+selectedString+'>'+cLabel+'</option>';
                }
                element += '</select>';
            }else if(type == "image" && param.get("uploadAction")){
                if(defaultValue){
                    var conn = new Connexion();
                    var imgSrc = conn._baseUrl + "&get_action=" +param.get("loadAction") + "&binary_id=" + defaultValue;
                    if(param.get("binary_context")){
                        imgSrc += "&" + param.get("binary_context");
                    }
                }else if(param.get("defaultImage")){
                    imgSrc = param.get("defaultImage");
                }
                element = "<div class='SF_image_block'><img src='"+imgSrc+"' class='SF_image small'><span class='SF_image_link'>"+
                    (param.get("uploadLegend")?param.get("uploadLegend"):"update")+"</span>" +
                    "<input type='hidden' name='"+param.get("name")+"' data-ajxp_type='binary'>" +
                    "<input type='hidden' name='"+param.get("name")+"_original_binary' value='"+ defaultValue +"' data-ajxp_type='string'></div>";
            }
			var div = new Element('div', {className:"SF_element" + (addFieldCheckbox?" SF_elementWithCheckbox":"")});

            // INSERT LABEL
            div.insert(new Element('div', {className:"SF_label"}).update(label+(mandatory?'*':'')+' :'));
            // INSERT CHECKBOX
            if(addFieldCheckbox){
                cBox = '<input type="checkbox" class="SF_fieldCheckBox" name="SFCB_'+name+'" '+(defaultValue?'checked':'')+'/>';
                cBox = new Element('input', {type:'checkbox', className:'SF_fieldCheckBox', name:'SFCB_'+name});
                cBox.checked = defaultValue?true:false;
                div.insert(cBox);
            }
            // INSERT ELEMENT
            div.insert(element);
            if(type == "image"){
                var imgLink = div.down("span.SF_image_link");
                imgLink.observe("click", function(){
                    this.createUploadForm(form, div.down('img'), param);
                }.bind(this));
            }
			if(desc){
				modal.simpleTooltip(div.select('.SF_label')[0], '<div class="simple_tooltip_title">'+label+'</div>'+desc);
			}
            if(json_list){
                var conn = new Connexion();
                element = div.down("select");
                conn.setParameters({get_action:json_list});
                conn.onComplete = function(transport){
                    element.down("option").update("Select an action");
                    var json = transport.responseJSON;
                    if(json.HAS_GROUPS){
                        for(var key in json.LIST){
                            var opt = new Element("OPTGROUP", {label:key});
                            element.insert(opt);
                            for (var index=0;index<json.LIST[key].length;index++){
                                var option = new Element("OPTION").update(json.LIST[key][index].action);
                                element.insert(option);
                            }
                        }
                    }
                };
                conn.sendAsync();
            }

            if(param.get('replicationGroup')){
                var repGroupName = param.get('replicationGroup');
                var repGroup;
                if(replicableGroups.get(repGroupName)) {
                    repGroup = replicableGroups.get(repGroupName);
                }else {
                    repGroup = new Element("div", {id:"replicable_"+repGroupName, className:'SF_replicableGroup'});
                }
                repGroup.insert(div);
                replicableGroups.set(repGroupName, repGroup);
                div = repGroup;
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
		}.bind(this));
        if(replicableGroups.size()){
            replicableGroups.each(function(pair){
                var repGroup = pair.value;
                var replicationButton = new Element("a", {className:'SF_replication_Add', title:'Replicate this group'}).update("&nbsp;").observe("click", function(event){
                    this.replicateRow(repGroup,  1, form);
                }.bind(this));
                repGroup.insert({bottom:replicationButton});
                repGroup.insert({bottom:new Element('div', {className:'SF_rgClear'})});
                if(values){
                    var hasReplicates = true;
                    var replicIndex = 1;
                    while(hasReplicates){
                        //hasReplicates = false;
                        var repInputs = repGroup.select('input,select,textarea');
                        if(!repInputs.length) break;
                        repInputs.each(function(element){
                            var name = element.name;
                            hasReplicates &= (values.get(name+"_"+replicIndex) != null);
                        });
                        if(hasReplicates){
                            this.replicateRow(repGroup, 1, form, values);
                            replicIndex++;
                        }
                    }
                }
            }.bind(this));
        }
        if(!groupDivs.size()) return;
        var firstGroup = true;
        groupDivs.each(function(pair){
            var title = new Element('div',{className:'accordion_toggle', tabIndex:0}).update(pair.key);
            title.observe('focus', function(){
                if(form.SF_accordion && form.SF_accordion.showAccordion!=title.next(0)) {
                    form.SF_accordion.activate(title);
                }
            });
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

    createUploadForm : function(modalParent, imgSrc, param){
        if(this.modalParent) modalParent = this.modalParent;
        var conn = new Connexion();
        var url = conn._baseUrl + "&get_action=" + param.get("uploadAction");
        if(param.get("binary_context")){
            url += "&" + param.get("binary_context");
        }
        if(!$("formManager_hidden_iframe")){
            $$("body")[0].insert(new Element("iframe", {id:"formManager_hidden_iframe"}));
        }
        var paramName = param.get("name");
        var pane = new Element("div");
        pane.update("<form id='formManager_uploader' enctype='multipart/form-data' target='formManager_hidden_iframe' method='post' action='"+url+"'>" +
            "<div class='dialogLegend'>Select an image on your computer</div> " +
            "<input type='file' name='userfile' style='width: 270px;'>" +
            "</form>")
        modal.showSimpleModal(modalParent, pane, function(){
            window.formManagerHiddenIFrameSubmission = function(result){
                imgSrc.src = conn._baseUrl + "&get_action=" + param.get("loadAction")+"&tmp_file="+result.trim();
                imgSrc.next("input[type='hidden']").setValue(result.trim());
                imgSrc.next("input[type='hidden']").setAttribute("data-ajxp_type", "binary");
                window.formManagerHiddenIFrameSubmission = null;
            };
            pane.down("#formManager_uploader").submit();
            return true;
        }.bind(this) , function(){
            return true;
        }.bind(this) );

    },

	serializeParametersInputs : function(form, parametersHash, prefix, skipMandatoryWarning){
		prefix = prefix || '';
		var missingMandatory = $A();
        var checkboxesActive = false;
		form.select('input,textarea').each(function(el){
			if(el.type == "text" || el.type == "hidden" || el.type == "password" || el.nodeName.toLowerCase() == 'textarea'){
				if(el.getAttribute('data-ajxp_mandatory') == 'true' && el.value == '' && !el.disabled){
					missingMandatory.push(el);
				}
				parametersHash.set(prefix+el.name, el.value);				
			}
			else if(el.type=="radio" && el.checked){
				parametersHash.set(prefix+el.name, el.value)
			};
			if(el.getAttribute('data-ajxp_type')){
				parametersHash.set(prefix+el.name+'_ajxptype', el.getAttribute('data-ajxp_type'));
			}
            if(form.down('[name="SFCB_'+el.name+'"]')){
                checkboxesActive = true;
                parametersHash.set(prefix+el.name+'_checkbox', form.down('[name="SFCB_'+el.name+'"]').checked?'checked':'unchecked');
            }
            if(el.up('.SF_replicableGroup')){
                parametersHash.set(prefix+el.name+'_replication', el.up('.SF_replicableGroup').id);
            }
		});		
		form.select('select').each(function(el){
			if(el.getAttribute("data-ajxp_mandatory") == 'true' && el.getValue() == '' && !el.disabled){
				missingMandatory.push(el);
			}
			parametersHash.set(prefix+el.name, el.getValue());
            if(form.down('[name="SFCB_'+el.name+'"]')){
                checkboxesActive = true;
                parametersHash.set(prefix+el.name+'_checkbox', form.down('[name="SFCB_'+el.name+'"]').checked?'checked':'unchecked');
            }
            if(el.up('.SF_replicableGroup')){
                parametersHash.set(prefix+el.name+'_replication', el.up('.SF_replicableGroup').id);
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
	replicateRow : function(templateRow, number, form, values){
        var repIndex = templateRow.getAttribute('data-ajxp-replication-index');
        if(repIndex === null){
            repIndex = 0;
        }else{
            repIndex = parseInt(repIndex);
        }
		for(var index=0;index < number ;index++){
            repIndex ++;
            templateRow.setAttribute('data-ajxp-replication-index', repIndex);
			var tr = $(templateRow.cloneNode(true));
			if(tr.id) tr.id = tr.id+'_'+repIndex;
			var inputs = tr.select('input', 'select', 'textarea');
			inputs.each(function(input){
				var newName = input.getAttribute('name')+'_'+repIndex;
				input.setAttribute('name', newName);
				if(form && Prototype.Browser.IE){form[newName] = input;}
                if(values && values.get(newName)){
                    input.setValue(values.get(newName));
                }
			});
			templateRow.up().insert({bottom:tr});
            if(tr.select('.SF_replication_Add').length){
                tr.select('.SF_replication_Add').invoke("remove");
            }
            if(index == number - 1){
                if(templateRow.up().select('.SF_replication_Add').length){
                    tr.insert(templateRow.up().select('.SF_replication_Add')[0]);
                }
            }
            var removeButton = new Element('a', {className:'SF_replication_Remove', title:'Remove this group'})
                .update('&nbsp;')
                .observe('click', function(){
                    if(tr.select('.SF_replication_Add').length){
                        tr.previous('.SF_replicableGroup').insert(tr.select('.SF_replication_Add')[0]);
                    }
                    tr.remove();
            });
            tr.insert(removeButton);
		}
        /*
		templateRow.select('input', 'select', 'textarea').each(function(origInput){
			var newName = origInput.getAttribute('name')+'_0';
			origInput.setAttribute('name', newName);
			if(form && Prototype.Browser.IE){form[newName] = origInput;}
		});
		*/
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