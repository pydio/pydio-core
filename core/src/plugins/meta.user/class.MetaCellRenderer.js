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

    staticGetMetaConfigs: function(){
        if(pydio && pydio.user && pydio.user.activeRepository && MetaCellRenderer.configsCache && MetaCellRenderer.configsCache.get(pydio.user.activeRepository )){
            return MetaCellRenderer.configsCache.get(pydio.user.activeRepository);
        }
        var configs = $H();
        try{
            configs = $H(pydio.getPluginConfigs("meta.user").get("meta_definitions").evalJSON());
            configs.each(function(pair){
                var type = pair.value.type;
                if(type == 'choice' && pair.value.data){
                    var values = {};
                    $A(pair.value.data.split(",")).each(function(keyLabel){
                        var parts = keyLabel.split("|");
                        values[parts[0]] = parts[1];
                    });
                    pair.value.data = values;
                }
            });
        }catch(e){
        }
        if(pydio && pydio.user && pydio.user.activeRepository){
            if(!MetaCellRenderer.configsCache) MetaCellRenderer.configsCache = $H();
            MetaCellRenderer.configsCache.set(pydio.user.activeRepository, configs);
        }
        return configs;
    },

    /*************/
    /* SELECTORS */
    /*************/
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
                if(element.down('.text_label') && values[element.down('.text_label').innerHTML.stripTags()]){
                    element.down('.text_label').update(values[element.down('.text_label').innerHTML.stripTags()]);
                }
                element.writeAttribute("data-"+metadataDef.attributeName+"-sorter_value", nodeMetaValue);
            }
        }
    },

    formPanelSelectorFilter: function(formElement, form){
        var selectorValues;
        try{
            selectorValues = MetaCellRenderer.staticMetadataCache.get(formElement.name);
        }catch(e){}
        if(!selectorValues){
            var definitions = MetaCellRenderer.prototype.staticGetMetaConfigs();
            selectorValues = definitions.get(formElement.name).data;
        }
        if(selectorValues){
            if(!selectorValues) return;
            var value = formElement.getValue();
            var select = new Element('select', {name: formElement.name, className:'select_meta_selector'});
            select.insert(new Element("option", {value:''}).update(''));
            $H(selectorValues).each(function(pair){
                select.insert(new Element("option", {value:pair.key}).update(pair.value));
                if(value) select.setValue(value);
            });
            if(formElement.id) select.id = formElement.id;
            formElement.replace(select);
        }
    },

    /*****************/
	/* LABELS SYSTEM */
    /*****************/
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

            if(element.nodeName.toLowerCase() == 'span') {
                element = element.up(".detailed");
                if(!element) return;
            }
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
		var hidden = new Element('input', {
            type:'hidden',
            name:formElement.name,
            value:formElement.value,
            id: formElement.id
        });
		form.insert(hidden);
		var cssList = obj.cssList;
		var selector = new Element('select', {className:'select_meta_selector'});
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

	/*********************/
	/* STARS RATE SYSTEM */
    /*********************/
	starsRateFilter: function(element, ajxpNode, type, metadataDef, ajxpNodeObject){
        var attributeName = metadataDef.attributeName;

		if(type == 'thumb') return;
        if(!element) return;
		var value = 0;
		try{
			var content = element.down('span').innerHTML;
		}catch(e){
		}
		if(content) value = parseInt(content);
        else{
            content = ajxpNode.getMetadata().get(attributeName);
            if(content) value = parseInt(content);
        }
		var obj = new MetaCellRenderer();
		if(element.down('span.text_label')){
			var div = obj.createStars(value, null, attributeName);
			div.setStyle({width:'70px'});
            if(type == 'detail') {
                div.setStyle({display:'inline-block'});
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

    formPanelStars: function(formElement, form){
        var value = formElement.value;
        var name = formElement.name;
        var obj = new MetaCellRenderer();
        var div = obj.createStars(value, form, name);
        div.setStyle({paddingTop:3});
        formElement.replace(div);
        form.insert(new Element('input', {
            type:'hidden',
            name:name,
            value:value,
            id:formElement.id
        }));
    },

    createStars : function(value, containingForm, elementName){
        var imgOff = 'plugins/meta.user/rating_off.png';
        var imgOn = 'plugins/meta.user/rating.png';
        var imgRemove = 'plugins/meta.user/rating_remove.png';
        var cont = new Element('div');
        var img;
        if(containingForm){
            img = new Element('img',{
                src:imgRemove,
                style:'float:left;cursor:pointer;margin-right:2px;padding-right:3px;border-right:1px solid #ccc;',
                note:0,
                title:MessageHash['meta.user.3']
            });
            cont.insert(img);
        }
        for(var i=1;i<6;i++){
            img = new Element('img',{
                src:(value>=i?imgOn:imgOff),
                style:'float:left;cursor:pointer;margin-right:2px;',
                note:i,
                title:i
            });
            cont.insert(img);
        }
        cont.select('img').invoke('observe', 'click', function(event){
            var note = Event.element(event).readAttribute('note');
            if(!containingForm){
                window.setTimeout(function(){
                    var selectedNode = pydio.getUserSelection().getUniqueNode();
                    var conn = new Connexion();
                    conn.setParameters($H({
                        get_action : 'edit_user_meta',
                        file	   : selectedNode.getPath()
                    }));
                    conn.addParameter(elementName, note);
                    conn.onComplete = function(transport){
                        pydio.actionBar.parseXmlMessage(transport.responseXML);
                    };
                    conn.sendAsync();
                }, 500);
            }else{
                if(note != '0'){
                    containingForm.down('input[name="'+elementName+'"]').setValue(note);
                }else{
                    containingForm.down('input[name="'+elementName+'"]').setValue('');
                }
                var img = Event.element(event);
                img.previousSiblings('img[src="'+imgOff+'"]').each(function(i){if(i.src!=imgRemove){
                    i.src = imgOn;
                }});
                img.nextSiblings('img[src="'+imgOn+'"]').each(function(i){i.src = imgOff;});
                img.src = imgOn;
            }
        });
        return cont;
    },


    /*******************/
    /* TAGS MODIFIERS  */
    /*******************/
    formPanelTags: function(formElement, form){

        var fieldName = formElement.name;
        var completer = new MetaTagsCompleter(formElement, fieldName);

    },

    displayTagsAsBlocks: function(element, value, ajxpNode){
        if(!value) return;
        var values = $A(value.split(",")).invoke("strip");
        element.update('');
        values.each(function(v){
            var tag = new Element('span', {className:"meta_user_tag_block"}).update(v + " <span class='icon-remove' style='cursor: pointer;'></span>");
            element.insert(tag);
            var remove = tag.down(".icon-remove");
            remove.observe("click", function(){
                var conn = new Connexion();
                conn.setParameters($H({
                    get_action:"edit_user_meta",
                    file: ajxpNode.getPath(),
                    tags: values.without(v).join(", ")
                }));
                conn.onComplete = function(transport){
                    pydio.actionBar.parseXmlMessage(transport.responseXML);
                };
                conn.sendAsync();
            });
        });
    },

    /************/
    /* TEXTAREA */
    /************/
    formTextarea: function(formElement, form){
        var obj = new MetaCellRenderer();
        var cont = new Element('textarea', {name:formElement.name,style:'float: left;width: 161px;border-radius: 3px;padding: 2px;height:100px;'});
        cont.innerHTML = formElement.value;
        formElement.replace(cont);
    },

    /********************/
    /* GENERIC METHODS  */
    /********************/
	infoPanelModifier : function(htmlElement, ajxpNode){
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
                case "tags":
                    var value = td.innerHTML.strip();
                    this.displayTagsAsBlocks(td, value, ajxpNode);
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

    }

});

/**
 * Encapsulation of the Prototype Autocompleter for Pydio purposes.
 * Should be ported for local provides
 */
Class.create("MetaTagsCompleter", Autocompleter.Base, {

    valuesLoaded: null,
    /**
     * Constructor
     * @param element HTMLElement
     * @param fieldName String
     */
    initialize: function(element, fieldName) {
        var update = "meta_tags_complete_"+fieldName;
        if(Object.isString(update) && !$(update)){
            $$('body')[0].insert(new Element('div', {
                id:update,
                className:"autocomplete",
                style:"position:absolute;z-index:100000;margin-top: 0;"
            }));
        }
        var options = {fieldName: fieldName, tokens: ","};
        this.baseInitialize(element, update, options);
        this.options.defaultParams = this.options.parameters || null;
        this.options.minChars	   = 0;

        element.observe("click", function(){
            this.activate();
        }.bind(this));
    },

    valuesToChoices: function(search){
        var currentValues = $A(this.element.getValue().split(",")).invoke("strip");
        var choices = "";
        if(search && this.valuesLoaded.indexOf(search)) {
            choices += "<li>"+search+"</li>";
        }
        this.valuesLoaded.each(function(v){
            if(v.indexOf(search) === 0 && currentValues.indexOf(v) === -1){
                choices += "<li>"+v+"</li>";
            }
        }.bind(this));
        choices = "<ul>"+ choices + "</ul>";
        this.updateChoices(choices);
        return choices;
    },

    /**
     * Gets the choices
     */
    getUpdatedChoices: function() {
        this.startIndicator();
        var value = this.getToken().strip();
        if(this.valuesLoaded){
            var choices = this.valuesToChoices(value);
            this.updateChoices(choices);
            return choices;
        }

        var connexion = new Connexion();
        connexion.setParameters($H({get_action:'meta_user_list_tags'}));
        if(this.options.fieldName){
            connexion.addParameter("meta_field_name", this.options.fieldName);
        }
        connexion.onComplete = function(transport){
            this.valuesLoaded = $A(transport.responseJSON);
            var choices = this.valuesToChoices(value);
            this.updateChoices(choices);
            this.stopIndicator();
            return choices;
        }.bind(this);

        connexion.sendAsync();

        return "";
    }

});