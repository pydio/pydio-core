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


// TO ADD GLOBALLY
var timerClearObserver = {
	onEnd:function(){
		if(window.WebFXtimer) clearTimeout(window.WebFXtimer);
	}
} ;
document.observe("ajaxplorer:loaded", function(){
	Draggables.addObserver(timerClearObserver);
	Draggables.addObserver({onDrag:function(eventName,element,event){
		if(element.updateCtrlKey){
			element.updateCtrlKey(event);
		}
	}});	
});
window.AllAjxpDraggables = $A([]);
var AllAjxpDroppables = $A([]);
Event.observe(window, "unload", function(){
	Draggables.removeObserver(timerClearObserver);
    window.AllAjxpDraggables.each(function(el){
        try{
		    el.destroy();
        }catch(z){}
	});
    window.AllAjxpDroppables.each(function(el){
        try{
    		Droppables.remove(el);
        }catch(z){}
	});
});
/**
 * Pydio encapsulation of the Prototype Draggable
 */
Class.create("AjxpDraggable", Draggable, {
	/**
	 * Constructor
	 * @param $super klass Reference to superclass
	 * @param element HTMLElement Node anchor
	 * @param options Object Class options
	 * @param component Object 
	 * @param componentType String
	 */
	initialize: function($super, element, options, component, componentType){		
		element = $(element);
		element.addClassName('ajxp_draggable');
        if(!options.zindex) options.zindex = 900;
		$super(element, options);
		this.options.reverteffect =  function(element, top_offset, left_offset) {
			new Effect.Move(element, { x: -left_offset, y: -top_offset, duration: 0,
			queue: {scope:'_draggable', position:'end'}
			});
		};
		this.options.delay = (Prototype.Browser.IE?350:200);
		this.component = component;
        window.AllAjxpDraggables.push(this);
	},
	
	/**
	 * Destroy the Draggable object, unregisters it.
	 */
	destroy : function(){
	    Event.stopObserving(this.handle, "mousedown", this.eventMouseDown);
	    this.element.removeClassName('ajxp_draggable');
	    this.element = null;
	    Draggables.unregister(this);
	},
	
	/**
	 * Called at the start of dragging
	 * @param event Event Drag event
	 */
	initDrag: function(event) {
		if(!Object.isUndefined(Draggable._dragging[this.element]) &&
		Draggable._dragging[this.element]) return;
		if(Event.isLeftClick(event)) {
			// abort on form elements, fixes a Firefox issue
			var src = Event.element(event);
            var tag_name;
			if((tag_name = src.tagName.toUpperCase()) && (
			tag_name=='INPUT' ||
			tag_name=='SELECT' ||
			tag_name=='OPTION' ||
			tag_name=='BUTTON' ||
			tag_name=='TEXTAREA')) return;

			var pointer = [Event.pointerX(event), Event.pointerY(event)];
			var pos     = Position.cumulativeOffset(this.element);
			// CORRECT OFFSET HERE AS OUR DRAGGED ELEMENT IS NOT
			// NECESSARY THE ORIGINAL ELEMENT CLONE.
			this.offset = [0,1].map( function(i) { return Math.min(15, (pointer[i] - pos[i])); });
			
			Draggables.activate(this);
			Event.stop(event);
		}
	},

	/**
	 * @param event Event
	 */
    startDrag : function(event){
	    if(!this.delta)
	    	this.delta = this.currentDelta();
	    
		this.dragging = true;
		this._draggingMultiple = false;
		
		if(this.options.zindex) {
			this.originalZ = parseInt(Element.getStyle(this.element,'z-index') || 0);
			this.element.setStyle({zIndex:this.options.zindex});
		}

		if(this.options.ghosting) {
			var selection = ajaxplorer.getUserSelection();
            if(this.component.findSelectableParent){
                if(selection.isEmpty()){
                    this.component.findSelectableParent(this.element, true);
                }else{
                    var selItems = this.component.getSelectedItems();
                    var item = this.component.findSelectableParent(this.element, false);
                    if(!$A(selItems).include(item)){
                        this.component.setSelectedNodes([]);
                        this.component.findSelectableParent(this.element, true);
                    }
                }
            }
			var nodes = selection.getSelectedNodes();
			
			this._draggingMultiple = true;
			this._clone = new Element('div');
			$(this._clone).addClassName("ajxp_draggable");
			$(this._clone).addClassName('multiple_selection_draggable');
			this._clone.setAttribute('user_selection', 'true');
			$(ajxpBootstrap.parameters.get("MAIN_ELEMENT")).insert(this._clone);
			this.original = this.element;
			this.element = this._clone;			
			var max = Math.min(nodes.length,5);
			var maxWidth = 0;
			for(var i=0;i<max;i++){
				var text = nodes[i].getLabel() + (i<max-1?",<br>":"");
				maxWidth = Math.max(maxWidth, testStringWidth(text));
				this._clone.insert(text);
			}
			if(max < nodes.length){
				this._clone.insert(',<br> ' + (nodes.length-max) + ' '+MessageHash[334]+'...');
			}
			this.element.setStyle({height:'auto', width:maxWidth + 'px'});			
			Position.absolutize(this._clone);
			var zIndex = 10000;
			if(this.element.getStyle('zIndex')){
				zIndex = this.element.getStyle('zIndex') + 100;
			}
			this.element.setStyle({zIndex:zIndex});
		}
	
		if(this.options.scroll) {
			if (this.options.scroll == window) {
				var where = this._getWindowScroll(this.options.scroll);
				this.originalScrollLeft = where.left;
				this.originalScrollTop = where.top;
			} else {
				this.originalScrollLeft = this.options.scroll.scrollLeft;
				this.originalScrollTop = this.options.scroll.scrollTop;
			}
		}
		
		Draggables.notify('onStart', this, event);
	
		if(this.options.starteffect){
			this.options.starteffect(this.element);
		}
		this.dndAction = ajaxplorer.getActionBar().getDefaultAction('dragndrop');
		this.ctrlDndAction = ajaxplorer.getActionBar().getDefaultAction('ctrldragndrop');			
    },


    updateDrag: function(event, pointer) {
      if(!this.dragging) this.startDrag(event);
      if(!this.options.quiet){
        Position.prepare();
        Position.includeScrollOffsets = true;
        Droppables.show(pointer, this.element);
      }

      Draggables.notify('onDrag', this, event);

      this.draw(pointer);
      if(this.options.change) this.options.change(this);

      if(this.options.scroll) {
        this.stopScrolling();

        var p;
        if (this.options.scroll == window) {
          with(this._getWindowScroll(this.options.scroll)) { p = [ left, top, left+width, top+height ]; }
        } else {
          p = Position.page(this.options.scroll);
          p[0] += /*this.options.scroll.scrollLeft + */Position.deltaX;
          p[1] += /*this.options.scroll.scrollTop + */ Position.deltaY;
          p[2] = p[0]+this.options.scroll.offsetWidth;
          p[3] = p[1]+this.options.scroll.offsetHeight;
        }
        var speed = [0,0];
        if(pointer[0] < (p[0]+this.options.scrollSensitivity)) speed[0] = pointer[0]-(p[0]+this.options.scrollSensitivity);
        if(pointer[1] < (p[1]+this.options.scrollSensitivity)) speed[1] = pointer[1]-(p[1]+this.options.scrollSensitivity);
        if(pointer[0] > (p[2]-this.options.scrollSensitivity)) speed[0] = pointer[0]-(p[2]-this.options.scrollSensitivity);
        if(pointer[1] > (p[3]-this.options.scrollSensitivity)) speed[1] = pointer[1]-(p[3]-this.options.scrollSensitivity);
        this.startScrolling(speed);
      }

      // fix AppleWebKit rendering
      if(Prototype.Browser.WebKit) window.scrollBy(0,0);

      Event.stop(event);
    },


    draw: function(point) {
        var pos = Position.cumulativeOffset(this.element);
        if(this.options.ghosting) {
          var r   = Position.realOffset(this.element);
          pos[0] += r[0] - Position.deltaX; pos[1] += r[1] - Position.deltaY;
        }
        
        var d = this.currentDelta();
        pos[0] -= d[0]; pos[1] -= d[1];
        
        if(this.options.scroll && (this.options.scroll != window && this._isScrollChild)) {
          pos[0] -= this.options.scroll.scrollLeft-this.originalScrollLeft;
          pos[1] -= this.options.scroll.scrollTop-this.originalScrollTop;
        }
        if(this.options.containerScroll && (this.options.containerScroll != window)) {        	
            pos[0] += this.options.containerScroll.scrollLeft;
            pos[1] += this.options.containerScroll.scrollTop;
          }
        
        var p = [0,1].map(function(i){ 
          return (point[i]-pos[i]-this.offset[i]);
        }.bind(this));
        
        if(this.options.snap) {
          if(Object.isFunction(this.options.snap)) {
            p = this.options.snap(p[0],p[1],this);
          } else {
          if(Object.isArray(this.options.snap)) {
            p = p.map( function(v, i) {
              return (v/this.options.snap[i]).round()*this.options.snap[i]; }.bind(this));
          } else {
            p = p.map( function(v) {
              return (v/this.options.snap).round()*this.options.snap; }.bind(this));
          }
        }}
        
        var style = this.element.style;
        if((!this.options.constraint) || (this.options.constraint=='horizontal'))
          style.left = p[0] + "px";
        if((!this.options.constraint) || (this.options.constraint=='vertical'))
          style.top  = p[1] + "px";
        
        if(style.visibility=="hidden") style.visibility = ""; // fix gecko rendering
      },    

    /**
     * End of drag
     * @param event Event
     * @param success Boolean
     */
	finishDrag : function(event, success) {
		this.dragging = false;
	
		if(this.options.quiet){
			Position.prepare();
			var pointer = [Event.pointerX(event), Event.pointerY(event)];
			Droppables.show(pointer, this.element);
		}
	
		var dropped = false;
		if(success) {
			dropped = Droppables.fire(event, this.element);
			if (!dropped) dropped = false;
		}
		if(dropped && this.options.onDropped) this.options.onDropped(this.element);
		Draggables.notify('onEnd', this, event);
	
		var revert = this.options.revert;
		if(revert && typeof revert == 'function') revert = revert(this.element);
	
		var d = this.currentDelta();	
		if(revert && this.options.reverteffect) {
			if (dropped == 0 || revert != 'failure'){
				if(!this._draggingMultiple){
					this.options.reverteffect(this.element,	d[1]-this.delta[1], d[0]-this.delta[0]);
				}
			}
		} else {
			this.delta = d;
		}
	
		if(this.options.zindex){
			this.element.setStyle({zIndex:this.originalZ});
		}
	
		if(this.options.endeffect){
				this.options.endeffect(this.element);
		}
		
		if(this._draggingMultiple){
			var selectDiv = this.element;
			this.element = this.original;
			Element.remove(selectDiv);
		}
	
		Draggables.deactivate(this);
		Droppables.reset();
	}, 

    scroll: function() {
      var current = new Date();
      var delta = current - this.lastScrolled;
      this.lastScrolled = current;
      if(this.options.scroll == window) {
        with (this._getWindowScroll(this.options.scroll)) {
          if (this.scrollSpeed[0] || this.scrollSpeed[1]) {
            var d = delta / 1000;
            this.options.scroll.scrollTo( left + d*this.scrollSpeed[0], top + d*this.scrollSpeed[1] );
          }
        }
      } else {
          if(this.options.scroll.scrollerInstance){
              var pos = this.options.scroll.scrollTop + this.scrollSpeed[1] * delta / 1000;
              this.options.scroll.scrollerInstance.scrollTo(pos);
          }else{
              this.options.scroll.scrollLeft += this.scrollSpeed[0] * delta / 1000;
              this.options.scroll.scrollTop  += this.scrollSpeed[1] * delta / 1000;
          }
      }

      Position.prepare();
      Position.includeScrollOffsets = true;
      Droppables.show(Draggables._lastPointer, this.element);
      Draggables.notify('onDrag', this);
      if (this._isScrollChild) {
        Draggables._lastScrollPointer = Draggables._lastScrollPointer || $A(Draggables._lastPointer);
        Draggables._lastScrollPointer[0] += this.scrollSpeed[0] * delta / 1000;
        Draggables._lastScrollPointer[1] += this.scrollSpeed[1] * delta / 1000;
        if (Draggables._lastScrollPointer[0] < 0)
          Draggables._lastScrollPointer[0] = 0;
        if (Draggables._lastScrollPointer[1] < 0)
          Draggables._lastScrollPointer[1] = 0;
        this.draw(Draggables._lastScrollPointer);
      }

      if(this.options.change) this.options.change(this);
    },


	/**
	 * Triggered when the ctrlkey is pressed or released
	 * @param event Event
	 */
	updateCtrlKey: function(event)
	{
		if(!event) return;
		var ctrl = event['ctrlKey'];	
		if(this.ctrlDndAction && (ctrl || (this.dndAction.deny))){
			this.addCopyClass();
		}else{
			this.removeCopyClass();
		}
	},

	/**
	 * Updates the element class with "selection_ctrl_key"
	 */
	addCopyClass : function()
	{
		$(this.element).addClassName('selection_ctrl_key');
	},
	
	/**
	 * Updates the element class with "selection_ctrl_key"
	 */
	removeCopyClass : function()
	{
		$(this.element).removeClassName('selection_ctrl_key');
	}
			
});

var AjxpDroppables = {

	options : 
	{
		hoverclass:'droppableZone', 
		accept:'ajxp_draggable',		
		onDrop:function(draggable, droppable, event)
				{
					if(!(draggable.ajxpNode || draggable.getAttribute('user_selection')) || !droppable.ajxpNode) return;
					var targetName = droppable.ajxpNode.getPath();
					var srcName;
					if(draggable.ajxpNode){
						srcName = draggable.ajxpNode.getPath();
					}
                    if(window.WebFXtimer) clearTimeout(window.WebFXtimer);
                    var nodeId = null;
                    if(droppable.id && webFXTreeHandler.all[droppable.id]){
                        nodeId = droppable.id;
                    }
                    var copy = draggable.hasClassName("selection_ctrl_key");
                    if(droppable.applyDragMove){
                        if(!srcName && draggable.getAttribute('user_selection')){
                            srcName = 'ajxp-user-selection';
                        }
                        droppable.applyDragMove(srcName, targetName, nodeId, copy);
                    }else{
                        ajaxplorer.actionBar.applyDragMove(srcName, targetName, nodeId, copy);
                    }
				},
		onHover:function(draggable, droppable, event)
				{
					if(window.WebFXtimer) clearTimeout(window.WebFXtimer);
					if(droppable.id && webFXTreeHandler.all[droppable.id])
					{
                        var container = droppable.up('div.show_first_level');
                        if(container) {
                            container.removeClassName("show_first_level");
                            container.addClassName("reset_show_first_level");
                        }
						window.WebFXtimer = window.setTimeout(function(){
							var node = webFXTreeHandler.all[droppable.id];
							if(node &&  node.folder && !node.open) node.expand();
						}, 500);
					}
				}, 
		onOut:function(droppable)
				{
					if(window.WebFXtimer) clearTimeout(window.WebFXtimer);
                    var container = droppable.up('div.reset_show_first_level');
                    if(container) container.addClassName("show_first_level");
				}
	},

	add: function(element, ajxpNode){
        if(ajxpNode && ajxpNode.hasMetadataInBranch("ajxp_readonly", "true")){
            return;
        }
		Droppables.add(element, this.options);
		AllAjxpDroppables.push($(element));

        if(AjxpDroppables.dragOverHook){
            $(element).select("*").invoke("observe", "dragover", AjxpDroppables.dragOverHook, true);
            $(element).select("*").invoke("observe", "drop", AjxpDroppables.dropHook, true);
            $(element).select("*").invoke("observe", "dragenter", AjxpDroppables.dragEnterHook, true);
            $(element).select("*").invoke("observe", "dragleave", AjxpDroppables.dragLeaveHook, true);
        }

    }
};