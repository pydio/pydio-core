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
 * A dynamic panel displaying details on the current selection. Works with Templates.
 */
Class.create("InfoPanel", AjxpPane, {

	/**
	 * Constructor
	 * @param $super klass Superclass reference
	 * @param htmlElement HTMLElement
	 */
	initialize: function($super, htmlElement, options){
		$super(htmlElement, options);
		disableTextSelection(htmlElement);
        var id = htmlElement.id;
        var container = new Element("div", {className:"panelContent", id:"ip_content_"+id});
        if(options.replaceScroller){
            this.scroller = new Element('div', {id:'ip_scroller_'+id, className:'scroller_track'});
            this.scroller.insert(new Element('div', {id:'ip_scrollbar_handle_'+id, className:'scroller_handle'}));
            this.htmlElement.insert(this.scroller);
            container.setStyle({overflow:"hidden"});
        }
        this.htmlElement.insert(container);
        if(options.replaceScroller){
            this.scrollbar = new Control.ScrollBar('ip_content_'+id,'ip_scroller_'+id, {fixed_scroll_distance:50});
        }
        if(window.ajxpMobile){
            attachMobileScroll(container, "vertical");
        }
        
        this.contentContainer = container;
		this.setContent('<br><br><center><i>'+MessageHash[132]+'</i></center>');
		this.mimesTemplates = new Hash();
		this.registeredMimes = new Hash();
		
		this.updateHandler = this.update.bind(this);
		this.componentConfigHandler = function(event){
			if(event.memo.className == "InfoPanel"){
				this.parseComponentConfig(event.memo.classConfig.get('all'));
			}
		}.bind(this);
		this.userLogHandler = this.clearPanels.bind(this);
		if(!this.options.skipObservers){
            document.observe("ajaxplorer:actions_refreshed", this.updateHandler );
            document.observe("ajaxplorer:component_config_changed", this.componentConfigHandler );
            document.observe("ajaxplorer:user_logged", this.userLogHandler );
        }
	},
	/**
	 * Clean destroy of the panel, remove listeners
	 */
	destroy : function(){
        if(!this.options.skipObservers){
            document.stopObserving("ajaxplorer:actions_refreshed", this.updateHandler );
            document.stopObserving("ajaxplorer:component_config_changed", this.componentConfigHandler );
            document.stopObserving("ajaxplorer:user_logged", this.userLogHandler );
        }
		this.empty();
        if(this.scrollbar){
            this.scrollbar.destroy();
            this.scroller.remove();
        }
        this.htmlElement.update("");
        if(window[this.htmlElement.id]){
            delete window[this.htmlElement.id];
        }
		this.htmlElement = null;
	},
	/**
	 * Clear all panels
	 */
	clearPanels:function(){
		this.mimesTemplates = new Hash();
		this.registeredMimes = new Hash();
	},
	/**
	 * Sets empty content
	 */
	empty : function(){
        if(this.currentPreviewElement && this.currentPreviewElement.destroyElement){
            this.currentPreviewElement.destroyElement();
            this.currentPreviewElement = null;
        }
		this.setContent('');
	},
	
	/**
	 * Updates content by finding the right template and applying it.
	 */
	update : function(objectOrEvent){
		if(!this.htmlElement) return;
        if(objectOrEvent.__className && objectOrEvent.__className == "AjxpNode"){
            var passedNode = objectOrEvent;
        }
        var userSelection = ajaxplorer.getUserSelection();
        var contextNode = userSelection.getContextNode();
		this.empty();
        if(this.scrollbar) this.scrollbar.recalculateLayout();
		if(!contextNode) {
			return;
		}
		if(!passedNode && userSelection.isEmpty())
		{
			var currentRep;
			if(userSelection.getContextNode()){
				currentRep = getBaseName(userSelection.getContextNode().getPath());
			}
			if(currentRep == ""){
				currentRep = $('repo_path').value;
			}
			
			var items = userSelection.getContextNode().getChildren();
			var size = 0;
			var folderNumber = 0;
			var filesNumber = 0;
			for(var i=0;i<items.length;i++){				
				if(!items[i].isLeaf()){
					folderNumber++;
				}else {
					filesNumber++;
				}
				var itemData = items[i].getMetadata();
				if(itemData.get("bytesize") && itemData.get("bytesize")!=""){
					size += parseInt(itemData.get("bytesize"));
				}
			}
			
			this.evalTemplateForMime("no_selection", null, {
				filelist_folders_count:folderNumber,
				filelist_files_count:filesNumber,
				filelist_totalsize:roundSize(size, (MessageHash?MessageHash[266]:'B')),
				current_folder:currentRep
			});
				try{
				if(!folderNumber && $(this.contentContainer).select('[id="filelist_folders_count"]').length){
					$(this.contentContainer).select('[id="filelist_folders_count"]')[0].hide();
				}
				if(!filesNumber && $(this.contentContainer).select('[id="filelist_files_count').length){
					$(this.contentContainer).select('[id="filelist_files_count"]')[0].hide();
				}
				if(!size && $(this.contentContainer).select('[id="filelist_totalsize"]').length){
					$(this.contentContainer).select('[id="filelist_totalsize"]')[0].hide();
				}
			}catch(e){}
			this.addActions('empty');
            if(this.scrollbar) this.scrollbar.recalculateLayout();
			return;
		}
		if(!passedNode && !userSelection.isUnique())
		{
			this.setContent('<br><br><center><i>'+ userSelection.getFileNames().length + ' '+MessageHash[128]+'</i></center><br><br>');
			this.addActions('multiple');
            if(this.scrollbar) this.scrollbar.recalculateLayout();
			return;
		}

        if(!passedNode){
            var uniqNode = userSelection.getUniqueNode();
        }else{
            uniqNode = passedNode;
        }
		var isFile = false;
		if(uniqNode) isFile = uniqNode.isLeaf();
		this.evalTemplateForMime((isFile?'generic_file':'generic_dir'), uniqNode);
		
		var extension = getAjxpMimeType(uniqNode);
		if(extension != "" && this.registeredMimes.get(extension)){
			this.evalTemplateForMime(extension, uniqNode);
		}
		
		this.addActions('unique');
		var fakes = this.contentContainer.select('div[id="preview_rich_fake_element"]');
		if(fakes && fakes.length){
			this.currentPreviewElement = this.getPreviewElement(uniqNode, false);
			$(fakes[0]).replace(this.currentPreviewElement);			
			this.resize();
		}
		if(this.scrollbar) this.scrollbar.recalculateLayout();
	},
	/**
	 * Insert html in content pane
	 * @param sHtml String
	 */
	setContent : function(sHtml){
		if(!this.htmlElement) return;
		this.contentContainer.update(sHtml);
	},
	/**
	 * Show/Hide the panel
	 * @param show Boolean
	 */
	showElement : function(show){
		if(!this.htmlElement) return;
		if(show) this.htmlElement.show();
		else this.htmlElement.hide();
	},
	/**
	 * Resize the panel
	 */
	resize : function(){
		fitHeightToBottom(this.contentContainer, null);
        if(this.scrollbar){
            this.scroller.setStyle({height:parseInt(this.contentContainer.getHeight())+'px'});
            this.scrollbar.recalculateLayout();
        }
		if(this.htmlElement && this.currentPreviewElement && this.currentPreviewElement.visible()){
			var squareDim = Math.min(parseInt(this.htmlElement.getWidth()-40));
			this.currentPreviewElement.resizePreviewElement({width:squareDim,height:squareDim, maxHeight:150});
		}
	},
	/**
	 * Find template and evaluate it
	 * @param mimeType String
	 * @param fileNode AjxpNode
	 * @param tArgs Object
	 */
	evalTemplateForMime: function(mimeType, fileNode, tArgs){
		if(!this.htmlElement) return;
		if(!this.registeredMimes.get(mimeType)) return;		
		var registeredTemplates = this.registeredMimes.get(mimeType);
		for(var i=0;i<registeredTemplates.length;i++){		
			var templateData = this.mimesTemplates.get(registeredTemplates[i]);
			var tString = templateData[0];
			var tAttributes = templateData[1];
			var tMessages = templateData[2];
			var tModifier = templateData[3];
			if(!tArgs){
				tArgs = new Object();
			}
			var panelWidth = this.htmlElement.getWidth();
			var oThis = this;
			if(fileNode){
				var metadata = fileNode.getMetadata();			
				tAttributes.each(function(attName){				
					if(attName == 'basename' && metadata.get('filename')){
						this[attName] = getBaseName(metadata.get('filename'));						
					}
					else if(attName == 'compute_image_dimensions'){
						if(metadata.get('image_width') && metadata.get('image_height')){
							var width = metadata.get('image_width');
							var height = metadata.get('image_height');
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
					else if(attName == 'preview_rich'){
						this[attName] = oThis.getPreviewElement(fileNode, true);
					}
					else if(attName == 'encoded_filename' && metadata.get('filename')){
						this[attName] = encodeURIComponent(metadata.get('filename'));					
					}
					else if(attName == 'escaped_filename' && metadata.get('filename')){
						this[attName] = escape(encodeURIComponent(metadata.get('filename')));					
					}else if(attName == 'formated_date' && metadata.get('ajxp_modiftime')){
						var modiftime = metadata.get('ajxp_modiftime');
						if(modiftime instanceof Object){
							this[attName] = formatDate(modiftime);
						}else{
							var date = new Date();
							date.setTime(parseInt(metadata.get('ajxp_modiftime'))*1000);
							this[attName] = formatDate(date);
						}
					}
					else if(attName == 'uri'){
						var url = document.location.href;
						if(url[(url.length-1)] == '/'){
							url = url.substr(0, url.length-1);
						}else if(url.lastIndexOf('/') > -1){
							url = url.substr(0, url.lastIndexOf('/'));
						}
						this[attName] = url;
					}
					else if(metadata.get(attName)){
						this[attName] = metadata.get(attName);
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
			this.contentContainer.insert(template.evaluate(tArgs));
			if(tModifier){
				var modifierFunc = eval(tModifier);
				modifierFunc(this.contentContainer);
			}
		}
	},
		
	/**
	 * Adds an "Action" section below the templates
	 * @param selectionType String 'empty', 'multiple', 'unique'
	 */
	addActions: function(selectionType){
        if(this.options.skipActions) return;
		var actions = ajaxplorer.actionBar.getActionsForAjxpWidget("InfoPanel", this.htmlElement.id);
		if(!actions.length) return;
		var actionString = '<div class="panelHeader infoPanelGroup">'+MessageHash[5]+'</div><div class="infoPanelActions">';
		var count = 0;
		actions.each(function(action){
			if(selectionType == 'empty' && action.context.selection) return;
			if(selectionType == 'multiple' && action.selectionContext.unique) return; 
			if(selectionType == 'unique' && (!action.context.selection || action.selectionContext.multipleOnly)) return;			
			actionString += '<a href="" onclick="ajaxplorer.actionBar.fireAction(\''+action.options.name+'\');return false;"><img src="'+resolveImageSource(action.options.src, '/images/actions/ICON_SIZE', 16)+'" width="16" height="16" align="absmiddle" border="0"> '+action.options.title+'</a>';
			count++;
		}.bind(this));
		actionString += '</div>';
		if(!count) return;
		this.contentContainer.insert(actionString);
	},
	/**
	 * Use editors extensions to find a preview element for the current node
	 * @param ajxpNode AjxpNode
	 * @param getTemplateElement Boolean If true, will return a fake div that can be inserted in template and replaced later
	 * @returns String
	 */
	getPreviewElement : function(ajxpNode, getTemplateElement){
		var editors = ajaxplorer.findEditorsForMime(ajxpNode.getAjxpMime(), true);
		if(editors && editors.length)
		{
			ajaxplorer.loadEditorResources(editors[0].resourcesManager);
			var editorClass = Class.getByName(editors[0].editorClass);
			if(editorClass){
				if(getTemplateElement){
					return '<div id="preview_rich_fake_element"></div>';
				}else{
					var element = editorClass.prototype.getPreview(ajxpNode, true);
					return element;	
				}
			}
		}
		return '<img src="' + resolveImageSource(ajxpNode.getIcon(), '/images/mimes/ICON_SIZE',64) + '" height="64" width="64">';
	},
	/**
	 * Parses config node
	 * @param configNode DOMNode
	 */
	parseComponentConfig: function(configNode){
		var panels = XPathSelectNodes(configNode, "infoPanel|infoPanelExtension");
		for(var i = 0; i<panels.length; i++){
			var panelMimes = panels[i].getAttribute('mime');
			var attributes = $A(panels[i].getAttribute('attributes').split(","));
			var messages = new Hash();
			var modifier = panels[i].getAttribute('modifier') || '';
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
			var tId = hex_md5(htmlContent);
			if(this.mimesTemplates.get(tId)){
				continue;
			}
			this.mimesTemplates.set(tId, $A([htmlContent,attributes, messages, modifier]));				
			
			$A(panelMimes.split(",")).each(function(mime){
				var registered = this.registeredMimes.get(mime) || $A([]);
				registered.push(tId);
				this.registeredMimes.set(mime, registered);
			}.bind(this));
		}
	}
	
});
