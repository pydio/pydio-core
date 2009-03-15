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
Event.observe(window, "load", function(){
	Draggables.addObserver(timerClearObserver);
	Draggables.addObserver({onDrag:function(eventName,element,event){
		if(element.updateCtrlKey){
			element.updateCtrlKey(event);
		}
	}});	
});
Event.observe(window, "unload", function(){
	Draggables.removeObserver(timerClearObserver);
	if(ajaxplorer){
		ajaxplorer.filesList.allDraggables.each(function(el){
			el.destroy();
		});
		ajaxplorer.filesList.allDroppables.each(function(el){
			Droppables.remove(el);
		});
	}
});


var AjxpDraggable = Class.create(Draggable, {
	
	initialize: function($super, element, options){		
		$(element).addClassName('ajxp_draggable');
		$super(element, options);
		this.options.reverteffect =  function(element, top_offset, left_offset) {
			new Effect.Move(element, { x: -left_offset, y: -top_offset, duration: 0,
			queue: {scope:'_draggable', position:'end'}
			});
		};
		
	},
	
	destroy : function(){
	    Event.stopObserving(this.handle, "mousedown", this.eventMouseDown);
	    this.element.removeClassName('ajxp_draggable');
	    this.element = null;
	    Draggables.unregister(this);
	},
	
    startDrag : function(event){
	
	    if(!this.delta)
	    this.delta = this.currentDelta();
	
		this.dragging = true;
		this._draggingMultiple = false;
		
		if(this.options.zindex) {
			this.originalZ = parseInt(Element.getStyle(this.element,'z-index') || 0);
			this.element.style.zIndex = this.options.zindex;
		}
	
		if(this.options.ghosting) {
			var selection = ajaxplorer.filesList.getUserSelection();
			//console.log(selection);
			if(selection.isMultiple()){
				// Move all selection
				// Make new div for element, clone all others.			
				this._draggingMultiple = true;
				this._clone = document.createElement('div');
				$(this._clone).addClassName("ajxp_draggable");
				if(ajaxplorer.filesList._displayMode == 'thumb'){
					$(this._clone).addClassName('multiple_thumbnails_draggable');
				}else{
					$(this._clone).addClassName('multiple_selection_draggable');
				}
				this._clone.setAttribute('user_selection', 'true');
				if (Prototype.Browser.IE || Prototype.Browser.Opera){
					$('browser').appendChild(this._clone);
					$(this._clone).setStyle({width:$(this.element).getWidth()+'px'});
				}else{
					this.element.parentNode.appendChild(this._clone);
				}
				this.original = this.element;
				this.element = this._clone;
				var selectedItems = ajaxplorer.filesList.getSelectedItems();			
				for(var i=0; i<selectedItems.length;i++)
				{	
					var objectToClone;
					if(ajaxplorer.filesList._displayMode == 'thumb'){
						 objectToClone = $(selectedItems[i]);
					}
					else {
						objectToClone = $(selectedItems[i]).getElementsBySelector('span.list_selectable_span')[0];
					}
					var newObj = refreshPNGImages(objectToClone.cloneNode(true));				
					this.element.appendChild(newObj);
					if(ajaxplorer.filesList._displayMode == 'thumb'){
						$(newObj).addClassName('simple_selection_draggable');
					}
				}
				Position.absolutize(this.element);
			}else{
				if(selection.isEmpty()){
					ajaxplorer.getFilesList().findSelectableParent(this.element, true);
				}
				this._clone = this.element.cloneNode(true);
				refreshPNGImages(this._clone);
				Position.absolutize(this.element);
				this.element.parentNode.insertBefore(this._clone, this.element);
				$(this.element).addClassName('simple_selection_draggable');
				if(Prototype.Browser.IE || Prototype.Browser.Opera) // MOVE ELEMENT TO $('browser')
				{
					var newclone = this.element.cloneNode(true);
					refreshPNGImages(newclone);
					$('browser').appendChild(newclone);
					$(newclone).setStyle({width:$(this._clone).getWidth()+'px'});
					Element.remove(this.element);
					this.element = newclone;				
				}
			}
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
	
		if(this.options.ghosting && !this._draggingMultiple) {
			this.removeCopyClass();
			if(Prototype.Browser.IE || Prototype.Browser.Opera) // MOVE ELEMENT TO $('browser')
			{
				this._clone.parentNode.insertBefore(this.element, this._clone);
			}
			this.element.removeClassName('simple_selection_draggable');
			Position.relativize(this.element);
			Element.remove(this._clone);
			this._clone = null;
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
			this.element.style.zIndex = this.originalZ;
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
		if(this._draggingMultiple && ajaxplorer.filesList._displayMode == 'thumb')
		{
			$(this.element).select('div.thumbnail_selectable_cell').each(function(el){
				el.addClassName('selection_ctrl_key');
			});
		}else{
			$(this.element).addClassName('selection_ctrl_key');
		}
	},
	
	removeCopyClass : function()
	{
		if(this._draggingMultiple && ajaxplorer.filesList._displayMode == 'thumb')
		{
			$(this.element).select('div.thumbnail_selectable_cell').each(function(el){
				el.removeClassName('selection_ctrl_key');
			});
		}else{
			$(this.element).removeClassName('selection_ctrl_key');
		}
	}
			
});

var AjxpDroppables = {

	options : 
	{
		hoverclass:'droppableZone', 
		accept:'ajxp_draggable',		
		onDrop:function(draggable, droppable, event)
				{
					var targetName = droppable.getAttribute('filename');
					var srcName = draggable.getAttribute('filename');
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
	}	
};
