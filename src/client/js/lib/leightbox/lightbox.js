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

var detect = navigator.userAgent.toLowerCase();
var OS,browser,version,total,thestring;
var currentLightBox, currentDraggable;

/*-----------------------------------------------------------------------------------------------*/

//Browser detect script origionally created by Peter Paul Koch at http://www.quirksmode.org/

function getBrowserInfo() {
	if (checkIt('konqueror')) {
		browser = "Konqueror";
		OS = "Linux";
	}
	else if (checkIt('safari')) browser 	= "Safari" ;
	else if (checkIt('omniweb')) browser 	= "OmniWeb" ;
	else if (checkIt('opera')) browser 		= "Opera" ;
	else if (checkIt('webtv')) browser 		= "WebTV";
	else if (checkIt('icab')) browser 		= "iCab" ;
	else if (checkIt('msie')) browser 		= "Internet Explorer" ;
	else if (!checkIt('compatible')) {
		browser = "Netscape Navigator" ;
		version = detect.charAt(8);
	}
	else browser = "An unknown browser";

	if (!version) version = detect.charAt(place + thestring.length);

	if (!OS) {
		if (checkIt('linux')) OS 		= "Linux";
		else if (checkIt('x11')) OS 	= "Unix";
		else if (checkIt('mac')) OS 	= "Mac" ;
		else if (checkIt('win')) OS 	= "Windows" ;
		else OS 		= "an unknown operating system";
	}
}

function checkIt(string) {
	place = detect.indexOf(string) + 1;
	thestring = string;
	return place;
}

/*-----------------------------------------------------------------------------------------------*/

Event.observe(window, 'load', initialize, false);
Event.observe(window, 'load', getBrowserInfo, false);
Event.observe(window, 'unload', Event.unloadCache, false);

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
		if (browser == 'Internet Explorer'){
			this.getScroll();
			//this.prepareIE('100%', 'hidden');
			this.setScroll(0,0);
			//this.hideSelects('hidden');
		}
		this.displayLightbox("block");
	},
	
	// Ie requires height to 100% and overflow hidden or else you can scroll down past the lightbox
	prepareIE: function(height, overflow){
		bod = document.getElementsByTagName('body')[0];
		bod.style.overflow = overflow;
		bod.style.height = height;
  
		htm = document.getElementsByTagName('html')[0];
		htm.style.overflow = overflow; 
		htm.style.height = height;
	},
	
	// In IE, select elements hover on top of the lightbox
	hideSelects: function(visibility){
		selects = document.getElementsByTagName('select');
		for(i = 0; i < selects.length; i++) {
			selects[i].style.visibility = visibility;
		}
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
			$('overlay').fade({duration:0.5});
		}else{
			$('overlay').style.display = display;
		}
		if(this.content != null)
		{
			$(this.content).style.display = display;
			currentDraggable = new Draggable(this.content, {
				handle:"dialogTitle",
				zindex:1050, 
				starteffect : function(element){
					if(element.shadows) {
						Shadower.deshadow(element);
						element.hadShadow = true;
					}
				},
				endeffect : function(element){
					if(element.hadShadow){
						Shadower.shadow(element,{
							distance: 4,
							angle: 130,
							opacity: 0.5,
							nestedShadows: 3,
							color: '#000000',
							shadowStyle:{display:'block'}
						});
					}
				}
			});
		}
		//if(display != 'none') this.actions();		
	},
	
	// Search through new links within the lightbox, and attach click event
	// WARNING : QUITE LONG IN I.E.
	actions: function(){
		
		lbActions = document.getElementsByClassName('lbAction');

		for(i = 0; i < lbActions.length; i++) {
			Event.observe(lbActions[i], 'click', this[lbActions[i].rel].bindAsEventListener(this), false);
			lbActions[i].onclick = function(){return false;};
		}
		
	},
	
	// Example of creating your own functionality once lightbox is initiated
	deactivate: function(){
		if (browser == "Internet Explorer"){
			this.setScroll(0,this.yPos);
			//this.prepareIE("auto", "hidden");
			//this.hideSelects("visible");
		}
		
		this.displayLightbox("none");
	}
};

/*-----------------------------------------------------------------------------------------------*/

// Onload, make all links that need to trigger a lightbox active
function initialize(){
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
			hideLightBox();
		}
		if(e.keyCode == 9) return false;
		return true;
	});
	
}

function displayLightBoxById(id)
{
	valid = new lightbox(id);
	valid.activate();
	currentLightBox = valid;	
	if(id != 'copymove_div')
	{
		
	}
}

function hideLightBox(onFormSubmit)
{
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
	Shadower.deshadow($(modal.elementName));
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

	bod 				= document.getElementsByTagName('body')[0];
	overlay 			= document.createElement('div');
	overlay.id			= 'overlay';
	bod.appendChild(overlay);
}

function addLightboxMarkupToElement(element, skipElement) 
{
	overlay 			= document.createElement('div');
	overlay.id			= 'element_overlay';
	if (Prototype.Browser.IE){
		var position = Position.positionedOffset($(element)); // IE CASE
		//Position.offsetParent(element);
		overlay.style.top = position[1];
		overlay.style.left = 0;
	}
	else
	{
		var position = Position.cumulativeOffset($(element));
		overlay.style.top = position[1];
		overlay.style.left = position[0];
	}
	overlay.style.width = element.getWidth();
	overlay.style.height = element.getHeight();
	if(skipElement)
	{
		var addTop = parseInt(overlay.style.top) + parseInt(skipElement.getHeight());
		var addHeight = parseInt(overlay.style.height) + parseInt(skipElement.getHeight());
		overlay.style.top = addTop + 'px';
		overlay.style.height = addHeight + 'px';
	}
	element.appendChild(overlay);
}

function removeLightboxFromElement(element)
{
	var  tmp = $(element).select('#element_overlay');
	if(tmp.length){
		tmp[0].remove();
	}
}
