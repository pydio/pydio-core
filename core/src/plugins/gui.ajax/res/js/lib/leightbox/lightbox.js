/*
Created By: Chris Campbell
Website: http://particletree.com
Date: 2/1/2006

Adapted By: Simon de Haan
Website: http://blog.eight.nl
Date: 21/2/2006

Inspired by the lightbox implementation found at http://www.huddletogether.com/projects/lightbox/
And the lightbox gone wild by ParticleTree at http://particletree.com/features/lightbox-gone-wild/

*/

/*-------------------------------GLOBAL VARIABLES------------------------------------*/
var currentLightBox, currentDraggable;
/*-----------------------------------------------------------------------------------*/
Event.observe(window, 'load', initializeLightbox, false);

var lightbox = Class.create();

lightbox.prototype = {

	yPos : 0,
	xPos : 0,

/*	initialize: function(ctrl) {
		this.content = ctrl.rel;
		Event.observe(ctrl, 'click', this.activate.bindAsEventListener(this), false);
		ctrl.onclick = function(){return false;};
	},
*/	
	initialize: function(id) {
		this.content = id;
	},
	
	// Turn everything on - mainly the IE fixes
	activate: function(){
		if (Prototype.Browser.IE){
			this.getScroll();
			this.setScroll(0,0);
		}
		this.displayLightbox("block");
	},
	

	// Taken from lightbox implementation found at http://www.huddletogether.com/projects/lightbox/
	getScroll: function(){
		if (self.pageYOffset) {
			this.yPos = self.pageYOffset;
		} else if (document.documentElement && document.documentElement.scrollTop){
			this.yPos = document.documentElement.scrollTop; 
		} else if (document.body) {
			this.yPos = document.body.scrollTop;
		}
	},
	
	setScroll: function(x, y){
		window.scrollTo(x, y); 
	},
	
	displayLightbox: function(display){		
		if(!$('overlay')){
			addLightboxMarkup();
		}
		if(display == 'none'){
			$('overlay').fade({
                duration:0.5,
                afterFinish : function(){
                    if(this.originalStyle){
                        $('overlay').setStyle(this.originalStyle);
                    }
                }.bind(this)
            });
		}else{
            $('overlay').setAttribute('style', '');
			$('overlay').style.display = display;
            if(this.overlayStyle){
                this.originalStyle = {};
                for(var key in this.overlayStyle){
                    this.originalStyle[key] = $('overlay').getStyle(key);
                }
                $('overlay').setStyle(this.overlayStyle);
            }
            if(this.overlayClass){
                $('overlay').className = 'overlay '+this.overlayClass;
            }else{
                $('overlay').className = 'overlay';
            }
		}
		if(this.content != null)
		{
			$(this.content).style.display = display;
			currentDraggable = new Draggable(this.content, {
				handle:"dialogTitle",
				zindex:1050, 
				starteffect : function(element){},
				endeffect : function(element){}
			});
		}
		//if(display != 'none') this.actions();		
	},
	
	// Search through new links within the lightbox, and attach click event
	// WARNING : QUITE LONG IN I.E.
	actions: function(){
		
		var lbActions = document.getElementsByClassName('lbAction');

		for(var i = 0; i < lbActions.length; i++) {
			Event.observe(lbActions[i], 'click', this[lbActions[i].rel].bindAsEventListener(this), false);
			lbActions[i].onclick = function(){return false;};
		}
		
	},
	
	// Example of creating your own functionality once lightbox is initiated
	deactivate: function(){
		if (Prototype.Browser.IE){
			this.setScroll(0,this.yPos);
		}
		this.displayLightbox("none");
	}
};

/*-----------------------------------------------------------------------------------------------*/

// Onload, make all links that need to trigger a lightbox active
function initializeLightbox(){
	addLightboxMarkup();
	/*
	lbox = document.getElementsByClassName('lbOn');
	for(i = 0; i < lbox.length; i++) {
		valid = new lightbox(lbox[i]);
	}
	*/
	
	Event.observe(document, "keydown", function(e){
		if(e==null)e=window.event;		
		if(e.keyCode == 27)
		{
			ajaxplorer.cancelCopyOrMove();
			//modal.close();
            if(modal.currentLightBoxElement){
                removeLightboxFromElement(modal.currentLightBoxElement);
                if(modal.currentLightBoxModal && modal.currentLightBoxModal.parentNode) {
                    modal.currentLightBoxModal.remove();
                }
                modal.currentLightBoxElement = null;
                modal.currentLightBoxModal = null;
            }else{
                hideLightBox();
            }
		}
		if(e.keyCode == 9) return false;
		return true;
	});
	
}

function displayLightBoxById(id, overlayStyle, overlayClass)
{
	var valid = new lightbox(id);
    if(overlayStyle) valid.overlayStyle = overlayStyle;
    if(overlayClass) valid.overlayClass = overlayClass;
	valid.activate();
	currentLightBox = valid;	
	if(id != 'copymove_div')
	{
		
	}
}

function hideLightBox(onFormSubmit)
{
	if(modal.closeValidation){
		var res = modal.closeValidation();
		if(res === false){
			return false;
		}
		modal.closeValidation = null;
	}
	if(currentLightBox)
	{
		currentLightBox.deactivate();
		hideOverlay();
		if(!onFormSubmit)
		{
			currentLightBox = null;
		}
		ajaxplorer.enableNavigation();
		ajaxplorer.focusLast();
		ajaxplorer.enableShortcuts();
		document.fire("ajaxplorer:selection_changed");
	}
	if(currentDraggable) currentDraggable.destroy();
	if(modal.closeFunction){
		modal.closeFunction();
		modal.closeFunction = null;
	}
    return true;
}

function setOverlay()
{
	currentLightBox = new lightbox(null);
	currentLightBox.activate();
}

function hideOverlay()
{
	if(currentLightBox)
	{
		currentLightBox.deactivate();
		currentLightBox = null;
	}
}

// Add in markup necessary to make this work. Basically two divs:
// Overlay holds the shadow
// Lightbox is the centered square that the content is put into.
function addLightboxMarkup() {

	var bod 				= document.getElementsByTagName('body')[0];
	var overlay 			= document.createElement('div');
	overlay.id			    = 'overlay';
	bod.appendChild(overlay);
}

function addLightboxMarkupToElement(element, skipElement) 
{
	var overlay 		= document.createElement('div');
	overlay.id			= 'element_overlay';
	var top, left, height, width;
	if (Prototype.Browser.IE){
		var position = Position.positionedOffset($(element)); // IE CASE
		//Position.offsetParent(element);
		top = position[1];
		left = 0;
	}
	else
	{
		var position = Position.cumulativeOffset($(element));
		top = position[1];
		left = position[0];
	}
	width = element.getWidth();
	height = element.getHeight();	
	if(skipElement)
	{
		var addTop = top + parseInt(skipElement.getHeight());
		var addHeight = height + parseInt(skipElement.getHeight());
		top = addTop + 'px';
		height = addHeight + 'px';
	}
	var borders = {
		top:parseInt(element.getStyle('borderTopWidth')),
		bottom:parseInt(element.getStyle('borderBottomWidth')),
		right:parseInt(element.getStyle('borderRightWidth')),
		left:parseInt(element.getStyle('borderLeftWidth'))
	};
	top += borders.top;
	height -= borders.top + borders.bottom;
	left += borders.left;
	width -= borders.left + borders.right;
	$(overlay).setStyle({top:top,left:left,height:height,width:width});
	
	element.appendChild(overlay);
}

function removeLightboxFromElement(element)
{
	$(element).select('#element_overlay').invoke('remove');
}
