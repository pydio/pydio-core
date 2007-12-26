ResizeableBar = Class.create({

	hasExpandButton : false,

	initialize: function(containerName, widthReferenceName, elementsSelector, titleElementName, buttonElementName)
	{
		this.containerElement = $(containerName);
		this.titleElementName = titleElementName;
		this.elementsSelector = elementsSelector;
		this.buttonElementName = buttonElementName;
		this.widthReferenceElement = $(widthReferenceName);
	
		this.hiddenElements = new Array();
		this.buttonImage = new Element('img', {
			src:ajxpResourcesFolder+'/images/crystal/lower.png',
			style:'cursor:pointer',
			align:'absmiddle'
		});
		this.buttonImage.observe('click', function(){
			this.toggleExtension();
		}.bind(this));
		
		Event.observe(window, "resize", function(){
			this.toggleExtension(true); this.updateUI();
			}.bind(this)
		);
	},
	
	updateUI: function(){
		var totalWidth = this.widthReferenceElement.getWidth()-43;
		if(this.titleElementName && $(this.titleElementName))
		{
			totalWidth -= $(this.titleElementName).getWidth();
		}
		var currentWidth = 0;
	    var elements = this.containerElement.getElementsByClassName(this.elementsSelector);
	    this.hiddenElements = new Array();
	    elements.each(function(element){
	    	if(element.readAttribute("extendedMenu") == "true") return;
			currentWidth += element.getWidth();
			if(currentWidth >= totalWidth){
				element.hide();
				this.hiddenElements.push(element);
			}
			else{
				element.show();
			}
		}.bind(this));
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
	},
	
	toggleExtension: function(hideOnly){
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
			this.menu.setStyle({
				position:"absolute", 
				zIndex:1500, 
				border:"1px solid black", 
				backgroundColor:"white", 
				textAlign:"left"});
			this.hiddenElements.each(function(element){
				$(element).setStyle({cssFloat:"none", textAlign:"left"});
				$(element).setAttribute("extendedMenu", "true");
				this.menu.appendChild(element.show());
			}.bind(this));
			body.appendChild(this.menu);		
			this.menuIsVisible = true;
			var buttonPosition = Position.cumulativeOffset($(this.buttonImage));
			var topPos = buttonPosition[1] + $(this.buttonImage).getHeight();
			var leftPos = buttonPosition[0] + $(this.buttonImage).getWidth() - this.menu.getWidth();
			this.menu.style.top = topPos + "px";
			this.menu.style.left = leftPos + "px";
			var closeHandler = function(){this.toggleExtension(true); Event.stopObserving(body,"click", closeHandler); return true;}.bind(this);
			window.setTimeout(function(){ Event.observe(body,"click", closeHandler);}, 100);
		}
	}
});