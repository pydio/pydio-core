/**
 * @package info.ajaxplorer.plugins
 * 
 * Copyright 2007-2009 Charles du Jeu
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 * 
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 * 
 * The main conditions are as follow : 
 * You must conspicuously and appropriately publish on each copy distributed 
 * an appropriate copyright notice and disclaimer of warranty and keep intact 
 * all the notices that refer to this License and to the absence of any warranty; 
 * and give any other recipients of the Program a copy of the GNU Lesser General 
 * Public License along with the Program. 
 * 
 * If you modify your copy or copies of the library or any portion of it, you may 
 * distribute the resulting library provided you do so under the GNU Lesser 
 * General Public License. However, programs that link to the library may be 
 * licensed under terms of your choice, so long as the library itself can be changed. 
 * Any translation of the GNU Lesser General Public License must be accompanied by the 
 * GNU Lesser General Public License.
 * 
 * If you copy or distribute the program, you must accompany it with the complete 
 * corresponding machine-readable source code or with a written offer, valid for at 
 * least three years, to furnish the complete corresponding machine-readable source code. 
 * 
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * Description : Static class for renderers
 */
Class.create("MetaCellRenderer", {	
	initialize: function(){
		this.cssList = new Hash({
			'low': {cssClass:'meta_low', label:MessageHash['meta.serial.4'], sortValue:'5'},
			'todo' : {cssClass:'meta_todo', label:MessageHash['meta.serial.5'], sortValue:'4'},
			'personal' : {cssClass:'meta_personal', label:MessageHash['meta.serial.6'], sortValue:'3'},
			'work' : {cssClass:'meta_work', label:MessageHash['meta.serial.7'], sortValue:'2'},
			'important' : {cssClass:'meta_important', label:MessageHash['meta.serial.8'], sortValue:'1'}
		});
		var head = $$('head')[0];
		var href = "plugins/meta.serial/css/labelsClasses.css";
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
	
	/* LABELS SYSTEM */
	cssLabelsFilter : function(element, ajxpNode, type){
		if(type == 'row'){
			try{
				var span = element.down('span');
				var content = span.innerHTML;
			}catch(e){
			}
			if(content){
				var obj = new MetaCellRenderer();
				var rule = obj.findCssRule(content.strip());
				if(rule){
					element.up().addClassName(rule.cssClass);					
					span.update(rule.label);
					element.writeAttribute("sorter_value", rule.sortValue);
				}
			}
		}else if(type =='thumb'){
			var content = ajxpNode.getMetadata().get('css_label');
			if(content){
				var obj = new MetaCellRenderer();
				var rule = obj.findCssRule(content.strip());
				if(rule){
					element.addClassName(rule.cssClass);
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
		var selector = new Element('select', {style:"width:120px;height:20px;"});
		selector.insert(new Element('option', {
			name:'',
			value:'', 
			selected:(!value)
		}).update(MessageHash['meta.serial.2']));
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
	starsRateFilter: function(element, ajxpNode, type){
		if(type == 'thumb') return;
		var value = 0;
		try{
			var content = element.select('span')[0].innerHTML;
		}catch(e){
		}
		if(content) value = parseInt(content);
		var obj = new MetaCellRenderer();
		if(element.down('span.text_label')){
			var div = obj.createStars(value);
			div.setStyle({width:'70px'});
			element.down('span.text_label').update(div);
		}else{
			element.update(obj.createStars(value));	
		}
		element.writeAttribute("sorter_value", value);	
	},
	
	infoPanelModifier : function(htmlElement){
		var td = htmlElement.select('#ip_stars_rate')[0];
		if(td){
			var obj = new MetaCellRenderer();
			var value = parseInt(td.innerHTML);
			td.update(obj.createStars(value));
		}
		td = htmlElement.select('#ip_css_label')[0];
		if(td){
			var obj = new MetaCellRenderer();
			var value = td.innerHTML.strip();
			var rule = obj.findCssRule(value);
			if(rule){
				td.addClassName(rule.cssClass);
				td.update(rule.label);
			}
		}				
	},
	
	formPanelStars: function(formElement, form){
		var value = formElement.value;
		var obj = new MetaCellRenderer();
		var div = obj.createStars(value, form);
		div.setStyle({paddingTop:3});
		formElement.replace(div);
		form.insert(new Element('input', {type:'hidden',name:'stars_rate',value:value}));
	},
		
	createStars : function(value, containingForm){
		var imgOff = 'plugins/meta.serial/rating_off.png';
		var imgOn = 'plugins/meta.serial/rating.png';
		var imgRemove = 'plugins/meta.serial/rating_remove.png';
		var cont = new Element('div');
		if(containingForm){
			var img = new Element('img',{
				src:imgRemove,
				style:'float:left;cursor:pointer;margin-right:2px;padding-right:3px;border-right:1px solid #ccc;',
				note:0,
				title:MessageHash['meta.serial.3']
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
				conn.setParameters(new Hash({
					get_action : 'edit_serial_meta',
					stars_rate : note,
					file	   : selectedNode.getPath()
				}));
				if(containingForm){
					containingForm.select('input').each(function(el){						
						if(el.name != 'stars_rate'){
							conn.addParameter(el.name, el.value);
						}
					});
				}
				conn.onComplete = function(){
					ajaxplorer.getContextHolder().setPendingSelection(selectedNode.getPath());
					ajaxplorer.fireContextRefresh();
					if(containingForm){
						hideLightBox(true);
					}
				};
				conn.sendAsync();
			}, 500);
		});
		return cont;
	}
});