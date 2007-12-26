Diaporama = Class.create({

	initialize: function(div)
	{
		this.element = div;
		this.nextButton = div.select('a[id="nextButton"]')[0];
		this.previousButton = div.select('a[id="prevButton"]')[0];
		this.closeButton = div.select('a[id="closeButton"]')[0];
		this.downloadButton = div.select('a[id="downloadDiapoButton"]')[0];
		this.actualSizeButton = div.select('img[id="actualSizeButton"]')[0];
		this.fitToScreenButton = div.select('img[id="fitToScreenButton"]')[0];
		this.imgTag = div.select('img[id="mainImage"]')[0];
		this.imgContainer = div.select('div[id="imageContainer"]')[0];
		fitHeightToBottom(this.imgContainer, null, 5);
		this.zoomInput = div.select('input[id="zoomValue"]')[0];
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
		this.closeButton.onclick = function(){
			hideLightBox(true);
			return false;
		}	
		this.downloadButton.onclick = function(){
			if(!this.currentFile) return;		
			document.location.href = 'content.php?action=download&file='+this.currentFile;
			return false;
		}.bind(this);
		this.actualSizeButton.onclick = function(){
			this.slider.setValue(100);
			this.resizeImage();
		}.bind(this);
		this.fitToScreenButton.onclick = function(){
			this.fitToScreen();
		}.bind(this);
		
		this.imgTag.onload = function(){
			this.resizeImage();
			this.downloadButton.removeClassName("disabled");
			this.updateModalTitle();
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
				this.resizeImage();
				Event.stop(e);
			}
			return true;
		}.bind(this));
		this.containerDim = $(this.imgContainer).getDimensions();
	},
	
	open : function(aFilesList, sCurrentFile)
	{
		this.currentFile = sCurrentFile;
		var allItems = aFilesList;
		this.items = new Array();
		this.sizes = new Hash();
		$A(allItems).each(function(rowItem){
			if(rowItem.getAttribute('is_image')=='1'){
				this.items.push(rowItem.getAttribute('filename'));
				this.sizes.set(rowItem.getAttribute('filename'),  {height:rowItem.getAttribute('image_height'), 
															   width:rowItem.getAttribute('image_width')});
			}
		}.bind(this));	
		
		var sliderDiv = this.element.select('div[id="slider-2"]')[0];
		var sliderInput = this.element.select('input[id="slider-input-2"]')[0];
		this.slider = new Slider(sliderDiv, sliderInput);
		this.slider.setMaximum(200);
		this.slider.setMinimum(10);	
		this.slider.setValue(100);
		this.zoomInput.value = '100';
		this.slider.recalculate();
		this.slider.onchange = function(){
			this.resizeImage();
		}.bind(this);
	
		this.updateImage();
		this.updateButtons();
	},
	
	updateModalTitle : function(){
		var titleDiv = $(modal.dialogTitle);
		var crtTitle = titleDiv.select('span.titleString')[0];
		var filenameSpans = crtTitle.select('span');
		var text = ' - '+getBaseName(this.currentFile) + ' ('+this.sizes.get(this.currentFile).width+' X '+this.sizes.get(this.currentFile).height+')';
		if(filenameSpans.length) filenameSpans[0].innerHTML = text;
		else {
			newSpan = document.createElement('span');
			newSpan.appendChild(document.createTextNode(text));
			crtTitle.appendChild(newSpan);
		}
	},
	
	resizeImage : function(){	
		var nPercent = this.slider.getValue();
		this.zoomInput.value = nPercent;
		var height = parseInt(nPercent*this.crtHeight / 100);	
		var width = parseInt(nPercent*this.crtWidth / 100);
		this.imgTag.setStyle({height:height+'px', width:width+'px'});
		// Center vertically
		if (height < this.containerDim.height){
			var margin = parseInt((this.containerDim.height - height) / 2)-5;
			this.imgTag.setStyle({marginTop:margin+'px'});
		}
		else{
			this.imgTag.setStyle({marginTop:'0px'});
		}
		
	},
	
	updateImage : function(){
		var dimObject = this.sizes.get(this.currentFile);
		this.crtHeight = dimObject.height;
		this.crtWidth = dimObject.width;
		this.crtRatio = this.crtHeight / this.crtWidth;
		this.downloadButton.addClassName("disabled");
		this.imgTag.src  = this.baseUrl + this.currentFile;	
	},

	fitToScreen : function(){
		zoomFactor1 = parseInt(this.imgContainer.getHeight() / this.crtHeight * 100);
		zoomFactor2 = parseInt(this.imgContainer.getWidth() / this.crtWidth * 100);
		this.slider.setValue(Math.min(zoomFactor1, zoomFactor2)-1);
		this.resizeImage();
	},
	
	close : function(){
		this.currentFile = null;
		this.items = null;
		this.imgTag.src = '';
	},
	
	next : function(){
		if(this.currentFile != this.items.last())
		{
			this.currentFile = this.items[this.items.indexOf(this.currentFile)+1];
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
		if(this.currentFile == this.items.first()) this.previousButton.addClassName("disabled");
		else this.previousButton.removeClassName("disabled");
		if(this.currentFile == this.items.last()) this.nextButton.addClassName("disabled");
		else this.nextButton.removeClassName("disabled");
	}
});