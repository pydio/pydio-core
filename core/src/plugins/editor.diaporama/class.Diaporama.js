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
Diaporama = Class.create(AbstractEditor, {

	fullscreenMode: false,
	
	initialize: function($super, oFormObject)
	{
		$super(oFormObject);
		
		this.nextButton = this.actions.get("nextButton");
		this.previousButton = this.actions.get("prevButton");
		this.downloadButton = this.actions.get("downloadDiapoButton");
		this.playButton = this.actions.get("playButton");
		this.stopButton = this.actions.get("stopButton");
		
		this.actualSizeButton = this.element.select('img[id="actualSizeButton"]')[0];
		this.fitToScreenButton = this.element.select('img[id="fitToScreenButton"]')[0];
		this.imgTag = this.element.select('img[id="mainImage"]')[0];
		this.imgContainer = this.element.select('div[id="imageContainer"]')[0];
		fitHeightToBottom(this.imgContainer);
		this.zoomInput = this.element.select('input[id="zoomValue"]')[0];
		this.timeInput = this.element.select('input[id="time"]')[0];
		this.baseUrl = 'content.php?action=image_proxy&file=';
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
			this.slider.setValue(100);
			this.resizeImage(true);
		}.bind(this);
		this.fitToScreenButton.onclick = function(){
			this.toggleFitToScreen();
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
		
		this.imgTag.onload = function(){
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
					this.zoomInput.value = value;
				}
				this.slider.setValue(this.zoomInput.value);
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
		this.containerDim = $(this.imgContainer).getDimensions();
		// Init preferences
		if(ajaxplorer && ajaxplorer.user){
			var autoFit = ajaxplorer.user.getPreference('diapo_autofit');
			if(autoFit && autoFit == "true"){
				this.autoFit = true;
				this.fitToScreenButton.addClassName('diaporamaButtonActive');
			}
		}
		this.contentMainContainer = this.imgContainer ;
		this.element.observe("editor:resize", this.resizeImage.bind(this));
		this.element.observe("editor:close", function(){
			this.currentFile = null;
			this.items = null;
			this.imgTag.src = '';
			this.stop();
		}.bind(this) );
	},
	
	open : function($super, userSelection, aFilesList)
	{
		$super(userSelection, aFilesList);
		var allItems, sCurrentFile;
		if(userSelection.isUnique()){
			allItems = aFilesList.getItems();
			sCurrentFile = userSelection.getUniqueFileName();
		}else{
			allItems = aFilesList.getSelectedItems();
		}		
		this.items = new Array();
		this.sizes = new Hash();
		$A(allItems).each(function(rowItem){
			if(rowItem.getAttribute('is_image')=='1'){
				this.items.push(rowItem.getAttribute('filename'));
				this.sizes.set(rowItem.getAttribute('filename'),  {height:rowItem.getAttribute('image_height')||'n/a', 
															   width:rowItem.getAttribute('image_width')||'n/a'});
			}
		}.bind(this));	
		
		if(!sCurrentFile && this.items.length) sCurrentFile = this.items[0];		
		this.currentFile = sCurrentFile;
		
		var sliderDiv = this.element.select('div[id="slider-2"]')[0];
		var sliderInput = this.element.select('input[id="slider-input-2"]')[0];
		this.slider = new Slider(sliderDiv, sliderInput);
		this.slider.setMaximum(200);
		this.slider.setMinimum(10);	
		this.slider.setValue(100);
		this.zoomInput.value = '100';
		this.slider.recalculate();
		this.slider.onchange = function(){
			this.resizeImage(false);
		}.bind(this);
	
		this.updateImage();
		this.updateButtons();
	},
		
	resizeImage : function(morph){	
		if(this.autoFit){
			this.computeFitToScreenFactor();
		}
		var nPercent = this.slider.getValue();
		this.zoomInput.value = nPercent;
		var height = parseInt(nPercent*this.crtHeight / 100);	
		var width = parseInt(nPercent*this.crtWidth / 100);
		// Center vertically
		var margin=0;
		if (height < this.containerDim.height){
			var margin = parseInt((this.containerDim.height - height) / 2)-5;
		}
		if(morph){
			new Effect.Morph(this.imgTag,{
				style:{height:height+'px', width:width+'px',margin:margin+'px'}, 
				duration:0.5
				});
		}else{
			this.imgTag.setStyle({height:height+'px', width:width+'px',margin:margin+'px'});
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
		this.imgTag.src  = this.baseUrl + encodeURIComponent(this.currentFile);
		if(!this.crtWidth && !this.crtHeight){
			this.crtWidth = this.imgTag.getWidth();
			this.crtHeight = this.imgTag.getHeight();
			this.crtRatio = this.crtHeight / this.crtWidth;
		}
	},

	fitToScreen : function(){
		this.computeFitToScreenFactor();
		this.resizeImage(true);
	},
	
	computeFitToScreenFactor: function(){
		zoomFactor1 = parseInt(this.imgContainer.getHeight() / this.crtHeight * 100);
		zoomFactor2 = parseInt(this.imgContainer.getWidth() / this.crtWidth * 100);
		this.slider.setValue(Math.min(zoomFactor1, zoomFactor2)-1);		
	},
	
	toggleFitToScreen:function(){
		if(this.autoFit){
			this.autoFit = false;
			this.fitToScreenButton.removeClassName('diaporamaButtonActive');
		}else{
			this.autoFit = true;
			this.fitToScreenButton.addClassName('diaporamaButtonActive');
			this.fitToScreen();
		}
		if(ajaxplorer && ajaxplorer.user){
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
	}
});