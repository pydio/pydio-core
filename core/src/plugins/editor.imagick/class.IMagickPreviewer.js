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
Class.create("IMagickPreviewer", Diaporama, {

	fullscreenMode: false,
	src_file: "",

	initialize: function($super, oFormObject, editorOptions)
	{
        var options = Object.extend({
            floatingToolbar:true,
            replaceScroller:false,
            toolbarStyle: "icons_only diaporama_toolbar",
            actions : {}
        }, editorOptions);
		$super(oFormObject, options);
		this.baseUrl = ajxpBootstrap.parameters.get('ajxpServerAccess')+"&get_action=get_extracted_page&file=";
		// Override onload for the text
		this.jsImage.onload = function(){
			this.jsImageLoading = false;
			this.imgTag.src = this.jsImage.src;
            this.imgTag.setStyle({opacity:1});
			this.resizeImage(true);
			var i;
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
	
	open : function($super, node)
	{
		this.src_file = node.getPath();
		this.currentIM = getBaseName(this.src_file);
		// Extract the pages and load result!
		var connexion = new Connexion();
		connexion.addParameter("get_action", "imagick_data_proxy");
		connexion.addParameter("all", "true");
		connexion.addParameter("file", this.src_file);
		connexion.onComplete = function(transport){
			this.removeOnLoad();
			var result = transport.responseJSON;
			this.items = $A();
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
            className:'thumbnail_iconlike_shadow',
            align:'absmiddle',
            src:IMagickPreviewer.prototype.getThumbnailSource(ajxpNode)
		});		
		img.resizePreviewElement = function(dimensionObject){			
			var ratio = img.ratio;
			if(!ratio) {
				var fakeIm = new Image();
				fakeIm.onload = function(){	
					img.ratio = fakeIm.width/fakeIm.height;
					img.resizePreviewElement(dimensionObject);
				};
				fakeIm.src = img.src;
				//img.onload = function(){img.resizePreviewElement(dimensionObject);};
				ratio = 1.0;
			}
			var imgDim = {
				width:20,
				height:20/ratio
			};
			var styleObj = fitRectangleToDimension(imgDim, dimensionObject);
			img.setStyle(styleObj);
        };
		img.observe("mouseover", function(event){
			var theImage = event.target;
			if(theImage.up('.thumbnail_selectable_cell')) return;
			if(!theImage.openBehaviour){
				var opener = new Element('div').update(MessageHash[411]);
				opener.setStyle({
					width:'',
					display:'none', 
					position:'absolute', 
					color: 'white',
					backgroundColor: 'black',
					opacity: '0.6',
					fontWeight: 'bold',
					fontSize: '12px',
					textAlign: 'center',
					cursor: 'pointer'
				});
                opener.addClassName('imagePreviewOverlay');
				img.previewOpener = opener;
				theImage.insert({before:opener});
				theImage.setStyle({cursor:'pointer'});
				theImage.openBehaviour = true;
				theImage.observe("click", function(event){
					ajaxplorer.actionBar.fireAction('open_with');
				});
			}
            var off = theImage.positionedOffset();
            var realLeftOffset = Math.max(off.left, theImage.parentNode.positionedOffset().left);
			theImage.previewOpener.setStyle({
                display:'block',
                left: realLeftOffset + 'px',
                width:theImage.getWidth() + "px",
                top: (off.top + theImage.getHeight() - theImage.previewOpener.getHeight()) + "px"
            });
		});
		img.observe("mouseout", function(event){
			var theImage = event.target;
			if(theImage.up('.thumbnail_selectable_cell')) return;
			theImage.previewOpener.setStyle({display:'none'});
		});		
		return img;
	},

    getRESTPreviewLinks:function(node){
        return {
            "First Page Thumbnail": "&file=" + encodeURIComponent(node.getPath())
        };
    },


	getThumbnailSource : function(ajxpNode){
        var repoString = "";
        if(ajaxplorer.repositoryId && ajxpNode.getMetadata().get("repository_id") && ajxpNode.getMetadata().get("repository_id") != ajaxplorer.repositoryId){
            repoString = "&tmp_repository_id=" + ajxpNode.getMetadata().get("repository_id");
        }
        var mtimeString = "&time_seed=" + ajxpNode.getMetadata().get("ajxp_modiftime");
		return ajxpServerAccessPath+"&get_action=imagick_data_proxy"+repoString + mtimeString +"&file="+encodeURIComponent(ajxpNode.getPath());
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
	},
	
	updateImage : function(){
		var dimObject = this.sizes.get(this.currentFile);
		this.crtHeight = dimObject.height;
		this.crtWidth = dimObject.width;
		if(this.crtWidth){
			this.crtRatio = this.crtHeight / this.crtWidth;
		}
		new Effect.Opacity(this.imgTag, {afterFinish : function(){
			this.jsImageLoading = true;
			this.jsImage.src  = this.baseUrl + encodeURIComponent(this.currentFile) + "&src_file=" + this.src_file;
			if(!this.crtWidth && !this.crtHeight){
				this.crtWidth = this.imgTag.getWidth();
				this.crtHeight = this.imgTag.getHeight();
				this.crtRatio = this.crtHeight / this.crtWidth;
			}
            this.imgTag.show();
		}.bind(this), from:1.0,to:0, duration:0.3});
        
        this.updateInfoPanel();
	}
	
});