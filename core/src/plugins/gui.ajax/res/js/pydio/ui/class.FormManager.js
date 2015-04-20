/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 */

/**
 * An simple form generator 
 */
Class.create("FormManager", {

    modalParent : null,

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
        var collectCdata = false;
        for(var i=0; i<paramsAtts.length; i++){
            var attName = paramsAtts.item(i).nodeName;
            var value = paramsAtts.item(i).value;
            if( (attName == "label" || attName == "description" || attName == "group" || attName.indexOf("group_switch_") === 0) && MessageHash[value] ){
                value = MessageHash[value];
            }
            if( attName == "cdatavalue" ){
                collectCdata = true;
                continue;
            }
			paramsHash.set(attName, value);
		}
        if(collectCdata){
            paramsHash.set("value", paramNode.firstChild.value);
        }
        paramsHash.set("xmlNode", paramNode);
		return paramsHash;
	},
	
	createParametersInputs : function(form, parametersDefinitions, showTip, values, disabled, skipAccordion, addFieldCheckbox, startAccordionClosed){
        var b=document.body;
        var groupDivs = $H({});
        var replicableGroups = $H({});
		parametersDefinitions.each(function(param){		
			var label = param.get('label');
			if(param.get('labelId')){
				label = MessageHash[param.get('labelId')];
			}
            if(param.get('group_switch_name')) {
                return;
            }
			var name = param.get('name');
			var type = param.get('type');
			var desc = param.get('description');
            var conn = new Connexion();

            // deduplicate
            if(form.down('[name="'+name+'"]')) return;

			if(param.get('descriptionId')){
				desc = MessageHash[param.get('descriptionId')];
			}
            var group = param.get('group') || MessageHash[439];
            if(param.get('groupId')){
                group = MessageHash[param.get('groupId')];
            }
			var mandatory = false;
			if(param.get('mandatory') && param.get('mandatory')=='true') mandatory = true;
            var defaultValue = '';
            var defaultValueExists = false;
            if(values && values.get(name) !== undefined){
                defaultValue = values.get(name);
                defaultValueExists = true;
            }else if(!addFieldCheckbox && param.get('default') !== undefined){
                defaultValue = param.get('default');
                defaultValueExists = true;
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
			if(type == 'string' || type == 'integer' || type == 'array' || type == "hidden"){
                element = new Element('input', Object.extend({type: (type == "hidden" ? 'hidden' : 'text'), className:'SF_input', value:defaultValue}, commonAttributes));
            }else if(type == 'button'){

                element = new Element('div', {className:'SF_input SF_inlineButton'}).update('<span class="icon-play-circle"></span>'+param.get('description'));
                element.observe("click", function(event){
                    element.addClassName('SF_inlineButtonWorking');
                    var testValues = $H();
                    var choicesValue = param.get("choices").split(":");
                    var firstPart = choicesValue.shift();
                    if(firstPart == "run_client_action"){
                        element.removeClassName('SF_inlineButtonWorking');
                        ajaxplorer.actionBar.fireAction(choicesValue.shift());
                        return;
                    }
                    testValues.set('get_action', firstPart);
                    this.serializeParametersInputs(form, testValues, "DRIVER_OPTION_");

                    if(choicesValue.length > 1){
                        testValues.set("action_plugin_id", choicesValue.shift());
                        testValues.set("action_plugin_method", choicesValue.shift());
                    }
                    if(name.indexOf("/") !== -1){
                        testValues.set("button_key", getRepName(name));
                    }
                    conn.setMethod('post');
                    conn.setParameters(testValues);
                    conn.onComplete = function(transport){
                        element.removeClassName('SF_inlineButtonWorking');
                        if(transport.responseText.startsWith('SUCCESS:')){
                            ajaxplorer.displayMessage("SUCCESS", transport.responseText.replace("SUCCESS:", ""));
                        }else{
                            ajaxplorer.displayMessage("ERROR", transport.responseText.replace("ERROR:", ""));
                        }
                        element.siblings().each(function(el){
                            if(el.pe) el.pe.onTimerEvent();
                        });
                    };
                    conn.sendAsync();
                }.bind(this));

            }else if(type == 'monitor'){

                element = new Element('div', {className:'SF_input SF_inlineMonitoring'}).update('loading...');
                element.pe = new PeriodicalExecuter(function(){
                    element.addClassName('SF_inlineMonitoringWorking');
                    var testValues = $H();
                    this.serializeParametersInputs(form, testValues, "DRIVER_OPTION_");

                    var choicesValue = param.get("choices").split(":");
                    testValues.set('get_action', choicesValue.shift());
                    if(choicesValue.length > 1){
                        testValues.set("action_plugin_id", choicesValue.shift());
                        testValues.set("action_plugin_method", choicesValue.shift());
                    }
                    if(name.indexOf("/") !== -1){
                        testValues.set("button_key", getRepName(name));
                    }
                    conn.discrete = true;
                    conn.setMethod('post');
                    conn.setParameters(testValues);
                    conn.onComplete = function(transport){
                        element.removeClassName('SF_inlineMonitoringWorking');
                        element.update(transport.responseText);
                    };
                    conn.sendAsync();

                }.bind(this), 10);
                // run now
                element.pe.onTimerEvent();

            }else if(type == 'textarea'){
                if(defaultValue) defaultValue = defaultValue.replace(new RegExp("__LBR__", "g"), "\n");
                element = '<textarea class="SF_input" style="height:70px;" data-ajxp_type="'+type+'" data-ajxp_mandatory="'+(mandatory?'true':'false')+'" name="'+name+'"'+disabledString+'>'+defaultValue+'</textarea>'
		    }else if(type == 'password'){
				element = '<input type="password" autocomplete="off" data-ajxp_type="'+type+'" data-ajxp_mandatory="'+(mandatory?'true':'false')+'" name="'+name+'" value="'+defaultValue+'"'+disabledString+' class="SF_input">';
			}else if(type == 'password-create'){
                element = new Element('input', {
                    type:'text',
                    autocomplete:'off',
                    'data-ajxp_type':'password',
                    'data-ajxp_mandatory': (mandatory?'true':'false'),
                    name:name,
                    value:defaultValue,
                    className:'SF_input'
                });
			}else if(type == 'boolean'){
				var selectTrue, selectFalse;
				if(defaultValue !== undefined){
					if(defaultValue == "true" || defaultValue == "1" || defaultValue === true ) selectTrue = true;
					if(defaultValue == "false" || defaultValue == "0" || defaultValue === false) selectFalse = true;
				}
                if(!selectTrue && !selectFalse) selectFalse = true;
				element = '<input type="radio" data-ajxp_type="'+type+'" class="SF_box" name="'+name+'" id="'+name+'-true" value="true" '+(selectTrue?'checked':'')+''+disabledString+'><label for="'+name+'-true">'+MessageHash[440]+'</label>';
				element = element + '<input type="radio" data-ajxp_type="'+type+'" class="SF_box" name="'+name+'" id="'+name+'-false"  '+(selectFalse?'checked':'')+' value="false"'+disabledString+'><label for="'+name+'-false">'+MessageHash[441] + '</label>';
				element = '<div class="SF_input">'+element+'</div>';
			}else if(type == 'select'){
                var choices, json_list, json_file;
                if(Object.isString(param.get("choices"))){
                    if(param.get("choices").startsWith("json_list:")){
                        choices = ["loading|"+MessageHash[466]+"..."];
                        json_list = param.get("choices").split(":")[1];
                    }else if(param.get("choices").startsWith("json_file:")){
                        choices = ["loading|"+MessageHash[466]+"..."];
                        json_file = param.get("choices").split(":")[1];
                    }else if(param.get("choices") == "AJXP_AVAILABLE_LANGUAGES"){
                        var object = window.ajxpBootstrap.parameters.get("availableLanguages");
                        choices = [];
                        for(key in object){
                            if(object.hasOwnProperty(key)){
                                choices.push(key + "|" + object[key]);
                            }
                        }
                    }else if(param.get("choices") == "AJXP_AVAILABLE_REPOSITORIES"){
                        choices = [];
                        if(ajaxplorer.user){
                            ajaxplorer.user.repositories.each(function(pair){
                                choices.push(pair.value.getId() + '|' + pair.value.getLabel());
                            });
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
                    var imgSrc = conn._baseUrl + "&get_action=" +param.get("loadAction") + "&binary_id=" + defaultValue;
                    if(param.get("binary_context")){
                        imgSrc += "&" + param.get("binary_context");
                    }
                }else if(param.get("defaultImage")){
                    imgSrc = param.get("defaultImage");
                }
                element = "<div class='SF_image_block'><img src='"+imgSrc+"' class='SF_image small'><span class='SF_image_link image_update'>"+
                    (param.get("uploadLegend")?param.get("uploadLegend"):MessageHash[457])+"</span><span class='SF_image_link image_remove'>"+
                    (param.get("removeLegend")?param.get("removeLegend"):MessageHash[458])+"</span>" +
                    "<input type='hidden' name='"+param.get("name")+"' data-ajxp_type='binary'>" +
                    "<input type='hidden' name='"+param.get("name")+"_original_binary' value='"+ defaultValue +"' data-ajxp_type='string'></div>";
            }else if(type.indexOf("group_switch:") === 0){

                // Get all values
                var switchName = type.split(":")[1];
                var switchValues = {};
                defaultValue = "";
                if(values && values.get(name)){
                    defaultValue = values.get(name);
                }
                var potentialSubSwitches = $A();
                parametersDefinitions.each(function(p){
                    "use strict";
                    if(!p.get('group_switch_name')) return;
                    if(p.get('group_switch_name') != switchName){
                        p = new Hash(p._object);
                        potentialSubSwitches.push(p);
                        return;
                    }
                    if( !switchValues[p.get('group_switch_value')]){
                        switchValues[p.get('group_switch_value')] = {label :p.get('group_switch_label'), fields : [], values : $H(), fieldsKeys:{}};
                    }
                    p = new Hash(p._object);
                    p.unset('group_switch_name');
                    p.set('name', name + '/' + p.get('name'));
                    var vKey = p.get("name");
                    if(switchValues[p.get('group_switch_value')].fieldsKeys[vKey]){
                       return;
                    }
                    switchValues[p.get('group_switch_value')].fields.push(p);
                    switchValues[p.get('group_switch_value')].fieldsKeys[vKey] = vKey;
                    if(values && values.get(vKey)){
                        switchValues[p.get('group_switch_value')].values.set(vKey, values.get(vKey));
                    }
                });
                var selector = new Element('select', {className:'SF_input', name:name, "data-ajxp_mandatory":(mandatory?'true':'false'), "data-ajxp_type":type});
                if(!mandatory){
                    selector.insert(new Element('option'));
                }
                $H(switchValues).each(function(pair){
                    "use strict";
                    var options = {value:pair.key};
                    if(defaultValue && defaultValue == pair.key) options.selected = "true";
                    selector.insert(new Element('option', options).update(pair.value.label));
                    if(potentialSubSwitches.length){
                        potentialSubSwitches.each(function(sub){
                            pair.value.fields.push(sub);
                        });
                    }
                });
                selector.SWITCH_VALUES = $H(switchValues);
                element = new Element("div").update(selector);
                var subFields = new Element("div");
                element.insert(subFields);
                if(form.ajxpPaneObject) subFields.ajxpPaneObject = form.ajxpPaneObject;
                selector.FIELDS_CONTAINER = subFields;

                selector.observe("change", function(e){
                    "use strict";
                    var target = e.target;
                    target.FIELDS_CONTAINER.update("");
                    if(!target.getValue()) return;
                    var data = target.SWITCH_VALUES.get(target.getValue());
                    this.createParametersInputs(
                        target.FIELDS_CONTAINER,
                        data.fields,
                        true,
                        null,
                        false,
                        true);
                    if(selector.getAttribute('data-disableShortcutsOnForm')){
                        this.disableShortcutsOnForm(target.FIELDS_CONTAINER);
                    }
                }.bind(this));

                if(selector.getValue()){
                    var data = selector.SWITCH_VALUES.get(selector.getValue());
                    this.createParametersInputs(
                        selector.FIELDS_CONTAINER,
                        data.fields,
                        true,
                        values,
                        false,
                        true
                    );
                }

            }
            var div;
            // INSERT LABEL
            if(type != "legend"){
                div = new Element('div', {className:"SF_element" + (addFieldCheckbox?" SF_elementWithCheckbox":"")});
                if(type == "hidden") div.setStyle({display:"none"});

                div.insert(new Element('div', {className:"SF_label"}).update('<span>'+label+(mandatory?'*':'')+'</span>'));
                // INSERT CHECKBOX
                if(addFieldCheckbox){
                    //var cBox = '<input type="checkbox" class="SF_fieldCheckBox" name="SFCB_'+name+'" '+(defaultValueExists?'checked':'')+'/>';
                    var cBox = new Element('input', {type:'checkbox', className:'SF_fieldCheckBox', name:'SFCB_'+name});
                    cBox.checked = defaultValueExists;
                    div.insert(cBox);
                }
                // INSERT ELEMENT
                div.insert(element);
            }else{
                div = new Element('div', {className:'dialogLegend'}).update(desc);
            }
            if(type == "image"){
                div.down("span.SF_image_link.image_update").observe("click", function(){
                    this.createUploadForm(form, div.down('img'), param);
                }.bind(this));
                div.down("span.SF_image_link.image_remove").observe("click", function(){
                    this.confirmExistingImageDelete(form, div.down('img'), div.down('input[name="'+param.get("name")+'"]'), param);
                }.bind(this));
            }else if(type=='password-create'){
                var button = new Element('span', {className:'icon-refresh ajxpPasswordGenerate'});
                element.insert({after:button});
                div.setStyle({position:'relative'});
                button.observe('click', function(){
                    element.setValue(Math.random().toString(36).slice(-10));
                });
                button.setStyle({
                    position: 'absolute',
                    right: '19px',
                    top: '12px',
                    fontSize: '14px',
                    cursor: 'pointer'
                });
                var fObs = function(){
                    var val = element.getValue();
                    if(val) button.hide();
                    else button.show();
                };
                element.observe('keyup', fObs);
                element.observe('blur', fObs);
            }
			if(desc && type != "legend"){
                var ttSpan = div.down('.SF_label').down('span');
				modal.simpleTooltip(ttSpan, '<div class="simple_tooltip_title">'+label+'</div>'+desc,
                    'middle left', "right_arrow_tip", "element");
                ttSpan.writeAttribute('data-tooltipLabel', label);
                ttSpan.writeAttribute('data-tooltipDescription', desc);
			}
            var key;
            var conn = new Connexion();
            if(json_list){
                element = div.down("select");
                if(defaultValue) element.defaultValue = defaultValue;
                conn.setParameters({get_action:json_list});
                conn.onComplete = function(transport){
                    var json = transport.responseJSON;
                    element.down("option").update(json.LEGEND ? json.LEGEND : "Select...");
                    if(json.HAS_GROUPS){
                        for(key in json.LIST){
                            if(json.LIST.hasOwnProperty(key)){
                                var opt = new Element("OPTGROUP", {label:key});
                                element.insert(opt);
                                for (var index=0;index<json.LIST[key].length;index++){
                                    element.insert(new Element("OPTION").update(json.LIST[key][index].action));
                                }
                            }
                        }
                    }else{
                        for (key in json.LIST){
                            if(json.LIST.hasOwnProperty(key)){
                                var option = new Element("OPTION", {value:key}).update(json.LIST[key]);
                                if(key == defaultValue) option.setAttribute("selected", "true");
                                element.insert(option);
                            }
                        }
                    }
                    element.fire("chosen:updated");
                };
                conn.sendAsync();
            }else if(json_file){
                var req = new Connexion(json_file);
                req.onComplete = function(transport){
                    element = div.down("select");
                    if(defaultValue) element.defaultValue = defaultValue;
                    element.down('option[value="loading"]').remove();
                    $A(transport.responseJSON).each(function(entry){
                        var option = new Element('OPTION', {value:entry.key}).update(entry.label);
                        if(entry.key == defaultValue) option.setAttribute("selected", "true");
                        element.insert(option);
                    });
                    element.fire("chosen:updated");
                };
                req.sendAsync();
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
                var ref = parseInt(form.getWidth()) + (Prototype.Browser.IE?40:0);
                if(ref > (Prototype.Browser.IE?40:0)){
                    var lab = div.down('.SF_label');
                    if(lab && lab.getStyle('float') == 'left'){
                        var fontSize = lab.getStyle('fontSize');
                        lab.setStyle({fontSize:fontSize});
                        lab.setStyle({width:parseInt(39*ref/100)+'px'});
                        if( parseInt(lab.getHeight()) > Math.round(parseFloat(lab.getStyle('lineHeight')) + Math.round(parseFloat(lab.getStyle('paddingTop'))) + Math.round(parseFloat(lab.getStyle('paddingBottom')))) ){
                            lab.next().setStyle({marginTop:lab.getStyle('lineHeight')});
                        }
                        lab.setStyle({width:'39%'});
                    }
                }
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
        if(!startAccordionClosed) form.SF_accordion.activate(form.down('div.accordion_toggle'));
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
                    fElements.each(function(el){
                        if(el && el[(state?"disable":"enable")]) el[(state?"disable":"enable")]();
                    });
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
            $('hidden_frames').insert(new Element("iframe", {id:"formManager_hidden_iframe", name:"formManager_hidden_iframe"}));
        }
        var pane = new Element("div");
        pane.update("<form id='formManager_uploader' enctype='multipart/form-data' target='formManager_hidden_iframe' method='post' action='"+url+"'>" +
            "<div class='dialogLegend'>Select an image on your computer</div> " +
            "<input type='file' name='userfile' style='width: 270px;'>" +
            "</form>");
        modal.showSimpleModal(modalParent, pane, function(){
            window.formManagerHiddenIFrameSubmission = function(result){
                imgSrc.src = conn._baseUrl + "&get_action=" + param.get("loadAction")+"&tmp_file="+result.trim();
                imgSrc.next("input[type='hidden']").setValue(result.trim());
                this.triggerEvent(imgSrc.next("input[type='hidden']"), 'change');
                imgSrc.next("input[type='hidden']").setAttribute("data-ajxp_type", "binary");
                window.formManagerHiddenIFrameSubmission = null;
            }.bind(this);
            pane.down("#formManager_uploader").submit();
            return true;
        }.bind(this) , function(){
            return true;
        }.bind(this) );

    },

    triggerEvent : function(element, eventName) {
        // safari, webkit, gecko
        if (document.createEvent)
        {
            var evt = document.createEvent('HTMLEvents');
            evt.initEvent(eventName, true, true);
            return element.dispatchEvent(evt);
        }

        // Internet Explorer
        if (element.fireEvent) {
            return element.fireEvent('on' + eventName);
        }
    },

    observeFormChanges : function(form, callback, bufferize){
        var realCallback;
        var randId = 'timer-'+parseInt(Math.random()*1000);
        if(bufferize){
            realCallback = function(){
                if(window[randId]) window.clearTimeout(window[randId]);
                window[randId] = window.setTimeout(function(){
                    callback();
                }, bufferize);
            };
        }else{
            realCallback = callback;
        }
        form.select("div.SF_element").each(function(element){
            element.select("input,textarea,select").invoke("observe", "change", realCallback);
            element.select("input,textarea").invoke("observe", "keydown", function(event){
                if(event.keyCode == Event.KEY_DOWN || event.keyCode == Event.KEY_UP || event.keyCode == Event.KEY_RIGHT || event.keyCode == Event.KEY_LEFT || event.keyCode == Event.KEY_TAB){
                    return;
                }
                realCallback();
            });
        }.bind(this) );
        if(form.ajxpPaneObject){
            form.ajxpPaneObject.observe("after_replicate_row", function(replicate){
                replicate.select("div.SF_element").each(function(element){
                    element.select("input,textarea,select").invoke("observe", "change", realCallback);
                    element.select("input,textarea").invoke("observe", "keydown", function(event){
                        if(event.keyCode == Event.KEY_DOWN || event.keyCode == Event.KEY_UP || event.keyCode == Event.KEY_RIGHT || event.keyCode == Event.KEY_LEFT || event.keyCode == Event.KEY_TAB){
                            return;
                        }
                        realCallback();
                    });
                }.bind(this) );
            });
        }
    },

    disableShortcutsOnForm: function(form){
        form.select("input,textarea,select").invoke("observe", "focus", function(event){
            if(event.target.nodeName.toLowerCase() == 'select') event.target.writeAttribute('data-disableShortcutsOnForm', 'true');
            ajaxplorer.disableAllKeyBindings();
        });
        form.select("input,textarea,select").invoke("observe", "blur", function(){
            ajaxplorer.enableAllKeyBindings();
        });
        form.select(".SF_replicableGroup").invoke("writeAttribute", "data-disableShortcutsOnForm", "true");
    },

    confirmExistingImageDelete : function(modalParent, imgSrc, hiddenInput, param){
        if(window.confirm('Do you want to remove the current image?')){
            hiddenInput.setValue("ajxp-remove-original");
            imgSrc.src = param.get('defaultImage');
            this.triggerEvent(imgSrc.next("input[type='hidden']"), 'change');
        }
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
			}
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
            if(el.getAttribute('data-ajxp_type')){
                parametersHash.set(prefix+el.name+'_ajxptype', el.getAttribute('data-ajxp_type'));
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
	        	if(form.SF_accordion && el.up('div.accordion_content').previous('div.accordion_toggle')){
	        		el.up('div.accordion_content').previous('div.accordion_toggle').addClassName('accordion_toggle_failed');
	        	}
	        });
        }
        // Reorder keys
        var allKeys = parametersHash.keys();
        allKeys.sort();
        allKeys.reverse();
        var treeKeys = {};
        allKeys.each(function(key){
            if(key.indexOf("/") === -1) return;
            if(key.endsWith("_ajxptype")) return;
            var typeKey = key + "_ajxptype";
            var parts = key.split("/");
            var parentName = parts.shift();
            var parentKey;
            while(parts.length > 0){
                if(!parentKey){
                    parentKey = treeKeys;
                }
                if(!parentKey[parentName]) {
                    parentKey[parentName] = {};
                }
                parentKey = parentKey[parentName];
                parentName = parts.shift();
            }
            var type = parametersHash.unset(typeKey);
            if(parentKey && !parentKey[parentName]) {
                if(type == "boolean"){
                    var v = parametersHash.get(key);
                    parentKey[parentName] = (v == "true" || v == 1 || v === true );
                }else if(type == "integer"){
                    parentKey[parentName] = parseInt(parametersHash.get(key));
                }else{
                    parentKey[parentName] = parametersHash.get(key);
                }
            }else if(parentKey && type.startsWith('group_switch:')){
                parentKey[parentName]["group_switch_value"] = parametersHash.get(key);
            }
            parametersHash.unset(key);
        });
        $H(treeKeys).each(function(pair){
            if(parametersHash.get(pair.key + '_ajxptype') && parametersHash.get(pair.key + '_ajxptype').startsWith('group_switch:')
                && !pair.value['group_switch_value']){
                pair.value['group_switch_value'] = parametersHash.get(pair.key);
            }
            parametersHash.set(pair.key, Object.toJSON(pair.value));
            parametersHash.set(pair.key+"_ajxptype", "text/json");
        });

		return missingMandatory.size();
	},		
	
	/**
	 * Replicate a template row
	 * @param templateRow HTMLElement
	 * @param number Integer
	 * @param form HTMLForm
	 */
	replicateRow : function(templateRow, number, form, values){
        if(form.ajxpPaneObject) form.ajxpPaneObject.notify('before_replicate_row', templateRow);
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
                input.writeAttribute('data-originalName', input.getAttribute('name'));
				input.setAttribute('name', newName);
				if(form && Prototype.Browser.IE){form[newName] = input;}
                if(values && values.get(newName)){
                    input.setValue(values.get(newName));
                }else{
                    input.setValue('');
                }
			});
			templateRow.insert({after:tr});
            if(tr.select('.SF_replication_Add').length){
                tr.select('.SF_replication_Add').invoke("remove");
            }
            if(index == number - 1){
                if(templateRow.select('.SF_replication_Add').length){
                    tr.insert(templateRow.select('.SF_replication_Add')[0]);
                }
            }
            tr.select(".simple_tooltip_observer[data-toolTipLabel]").each(function(sT){
                var label = sT.readAttribute('data-tooltipLabel');
                var desc = sT.readAttribute('data-tooltipDescription');
                modal.simpleTooltip(sT, '<div class="simple_tooltip_title">'+label+'</div>'+desc,
                    'middle left', "right_arrow_tip", "element");
            });
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
        if(tr.readAttribute('data-disableShortcutsOnForm')){
            this.disableShortcutsOnForm(tr);
        }
        if(form.ajxpPaneObject) form.ajxpPaneObject.notify('after_replicate_row', tr);
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
            var realFieldName;
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
	},

    destroyForm : function(form){
        form.select("div.SF_inlineMonitoring").each(function(el){
            if(el.pe) el.pe.stop();
        });
    }
});