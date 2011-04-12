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
