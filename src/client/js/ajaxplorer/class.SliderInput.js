Class.create("SliderInput", {
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
	
	setValue : function(value){
		if(this.slider){
			this.slider.setValue(value);
		}
		if(this.input){
			this.input.value = value;
		}
	},
	
	show : function(){
		var pos = this.computeAnchorPosition(this.input);
		this.holder.setStyle(pos);
		this.slider.setValue(parseFloat(this.input.value));
		this.holder.show();
		this.delay();
	},
	
	hide : function(){
		this.holder.hide();
		if(this.timer) {
			window.clearTimeout(this.timer);
		}
		try{this.input.blur();}
		catch(e){}
	},
	
	delay : function(){
		if(this.timer) {
			window.clearTimeout(this.timer);
		}
		this.timer = window.setTimeout(this.hide.bind(this), 3000);
	},
	
	computeAnchorPosition : function(anchor){
		var anchorPosition = Position.cumulativeOffset($(anchor));
		var anchorScroll = anchor.cumulativeScrollOffset();		
		
		var topPos = anchorPosition[1] + anchor.getHeight() + this.options.topOffset - anchorScroll[1];
		var leftPos = anchorPosition[0] + this.options.leftOffset - anchorScroll[0];
		
		return {top : topPos, left:leftPos};
		
	}
	
});