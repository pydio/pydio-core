/**
 * @package info.ajaxplorer.plugins
 * 
 * Copyright 2007-2009 Charles du Jeu
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
 * 
 * Description : Encapsulation of the Drag'n'drop system.
 */

// TO ADD GLOBALLY
var timerClearObserver = {
	onEnd:function(){
		if(WebFXtimer) clearTimeout(WebFXtimer);
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
var AllAjxpDraggables = $A([]);
var AllAjxpDroppables = $A([]);
Event.observe(window, "unload", function(){
	Draggables.removeObserver(timerClearObserver);
	AllAjxpDraggables.each(function(el){
		el.destroy();
	});
	AllAjxpDroppables.each(function(el){
		Droppables.remove(el);
	});
});


Class.create("AjxpDraggable", Draggable, {
	
	initialize: function($super, element, options, component, componentType){		
		element = $(element);
		element.addClassName('ajxp_draggable');
		$super(element, options);
		this.options.reverteffect =  function(element, top_offset, left_offset) {
			new Effect.Move(element, { x: -left_offset, y: -top_offset, duration: 0,
			queue: {scope:'_draggable', position:'end'}
			});
		};
		this.options.delay = 200;
		this.component = component;
		this.componentType = componentType;
		AllAjxpDraggables.push(this);
	},
	
	destroy : function(){
	    Event.stopObserving(this.handle, "mousedown", this.eventMouseDown);
	    this.element.removeClassName('ajxp_draggable');
	    this.element = null;
	    Draggables.unregister(this);
	},
	
	initDrag: function(event) {
		if(!Object.isUndefined(Draggable._dragging[this.element]) &&
		Draggable._dragging[this.element]) return;
		if(Event.isLeftClick(event)) {
			// abort on form elements, fixes a Firefox issue
			var src = Event.element(event);
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
			this.offset = [0,1].map( function(i) { return Math.min(15, (pointer[i] - pos[i])) });
			
			Draggables.activate(this);
			Event.stop(event);
		}
	},

	
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
			if(selection.isEmpty() && this.component.findSelectableParent){
				this.component.findSelectableParent(this.element, true);
			}
			var nodes = selection.getSelectedNodes();
			
			this._draggingMultiple = true;
			this._clone = new Element('div');
			$(this._clone).addClassName("ajxp_draggable");
			$(this._clone).addClassName('multiple_selection_draggable');
			this._clone.setAttribute('user_selection', 'true');
			$('ajxp_desktop').insert(this._clone);
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

	addCopyClass : function()
	{
		$(this.element).addClassName('selection_ctrl_key');
	},
	
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
						var srcName = draggable.ajxpNode.getPath();
					}
					if(WebFXtimer) clearTimeout(WebFXtimer);
					var nodeId = null;
					if(droppable.id && webFXTreeHandler.all[droppable.id]){
						nodeId = droppable.id;
					}
					ajaxplorer.actionBar.applyDragMove(srcName, targetName, nodeId, event['ctrlKey']);
				},
		onHover:function(draggable, droppable, event)
				{
					if(WebFXtimer) clearTimeout(WebFXtimer);
					if(droppable.id && webFXTreeHandler.all[droppable.id])
					{
						var jsString = "javascript:";			
						WebFXtimer = window.setTimeout(function(){
							var node = webFXTreeHandler.all[droppable.id];
							if(node &&  node.folder && !node.open) node.expand();
						}, 500);
					}
				}, 
		onOut:function(droppable)
				{
					if(WebFXtimer) clearTimeout(WebFXtimer);					
				}
	},

	add: function(element){
		Droppables.add(element, this.options);
		AllAjxpDroppables.push($(element));
	}	
};