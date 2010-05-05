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
Class.create("Diaporama", AbstractEditor, {

	fullscreenMode: false,
	_minZoom : 10,
	_maxZoom : 500,
	
	initialize: function($super, oFormObject)
	{
		//this.editorOptions.floatingToolbar = true;
		$super(oFormObject);
		this.nextButton = this.actions.get("nextButton");
		this.previousButton = this.actions.get("prevButton");
		this.downloadButton = this.actions.get("downloadDiapoButton");
		this.playButton = this.actions.get("playButton");
		this.stopButton = this.actions.get("stopButton");		
		this.actualSizeButton = this.actions.get('actualSizeButton');
		this.fitToScreenButton = this.actions.get('fitToScreenButton');
		
		this.imgTag = this.element.select('img[id="mainImage"]')[0];
		this.imgBorder = this.element.select('div[id="imageBorder"]')[0];
		this.imgContainer = this.element.select('div[id="imageContainer"]')[0];
		this.zoomInput = this.element.select('input[id="zoomValue"]')[0];	
		this.timeInput = this.element.select('input[id="time"]')[0];
		
		new SliderInput(this.zoomInput, {
			onSlide:function(value){
				this.setZoomValue(parseInt(value));
				this.zoomInput.value = value + ' %';
				this.resizeImage(false);				
			}.bind(this),
			range : $R(this._minZoom, this._maxZoom),
			increment : 1
		});
		new SliderInput(this.timeInput, {
			onSlide:function(value){				
				this.timeInput.value = parseInt(value) + ' s';
			}.bind(this),
			onChange:function(value){
				if(this.slideShowPlaying && this.pe){
					this.stop();
					this.play();
				}
			}.bind(this),
			range : $R(1, 15),
			increment : 1
		});
		
		var inputStyle = {
			backgroundImage:'url("'+ajxpResourcesFolder+'/images/locationBg.gif")',
			backgroundPosition:'left top',
			backgroundRepeat:'no-repeat'
		};
		this.zoomInput.setStyle(inputStyle);
		this.timeInput.setStyle(inputStyle);
		
		
		this.baseUrl = 'content.php?action=preview_data_proxy&file=';
		this.nextButton.onclick = function(){
			this.next();
			this.updateButtons();
			return false;
		}.bind(this);
		this.previousButton.onclick = function(){
			this.previous();
			this.updateButtons();
			return false;
		}.bind(this);
		this.downloadButton.onclick = function(){
			if(!this.currentFile) return;		
			ajaxplorer.triggerDownload('content.php?action=download&file='+this.currentFile);
			return false;
		}.bind(this);
		this.actualSizeButton.onclick = function(){
			this.setZoomValue(100);
			this.resizeImage(true);
			return false;
		}.bind(this);
		this.fitToScreenButton.onclick = function(){
			this.toggleFitToScreen();
			return false;
		}.bind(this);
		this.playButton.onclick = function(){
			this.play();
			this.updateButtons();
			return false;
		}.bind(this);
		this.stopButton.onclick = function(){
			this.stop();
			this.updateButtons();
			return false;
		}.bind(this);
		
		this.jsImage = new Image();
		this.imgBorder.hide();
		
		this.jsImage.onload = function(){
			this.imgTag.src = this.jsImage.src;
			this.resizeImage(true);
			this.downloadButton.removeClassName("disabled");
			var text = getBaseName(this.currentFile) + ' ('+this.sizes.get(this.currentFile).width+' X '+this.sizes.get(this.currentFile).height+')';
			this.updateTitle(text);
		}.bind(this);
		Event.observe(this.zoomInput, "keypress", function(e){
			if(e == null) e = window.event;
			if(e.keyCode == Event.KEY_RETURN || e.keyCode == Event.KEY_UP || e.keyCode == Event.KEY_DOWN){
				if(e.keyCode == Event.KEY_UP || e.keyCode == Event.KEY_DOWN)
				{
					var crtValue = parseInt(this.zoomInput.value);
					var value = (e.keyCode == Event.KEY_UP?(e.shiftKey?crtValue+10:crtValue+1):(e.shiftKey?crtValue-10:crtValue-1));
					this.zoomInput.value = value + ' %';
				}
				var newValue = parseInt(this.zoomInput.value);
				newValue = Math.max(this._minZoom, newValue);
				newValue = Math.min(this._maxZoom, newValue);
				this.setZoomValue(newValue);
				this.resizeImage(false);
				Event.stop(e);
			}
			return true;
		}.bind(this));
		this.timeInput.observe('change', function(e){			
			if(this.slideShowPlaying && this.pe){
				this.stop();
				this.play();
			}
		}.bind(this));
		// Init preferences
		if(ajaxplorer && ajaxplorer.user){
			var autoFit = ajaxplorer.user.getPreference('diapo_autofit');
			if(autoFit && autoFit == "true"){
				this.autoFit = true;
				this.fitToScreenButton.select('img')[0].src = ajxpResourcesFolder + '/images/actions/22/zoom-fit-restore.png';
				this.fitToScreenButton.select('span')[0].update(MessageHash[326]);
			}
		}
		this.contentMainContainer = this.imgContainer ;
		this.element.observe("editor:close", function(){
			this.currentFile = null;
			this.items = null;
			this.imgTag.src = '';
			if(this.slideShowPlaying){
				this.stop();
			}
		}.bind(this) );
		
		this.element.observe("editor:enterFSend", function(e){this.resize();}.bind(this));
		fitHeightToBottom(this.imgContainer, $(modal.elementName), 3);
	},
	
	resize : function(size){
		if(size){
			this.imgContainer.setStyle({height:size});
		}else{
			if(this.fullScreenMode){
				fitHeightToBottom(this.imgContainer, this.element);
			}else{
				fitHeightToBottom(this.imgContainer, $(modal.elementName), 3);
			}
		}
		this.resizeImage();
		this.element.fire("editor:resize", size);
	},
	
	open : function($super, userSelection)
	{
		$super(userSelection);
		var allNodes, sCurrentFile;
		if(userSelection.isUnique()){
			allItems = userSelection.getContextNode().getChildren();
			sCurrentFile = userSelection.getUniqueFileName();
		}else{
			allItems = userSelection.getSelectedNodes();
		}		
		this.items = new Array();
		this.sizes = new Hash();
		$A(allItems).each(function(node){
			var meta = node.getMetadata();
			if(meta.get('is_image')=='1'){
				this.items.push(node.getPath());
				this.sizes.set(node.getPath(),  {height:meta.get('image_height')||'n/a', 
												 width:meta.get('image_width')||'n/a'});
			}
		}.bind(this));	
		
		if(!sCurrentFile && this.items.length) sCurrentFile = this.items[0];		
		this.currentFile = sCurrentFile;
		
		this.setZoomValue(100);
		this.zoomInput.value = '100 %';	
		this.updateImage();
		this.updateButtons();
	},
		
	resizeImage : function(morph){	
		if(this.autoFit && morph){
			this.computeFitToScreenFactor();
		}
		var nPercent = this.getZoomValue();
		this.zoomInput.value = nPercent + ' %';
		var height = parseInt(nPercent*this.crtHeight / 100);	
		var width = parseInt(nPercent*this.crtWidth / 100);
		// Center vertically
		var marginTop=0;
		var marginLeft=0;
		this.containerDim = $(this.imgContainer).getDimensions();		
		if (height < this.containerDim.height){
			marginTop = parseInt((this.containerDim.height - height) / 2);
		}
		if (width < this.containerDim.width){
			marginLeft = parseInt((this.containerDim.width - width) / 2);
		}
		if(morph && this.imgBorder.visible()){
			new Effect.Morph(this.imgBorder,{
				style:{height:height+'px', width:width+'px',marginTop:marginTop+'px',marginLeft:marginLeft+'px'}, 
				duration:0.5,
				afterFinish : function(){
					this.imgTag.setStyle({height:height+'px', width:width+'px'});
					new Effect.Opacity(this.imgTag, {from:0,to:1.0, duration:0.3});
				}.bind(this)
			});
		}else{
			this.imgBorder.setStyle({height:height+'px', width:width+'px',marginTop:marginTop+'px',marginLeft:marginLeft+'px'});
			this.imgTag.setStyle({height:height+'px', width:width+'px'});
			if(!this.imgBorder.visible()){
				this.imgBorder.show();
				new Effect.Opacity(this.imgTag, {from:0,to:1.0, duration:0.3});
			}
		}
	},
	
	
	updateImage : function(){
		var dimObject = this.sizes.get(this.currentFile);
		this.crtHeight = dimObject.height;
		this.crtWidth = dimObject.width;
		if(this.crtWidth){
			this.crtRatio = this.crtHeight / this.crtWidth;
		}
		this.downloadButton.addClassName("disabled");
		new Effect.Opacity(this.imgTag, {afterFinish : function(){
			this.jsImage.src  = this.baseUrl + encodeURIComponent(this.currentFile);
			if(!this.crtWidth && !this.crtHeight){
				this.crtWidth = this.imgTag.getWidth();
				this.crtHeight = this.imgTag.getHeight();
				this.crtRatio = this.crtHeight / this.crtWidth;
			}
		}.bind(this), from:1.0,to:0, duration:0.3});	
	},

	setZoomValue : function(value){
		this.zoomValue = value;
	},
	
	getZoomValue : function(value){
		return this.zoomValue;
	},
	
	fitToScreen : function(){
		this.computeFitToScreenFactor();
		this.resizeImage(true);
	},
	
	computeFitToScreenFactor: function(){
		zoomFactor1 = parseInt(this.imgContainer.getHeight() / this.crtHeight * 100);
		zoomFactor2 = parseInt(this.imgContainer.getWidth() / this.crtWidth * 100);
		var zoomFactor = Math.min(zoomFactor1, zoomFactor2)-1;
		zoomFactor = Math.max(this._minZoom, zoomFactor);
		zoomFactor = Math.min(this._maxZoom, zoomFactor);		
		this.setZoomValue(zoomFactor);		
	},
	
	toggleFitToScreen:function(skipSave){
		var src = '';
		var id;
		if(this.autoFit){
			this.autoFit = false;
			src = 'zoom-fit-best';
			id = 325;
		}else{
			this.autoFit = true;
			src = 'zoom-fit-restore';
			id = 326;
			this.fitToScreen();
		}
		this.fitToScreenButton.select('img')[0].src = ajxpResourcesFolder + '/images/actions/22/'+src+'.png';
		this.fitToScreenButton.select('span')[0].update(MessageHash[id]);
		if(ajaxplorer && ajaxplorer.user && !skipSave){
			ajaxplorer.user.setPreference("diapo_autofit", (this.autoFit?'true':'false'));
			ajaxplorer.user.savePreferences();
		}
	},
		
	play: function(){
		if(!this.timeInput.value) this.timeInput.value = 3;
		this.pe = new PeriodicalExecuter(this.next.bind(this), parseInt(this.timeInput.value));
		this.slideShowPlaying = true;
	},
	
	stop: function(){
		if(this.pe) this.pe.stop();
		this.slideShowPlaying = false;
	},
	
	next : function(){
		if(this.currentFile != this.items.last())
		{
			this.currentFile = this.items[this.items.indexOf(this.currentFile)+1];
			this.updateImage();
		}
		else if(this.slideShowPlaying){
			this.currentFile = this.items[0];
			this.updateImage();
		}
	},
	
	previous : function(){
		if(this.currentFile != this.items.first())
		{
			this.currentFile = this.items[this.items.indexOf(this.currentFile)-1];
			this.updateImage();	
		}
	},
	
	updateButtons : function(){
		if(this.slideShowPlaying){
			this.previousButton.addClassName("disabled");
			this.nextButton.addClassName("disabled");
			this.playButton.addClassName("disabled");
			this.stopButton.removeClassName("disabled");
		}else{
			if(this.currentFile == this.items.first()) this.previousButton.addClassName("disabled");
			else this.previousButton.removeClassName("disabled");
			if(this.currentFile == this.items.last()) this.nextButton.addClassName("disabled");
			else this.nextButton.removeClassName("disabled");
			this.playButton.removeClassName("disabled");
			this.stopButton.addClassName("disabled");
		}
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