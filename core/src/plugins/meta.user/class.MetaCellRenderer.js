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
 * Description : Static class for renderers
 */
Class.create("MetaCellRenderer", {	
	initialize: function(){
		this.cssList = new Hash({
			'low': {cssClass:'meta_low', label:MessageHash['meta.user.4'], sortValue:'5'},
			'todo' : {cssClass:'meta_todo', label:MessageHash['meta.user.5'], sortValue:'4'},
			'personal' : {cssClass:'meta_personal', label:MessageHash['meta.user.6'], sortValue:'3'},
			'work' : {cssClass:'meta_work', label:MessageHash['meta.user.7'], sortValue:'2'},
			'important' : {cssClass:'meta_important', label:MessageHash['meta.user.8'], sortValue:'1'}
		});
		var head = $$('head')[0];
		var href = "plugins/meta.user/css/labelsClasses.css";
		if(!head.down('link[href="'+href+'"]')){
			var cssNode = new Element('link', {
				type : 'text/css',
				rel  : 'stylesheet',
				href : href,
				media : 'screen'
			});
			head.insert(cssNode);
		}
	},

    /* SELECTORS */
    selectorsFilter : function(element, ajxpNode, type, metadataDef, ajxpNodeObject){
        if(!metadataDef) return;
        if(!MetaCellRenderer.staticMetadataCache){
            MetaCellRenderer.staticMetadataCache = $H();
        }
        var values = {};
        if(!MetaCellRenderer.staticMetadataCache.get(metadataDef.attributeName)){
            if(metadataDef['metaAdditional']){
                metadataDef['metaAdditional'].split(",").each(function(keyLabel){
                    var parts = keyLabel.split("|");
                    values[parts[0]] = parts[1];
                });
                MetaCellRenderer.staticMetadataCache.set(metadataDef.attributeName, values);
            }
        }
        values = MetaCellRenderer.staticMetadataCache.get(metadataDef.attributeName);
        var nodeMetaValue = ajxpNode.getMetadata().get(metadataDef.attributeName) || '';
        if(nodeMetaValue){
            nodeMetaValue = $H(values).keys().indexOf(nodeMetaValue);
        }else{
            nodeMetaValue = -1;
        }
        if(element != null){
            if(type == 'row'){
                if(values[element.down('.text_label').innerHTML.stripTags()]){
                    element.down('.text_label').update(values[element.down('.text_label').innerHTML.stripTags()]);
                }
                element.writeAttribute("data-sorter_value", nodeMetaValue);
            }else{
                element.writeAttribute("data-"+metadataDef.attributeName+"-sorter_value", nodeMetaValue);
            }
        }
    },

    formPanelSelectorFilter: function(formElement, form){
        if(MetaCellRenderer.staticMetadataCache && MetaCellRenderer.staticMetadataCache.get(formElement.name)){
            var selectorValues  = MetaCellRenderer.staticMetadataCache.get(formElement.name);
            if(!selectorValues) return;
            var value = formElement.getValue();
            var select = new Element('select', {name: formElement.name,style:'width:56%;height:24px;'});
            $H(selectorValues).each(function(pair){
                select.insert(new Element("option", {value:pair.key}).update(pair.value));
                if(value) select.setValue(value);
            });
            formElement.replace(select);
        }
    },

	/* LABELS SYSTEM */
	cssLabelsFilter : function(element, ajxpNode, type, metadataDef, ajxpNodeObject){
        var attName = metadataDef.attributeName;
        var content, obj, rule;
        if(!element && ajxpNodeObject){
            content = ajxpNode.getMetadata().get(attName);
            if(content){
                obj = new MetaCellRenderer();
                rule = obj.findCssRule(content.strip());
                if(rule){
                    ajxpNodeObject.addClassName(rule.cssClass);
                }
            }
        }else if(type == 'row'){
			try{
				var span = element.down('span');
				content = span.innerHTML;
			}catch(e){
			}
			if(content){
				obj = new MetaCellRenderer();
				rule = obj.findCssRule(content.strip());
				if(rule){
					element.up().addClassName(rule.cssClass);					
					span.update(rule.label);
					element.writeAttribute("data-sorter_value", rule.sortValue);
				}
			}
		}else if(type =='thumb'){
			content = ajxpNode.getMetadata().get(attName);
			if(content){
				obj = new MetaCellRenderer();
				rule = obj.findCssRule(content.strip());
				if(rule){
					element.addClassName(rule.cssClass);
                    element.writeAttribute("data-"+attName+"-sorter_value", rule.sortValue);
				}
			}			
		}else if(type == 'detail'){

            if(element.nodeName.toLowerCase() == 'span') return;
            content = ajxpNode.getMetadata().get(attName);
            if(content){
                obj = new MetaCellRenderer();
                rule = obj.findCssRule(content.strip());
                if(rule && element){
                    element.addClassName(rule.cssClass);
                    element.writeAttribute("data-"+attName+"-sorter_value", rule.sortValue);
                }
            }


        }
	},
	
	formPanelCssLabels: function(formElement, form){
		var value = formElement.value;
		var obj = new MetaCellRenderer();
		var hidden = new Element('input', {type:'hidden', name:formElement.name, value:formElement.value});
		form.insert(hidden);
		var cssList = obj.cssList;
		var selector = new Element('select', {style:"width:56%;height:24px;"});
		selector.insert(new Element('option', {
			name:'',
			value:'', 
			selected:(!value)
		}).update(MessageHash['meta.user.2']));
		cssList.each(function(pair){
			var option = new Element('option', {
				name:pair.key,
				value:pair.key, 
				selected:(value == pair.key),
				className:pair.value.cssClass				
			}).update(pair.value.label);
			selector.insert(option);
		});
		formElement.replace(selector);
		selector.observe("change", function(){
			hidden.value = selector.getValue();
		});
	},
	
	findCssRule : function(value){
		return this.cssList.get(value);
	},
	
	/* STARS RATE SYSTEM */
	starsRateFilter: function(element, ajxpNode, type, metadataDef, ajxpNodeObject){
        var attributeName = metadataDef.attributeName;

		if(type == 'thumb') return;
        if(!element) return;
		var value = 0;
		try{
			var content = element.select('span')[0].innerHTML;
		}catch(e){
		}
		if(content) value = parseInt(content);
		var obj = new MetaCellRenderer();
		if(element.down('span.text_label')){
			var div = obj.createStars(value, null, attributeName);
			div.setStyle({width:'70px'});
            if(type == 'detail') {
                div.setStyle({display:'inline'});
            }
			element.down('span.text_label').update(div);
		}else{
			if(type != 'detail') element.update(obj.createStars(value, null, attributeName));
		}
        if(type == 'row'){
            element.writeAttribute("data-sorter_value", value);
        }else{
            element.writeAttribute("data-"+attributeName+"-sorter_value", value);
        }
	},
	
	infoPanelModifier : function(htmlElement){
        var obj = new MetaCellRenderer();
        htmlElement.select('[data-metatype]').each(function(td){
            var metaType = td.readAttribute("data-metatype");
            var metaName = td.id.replace(/^ip_/, '');
            switch(metaType){
                case "stars_rate":
                    var value = parseInt(td.innerHTML);
                    td.update(this.createStars(value, null, metaName));
                break;
                case "css_label":
                    var value = td.innerHTML.strip();
                    var rule = this.findCssRule(value);
                    if(rule){
                        td.addClassName(rule.cssClass);
                        td.update(rule.label);
                    }
                break;
                case "choice":
                    if(MetaCellRenderer.staticMetadataCache && MetaCellRenderer.staticMetadataCache.get(metaName)){
                        var selectorValues  = MetaCellRenderer.staticMetadataCache.get(metaName);
                        if(!selectorValues) break;
                        var value = td.innerHTML.strip();
                        if(selectorValues[value]){
                            td.update(selectorValues[value]);
                        }
                    }
                break;
                case "text":
                case "string":
                case "textarea":
                    /*
                    if(typeof td.contentEditable != 'undefined'){
                        enableTextSelection(td);
                        var editableDiv = new Element("div", {
                            contentEditable:"true",
                            title : "Click to edit inline",
                            style:"padding:2px;border:1px solid #bbb; border-radius:2px;"}).update(td.innerHTML);
                        td.update(editableDiv);
                        obj.linkEditableDiv(editableDiv);
                    }*/
                    if(!td.innerHTML) td.update(MessageHash['meta.user.9']);
                break;
                default:
                break;
            }
        }.bind(obj));
	},

    linkEditableDiv : function(div){
        div.saver = new Element("img", {src:"plugins/gui.ajax/res/themes/umbra/images/actions/22/dialog_ok_apply.png"}).setStyle({
            float:"left",
            width: "22px",
            height:"22px",
            cursor:"pointer",
            border:"none"
        });
        div.saver.observe("click", function(){
            if(div.saver.removerTimeout){
                window.clearTimeout(div.saver.removerTimeout);
            }
            var selectedNode = ajaxplorer.getUserSelection().getUniqueNode();
            var conn = new Connexion();
            conn.setMethod("POST");
            conn.setParameters(new Hash({
                get_action  : 'edit_user_meta',
                file	    : selectedNode.getPath()
            }));
            var id = div.up("div").id.substring(3);
            conn.addParameter(id, div.textContent);
            conn.onComplete = function(){
                div.saver.remove();
                ajaxplorer.enableAllKeyBindings();
                ajaxplorer.fireNodeRefresh(selectedNode);
            };
            conn.sendAsync();
        });

        div.observe("focus", function(event){
            var source = event.target;
            var id = source.up("div").id.substring(3);
            source.insert({after:source.saver});
            ajaxplorer.disableAllKeyBindings();
            window.setTimeout(function(){
                document.observeOnce("click", function(clickEvent){
                    if(clickEvent.target != source) source.blur();
                });
            }, 500);
        }).observe("blur", function(event){
            ajaxplorer.enableAllKeyBindings();
            event.target.saver.removerTimeout = window.setTimeout(function(){
                event.target.saver.remove();
            }, 500);
        });

    },

	formPanelStars: function(formElement, form){
		var value = formElement.value;
        var name = formElement.name;
		var obj = new MetaCellRenderer();
		var div = obj.createStars(value, form, name);
		div.setStyle({paddingTop:3});
		formElement.replace(div);
		form.insert(new Element('input', {type:'hidden',name:name,value:value}));
	},
		
	createStars : function(value, containingForm, elementName){
		var imgOff = 'plugins/meta.user/rating_off.png';
		var imgOn = 'plugins/meta.user/rating.png';
		var imgRemove = 'plugins/meta.user/rating_remove.png';
		var cont = new Element('div');
		if(containingForm){
			var img = new Element('img',{
				src:imgRemove,
				style:'float:left;cursor:pointer;margin-right:2px;padding-right:3px;border-right:1px solid #ccc;',
				note:0,
				title:MessageHash['meta.user.3']
			});
			cont.insert(img);			
		}
		for(var i=1;i<6;i++){
			var img = new Element('img',{
				src:(value>=i?imgOn:imgOff),
				style:'float:left;cursor:pointer;margin-right:2px;',
				note:i,
				title:i
			});
			cont.insert(img);
		}
		cont.select('img').invoke('observe', 'click', function(event){
			var note = Event.element(event).readAttribute('note');
			window.setTimeout(function(){
				var selectedNode = ajaxplorer.getUserSelection().getUniqueNode();
				var conn = new Connexion();
                var paramms = new Hash({
                    get_action : 'edit_user_meta',
                    file	   : selectedNode.getPath()
                });
                paramms.set(elementName, note);
				conn.setParameters(paramms);
				if(containingForm){
					containingForm.select('input').each(function(el){						
						if(el.name != elementName){
							conn.addParameter(el.name, el.value);
						}
					});
				}
				conn.onComplete = function(){
					//ajaxplorer.getContextHolder().setPendingSelection(selectedNode.getPath());
					ajaxplorer.fireNodeRefresh(selectedNode);
					if(containingForm){
						hideLightBox(true);
					}
				};
				conn.sendAsync();
			}, 500);
		});
		return cont;
	},
	
	// mod for textarea
	formTextarea: function(formElement, form){
		var obj = new MetaCellRenderer();
		var cont = new Element('textarea', {name:formElement.name,style:'float: left;width: 161px;border-radius: 3px;padding: 2px;height:100px;'});
		cont.innerHTML = formElement.value;
		formElement.replace(cont);
	}
});