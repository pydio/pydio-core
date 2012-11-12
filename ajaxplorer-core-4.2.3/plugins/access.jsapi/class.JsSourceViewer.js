/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 * Description : A dynamic panel displaying details on the current selection. Works with Templates.
 */
Class.create("JsSourceViewer", AjxpPane, {

	/**
	 * Code Mirror Options
	 */
	cmOptions : {
		path:'plugins/editor.codemirror/CodeMirror/js/',
		parserfile:["tokenizejavascript.js", "parsejavascript.js"],
		stylesheet:"plugins/editor.codemirror/CodeMirror/css/jscolors.css",
		parserConfig:{},
		indentUnit : 4,
		textWrapping : false,
		lineNumbers : true,
		readOnly : true
	},
	
	/**
	 * Standard constructor
	 * @param $super klass Parent class reference
	 * @param htmlElement HTMLElement
	 */
	initialize: function($super, htmlElement){
		$super(htmlElement);
		attachMobileScroll(htmlElement, "vertical");
		disableTextSelection(htmlElement);
		this.setContent(' // SELECT A CLASS TO DISPLAY ITS SOURCE CODE');
		this.obs1 = this.update.bind(this);
		this.obs2 = this.clearPanels.bind(this);
		document.observe("ajaxplorer:actions_refreshed", this.obs1 );
		document.observe("ajaxplorer:selection_changed", this.obs1 );
		document.observe("ajaxplorer:user_logged", this.obs2 );
		
	},
		
	clearPanels:function(){
	},
	
	/**
	 * Test
	 * @return nothing
	 */
	empty : function(){
		this.setContent('');
	},
	
	destroy : function(){
		document.stopObserving("ajaxplorer:actions_refreshed", this.obs1 );
		document.stopObserving("ajaxplorer:selection_changed", this.obs1 );
		document.stopObserving("ajaxplorer:user_logged", this.obs2 );
		this.empty();
		$A(this.htmlElement.childNodes).each(function(el){
			if(el.remove)el.remove();
		});
		this.htmlElement = null;
	},
	
	update : function(){
		if(!this.htmlElement) return;
		var userSelection = ajaxplorer.getUserSelection();
		var contextNode = userSelection.getUniqueNode();
		this.empty();
		if(!contextNode) {
			contextNode = userSelection.getContextNode();
		}
		var path = contextNode.getPath();
		var objectNode = contextNode;
		if(contextNode.isLeaf() && !contextNode.getMetadata().get("API_OBJECT_NODE")){
			var metadata = contextNode.getMetadata();
			if(metadata.get("memberType") == "parent_method"){
				var redirect = '/Classes/' + metadata.get("parentClass");
				ajaxplorer.updateContextData(new AjxpNode(redirect, false, metadata.get("parentClass")));
				return;
			}
			var objectNode = contextNode.getParent();
			var currentPointer = getBaseName(contextNode.getPath());
			this.pendingPointer = currentPointer;
			this.pendingPointerType = "MemberName";
		} else {			
			if(contextNode.getMetadata().get("API_OBJECT_NODE")){
				objectNode = contextNode.getParent();
				this.pendingPointer = getBaseName(contextNode.getPath());
			}else{
				this.pendingPointer = getBaseName(objectNode.getPath());
			}				
			this.pendingPointerType = "ObjectName";
		}
		if(objectNode.getMetadata().get("API_CLASS") || objectNode.getMetadata().get("API_INTERFACE")){
			if(objectNode.getMetadata().get("API_SOURCE")){
				this.setContent(objectNode.getMetadata().get("API_SOURCE"));
			}else{
				this.setContent('Loading source for '+ objectNode.getPath() + (currentPointer?'#'+currentPointer:'') + '...');
				objectNode.observeOnce("api_source_loaded", function(){
					this.setContent(objectNode.getMetadata().get("API_SOURCE"));
				}.bind(this));
			}
		}
		
	},
	
	setContent : function(sHtml){
		if(sHtml == '') return;
		if(!this.htmlElement) return;
		if(!this.codeMirror){
			this.cmOptions.onLoad = function(mirror){
				mirror.setCode(sHtml);
				this.applyPointer();
			}.bind(this);
			this.codeMirror = new CodeMirror(function(iFrame){
				this.htmlElement.insert({bottom:iFrame});
				fitHeightToBottom($(iFrame), $(this.htmlElement));			
			}.bind(this), this.cmOptions);					
		}else{
			this.codeMirror.setCode(sHtml);
			this.applyPointer();
		}
	},
	
	applyPointer : function(){
		if(!this.pendingPointer) return;
		if(this.pendingPointerType == "MemberName"){
			cursor = this.codeMirror.getSearchCursor(this.pendingPointer+':', false, false);
			if(cursor.findNext()){
				cursor.select();
				return;
			}		
			cursor = this.codeMirror.getSearchCursor(this.pendingPointer+' :', false, false);
			if(cursor.findNext()){
				cursor.select();
				return;
			}
		}else{
			cursor = this.codeMirror.getSearchCursor(".create('" + this.pendingPointer+"'", false, false);
			if(cursor.findNext()){
				this.codeMirror.jumpToLine(cursor.position().line);
				return;
			}		
			cursor = this.codeMirror.getSearchCursor('.create("' + this.pendingPointer+'"', false, false);
			if(cursor.findNext()){
				this.codeMirror.jumpToLine(cursor.position().line);
				return;
			}			
		}		
	},
	
	showElement : function(show){
		if(!this.htmlElement) return;
		if(show) this.htmlElement.show();
		else this.htmlElement.hide();
	},
	
	resize : function(){
		fitHeightToBottom(this.htmlElement, null);
		fitHeightToBottom(this.codeMirror.wrapping, $(this.htmlElement));
	}
	
});
