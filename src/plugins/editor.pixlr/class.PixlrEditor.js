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
 * Description : The image gallery manager.
 */
Class.create("PixlrEditor", AbstractEditor, {

	fullscreenMode: false,
	
	initialize: function($super, oFormObject)
	{
		this.element =  $(oFormObject);
		this.defaultActions = new Hash();		
		this.createTitleSpans();
		modal.setCloseAction(function(){this.close();}.bind(this));
		this.container = $(oFormObject).select('div[id="pixlrContainer"]')[0];
		fitHeightToBottom($(this.container), $(modal.elementName));
		this.contentMainContainer = new Element("iframe", {			
			style:"border:none;width:"+this.container.getWidth()+"px;"
		});						
		this.container.update(this.contentMainContainer);
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
			hideLightBox(true);			
			ajaxplorer.actionBar.fireAction('refresh');
		}.bind(this);
		conn.sendAsync();
	},
	
	open : function($super, userSelection)
	{
		$super(userSelection);		
		this.setOnLoad(true);
		this.currentNode = userSelection.getUniqueNode();
		var fName = this.currentNode.getPath();
		var src = ajxpBootstrap.parameters.get('ajxpServerAccess')+"?get_action=post_to_server&file=" + base64_encode(fName) + "&parent_url=" + base64_encode(getRepName(document.location.href));
		this.contentMainContainer.src = src;
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
		return;		
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
		var img = new Element('img', {src:Diaporama.prototype.getThumbnailSource(ajxpNode), border:0});
		img.resizePreviewElement = function(dimensionObject){			
			var imgDim = {
				width:parseInt(ajxpNode.getMetadata().get("image_width")), 
				height:parseInt(ajxpNode.getMetadata().get("image_height"))
			};
			var styleObj = fitRectangleToDimension(imgDim, dimensionObject);
			img.setStyle(styleObj);
		}
		return img;
	},
	
	getThumbnailSource : function(ajxpNode){
		return ajxpServerAccessPath+"?get_action=preview_data_proxy&get_thumb=true&file="+encodeURIComponent(ajxpNode.getPath());
	}
	
});