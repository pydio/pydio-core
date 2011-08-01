/*
 * Copyright 2007-2011 Charles du Jeu
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
 */
/**
 * Slider component, used for changing the thumbnails size for example 
 */
Class.create("SliderInput", {
	/**
	 * Constructor
	 * @param inputElement HTMLElement
	 * @param options Object
	 */
	initialize : function(inputElement, options){
		this.input = $(inputElement);
		this.options = Object.extend({
			format : '',
			axis : 'vertical',
			onChange : Prototype.emptyFunction,
			onSlide : Prototype.emptyFunction,
			topOffset : 2,
			leftOffset : 3,
			range : $R(0, 1),
			alignY : 6
		}, options || { });

		var original = this.options.onSlide;
		this.options.onSlide = function(value){
			this.input.value = value;			
			original(value);
			this.delay();
		}.bind(this);
			
		if(this.options.sliderValue){
			this.input.value = this.options.sliderValue;
		}
		this.buildGui();
	},
	/**
	 * Creates the HTML
	 */
	buildGui : function(){
		// Create holder and slider, add them but set them hidden
		this.holder = new Element("div", {className : "slider-pane"});
		this.trackerTop = new Element("div", {className : "slider-tracker-top", style:"width:10px;height:2px;font-size:1px;"}).update("");
		this.tracker = new Element("div", {className : "slider-tracker", style:"width:10px;height:90px;"});
		this.cursor = new Element("div", {className : "slider-handle"});
		this.holder.insert(this.trackerTop);
		this.holder.insert(this.tracker);
		this.tracker.insert(this.cursor);		
		this.holder.hide();
		$$("body")[0].insert(this.holder);
		this.slider = new Control.Slider(this.cursor, this.tracker, this.options);		
		if(this.input.getAttribute("type") && this.input.getAttribute("type") == "image"){
			this.input.observe("click", this.show.bind(this) );
		}else{
			this.input.observe("focus", this.show.bind(this) );
		}
		document.observe("click", function(event){
			var element = Event.findElement(event);
			if(!element.descendantOf(this.holder) && element != this.input){
				this.hide();
			}
		}.bind(this));
	},
	/**
	 * Sets the value of the slider
	 * @param value Integer
	 */
	setValue : function(value){
		if(this.slider){
			this.slider.setValue(value);
		}
		if(this.input){
			this.input.value = value;
		}
	},
	
	/**
	 * Show the sub pane
	 */
	show : function(){
		var pos = this.computeAnchorPosition(this.input);
		this.holder.setStyle(pos);
		this.slider.setValue(parseFloat(this.input.value));
		this.holder.show();
		this.delay();
	},
	
	/**
	 * Hide the subpane
	 */
	hide : function(){
		this.holder.hide();
		if(this.timer) {
			window.clearTimeout(this.timer);
		}
		try{this.input.blur();}
		catch(e){}
	},
	/**
	 * Wait until automatically hiding the pane
	 */
	delay : function(){
		if(this.timer) {
			window.clearTimeout(this.timer);
		}
		this.timer = window.setTimeout(this.hide.bind(this), 3000);
	},
	/**
	 * Compute the position to attach the subpane to the input
	 * Returns a {top/left} object
	 * @param anchor HTMLElement
	 * @returns Object
	 */
	computeAnchorPosition : function(anchor){
		var anchorPosition = Position.cumulativeOffset($(anchor));
		var anchorScroll = anchor.cumulativeScrollOffset();		
		
		var topPos = anchorPosition[1] + anchor.getHeight() + this.options.topOffset - anchorScroll[1];
		var leftPos = anchorPosition[0] + this.options.leftOffset - anchorScroll[0];
		
		return {top : topPos, left:leftPos};
		
	}
	
});