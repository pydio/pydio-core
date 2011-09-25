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
Class.create("IMagickPreviewer", Diaporama, {

	fullscreenMode: false,

	initialize: function($super, oFormObject)
	{
		$super(oFormObject);
		this.baseUrl = ajxpBootstrap.parameters.get('ajxpServerAccess')+"&get_action=get_extracted_page&file=";
		// Override onload for the text
		this.jsImage.onload = function(){
			this.jsImageLoading = false;
			this.imgTag.src = this.jsImage.src;
			this.resizeImage(true);
			this.downloadButton.removeClassName("disabled");
			var i = 0;
			for(i=0;i<this.items.length;i++){
				if(this.items[i] == this.currentFile){
					break;
				}
			}
			i++;
			var text = this.currentIM + ' ('+MessageHash[331]+' '+i+' '+MessageHash[332]+' '+this.items.length+')';
			this.updateTitle(text);
		}.bind(this);

        this.resize();
	},
	
	open : function($super, userSelection)
	{
		this.downloadButton.onclick = function(){
			if(!this.currentFile) return;		
			ajaxplorer.triggerDownload(ajxpBootstrap.parameters.get('ajxpServerAccess')+'&action=download&file='+userSelection.getUniqueFileName());
			return false;
		}.bind(this);
				
		this.currentIM = getBaseName(userSelection.getUniqueFileName());
		// Extract the pages and load result!
		var connexion = new Connexion();
		connexion.addParameter("get_action", "imagick_data_proxy");
		connexion.addParameter("all", "true");
		connexion.addParameter("file", userSelection.getUniqueFileName());
		connexion.onComplete = function(transport){
			this.removeOnLoad();
			var result = transport.responseJSON;
			this.items = new Array();
			this.sizes = new Hash();			
			for(var i=0;i<result.length;i++){
				this.items.push(result[i].file);
				this.sizes.set(result[i].file, {height:result[i].height, width:result[i].width});
			}
			if(this.items.length){				
				this.currentFile = this.items[0];
				this.setZoomValue(100);
				this.zoomInput.value = '100 %';	
				this.updateImage();
				this.updateButtons();			
				
				var tItems = this.items;
				this.element.observe("editor:close", function(){					
					var connexion = new Connexion();
					connexion.addParameter("get_action", "delete_imagick_data");
					var prefix = tItems[0].replace("-0.jpg", "").replace(".jpg", "");
					connexion.addParameter("file", prefix);
					connexion.sendAsync();
				}.bind(this));
				
			}
		}.bind(this);
		this.setOnLoad();
		this.updateTitle(MessageHash[330]);
		connexion.sendAsync();
	},
						
	getPreview : function(ajxpNode){
		var img = new Element('img', {
			src:IMagickPreviewer.prototype.getThumbnailSource(ajxpNode), 
			style:'border:1px solid #676965;',
            align:'absmiddle'
		});
		img.resizePreviewElement = function(dimensionObject){			
			var imgDim = {
				width:21, 
				height:29
			};
			var styleObj = fitRectangleToDimension(imgDim, dimensionObject);
			img.setStyle(styleObj);
		}
		return img;
	},
	
	getThumbnailSource : function(ajxpNode){
		return ajxpServerAccessPath+"&get_action=imagick_data_proxy&file="+encodeURIComponent(ajxpNode.getPath());
	},
	
	setOnLoad: function()	{
		if(this.loading) return;
		addLightboxMarkupToElement(this.imgContainer);
		var img = document.createElement("img");
		img.src = ajxpResourcesFolder+'/images/loadingImage.gif';
		$(this.imgContainer).getElementsBySelector("#element_overlay")[0].appendChild(img);
		this.loading = true;
	},
	
	removeOnLoad: function(){
		removeLightboxFromElement(this.imgContainer);
		this.loading = false;
	}
	
	
});