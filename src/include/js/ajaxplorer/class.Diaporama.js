function Diaporama(div)
{
	this.nextButton = div.getElementsBySelector('a#nextButton')[0];
	this.previousButton = div.getElementsBySelector('a#prevButton')[0];
	this.closeButton = div.getElementsBySelector('a#closeButton')[0];
	this.downloadButton = div.getElementsBySelector('a#downloadDiapoButton')[0];
	this.actualSizeButton = div.getElementsBySelector('img#actualSizeButton')[0];
	this.fitToScreenButton = div.getElementsBySelector('img#fitToScreenButton')[0];
	this.imgTag = div.getElementsBySelector('img#mainImage')[0];
	this.imgContainer = div.getElementsBySelector('div#imageContainer')[0];
	fitHeightToBottom(this.imgContainer, null, 5);
	this.zoomInput = div.getElementsBySelector('input#zoomValue')[0];
	this.baseUrl = 'content.php?action=image_proxy&fic=';
	var oThis = this;
	this.nextButton.onclick = function(){
		oThis.next();
		oThis.updateButtons();
		return false;
	}
	this.previousButton.onclick = function(){
		oThis.previous();
		oThis.updateButtons();
		return false;
	}
	this.closeButton.onclick = function(){
		hideLightBox(true);
		return false;
	}	
	this.downloadButton.onclick = function(){
		if(!oThis.currentFile) return;		
		document.location.href = 'content.php?action=download&fic='+oThis.currentFile;
		return false;
	}
	this.actualSizeButton.onclick = function(){
		oThis.slider.setValue(100);
		oThis.resizeImage();
	}
	this.fitToScreenButton.onclick = function(){
		oThis.fitToScreen();
	}
	
	this.imgTag.onload = function(){
		oThis.resizeImage();
		oThis.downloadButton.removeClassName("disabled");
		oThis.updateModalTitle();
	}
	Event.observe(this.zoomInput, "keypress", function(e){
		if(e == null) e = window.event;
		if(e.keyCode == Event.KEY_RETURN || e.keyCode == Event.KEY_UP || e.keyCode == Event.KEY_DOWN){
			if(e.keyCode == Event.KEY_UP || e.keyCode == Event.KEY_DOWN)
			{
				var crtValue = parseInt(oThis.zoomInput.value);
				var value = (e.keyCode == Event.KEY_UP?(e.shiftKey?crtValue+10:crtValue+1):(e.shiftKey?crtValue-10:crtValue-1));
				oThis.zoomInput.value = value;
			}
			oThis.slider.setValue(oThis.zoomInput.value);
			oThis.resizeImage();
		}
		return true;
	});
	this.containerDim = $(this.imgContainer).getDimensions();
}

Diaporama.prototype.open = function(aFilesList, sCurrentFile)
{
	this.currentFile = sCurrentFile;
	var allItems = aFilesList;
	this.items = new Array();
	this.sizes = new Hash();
	var oThis = this;
	$A(allItems).each(function(rowItem){
		if(rowItem.getAttribute('is_image')=='1'){
			oThis.items.push(rowItem.getAttribute('filename'));
			oThis.sizes.set(rowItem.getAttribute('filename'),  {height:rowItem.getAttribute('image_height'), 
														   width:rowItem.getAttribute('image_width')});
		}
	});	
	
	this.slider = new Slider($("slider-2"), $("slider-input-2"));		
	this.slider.setMaximum(200);
	this.slider.setMinimum(10);	
	this.slider.setValue(100);
	this.zoomInput.value = '100';
	this.slider.recalculate();
	var oThis = this;
	this.slider.onchange = function()
	{
		oThis.resizeImage();
	}

	this.updateImage();
	this.updateButtons();
}

Diaporama.prototype.updateModalTitle = function()
{
	var titleDiv = $(modal.dialogTitle);
	var crtTitle = titleDiv.getElementsBySelector('span.titleString')[0];
	var filenameSpans = crtTitle.getElementsBySelector('span');
	var text = ' - '+getBaseName(this.currentFile) + ' ('+this.sizes.get(this.currentFile).width+' X '+this.sizes.get(this.currentFile).height+')';
	if(filenameSpans.length) filenameSpans[0].innerHTML = text;
	else {
		newSpan = document.createElement('span');
		newSpan.appendChild(document.createTextNode(text));
		crtTitle.appendChild(newSpan);
	}
}

Diaporama.prototype.resizeImage = function()
{	
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
	
}

Diaporama.prototype.updateImage = function()
{
	var dimObject = this.sizes.get(this.currentFile);
	this.crtHeight = dimObject.height;
	this.crtWidth = dimObject.width;
	this.crtRatio = this.crtHeight / this.crtWidth;
	this.downloadButton.addClassName("disabled");
	this.imgTag.src  = this.baseUrl + this.currentFile;	
}

Diaporama.prototype.fitToScreen = function()
{
	zoomFactor1 = parseInt(this.imgContainer.getHeight() / this.crtHeight * 100);
	zoomFactor2 = parseInt(this.imgContainer.getWidth() / this.crtWidth * 100);
	this.slider.setValue(Math.min(zoomFactor1, zoomFactor2)-1);
	this.resizeImage();
}

Diaporama.prototype.close = function()
{
	this.currentFile = null;
	this.items = null;
	this.imgTag.src = '';
}

Diaporama.prototype.next = function()
{
	if(this.currentFile != this.items.last())
	{
		this.currentFile = this.items[this.items.indexOf(this.currentFile)+1];
		this.updateImage();
	}
}

Diaporama.prototype.previous = function()
{
	if(this.currentFile != this.items.first())
	{
		this.currentFile = this.items[this.items.indexOf(this.currentFile)-1];
		this.updateImage();	
	}
}

Diaporama.prototype.updateButtons = function()
{
	if(this.currentFile == this.items.first()) this.previousButton.addClassName("disabled");
	else this.previousButton.removeClassName("disabled");
	if(this.currentFile == this.items.last()) this.nextButton.addClassName("disabled");
	else this.nextButton.removeClassName("disabled");
}