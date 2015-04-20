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
			alignY : 6,
            anchorActiveClass: 'slider-anchor-active'
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
		$(document.body).insert(this.holder);
		this.slider = new Control.Slider(this.cursor, this.tracker, this.options);
        this.showObserver = this.show.bind(this);
		if(this.input.getAttribute("type") && this.input.getAttribute("type") == "image" || this.input.nodeName != 'input'){
			this.input.observe("click", this.showObserver );
		}else{
			this.input.observe("focus", this.showObserver );
		}
        this.docObserver = function(event){
			var element = Event.findElement(event);
			if(element.descendantOf && !element.descendantOf(this.holder) && !element.descendantOf(this.input)
                && element != this.holder && element!=this.input) {
				this.hide();
			}
		}.bind(this);
		document.observe("click", this.docObserver);
	},

    destroy : function(){
        try{

            this.input.stopObserving("click", this.showObserver);
            this.input.stopObserving("focus", this.showObserver);
            document.stopObserving("click", this.docObserver);
            this.holder.remove();

        }catch(e){}
    },

	/**
	 * Sets the value of the slider
	 * @param value Integer
	 */
	setValue : function(value){
        var original = this.slider.options.onChange;
        this.slider.options.onChange = Prototype.emptyFunction;

		if(this.slider){
			this.slider.setValue(value);
		}
		if(this.input){
			this.input.value = value;
		}

        this.slider.options.onChange = original;
	},
	
	/**
	 * Show the sub pane
	 */
	show : function(anchor){
        var pos = this.computeAnchorPosition(this.input);
        if(anchor && !anchor.target){
            pos = this.computeAnchorPosition(anchor);
        }
		this.holder.setStyle(pos);
		this.slider.setValue(parseFloat(this.input.value));
		this.holder.show();
        this.input.addClassName(this.options.anchorActiveClass);
        if(anchor && !anchor.target){
            anchor.addClassName(this.options.anchorActiveClass);
        }
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
        this.input.removeClassName(this.options.anchorActiveClass);
	},
	/**
	 * Wait until automatically hiding the pane
	 */
	delay : function(){
		if(this.timer) {
			window.clearTimeout(this.timer);
		}
		//this.timer = window.setTimeout(this.hide.bind(this), 3000);
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
		
		return {top : topPos+'px', left:leftPos+'px'};
		
	}
	
});