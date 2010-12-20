/*
 * jquery.splitter.js - two-pane splitter window plugin
 *
 * version 1.01 (01/05/2007) 
 * 
 * Dual licensed under the MIT and GPL licenses: 
 *   http://www.opensource.org/licenses/mit-license.php 
 *   http://www.gnu.org/licenses/gpl.html 
 * 
 * Ported to Prototype by Charles du Jeu
 */

/**
 * The splitter() plugin implements a two-pane resizable splitter window.
 * The selected elements in the jQuery object are converted to a splitter;
 * each element should have two child elements which are used for the panes
 * of the splitter. The plugin adds a third child element for the splitbar.
 * 
 * For more details see: http://methvin.com/jquery/splitter/
 *
 *
 * @example $('#MySplitter').splitter();
 * @desc Create a vertical splitter with default settings 
 *
 * @example $('#MySplitter').splitter({direction: 'h', accessKey: 'M'});
 * @desc Create a horizontal splitter resizable via Alt+Shift+M
 *
 * @name splitter
 * @type jQuery
 * @param String options Options for the splitter
 * @cat Plugins/Splitter
 * @return jQuery
 * @author Dave Methvin (dave.methvin@gmail.com)
 */

Class.create("Splitter", AjxpPane, {
	
	initialize: function(container, options){				
		this.options = Object.extend({
			direction	: 	'vertical',
			activeClass	:	'active',
			fit			:	null,
			onDrag 		:	Prototype.EmptyFunction,
			endDrag 	:	Prototype.EmptyFunction,
			startDrag 	:	Prototype.EmptyFunction
			// initA, initB
			// minA, maxA, minB, maxB
		}, arguments[1]||{});
		var verticalOpts = {
			cursor:			'e-resize',
			splitbarClass: 	'vsplitbar',
			eventPointer:	Event.pointerX,
			set:			'left',
			adjust:			'width', 
			getAdjust:		this.getWidth,
			offsetAdjust:	'offsetWidth', 
			adjSide1:		'Left',
			adjSide2:		'Right',
			fixed:			'height',
			getFixed:		this.getHeight,
			offsetFixed:	'offsetHeight',
			fixSide1:		'Top',
			fixSide2:		'Bottom'
		};
		var horizontalOpts = {
			cursor:			'n-resize',
			splitbarClass: 	'hsplitbar',
			eventPointer:	Event.pointerY,
			set:			'top',			
			adjust:			'height', 
			getAdjust:		this.getHeight,
			offsetAdjust:	'offsetHeight', 
			adjSide1:		'Top',
			adjSide2:		'Bottom',
			fixed:			'width',
			getFixed:		this.getWidth,
			offsetFixed:	'offsetWidth',
			fixSide1:		'Left',
			fixSide2:		'Right'			
		};
		if(this.options.direction == 'vertical') Object.extend(this.options, verticalOpts);
		else Object.extend(this.options, horizontalOpts);
		
		this.htmlElement = $(container);
		this.htmlElement.ajxpPaneObject = this;
		
		this.group = $(container).setStyle({position:'relative'});
		var divs = this.group.childElements();
		divs.each(function(div){
			div.setStyle({
				position:'absolute',
				margin:0
			});
		});
		this.paneA = divs[0];
		this.paneB = divs[1];
		this.initBorderA = parseInt(this.paneA.getStyle('borderWidth')) || 0;
		this.initBorderB = parseInt(this.paneB.getStyle('borderWidth')) || 0;
		
		this.splitbar = new Element('div', {unselectable:'on'});
		this.splitbar.addClassName(this.options.splitbarClass).setStyle({position:'absolute', cursor:this.options.cursor,fontSize:'1px'});
		this.paneA.insert({after:this.splitbar});

		this.splitbar.observe("mousedown", this.startSplit.bind(this));
		this.splitbar.observe("mouseup", this.endSplit.bind(this));
		
		this.initCaches();
		
		this.paneA._init = (this.options.initA==true?parseInt(this.options.getAdjust(this.paneA)):this.options.initA) || 0;
		this.paneB._init = (this.options.initB==true?parseInt(this.options.getAdjust(this.paneB)):this.options.initB) || 0;
		if(this.paneB._init){
			this.paneB.setStyle(this.makeStyleObject(this.options.adjust, this.paneB._init));
		}
		if(this.paneA._init){
			this.paneA.setStyle(this.makeStyleObject(this.options.adjust, this.paneA._init));
		}
		//Event.observe(window,"resize", function(e){this.resizeGroup(e, null, true);}.bind(this));
		this.resizeGroup(null, this.paneB._init || this.paneA._init || Math.round((this.group[this.options.offsetAdjust]-this.group._borderAdjust-this.splitbar._adjust)/2));

		Event.observe(document, "ajaxplorer:user_logged", function(){		
			if(!ajaxplorer || !ajaxplorer.user) return;
			var sizePref = ajaxplorer.user.getPreference(this.htmlElement.id + "_size");
			if(sizePref){
				this.moveSplitter(parseInt(sizePref));
			}
		}.bind(this));
		
		Event.observe(this.group, "mousemove", this.doSplitMouse.bind(this));
		Event.observe(this.group, "mouseup", this.endSplit.bind(this));
		// NEW HTML5!
		this.splitbar.draggable = true;
	},
	
	resizeGroup: function(event, size, keepPercents){	
		// console.log("Resize", this.options.direction, size);
		var groupInitAdjust = this.group._adjust;
		this.group._fixed = this.options.getFixed(this.group) - this.group._borderFixed;
		this.group._adjust = this.group[this.options.offsetAdjust] - this.group._borderAdjust;
		
		if(this.group._fixed <= 0 || this.group._adjust <= 0) return;
		
		// Recompute fixed
		var optName = this.options.fixed;
		var borderAdj = (!Prototype.Browser.IE?(this.initBorderA*2):0);		
		this.paneA.setStyle(this.makeStyleObject(optName, this.group._fixed-this.paneA._padFixed-borderAdj+'px')); 
		var borderAdj = (!Prototype.Browser.IE?(this.initBorderB*2):0);		
		this.paneB.setStyle(this.makeStyleObject(optName,this.group._fixed-this.paneB._padFixed-borderAdj+'px')); 
		this.splitbar.setStyle(this.makeStyleObject(optName, this.group._fixed+'px'));		
		
		// Recompute adjust
		if(keepPercents && !size && groupInitAdjust){			
			size = parseInt(this.paneA[this.options.offsetAdjust] * this.group._adjust / groupInitAdjust );
			//console.log("moveSplitter::keep", this.options.direction, size);
		}else{
			size = size||(!this.options.initB?this.paneA[this.options.offsetAdjust]:this.group._adjust-this.paneB[this.options.offsetAdjust]-this.splitbar._adjust);
			//console.log("moveSplitter::nokeep", this.options.direction, size);
		}
		this.moveSplitter(size);
	},
	
	startSplit: function(event){
		this.splitbar.addClassName(this.options.activeClass);
		this.paneA._posAdjust = this.paneA[this.options.offsetAdjust] - this.options.eventPointer(event);
		/*
		if(!this.moveObserver){
			this.moveObserver = this.doSplitMouse.bind(this);
			this.upObserver = this.endSplit.bind(this);
		}
		*/
		//Event.observe(this.group, "mousemove", this.moveObserver);
		//Event.observe(this.group, "mouseup", this.upObserver);
		if(this.options.startDrag){
			this.options.startDrag(this.getCurrentSize());
		}
	},
	
	doSplitMouse: function(event){
        if (!this.splitbar.hasClassName(this.options.activeClass)){        	
        	return this.endSplit(event);
        }        
		this.moveSplitter(this.paneA._posAdjust + this.options.eventPointer(event));		
	}, 
	
	endSplit: function(event){
		if (!this.splitbar.hasClassName(this.options.activeClass)){
			return;
		}
		this.splitbar.removeClassName(this.options.activeClass);
		/*
		if(this.moveObserver){
			//Event.stopObserving(this.group, "mousemove", this.moveObserver);
			//Event.stopObserving(this.group, "mouseup", this.upObserver);
        	this.moveObserver = 0; this.upObserver = 0;
		}
		*/
		if(this.options.endDrag){
			this.options.endDrag(this.getCurrentSize());
		}
		if($(this.paneA).ajxpPaneObject){
			$(this.paneA).ajxpPaneObject.resize();
		}
		if($(this.paneB).ajxpPaneObject){
			$(this.paneB).ajxpPaneObject.resize();
		}
		if(ajaxplorer && ajaxplorer.user){
			ajaxplorer.user.setPreference(this.htmlElement.id+'_size', this.getCurrentSize());
			ajaxplorer.user.savePreference(this.htmlElement.id+'_size');
		}
	}, 
	
	moveSplitter:function(np){		
		np = Math.max(this.paneA._min+this.paneA._padAdjust, this.group._adjust - (this.paneB._max||9999), 16,
				Math.min(np, this.paneA._max||9999, this.group._adjust - this.splitbar._adjust - 
				Math.max(this.paneB._min+this.paneB._padAdjust, 16)));
		var optNameSet = this.options.set;				
		var optNameAdjust = this.options.adjust;				
		this.splitbar.setStyle(this.makeStyleObject(this.options.set, np+'px'));
		var borderAdj = 0;
		if(!Prototype.Browser.IE && this.initBorderA){
			borderAdj = this.initBorderA*2;
		}		
		this.paneA.setStyle(this.makeStyleObject(this.options.adjust, np-this.paneA._padAdjust-borderAdj+'px'));
		this.paneB.setStyle(this.makeStyleObject(this.options.set, np+this.splitbar._adjust+'px'));
		if(!Prototype.Browser.IE && this.initBorderB){
			borderAdj = this.initBorderB*2;
		}		
		this.paneB.setStyle(this.makeStyleObject(this.options.adjust, this.group._adjust-this.splitbar._adjust-this.paneB._padAdjust-np-borderAdj+"px"));
		if(!Prototype.Browser.IE){
			this.paneA.fire("resize");
			this.paneB.fire("resize");
		}
		if(this.options.onDrag) this.options.onDrag();
		if($(this.paneA).ajxpPaneObject){
			$(this.paneA).ajxpPaneObject.resize();
		}
		if($(this.paneB).ajxpPaneObject){
			$(this.paneB).ajxpPaneObject.resize();
		}		
	}, 
	
	cssCache:function(jq,n,pf,m1,m2){
		var boxModel = (!Prototype.Browser.IE || document.compatMode == "CSS1Compat");
		jq[n] = boxModel? (parseInt(jq.getStyle(pf+m1))||0) + (parseInt(jq.getStyle(pf+m2))||0) : 0;
	},
	
	optCache: function(jq, pane){
		jq._min = Math.max(0, this.options["min"+pane] || parseInt(jq.getStyle("min-"+this.options.adjust)) || 0);
		jq._max = Math.max(0, this.options["max"+pane] || parseInt(jq.getStyle("max-"+this.options.adjust)) || 0);		
	}, 
	
	initCaches: function(){
		this.splitbar._adjust = this.splitbar[this.options.offsetAdjust];
		this.cssCache(this.group, "_borderAdjust", "border", this.options.adjSide1, this.options.adjSide2);
		this.cssCache(this.group, "_borderFixed",  "border", this.options.fixSide1, this.options.fixSide2);
		this.cssCache(this.paneA, "_padAdjust", "padding", this.options.adjSide1, this.options.adjSide2);
		this.cssCache(this.paneA, "_padFixed",  "padding", this.options.fixSide1, this.options.fixSide2);
		this.cssCache(this.paneB, "_padAdjust", "padding", this.options.adjSide1, this.options.adjSide2);
		this.cssCache(this.paneB, "_padFixed",  "padding", this.options.fixSide1, this.options.fixSide2);
		this.optCache(this.paneA, 'A');
		this.optCache(this.paneB, 'B');		
	},
	
    getWidth: function(el) {
	    return el.offsetWidth;
    },
  
    getHeight: function(el) {
        if (el.offsetHeight){
            return parseInt(el.offsetHeight);                                 //ie
        } else {
        	var h = el.getHeight();
        	if(!h){        		
        		 h = $(el.parentNode).getHeight();
        		 if(!Prototype.Browser.IE) h -= parseInt($(el.parentNode).paddingHeight*2);
        	}
            return h;
        }
    }, 
    
    makeStyleObject: function(propStringName, propValue){
    	var sObject = {};
    	sObject[propStringName] = propValue;
    	return sObject;
    },
    
    getCurrentSize : function(){
    	return this.options.getAdjust(this.paneA);
    },
    
    resize : function(){
    	if(this.options.fit && this.options.fit == 'height'){
    		fitHeightToBottom(this.htmlElement, (this.options.fitParent?$(this.options.fitParent):null));
    	}
    	this.resizeGroup(null, null, true);
    },
    
    showElement : function(show){}

});
