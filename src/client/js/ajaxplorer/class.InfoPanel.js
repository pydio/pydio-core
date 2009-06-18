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
 * Description : A dynamic panel displaying details on the current selection. Works with Templates.
 */
InfoPanel = Class.create({

	initialize: function(htmlElement){
		this.htmlElement = $(htmlElement);
		this.setContent('<br><br><center><i>'+MessageHash[132]+'</i></center>');	
		this.mimesTemplates = new Hash();
		this.registeredMimes = new Hash();		
	},
	
	setTemplateForMime: function(mimeType, templateString, attributes, messages){
		var tId = this.mimesTemplate.size();
		this.registeredMimes.set(mimeType, tId);
		this.mimesTemplates.push($A([templateString,attributes, messages]));
	},
	
	clearPanels:function(){
		this.mimesTemplates = new Hash();
		this.registeredMimes = new Hash();
	},
	
	empty : function(){
		this.setContent('');
	},
	
	update : function(){
		if(!this.htmlElement) return;
		var filesList = ajaxplorer.getFilesList();
		var userSelection = filesList.getUserSelection();
		if(userSelection.isEmpty())
		{
			var currentRep = getBaseName(filesList.getCurrentRep());
			if(currentRep == ""){
				currentRep = $('repo_path').value;
			}
			var items = filesList.getItems();
			var size = 0;
			var folderNumber = 0;
			var filesNumber = 0;
			for(var i=0;i<items.length;i++){
				if(items[i].getAttribute("is_file")=="0") folderNumber++;
				else filesNumber++;
				if(items[i].getAttribute("bytesize") && items[i].getAttribute("bytesize")!=""){
					size += parseInt(items[i].getAttribute("bytesize"));
				}
			}
			
			this.evalTemplateForMime("no_selection", null, {
				filelist_folders_count:folderNumber,
				filelist_files_count:filesNumber,
				filelist_totalsize:roundSize(size, MessageHash[266]),
				current_folder:currentRep
			});
				try{
				if(!folderNumber && $(this.htmlElement).select('[id="filelist_folders_count"]').length){
					$(this.htmlElement).select('[id="filelist_folders_count"]')[0].hide();
				}
				if(!filesNumber && $(this.htmlElement).select('[id="filelist_files_count').length){
					$(this.htmlElement).select('[id="filelist_files_count"]')[0].hide();
				}
				if(!size && $(this.htmlElement).select('[id="filelist_totalsize"]').length){
					$(this.htmlElement).select('[id="filelist_totalsize"]')[0].hide();
				}
			}catch(e){}
			return;
		}
		if(!userSelection.isUnique())
		{
			this.setContent('<br><br><center><i>'+ userSelection.getFileNames().length + ' '+MessageHash[128]+'</i></center><br><br>');
			this.addActions('multiple');
			return;
		}
		
		var uniqItem = userSelection.getUniqueItem();
		var extension = getAjxpMimeType(uniqItem);
		if(extension != "" && this.registeredMimes.get(extension)){
			this.evalTemplateForMime(extension, uniqItem);
		}
		else{
			var isFile = parseInt(uniqItem.getAttribute('is_file'));
			this.evalTemplateForMime((isFile?'generic_file':'generic_dir'), uniqItem);
		}			
	},
	
	setContent : function(sHtml){
		if(!this.htmlElement) return;
		this.htmlElement.update(sHtml);
	},
	
	showElement : function(show){
		if(!this.htmlElement) return;
		if(show) this.htmlElement.show();
		else this.htmlElement.hide();
	},
	
	evalTemplateForMime: function(mimeType, fileData, tArgs){
		if(!this.htmlElement) return;
		if(!this.registeredMimes.get(mimeType)) return;		
		var templateData = this.mimesTemplates.get(this.registeredMimes.get(mimeType));
		var tString = templateData[0];
		var tAttributes = templateData[1];
		var tMessages = templateData[2];
		if(!tArgs){
			tArgs = new Object();
		}
		var panelWidth = this.htmlElement.getWidth();
		if(fileData){
			tAttributes.each(function(attName){
				if(attName == 'basename' && fileData.getAttribute('filename')){
					this[attName] = getBaseName(fileData.getAttribute('filename'));						
				}
				else if(attName == 'compute_image_dimensions'){
					if(fileData.getAttribute('image_width') && fileData.getAttribute('image_height')){
						var width = fileData.getAttribute('image_width');
						var height = fileData.getAttribute('image_height');
						var newHeight = 150;
						if(height < newHeight) newHeight = height;
						var newWidth = newHeight*width/height;
						var dimAttr = 'height="'+newHeight+'"';
						if(newWidth > panelWidth - 16) dimAttr = 'width="100%"';
					}else{
						dimAttr = 'height="64" width="64"';
					}
					this[attName] = dimAttr;
				}
				else if(attName == 'encoded_filename' && fileData.getAttribute('filename')){
					this[attName] = encodeURIComponent(fileData.getAttribute('filename'));					
				}
				else if(attName == 'escaped_filename' && fileData.getAttribute('filename')){
					this[attName] = escape(encodeURIComponent(fileData.getAttribute('filename')));					
				}else if(attName == 'formated_date' && fileData.getAttribute('ajxp_modiftime')){
					var modiftime = fileData.getAttribute('ajxp_modiftime');
					if(modiftime instanceof Object){
						this[attName] = formatDate(modiftime);
					}else{
						var date = new Date();
						date.setTime(parseInt(fileData.getAttribute('ajxp_modiftime'))*1000);
						this[attName] = formatDate(date);
					}
				}
				else if(attName == 'uri'){
					var url = document.location.href;
					if(url.indexOf('/#') > -1){
						url = url.substr(0, url.indexOf('#'));
					}
					if(url[(url.length-1)] == '/'){
						url = url.substr(0, url.length-1);
					}
					this[attName] = url;
				}
				else if(fileData.getAttribute(attName)){
					this[attName] = fileData.getAttribute(attName);
				}
				else{ 
					this[attName] = '';
				}
			}.bind(tArgs));
		}
		tMessages.each(function(pair){
			this[pair.key] = MessageHash[pair.value];
		}.bind(tArgs));
		var template = new Template(tString);
		this.setContent(template.evaluate(tArgs));
		this.addActions('unique');
	},
		
	addActions: function(selectionType){
		var actions = ajaxplorer.actionBar.getInfoPanelActions();
		var actionString = '<div class="infoPanelActions">';
		var count = 0;
		actions.each(function(action){
			if(selectionType == 'multiple' && action.selectionContext.unique) return; 
			if(selectionType == 'unique' && (!action.context.selection || action.selectionContext.multipleOnly)) return;
			//if(count > 0) actionString += ' | ';
			actionString += '<a href="" onclick="ajaxplorer.actionBar.fireAction(\''+action.options.name+'\');return false;"><img src="'+ajxpResourcesFolder+'/images/crystal/actions/22/'+action.options.src+'" width="22" height="22" align="absmiddle" border="0"> '+action.options.title+'</a>';
			count++;
		}.bind(this));
		actionString += '</div>';
		this.htmlElement.insert(actionString);
	},
	
	load: function(){
		if(!this.htmlElement) return;
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'get_driver_info_panels');
		connexion.onComplete = function(transport){
			this.parseXML(transport.responseXML);
		}.bind(this);
		this.clearPanels();
		connexion.sendSync();
	},
	
	parseXML: function(xmlResponse){
		if(xmlResponse == null || xmlResponse.documentElement == null) return;		
		var childs = xmlResponse.documentElement.childNodes;
		if(!childs.length) return;
		var panels = childs[0].childNodes;
		for(var i = 0; i<panels.length; i++){
			if(panels[i].nodeName != 'infoPanel') continue;
			var panelMimes = panels[i].getAttribute('mime');
			var attributes = $A(panels[i].getAttribute('attributes').split(","));
			var messages = new Hash();
			var htmlContent = '';
			var panelChilds = panels[i].childNodes;
			for(j=0;j<panelChilds.length;j++){
				if(panelChilds[j].nodeName == 'messages'){
					var messagesList = panelChilds[j].childNodes;					
					for(k=0;k<messagesList.length;k++){
						if(messagesList[k].nodeName != 'message') continue;
						messages.set(messagesList[k].getAttribute("key"), parseInt(messagesList[k].getAttribute("id")));
					}
				}
				else if(panelChilds[j].nodeName == 'html'){
					htmlContent = panelChilds[j].firstChild.nodeValue;
				}
			}
			var tId = 't_'+this.mimesTemplates.size();
			this.mimesTemplates.set(tId, $A([htmlContent,attributes, messages]));				
			$A(panelMimes.split(",")).each(function(mime){
				this.registeredMimes.set(mime, tId);				
			}.bind(this));
		}
	}
	
});
