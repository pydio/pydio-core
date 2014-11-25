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

    __implements: ["IActionProvider"],

	/**
	 * Constructor
	 * @param container HTMLElement
	 * @param options Object
	 */
	initialize: function(container, options){
		this.options = Object.extend({
			direction	: 	'vertical',
			activeClass	:	'active',
			fit			:	null,
            minSize     :   16,
            foldingButton           :  null,
            foldingAlternateClose   : null,
            foldingMinSize          : null,
            foldingButtonText       : null,
            invisibleBar:   false,
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
				position:'absolute'/*,
				margin:0*/
			});
		});
		this.paneA = divs[0];
		this.paneB = divs[1];
		this.initBorderA = parseInt(this.paneA.getStyle('border'+this.options.adjSide1+'Width')) + parseInt(this.paneA.getStyle('border'+this.options.adjSide2+'Width')) || 0;
		this.initBorderA += parseInt(this.paneA.getStyle('margin'+this.options.adjSide1)) + parseInt(this.paneA.getStyle('margin'+this.options.adjSide2));
		this.initBorderB = parseInt(this.paneB.getStyle('border'+this.options.adjSide1+'Width')) + parseInt(this.paneB.getStyle('border'+this.options.adjSide2+'Width'))  || 0;
		this.initBorderB += parseInt(this.paneB.getStyle('margin'+this.options.adjSide1)) + parseInt(this.paneB.getStyle('margin'+this.options.adjSide2));

        if(!this.initBorderA) this.initBorderA = 0;
        if(!this.initBorderB) this.initBorderB = 0;

		this.splitbar = new Element('div', {unselectable:'on'});
        var zIndex = (this.options.invisibleBar?parseInt(this.group.getStyle('zIndex'))+1:'inherit') || '10000';
		this.splitbar.addClassName(this.options.splitbarClass).setStyle({
            position:'absolute',
            cursor:this.options.cursor,
            fontSize:'1px'/*,
            zIndex:zIndex*/
        });
		this.paneA.insert({after:this.splitbar});

        this.startSplitFunc = this.startSplit.bind(this);
        this.endSplitFunc = this.endSplit.bind(this);
		this.splitbar.observe("mousedown", this.startSplitFunc);
		this.splitbar.observe("mouseup", this.endSplitFunc);

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

        this.userLoggedObs = function(){
            var sizePref = this.getUserPreference("size");
            var folded = this.getUserPreference("folded");
			if(sizePref){
                if(folded) this.moveSplitter(parseInt(sizePref));
                else {
                    this.prefoldValue = parseInt(sizePref);
                    this.resizeAnimated(parseInt(sizePref));
                }
			}
            if(folded){
                this.foldWithoutAnim();
            }
		}.bind(this);
		document.observe("ajaxplorer:user_logged",this.userLoggedObs);

        this.compConfigObs = function(event){
            if(!this.htmlElement){
                document.stopObserving("ajaxplorer:component_config_changed", this.compConfigObs);
                return;
            }
            if(event.memo.className == "Splitter::"+this.htmlElement.id){
                var node = event.memo.classConfig.get("all");
                var size = XPathSelectSingleNode(node, 'property[@name="resize"]');
                var folded = XPathSelectSingleNode(node, 'property[@name="folded"]');
                if(size){
                    var sizeValue = parseInt(size.getAttribute("value"));
                    if(size.getAttribute("value").indexOf("%")){
                        sizeValue = this.htmlElement.getWidth() * sizeValue / 100;
                    }
                    if(folded && folded.getAttribute("value") == "true") this.moveSplitter(sizeValue);
                    else {
                        this.effectWorking = false;
                        this.resizeAnimated(sizeValue);
                    }
                }
                if(folded && folded.getAttribute("value") == "true"){
                    this.foldWithoutAnim();
                }
            }
        }.bind(this);
        document.observe("ajaxplorer:component_config_changed", this.compConfigObs);

        this.doSplitFunc = this.doSplitMouse.bind(this);
		Event.observe(this.group, "mousemove", this.doSplitFunc);
		Event.observe(this.group, "mouseup", this.endSplitFunc);
		// NEW HTML5 : set it to false to disable native draggable!
		this.splitbar.draggable = false;
        disableTextSelection(this.group);
        if(this.options.startFolded){
            this.foldWithoutAnim();
        }
	},

    destroy:function($super){
        Event.stopObserving(this.group, "mousemove", this.doSplitFunc);
        this.splitbar.stopObserving("mousedown", this.startSplitFunc);
        this.splitbar.stopObserving("mouseup", this.endSplitFunc);
        document.stopObserving("ajaxplorer:user_logged",this.userLoggedObs);
        document.stopObserving("ajaxplorer:component_config_changed", this.compConfigObs);
        this.splitbar.remove();
        this.getActions().each(function(act){
            ajaxplorer.guiActions.unset(act.key);
        }.bind(this));
        if(this.paneA.ajxpPaneObject) {
            this.paneA.ajxpPaneObject.destroy();
            this.paneA.remove();
            this.paneA = null;
        }
        if(this.paneB.ajxpPaneObject) {
            this.paneB.ajxpPaneObject.destroy();
            this.paneB.remove();
            this.paneB = null;
        }
    },

    getFoldingAction : function(){
        return ajaxplorer.actionBar.getActionByName(this.htmlElement.id + "_folding_action");
    },

    /**
     * Gets the action of this component
     * @returns $H
     */
    getActions : function(){
        if(!this.options.foldingButton) return $H();
        var foldingValue = this.options.foldingButton;

        var butts = $H();
        // function may be bound to another context
        var oThis = this;
        var options = {
            name: oThis.htmlElement.id + '_folding_action',
            src:'view_left_close.png',
            icon_class:'icon-remove-sign',
            text_id:(oThis.options.foldingButtonText?oThis.options.foldingButtonText:416),
            title_id:(oThis.options.foldingButtonText?oThis.options.foldingButtonText:415),
            text:MessageHash[(oThis.options.foldingButtonText?oThis.options.foldingButtonText:416)],
            title:MessageHash[(oThis.options.foldingButtonText?oThis.options.foldingButtonText:415)],
            hasAccessKey:false,
            subMenu:false,
            subMenuUpdateImage:false,
            callback: function(){
                var state = oThis.toggleFolding(foldingValue == "B" ? oThis.paneB : oThis.paneA);
            },
            listeners : {
                init:function(){
                    this.refreshFoldingAction(foldingValue == "B" ? this.paneB : this.paneA);
                }.bind(this)
            }
        };
        var context = {
            selection:false,
            dir:true,
            actionBar:true,
            actionBarGroup:oThis.htmlElement.id+'-actions',
            contextMenu:false,
            infoPanel:false
            };
        // Create an action from these options!
        var foldingAction = new Action(options, context);
        butts.set(this.htmlElement.id + "_folding_button", foldingAction);

        if(this.options.foldingAlternateClose){
            var options2 = {
                name:'folding_close_action',
                src:'view_left_close.png',
                icon_class:'icon-remove-sign',
                text_id:416,
                title_id:415,
                text:MessageHash[416],
                title:MessageHash[415],
                hasAccessKey:false,
                subMenu:false,
                subMenuUpdateImage:false,
                callback: function(){
                    var state = oThis.toggleFolding(foldingValue == "B" ? oThis.paneB : oThis.paneA);
                    oThis.getFoldingAction().enable();
                },
                listeners : {
                    init:function(){
                        "use strict";
                        window.setTimeout(function(){
                            if(!oThis.splitbar.hasClassName("folded")) {
                                try{oThis.getFoldingAction().disable();}catch(e){}
                            }
                        }, 1500);
                    }
                }
            };
            var context2 = {
                selection:false,
                dir:true,
                actionBar:true,
                actionBarGroup:this.options.foldingAlternateClose,
                contextMenu:false,
                infoPanel:false
            };
            // Create an action from these options!
            var foldingCloseAction = new Action(options2, context2);
            butts.set(this.htmlElement.id + "_folding_close_button", foldingCloseAction);
            if(!this.splitbar.hasClassName("folded")) {
                foldingAction.deny = true;
                foldingAction.disable();
            }else {
                foldingAction.deny = true;
            }
        }



        return butts;
    },

	/**
	 * Resize panes on drag event or manually
	 * @param event Event
	 * @param size Integer
	 * @param keepPercents Boolean
	 */
	resizeGroup: function(event, size, keepPercents){
		// console.log("Resize", this.options.direction, size);
		var groupInitAdjust = this.group._adjust;
		this.group._fixed = this.options.getFixed(this.group) - this.group._borderFixed;
		this.group._adjust = this.group[this.options.offsetAdjust] - this.group._borderAdjust;

		//if(this.group._fixed <= 0 || this.group._adjust <= 0) return;

		// Recompute fixed
		var optName = this.options.fixed;
		var borderAdjA = this.initBorderA;
		this.paneA.setStyle(this.makeStyleObject(optName, this.group._fixed-this.paneA._padFixed-borderAdjA+'px'));
		var borderAdjB = this.initBorderB;
		this.paneB.setStyle(this.makeStyleObject(optName,this.group._fixed-this.paneB._padFixed-borderAdjB+'px'));
		this.splitbar.setStyle(this.makeStyleObject(optName, this.group._fixed +'px'));

        if(this.splitbar.hasClassName("folded")){
            if(this.foldedPane == this.paneA){
                var hiddenWidth = parseInt(this.paneA.getStyle(this.options.set));
                this.moveSplitter(0, true, -hiddenWidth);
                return;
            }else{
                //var hiddenWidth = parseInt(this.paneA.getStyle(this.options.set));
                this.moveSplitter(this.group._adjust, this.paneB, 0);
                return;
            }
        }
		// Recompute adjust
		if(keepPercents && !size && groupInitAdjust){
			size = parseInt(this.paneA[this.options.offsetAdjust] * this.group._adjust / groupInitAdjust ) + this.initBorderA;
			//console.log("moveSplitter::keep", this.options.direction, size);
		}else{
			size = size||(!this.options.initB?this.paneA[this.options.offsetAdjust]:this.group._adjust-this.paneB[this.options.offsetAdjust]-this.splitbar._adjust);
			//console.log("moveSplitter::nokeep", this.options.direction, size);
		}
		this.moveSplitter(size);
	},

    /**
     * @return boolean Folded (true) or not
     */
    toggleFolding : function(pane){
        if(this.splitbar.hasClassName("folded")) {
            var afE = this.options.autoFoldOnEvent;
            this.options.autoFoldOnEvent = false;
            this.unfold();
            this.options.autoFoldOnEvent = afE;
            return false;
        }else {
            this.foldPane(pane);
            return true;
        }
    },

    refreshFoldingAction : function(pane){
        if(!pane) pane = this.foldedPane;
        if(!this.paneA) return;
        var state = this.splitbar.hasClassName("folded");
        if(!this.getFoldingAction()) return;
        if(this.options.foldingAlternateClose){
            this.getFoldingAction()[(state?"enable":"disable")]();
        }else{
            this.getFoldingAction().setIconSrc(
                'view_left_'+ (state?'right':'close') + '.png',
                (state?(pane == this.paneA?'icon-caret-right':'icon-caret-left'):'icon-remove-sign'));
        }
        if(pane == this.paneA && $(this.paneA).ajxpPaneObject){
            $(this.paneA).ajxpPaneObject.resize();
        }
        if(pane == this.paneB && $(this.paneB).ajxpPaneObject){
            $(this.paneB).ajxpPaneObject.resize();
        }
    },

    fold:function(){

        this.foldPane(this.paneA);
        /*
        if(this.effectWorking) return;
        this.prefoldValue = this.options.getAdjust(this.paneA);
        this.effectWorking = true;
        new Effect.Tween(null, this.prefoldValue, 0, {afterFinish:function(){
            this.splitbar.addClassName('folded');
            this.effectWorking = false;
            this.setUserPreference("folded", true);
            this.refreshFoldingAction();
        }.bind(this)}, function(p){
            this.moveSplitter(p, (this.options.minA?false:true), this.prefoldValue);
        }.bind(this) );
        */
    },

    foldPane:function(pane){
        if(this.effectWorking || this.options.noFolding) return;
        var realFoldValue;
        if(this.options.foldingButton == "A"){
            realFoldValue = this.prefoldValue = this.options.getAdjust(this.paneA);
        }else{
            this.prefoldValue = this.options.getAdjust(this.paneB);
            realFoldValue = this.group._adjust - this.prefoldValue;
        }
        this.foldedPane = pane;
        this.effectWorking = true;
        var target = (pane==this.paneA?0:this.group._adjust);
        new Effect.Tween(null, realFoldValue, target, {afterFinish:function(){
            this.splitbar.addClassName('folded');
            this.foldedPane.addClassName('folded');
            this.effectWorking = false;
            this.setUserPreference("folded", true);
            this.refreshFoldingAction(pane);
        }.bind(this), duration: 0.8}, function(p){
            if(p == target - 3) this.foldedPane.addClassName('folded');
            this.moveSplitter(p, (this.options.minA?false:pane), realFoldValue);
        }.bind(this) );
    },

    unfold:function(){
        if(this.effectWorking) return;
        if(!this.prefoldValue){
            this.prefoldValue = 150;
            if(this.options.foldingButton == "A" && this.paneA._min && this.options.foldingMinSize !== undefined) {
                this.prefoldValue = this.paneA._min;
            } else if(this.options.foldingButton == "B" && this.paneB._min && this.options.foldingMinSize !== undefined) {
                this.prefoldValue = this.paneB._min;
            }
        }
        var target = this.options.foldingButton == "A" ? this.prefoldValue : (this.group._adjust - this.prefoldValue);
        var presetAdjust = this.prefoldValue;
        if(this.options.foldingButton == "B") {
            presetAdjust -= this.paneB._padAdjust + this.splitbar._adjust;
            this[this.options.foldingButton=="A"?"paneA":"paneB"].setStyle(this.makeStyleObject(this.options.adjust, presetAdjust+'px'));
        }
        this.effectWorking = true;
        new Effect.Tween(null, (this.foldedPane == this.paneA ? 0 : this.group._adjust), target, {afterFinish:function(){
            this.splitbar.removeClassName('folded');
            this.effectWorking = false;
            this.setUserPreference("folded", false);
            this.refreshFoldingAction(this.foldedPane);
        }.bind(this), duration: 0.8}, function(p){
            if(!this.paneA || !this.paneB) return;
            this.moveSplitter(p, (this.options.minA?false:this.foldedPane), target);
        }.bind(this) );
        if(this.foldedPane){
            var fPane = this.foldedPane;
            window.setTimeout(function(){fPane.removeClassName('folded');}, 200);
        }
        if(this.options.autoFoldOnEvent){
            document.observeOnce(this.options.autoFoldOnEvent, this.fold.bind(this));
        }
    },

    foldWithoutAnim : function(){
        if(this.options.noFolding) return;
        if(this.options.foldingButton == "A"){
            this.prefoldValue = this.options.getAdjust(this.paneA);
            this.moveSplitter(0, (!this.options.minA ? this.paneA:false), this.prefoldValue);
            this.foldedPane = this.paneA;
            this.paneA.addClassName('folded');
        }else{
            this.prefoldValue = this.options.getAdjust(this.paneB);
            this.moveSplitter(this.group._adjust, this.paneB, this.prefoldValue);
            this.foldedPane = this.paneB;
            this.paneB.addClassName('folded');
        }
        this.splitbar.addClassName('folded');
        this.setUserPreference("folded", true);
        this.refreshFoldingAction();
    },

    resizeAnimated : function(size){
        if(this.effectWorking) return;
        if(this.splitbar.hasClassName("folded")){
            return;
        }
        this.effectWorking = true;
        var current = this.options.getAdjust(this.paneA) + this.initBorderA;
        new Effect.Tween(null, current, size, {afterFinish:function(){
            this.effectWorking = false;
        }.bind(this) }, function(p){
            if(!this.paneA || !this.paneB) return;
            this.moveSplitter(p);
        }.bind(this) );
    },

	/**
	 * Start drag event
	 * @param event Event
	 */
	startSplit: function(event){
        if(this.splitbar.hasClassName("folded")){
            return false;
        }
		this.splitbar.addClassName(this.options.activeClass);
		this.paneA._posAdjust = this.options.getAdjust(this.paneA) + this.initBorderA - this.options.eventPointer(event);
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
	/**
	 * During drag event
	 * @param event Event
	 * @returns Boolean
	 */
	doSplitMouse: function(event){
        if (!this.splitbar.hasClassName(this.options.activeClass)){
        	return this.endSplit(event);
        }
		this.moveSplitter(this.paneA._posAdjust + this.options.eventPointer(event));
	},
	/**
	 * End drag event
	 * @param event Event
	 */
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
        this.setUserPreference("size", this.getCurrentSize());
	},
	/**
	 * Move the splitter object
	 * @param np Integer
	 */
	moveSplitter:function(np, folding, foldingSize){
        var minSize = this.options.minSize;
        var minPaneA = this.paneA._min;
        var minPaneB = this.paneB._min;
        if(folding && this.options.foldingMinSize !== undefined){
            minSize = minPaneA = minPaneB = this.options.foldingMinSize;
        }
        if(!folding && this.options.minA && np < (this.options.minA + 10) && !this.options.noFolding){
            np = this.options.minA;
            var forceFolded = true;
        }
		np = Math.max(minPaneA+this.paneA._padAdjust, this.group._adjust - (this.paneB._max||9999), minSize,
				Math.min(np, this.paneA._max||9999, this.group._adjust - this.splitbar._adjust -
				Math.max(minPaneB + this.paneB._padAdjust, minSize)));
		var optNameSet = this.options.set;
		var optNameAdjust = this.options.adjust;
        if(!np) np = this.paneA._init;
		this.splitbar.setStyle(this.makeStyleObject(this.options.set, (np + this.splitbar._reAdjust) +'px'));
		var borderAdjA = 0;
		var borderAdjB = 0;
		if(this.initBorderA){
			borderAdjA = this.initBorderA;
		}
        var targetAdjustA = np-this.paneA._padAdjust-borderAdjA;
        if(folding && folding == this.paneA){
            this.paneA.setStyle(this.makeStyleObject(this.options.set, (targetAdjustA - foldingSize) +'px'));
        }else{
            this.paneA.setStyle(this.makeStyleObject(this.options.adjust, targetAdjustA+'px'));
        }
        this.paneB.setStyle(this.makeStyleObject(this.options.set, np+this.splitbar._adjust+'px'));
		if(this.initBorderB){
			borderAdjB = this.initBorderB;
		}
        var bSide =this.group._adjust-this.splitbar._adjust-this.paneB._padAdjust-np-borderAdjB;
        bSide = Math.max(0,bSide);
        if(!folding || folding != this.paneB){
            this.paneB.setStyle(this.makeStyleObject(this.options.adjust, bSide+"px"));
        }
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
        if(forceFolded){
            if(!this.prefoldValue) this.prefoldValue = 150;
            if(this.options.foldingButton == "A"){
                this.paneA.addClassName("folded");
            }else{
                //if(!this.prefoldValue) this.prefoldValue = this.group._adjust - 150;
                this.paneB.addClassName("folded");
            }
            this.splitbar.addClassName('folded');
            this.setUserPreference("folded", true);
            this.refreshFoldingAction();
        }
	},

	/**
	 * Cache some CSS properties
	 * @param jq Object
	 * @param n Integer
	 * @param pf Integer
	 * @param m1 Integer
	 * @param m2 Integer
	 */
	cssCache:function(jq,n,pf,m1,m2){
		var boxModel = (!Prototype.Browser.IE || document.compatMode == "CSS1Compat");
		jq[n] = boxModel? (parseInt(jq.getStyle(pf+m1))||0) + (parseInt(jq.getStyle(pf+m2))||0) : 0;
	},
	/**
	 * Cache panes data
	 * @param jq Object
	 * @param pane Element
	 */
	optCache: function(jq, pane){
		jq._min = Math.max(0, this.options["min"+pane] || parseInt(jq.getStyle("min-"+this.options.adjust)) || 0);
		jq._max = Math.max(0, this.options["max"+pane] || parseInt(jq.getStyle("max-"+this.options.adjust)) || 0);
	},
	/**
	 * Initialize css cache
	 */
	initCaches: function(){
        if(this.options.invisibleBar){
            this.splitbar._adjust = 0;
            this.splitbar._reAdjust = - Math.round(this.splitbar[this.options.offsetAdjust])/2;
        }else{
            this.splitbar._adjust = this.splitbar[this.options.offsetAdjust];
            this.splitbar._reAdjust = 0;
        }
		this.cssCache(this.group, "_borderAdjust", "border", this.options.adjSide1, this.options.adjSide2);
		this.cssCache(this.group, "_borderFixed",  "border", this.options.fixSide1, this.options.fixSide2);
		this.cssCache(this.paneA, "_padAdjust", "padding", this.options.adjSide1, this.options.adjSide2);
		this.cssCache(this.paneA, "_padFixed",  "padding", this.options.fixSide1, this.options.fixSide2);
		this.cssCache(this.paneB, "_padAdjust", "padding", this.options.adjSide1, this.options.adjSide2);
		this.cssCache(this.paneB, "_padFixed",  "padding", this.options.fixSide1, this.options.fixSide2);
		this.optCache(this.paneA, 'A');
		this.optCache(this.paneB, 'B');
	},
	/**
	 * Get the width of an element
	 * @param el HTMLElement
	 * @returns Integer
	 */
    getWidth: function(el) {
	    return el.offsetWidth;
    },
  /**
   * Gets the height of an element
   * @param el HTMLElement
   * @returns Integer
   */
    getHeight: function(el) {
        if (typeof el.offsetHeight != "undefined"){
            return parseInt(el.offsetHeight);                                 //ie
        } else {
        	var h = el.getHeight();
        	if(!h && el.parentNode){
        		 h = $(el.parentNode).getHeight();
        		 if(!Prototype.Browser.IE) h -= parseInt($(el.parentNode).paddingHeight*2);
        	}
            return h;
        }
    },
    /**
     * Create a style object for Prototype setStyle method
     * @param propStringName String
     * @param propValue Mixed
     * @returns {Object}
     */
    makeStyleObject: function(propStringName, propValue){
    	var sObject = {};
    	sObject[propStringName] = propValue;
    	return sObject;
    },
    /**
     * Get the current size of paneA
     * @returns Integer
     */
    getCurrentSize : function(){
    	return this.options.getAdjust(this.paneA);
    },
    /**
     * Trigger a resize of the widget
     */
    resize : function(){
    	if(this.options.fit && this.options.fit == 'height'){
    		fitHeightToBottom(this.htmlElement, (this.options.fitParent?$(this.options.fitParent):null));
    	}
    	this.resizeGroup(null, null, true);
        if(!this.firstResizePassed){
            this.userLoggedObs();
            this.firstResizePassed = true;
        }
    },
    /**
     * Show/hide widget (do nothing)
     * @param show Boolean
     */
    showElement : function(show){}

});
