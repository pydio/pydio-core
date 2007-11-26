function ResizeableBar(containerName, widthReferenceName, elementsSelector, titleElementName, buttonElementName)
{
	this.containerElement = $(containerName);
	this.titleElementName = titleElementName;
	this.elementsSelector = elementsSelector;
	this.buttonElementName = buttonElementName;
	this.widthReferenceElement = $(widthReferenceName);

	this.hiddenElements = new Array();	
	var oThis = this;
	this.hasExpandButton = false;
	this.buttonImage = document.createElement("img");
	this.buttonImage.src = "images/crystal/lower.png";
	this.buttonImage.setAttribute("align", "absmiddle");
	$(this.buttonImage).setStyle({cursor:"pointer"});
	this.buttonImage.onclick = function(){oThis.toggleExtension();};
	Event.observe(window, "resize", function(){oThis.toggleExtension(true); oThis.updateUI();});
}

ResizeableBar.prototype.updateUI = function()
{
	var totalWidth = this.widthReferenceElement.getWidth()-43;
	if(this.titleElementName && $(this.titleElementName))
	{
		totalWidth -= $(this.titleElementName).getWidth();
	}
	var oThis = this;
	var currentWidth = 0;
    var elements = this.containerElement.getElementsByClassName(this.elementsSelector);
    this.hiddenElements = new Array();
    elements.each(function(element){
    	if(element.readAttribute("extendedMenu") == "true") return;
		currentWidth += element.getWidth();
		if(currentWidth >= totalWidth){
			element.hide();
			oThis.hiddenElements.push(element);
		}
		else
		{
			element.show();
			//oThis.hiddenElements = oThis.hiddenElements.without(element);
		}
	});
	//alert(this.hiddenElements.length);
	if(this.hiddenElements.length && !this.hasExpandButton)
	{
		if(this.buttonElementName && $(this.buttonElementName))
		{
			$(this.buttonElementName).appendChild(this.buttonImage);
		}
		else
		{
			this.containerElement.appendChild(this.buttonImage);
		}
		this.hasExpandButton = true;
	}
	else if(!this.hiddenElements.length && this.hasExpandButton)
	{
		if(this.buttonElementName && $(this.buttonElementName) && this.buttonImage)
		{
			$(this.buttonElementName).removeChild(this.buttonImage);
		}
		else
		{
			if(this.buttonImage) this.containerElement.removeChild(this.buttonImage);
		}
		this.hasExpandButton = false;		
	}
}

ResizeableBar.prototype.toggleExtension = function(hideOnly)
{
	if(!this.hiddenElements.length) return;
	body = document.getElementsByTagName('body')[0];
	if(this.menuIsVisible)
	{
		while(this.menu.firstChild)
		{
			$(this.menu.firstChild).setStyle({cssFloat:"right"});
			$(this.menu.firstChild).setAttribute("extendedMenu", "false");
			this.containerElement.appendChild(this.menu.firstChild.hide());
		}		
		body.removeChild(this.menu);
		this.menu = null;
		this.menuIsVisible = false;
	}
	else if(!hideOnly)
	{		
		// build menu and show next to button.
		this.menu = $(document.createElement("div"));
		//this.menu.setAttribute("style", "position:absolute; z-index:1500; border:1px solid black; background-color: white; text-align:left;");
		this.menu.setStyle({
			position:"absolute", 
			zIndex:1500, 
			border:"1px solid black", 
			backgroundColor:"white", 
			textAlign:"left"});
		var oThis = this;
		this.hiddenElements.each(function(element){
			$(element).setStyle({cssFloat:"none", textAlign:"left"});
			$(element).setAttribute("extendedMenu", "true");
			oThis.menu.appendChild(element.show());
		});
		body.appendChild(this.menu);		
		this.menuIsVisible = true;
		var buttonPosition = Position.cumulativeOffset($(this.buttonImage));
		var topPos = buttonPosition[1] + $(this.buttonImage).getHeight();
		var leftPos = buttonPosition[0] + $(this.buttonImage).getWidth() - this.menu.getWidth();
		this.menu.style.top = topPos + "px";
		this.menu.style.left = leftPos + "px";
		var closeHandler = function(){oThis.toggleExtension(true); Event.stopObserving(body,"click", closeHandler); return true;};
		window.setTimeout(function(){ Event.observe(body,"click", closeHandler);}, 100);
	}
}