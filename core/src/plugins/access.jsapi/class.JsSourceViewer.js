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
	 * @param $super Parent class reference
	 * @param htmlElement HTMLElement
	 */
	initialize: function($super, htmlElement){
		$super(htmlElement);
		attachMobileScroll(htmlElement, "vertical");
		disableTextSelection(htmlElement);
		this.setContent(' // SELECT A CLASS TO DISPLAY ITS SOURCE CODE');	
		document.observe("ajaxplorer:actions_refreshed", this.update.bind(this) );
		document.observe("ajaxplorer:selection_changed", this.update.bind(this) );
		document.observe("ajaxplorer:user_logged", this.clearPanels.bind(this) );
		
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
		if(contextNode.isLeaf()){
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
			this.pendingPointer = getBaseName(objectNode.getPath());
			this.pendingPointerType = "ObjectName";
		}
		if(objectNode.getMetadata().get("API_CLASS") || objectNode.getMetadata().get("API_INTERFACE")){
			if(objectNode.getMetadata().get("API_SOURCE")){
				this.setContent(objectNode.getMetadata().get("API_SOURCE"));
			}else{				
				if(this.loading) return;
				this.setContent('Loading '+ objectNode.getPath() + (currentPointer?'#'+currentPointer:'') + '...');
				this.loading = true;
				var conn = new Connexion();
				conn.setParameters({
					get_action : 'get_js_source',
					object_type : (objectNode.getMetadata().get("API_CLASS")?'class':'interface'),
					object_name : getBaseName(objectNode.getPath())
				});
				conn.onComplete = function(transport){
					objectNode.getMetadata().set("API_SOURCE", transport.responseText);
					this.setContent(transport.responseText);
					this.loading = false;
				}.bind(this);
				conn.onError = function(){
					this.loading = false;
				}
				conn.sendAsync();
			}
		}
		
	},
	
	setContent : function(sHtml){
		if(sHtml == '') return;
		if(!this.htmlElement) return;
		this.parseJavadocs(sHtml);
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
	
	parseJavadocs : function(content){
		var reg = new RegExp(/\/\*\*(([^þ*]|\*(?!\/))*)\*\/([\n\r\s\w]*|[\n\r\s]Class|[\n\r\s]Interface)/gi);
		var keywords = $A(["param", "return"]);
		var res = reg.exec(content);
		var docs = {};
		while(res != null){
			var comment = res[1];
			var key = res[3].strip();
			var parsedDoc = {main : '', keywords:{}};
			$A(comment.split("@")).each(function(el){
				el = el.strip(el);
				el = el.replace("* ", "");
				var isKW = false;
				keywords.each(function(kw){
					if(el.indexOf(kw+" ") === 0){
						if(kw == "param"){
							if(!parsedDoc.keywords[kw]) parsedDoc.keywords[kw] = {};
							var kwCont = el.substring(kw.length+1);
							var paramName = kwCont.split(" ")[0];
							parsedDoc.keywords[kw][paramName] = kwCont.substring(paramName.length+1);													
						}else if(kw == "return"){
							parsedDoc.keywords[kw] = el.substring(kw.length+1);
						}
						isKW = true;
					}
				});
				if(!isKW){
					parsedDoc.main += el;
				}
			});
			docs[key] = parsedDoc;
			//docs.key = res[1];
			res = reg.exec(content);
		}
		//docs.initialize.split("@").invoke("strip")
		//console.log(docs);
		//window.jdocs = docs;
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
