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
Class.create("PixlrEditor", AbstractEditor, {

	fullscreenMode: false,
	
	initialize: function($super, oFormObject, options)
	{
        this.editorOptions = options;
		this.element =  $(oFormObject);
		this.defaultActions = new Hash();
		this.createTitleSpans();
		modal.setCloseAction(function(){this.close();}.bind(this));
		this.container = $(oFormObject).select('div[id="pixlrContainer"]')[0];
		fitHeightToBottom($(this.container), $(options.context.elementName));
		this.contentMainContainer = new Element("iframe", {			
			style:"border:none;width:100%;"
		});						
		this.container.update(this.contentMainContainer);
	},

    resize:function($super, s){
        $super(s);
        fitHeightToBottom(this.element);
        fitHeightToBottom(this.container, this.element);
        fitHeightToBottom(this.contentMainContainer);
    },
		
	save : function(pixlrUrl){
		this.setOnLoad();
		var conn = new Connexion();
		conn.addParameter("get_action", "retrieve_pixlr_image");
		conn.addParameter("original_file", this.currentNode.getPath());
		conn.addParameter("new_url", pixlrUrl);
		conn.onComplete = function(transp){
			var date = new Date();
			this.currentNode.getParent().getMetadata().set('preview_seed', Math.round(date.getTime()*Math.random()));
			this.removeOnLoad();
            if(this.editorOptions.context.__className == "Modal"){
                hideLightBox(true);
            }else if(this.editorOptions.context.__className == "AjxpTabulator"){
                this.editorOptions.context.closeTab("editor.pixlr:/" + this.currentNode.getPath());
            }
			ajaxplorer.actionBar.fireAction('refresh');
		}.bind(this);
		conn.sendAsync();
	},
	
	open : function($super, node)
	{
		this.setOnLoad(true);
		this.currentNode = node;
		var fName = this.currentNode.getPath();
        this.contentMainContainer.src = ajxpBootstrap.parameters.get('ajxpServerAccess')+"&get_action=post_to_server&file=base64encoded:" + base64_encode(fName) + "&parent_url=" + base64_encode(getUrlFromBase());
		var pe = new PeriodicalExecuter(function(){
			var href;
			try{
				href = this.contentMainContainer.contentDocument.location.href;
			}catch(e){
				if(this.loading){
					this.resize();
					// Force here for WebKit
					this.contentMainContainer.setStyle({height:this.container.getHeight() + 'px'});
					this.removeOnLoad();			
				}
			}
			if(href && href.indexOf('image=') > -1){				
	        	pe.stop();
	        	this.save(href);
			}else if(href && href.indexOf('close_pixlr')>-1){
				pe.stop();
				hideLightBox(true);
			}
		}.bind(this) , 0.5);
        this.element.fire("editor:updateTitle", node.getLabel());
	},
	
	setOnLoad: function(openMessage){
		if(this.loading) return;
		addLightboxMarkupToElement(this.container);
		var waiter = new Element("div", {align:"center", style:"font-family:Arial, Helvetica, Sans-serif;font-size:25px;color:#AAA;font-weight:bold;"});
		if(openMessage){
			waiter.update('<br><br><br>Please wait while opening Pixlr editor...<br>');
		}
		waiter.insert(new Element("img", {src:ajxpResourcesFolder+'/images/loadingImage.gif'}));
		$(this.container).select("#element_overlay")[0].insert(waiter);
		this.loading = true;
	},
	
	removeOnLoad: function(){
		removeLightboxFromElement(this.container);
		this.loading = false;
	},	
	
	getPreview : function(ajxpNode){
		if(ajxpNode.getAjxpMime() == "bmp"  || ajxpNode.getAjxpMime() == "pxd"){
			return AbstractEditor.prototype.getPreview(ajxpNode);
		}
		
		var img = new Element('img', {src:Diaporama.prototype.getThumbnailSource(ajxpNode), border:0});
		img.resizePreviewElement = function(dimensionObject){			
			var imgDim = {
				width:parseInt(ajxpNode.getMetadata().get("image_width")), 
				height:parseInt(ajxpNode.getMetadata().get("image_height"))
			};
			var styleObj = fitRectangleToDimension(imgDim, dimensionObject);
			img.setStyle(styleObj);
		};
		return img;
	},
	
	getThumbnailSource : function(ajxpNode){
		if(ajxpNode.getAjxpMime() == "bmp" || ajxpNode.getAjxpMime() == "pxd"){
			return AbstractEditor.prototype.getThumbnailSource(ajxpNode);
		}
        var repoString = "";
        if(ajaxplorer.repositoryId && ajxpNode.getMetadata().get("repository_id") && ajxpNode.getMetadata().get("repository_id") != ajaxplorer.repositoryId){
            repoString = "&tmp_repository_id=" + ajxpNode.getMetadata().get("repository_id");
        }
		return ajxpServerAccessPath+"&get_action=preview_data_proxy"+repoString+"&get_thumb=true&file="+encodeURIComponent(ajxpNode.getPath());
	}
	
});