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
 * A dynamic panel displaying details on the current selection. Works with Templates.
 */
Class.create("InfoPanel", AjxpPane, {

	/**
	 * Constructor
	 * @param $super klass Superclass reference
	 * @param htmlElement HTMLElement
     * @param options Object
	 */
	initialize: function($super, htmlElement, options){
        if(!options){
            options = {replaceScroller:true};
        }
		$super(htmlElement, Object.extend(Object.clone(options), {replaceScroller:false}));
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
        this.primaryTemplates = new Hash();

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
     * Opened as an editor
     * @param $super
     * @param node
     */
    open : function($super, node){
        this.htmlElement.up('div.dialogBox').setStyle({width:Math.min(450, document.viewport.getWidth())+'px'});
        this.htmlElement.up('div.dialogContent').setStyle({padding:0});
        this.htmlElement.down('#ip_content_info_panel').setStyle({position:"relative", top:0, left:0, width:'100%', height: Math.min(450, document.viewport.getHeight()-28)+'px', overflow:'auto'});
        try{
            this.htmlElement.down('#ip_content_modal_action_form').remove();
            this.htmlElement.down('#ip_scroller_modal_action_form').remove();
        }catch (e){}
        modal.refreshDialogPosition();
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
            try{delete window[this.htmlElement.id];}catch(e){}
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
        if(this.actionsToolbars){
            this.actionsToolbars.invoke('destroy');
            delete this.actionsToolbars;
        }
        if(this.htmlElement){
            this.htmlElement.select(".class-FetchedResultPane").each(function(el){
                el.ajxpPaneObject.destroy();
            });
        }
		this.setContent('');
	},
	
	/**
	 * Updates content by finding the right template and applying it.
	 */
	update : function(objectOrEvent){
		if(!this.htmlElement) return;
        var passedNode;
        if(objectOrEvent.__className && objectOrEvent.__className == "AjxpNode"){
            passedNode = objectOrEvent;
        }
        this.insertedTemplates = $A();
        var userSelection = ajaxplorer.getUserSelection();
        var contextNode = userSelection.getContextNode();
		this.empty();
        this.clearPanelHeaderIcons();
        if(this.scrollbar) this.scrollbar.recalculateLayout();
		if(!contextNode) {
			return;
		}
        var repoOptions = {};
        if(ajaxplorer.user && ajaxplorer.user.getActiveRepository()){
            var repo = ajaxplorer.user.repositories.get(ajaxplorer.user.getActiveRepository());
            if(repo){
                repoOptions = {
                    ws_label: repo.getLabel(),
                    ws_badge: repo.getHtmlBadge()
                }
            }
        }

		if(!passedNode && userSelection.isEmpty())
		{
			var currentRep;
			if(userSelection.getContextNode()){
				currentRep = getBaseName(userSelection.getContextNode().getPath());
			}
			if(currentRep == "" && $('repo_path')){
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
			var options = Object.extend({
                filelist_folders_count:folderNumber,
                filelist_files_count:filesNumber,
                filelist_totalsize:roundSize(size, (MessageHash?MessageHash[266]:'B')),
                current_folder:currentRep
            }, repoOptions);
			this.evalTemplateForMime(
                (contextNode.getPath() =="/" && this.registeredMimes.get("ajxp_root_node") ? "ajxp_root_node": "no_selection"),
                (contextNode.getPath() =="/" ? contextNode : null),
                options);
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
            this.updateTitle();
            disableTextSelection(this.contentContainer);
            return;
		}
		if(!passedNode && !userSelection.isUnique())
		{
            if(this.registeredMimes.get('generic_multiple')){
                this.evalTemplateForMime('generic_multiple', null, {
                    selectedCountSentence:userSelection.getFileNames().length + ' '+MessageHash[128]
                }, userSelection.getSelectedNodes());
            }
			//this.setContent('<br><br><center><i>'+ userSelection.getFileNames().length + ' '+MessageHash[128]+'</i></center><br><br>');
			this.addActions('multiple');
            if(this.scrollbar) this.scrollbar.recalculateLayout();
            disableTextSelection(this.contentContainer);
			return;
		}

        if(!passedNode){
            var uniqNode = userSelection.getUniqueNode();
        }else{
            uniqNode = passedNode;
        }

        this.updateTitle(uniqNode.getLabel());
		var isFile = false;
		if(uniqNode) isFile = uniqNode.isLeaf();
        if(!isFile && uniqNode && uniqNode.isRoot()){
            this.evalTemplateForMime("ajxp_root_node", uniqNode, repoOptions);
        }else{
    		this.evalTemplateForMime((isFile?'generic_file':'generic_dir'), uniqNode);
        }

		var extension = getAjxpMimeType(uniqNode);
        var metadata = uniqNode.getMetadata();
        this.registeredMimes.each(function(pair){
            "use strict";
            if(pair.key == extension){
                this.evalTemplateForMime(extension, uniqNode);
            }
            if(pair.key.indexOf('meta:') === 0 && metadata.get(pair.key.replace('meta:',''))){
                this.evalTemplateForMime(pair.key, uniqNode);
            }
        }.bind(this));
        this.contentContainer.select('[data-ajxpAction]').each(function(act){
            if(act.getAttribute('data-ajxpAction') != 'no-action'){
                act.observe('click', function(event){
                    window.ajaxplorer.actionBar.fireAction(event.target.getAttribute('data-ajxpAction'));
                }.bind(this));
            }else{
                act.setStyle({cursor:"default"});
            }
            var panelPointer = act.up("div.panelHeader").next();
            this.contributePanelHeaderIcon(
                act.getAttribute("class"),
                act.getAttribute("title"),
                act.getAttribute('data-ajxpAction'),
                panelPointer
            );
        }.bind(this));
        this.addActions('unique');
		var fakes = this.contentContainer.select('div[id="preview_rich_fake_element"]');
		if(fakes && fakes.length){
			this.currentPreviewElement = this.getPreviewElement(uniqNode, false);
			$(fakes[0]).replace(this.currentPreviewElement);			
			this.resize();
		}
		if(this.scrollbar) this.scrollbar.recalculateLayout();
        disableTextSelection(this.contentContainer);
	},
	/**
	 * Insert html in content pane
	 * @param sHtml String
	 */
	setContent : function(sHtml){
		if(!this.htmlElement) return;
		this.contentContainer.update(sHtml);
	},

    updateTitle : function(title){
        if(!this.htmlElement) return;
        if(!title) title = MessageHash[131];
        var panelTitle = this.htmlElement.down('div.panelHeader');
        if(panelTitle) {
            if(panelTitle.down('span[ajxp_message_id]')) panelTitle.down('span[ajxp_message_id]').update(title);
            //else panelTitle.update(title);
        }
    },

    clearPanelHeaderIcons:function(){
        if(!this.htmlElement) return;
        var div = this.htmlElement.down('div.folded_icons');
        if(div) div.update("");
    },

    contributePanelHeaderIcon:function(iconClass, iconTitle, ajxpAction, panelPointer){
        if(!this.htmlElement || !this.htmlElement.down('div.panelHeader')) return;
        var div = this.htmlElement.down('div.folded_icons');
        if(!div) {
            div = new Element('div', {className: 'folded_icons'});
            this.htmlElement.down('div.panelHeader').insert(div);
        }else{
            if(div.down('span.'+iconClass)) return;
        }
        var ic = new Element("span", {className:iconClass, title: iconTitle});
        div.insert(ic);
        if(ajxpAction){
            ic.addClassName('clickable');
            ic.observe("click", function(){
                ajaxplorer.actionBar.fireAction(ajxpAction);
            }.bind(this));
        }
        if(panelPointer){
             modal.simpleTooltip(ic, panelPointer, 'bottom left', 'foldedPanel_tooltip', 'element', true);
        }
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
        this.contentContainer.removeClassName('double')
            .removeClassName('triple')
            .removeClassName('small')
            .removeClassName('tiny');
        var previewMaxHeight = 200;
        var currentWidth = parseInt(this.contentContainer.getWidth());
        if(currentWidth < 280) {
            this.contentContainer.addClassName('small');
        }
        if(currentWidth < 100) {
            this.contentContainer.addClassName('tiny');
        }
        if(currentWidth > 500) {
            this.contentContainer.addClassName('double');
            previewMaxHeight = 300;
        }
        if(currentWidth > 750) {
            this.contentContainer.addClassName('triple');
            previewMaxHeight = 450;
        }
		fitHeightToBottom(this.contentContainer, null);
        previewMaxHeight = Math.min(previewMaxHeight, parseInt(this.contentContainer.getHeight()) - parseInt(this.contentContainer.getStyle('paddingTop')));
        if(this.scrollbar){
            this.scroller.setStyle({height:parseInt(this.contentContainer.getHeight())+'px'});
            this.scrollbar.recalculateLayout();
        }
		if(this.htmlElement && this.currentPreviewElement && this.currentPreviewElement.visible()){
			var squareDim = Math.min(parseInt(this.htmlElement.getWidth()-40));
			this.currentPreviewElement.resizePreviewElement({width:squareDim,height:squareDim, maxHeight:previewMaxHeight});
		}
        if(this.htmlElement){
            document.fire("ajaxplorer:resize-InfoPanel-" + this.htmlElement.id, this.htmlElement.getDimensions());
        }
    },
	/**
	 * Find template and evaluate it
	 * @param mimeType String
	 * @param fileNode AjxpNode
	 * @param tArgs Object
	 */
	evalTemplateForMime: function(mimeType, fileNode, tArgs, multipleNodes){
		if(!this.htmlElement) return;
		if(!this.registeredMimes.get(mimeType)) return;
		var registeredTemplates = this.registeredMimes.get(mimeType);
        if(mimeType == "generic_file"){
            var realExtension = getAjxpMimeType(fileNode);
            var genericId = this.primaryTemplates.get(mimeType);
            var primaryId = this.primaryTemplates.get(realExtension)
        }else{
            var skipId = this.primaryTemplates.get(mimeType);
        }
		for(var i=0;i<registeredTemplates.length;i++){
            if(skipId && skipId == registeredTemplates[i]){
                continue;
            }
            if(this.insertedTemplates.indexOf(registeredTemplates[i]) > -1){
                continue;
            }
            var templateData = this.mimesTemplates.get(registeredTemplates[i]);
            if(primaryId && genericId && genericId == registeredTemplates[i]){
                //replace;
                templateData = this.mimesTemplates.get(primaryId);
            }
			var tString = templateData[0];
			var tAttributes = templateData[1];
			var tMessages = templateData[2];
			var tModifier = templateData[3];
            var tPosition = templateData[4];
			if(!tArgs){
				tArgs = {};
			}
			var panelWidth = this.htmlElement.getWidth();
			var oThis = this;
			if(fileNode || multipleNodes){
                var metadata;
                if(fileNode){
    				metadata = fileNode.getMetadata();
                }else{
                    metadata = $H();
                }
				tAttributes.each(function(attName){
					if(attName == 'basename' && metadata.get('filename')){
						this[attName] = getBaseName(metadata.get('filename'));
					} else if(attName == 'compute_image_dimensions'){
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
					} else if(attName == 'preview_rich'){
    					this[attName] = oThis.getPreviewElement(fileNode, true, true);
					} else if(attName == 'preview_simple'){
                        if(multipleNodes){
                            var s ='';
                            var simpleTpl = new Template('<div class="info_panel_multiple_tile"><div class="tile_preview">#{preview}</div><div class="tile_label">#{label}</div></div>');
                            multipleNodes.each(function(n){
                                var p = oThis.getPreviewElement(n, false, false);
                                var args = {label:getBaseName(n.getMetadata().get('filename'))};
                                if(Object.isString(p)) args['preview']=p;
                                else if(Object.isElement(p) && p.outerHTML) args['preview']= p.outerHTML;
                                s += simpleTpl.evaluate(args);
                            });
                            this[attName] = s;
                        }else{
    						this[attName] = oThis.getPreviewElement(fileNode, false, false);
                        }
					} else if(attName == 'encoded_filename' && metadata.get('filename')){
						this[attName] = encodeURIComponent(metadata.get('filename'));					
					} else if(attName == 'escaped_filename' && metadata.get('filename')){
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
					} else if(attName == 'uri'){
						var url = document.location.href;
						if(url[(url.length-1)] == '/'){
							url = url.substr(0, url.length-1);
						}else if(url.lastIndexOf('/') > -1){
							url = url.substr(0, url.lastIndexOf('/'));
						}
						this[attName] = url;
					} else if(metadata.get(attName)){
						this[attName] = metadata.get(attName);
					} else{
						this[attName] = '';
					}
				}.bind(tArgs));
			}
			tMessages.each(function(pair){
				this[pair.key] = MessageHash[pair.value];
			}.bind(tArgs));
			var template = new Template(tString);
            if(this.contentContainer.down('div.infoPanelAllMetadata')){
                this.contentContainer.down('div.infoPanelAllMetadata').insert(template.evaluate(tArgs));
            }else{
                this.contentContainer.insert(template.evaluate(tArgs));
            }
            this.insertedTemplates.push(registeredTemplates[i]);
			if(tModifier){
                try{
    				var modifierFunc = eval(tModifier);
                }catch(e){
                    if(console) console.error('InfoPanel: trying to eval function ' + tModifier + ' failed!', e);
                }
                if(modifierFunc) modifierFunc(this.contentContainer, fileNode);
			}
		}
        if(this.contentContainer.down('#info_panel_primary') && this.contentContainer.down('div.infoPanelAllMetadata')){
            this.contentContainer.select('[data-infoPanelPosition]').each(function(ip){
                if(ip.readAttribute('data-infoPanelPosition') == 'first'){
                    this.contentContainer.down('#info_panel_primary').insert({after:ip});
                }else if(ip.readAttribute('data-infoPanelPosition') == 'last'){
                    this.contentContainer.down('div.infoPanelAllMetadata').insert(ip);
                }
            }.bind(this));
        }
	},
		
	/**
	 * Adds an "Action" section below the templates
	 * @param selectionType String 'empty', 'multiple', 'unique'
	 */
	addActions: function(selectionType){
        if(this.contentContainer.down('div.toolbar_placeholder')){
            this.contentContainer.select('div.toolbar_placeholder').each(function(placeholder){
                var tbId = placeholder.readAttribute("data-toolbarId");
                if(!this.actionsToolbars) this.actionsToolbars= $A();
                var bar = new ActionsToolbar(placeholder, {
                    toolbarsList : $A([tbId]),
                    manager:ajaxplorer.actionBar
                });
                bar.actionsLoaded();
                if(bar.registeredButtons){
                    bar.registeredButtons.each(function(B){
                        B.ACTION.fireSelectionChange(ajaxplorer.getContextHolder());
                    });
                }
                this.actionsToolbars.push(bar);
                placeholder.select('a.disabled,a.action_hidden').invoke('remove');
                if(placeholder.select('a').length>1){
                    placeholder.addClassName('has_many');
                    placeholder.up('div').addClassName('one_has_many');
                }else{
                    placeholder.addClassName('one_or_less');
                }
            }.bind(this));
        }
        if(this.options.skipActions) return;
		var actions = ajaxplorer.actionBar.getActionsForAjxpWidget("InfoPanel", this.htmlElement.id);
		if(!actions.length) return;
		var actionString = '<div class="panelHeader infoPanelGroup">'+MessageHash[5]+'</div><div class="infoPanelActions">';
		var count = 0;
		actions.each(function(action){
			if(selectionType == 'empty' && action.context.selection) return;
			if(selectionType == 'multiple' && action.selectionContext.unique) return; 
			if(selectionType == 'unique' && (!action.context.selection || action.selectionContext.multipleOnly)) return;
            var id ="";
            if(action.options.name) id = 'id="action_instance_'+action.options.name+'"';
			actionString += '<a href="" '+id+' onclick="ajaxplorer.actionBar.fireAction(\''+action.options.name+'\');return false;"><img src="'+resolveImageSource(action.options.src, '/images/actions/ICON_SIZE', 16)+'" width="16" height="16" align="absmiddle" border="0"> '+action.options.title+'</a>';
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
     * @param rich will be set to true by default
	 * @returns String
	 */
	getPreviewElement : function(ajxpNode, getTemplateElement, rich){
        if(rich == undefined) rich = true;
		var editors = ajaxplorer.findEditorsForMime(ajxpNode.getAjxpMime(), true);
		if(editors && editors.length)
		{
			ajaxplorer.loadEditorResources(editors[0].resourcesManager);
			var editorClass = Class.getByName(editors[0].editorClass);
			if(editorClass){
                this.contributePanelHeaderIcon('icon-eye-close', 'Preview', 'open_with');
				if(getTemplateElement){
					return '<div id="preview_rich_fake_element"></div>';
				}else{
					return editorClass.prototype.getPreview(ajxpNode, rich);
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
            var theme = panels[i].getAttribute('theme');
            var winTheme = window.ajxpBootstrap.parameters.get("theme");
            if(theme && theme.split(',').indexOf(winTheme) == -1){
                continue;
            }
			var messages = new Hash();
			var modifier = panels[i].getAttribute('modifier') || '';
			var position = panels[i].getAttribute('position') || '';
			var htmlContent = '';
			var panelChilds = panels[i].childNodes;
			for(var j=0;j<panelChilds.length;j++){
				if(panelChilds[j].nodeName == 'messages'){
					var messagesList = panelChilds[j].childNodes;					
					for(var k=0;k<messagesList.length;k++){
						if(messagesList[k].nodeName != 'message') continue;
						messages.set(messagesList[k].getAttribute("key"), messagesList[k].getAttribute("id"));
					}
				}
				else if(panelChilds[j].nodeName == 'html' && panelChilds[j].firstChild){
					htmlContent = panelChilds[j].firstChild.nodeValue;
				}
			}
			var tId = hex_md5(htmlContent);
			if(this.mimesTemplates.get(tId)){
				continue;
			}
			this.mimesTemplates.set(tId, $A([htmlContent,attributes, messages, modifier,position]));
            var primary = panels[i].getAttribute('primary');

			$A(panelMimes.split(",")).each(function(mime){
				var registered = this.registeredMimes.get(mime) || $A([]);
				registered.push(tId);
				this.registeredMimes.set(mime, registered);
                if(primary){
                    this.primaryTemplates.set(mime, tId);
                }
			}.bind(this));
		}
	}
	
});
