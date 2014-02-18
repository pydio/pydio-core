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
Class.create("CodeMirrorEditor", AbstractEditor, {

	initialize: function($super, oFormObject, options)
	{
		$super(oFormObject, options);
		
		this.textWrapping = false;
		this.lineNumbers = true;
		this.indentSize = 2;		
		
		if(!ajaxplorer.user || ajaxplorer.user.canWrite()){
			this.canWrite = true;
			this.actions.get("saveButton").observe('click', function(){
				this.saveFile();
				return false;
			}.bind(this));		
		}else{
			this.canWrite = false;
			this.actions.get("saveButton").hide();
		}

		this.actions.get("toggleLinesButton").observe('click', function(){
			if(this.codeMirror){
				this.lineNumbers = !this.codeMirror.lineNumbers;
				this.codeMirror.setLineNumbers(this.lineNumbers);
			}
			return false;
		}.bind(this));		
		
		this.actions.get("toggleWrapButton").observe('click', function(){
			if(this.codeMirror){
				this.textWrapping = !this.codeMirror.options.textWrapping;
				this.codeMirror.setTextWrapping(this.textWrapping);
			}
			return false;
		}.bind(this));		
		
		this.actions.get("undoButton").observe('click', function(){
			if(this.codeMirror){
				this.codeMirror.undo();
			}
			return false;
		}.bind(this));		
		
		this.actions.get("redoButton").observe('click', function(){
			if(this.codeMirror){
				this.codeMirror.redo();
			}
			return false;
		}.bind(this));		

        var gotoLine = this.element.down('#goto_line');
		gotoLine.observe('keypress', function(event){
			if(event.keyCode == Event.KEY_RETURN && this.codeMirror){
				this.codeMirror.jumpToLine(parseInt(event.target.value));
			}			
		}.bind(this) );

        var textSearch = this.element.down('#text_search');

		textSearch.observe('keypress', function(event){
			if(event.keyCode == Event.KEY_RETURN && this.codeMirror){
				var cursor;
				if(this.currentSearch && this.currentSearch == event.target.value && this.currentCursor){
					cursor = this.currentCursor;
				}else{
					cursor = this.codeMirror.getSearchCursor(event.target.value, false, false);
				}
				if(cursor.findNext()){
					cursor.select();
					this.currentSearch = event.target.value;
					this.currentCursor = cursor;
				}else{
					this.currentSearch = "";
					this.currentCursor = null;
				}
			}			
		}.bind(this) );

        textSearch.observe("focus", function(){ajaxplorer.disableAllKeyBindings()});
        textSearch.observe("blur", function(){ajaxplorer.enableAllKeyBindings()});
        gotoLine.observe("focus", function(){ajaxplorer.disableAllKeyBindings()});
        gotoLine.observe("blur", function(){ajaxplorer.enableAllKeyBindings()});

		// Remove python rule, if any
		$$('link[href="plugins/editor.codemirror/css/linenumbers-py.css"]').invoke('remove');
		
	},
	
	
	open : function($super, nodeOrNodes){
		$super(nodeOrNodes);
		var fileName = nodeOrNodes.getPath();
		
		var path = 'plugins/editor.codemirror/CodeMirror/';
        if(window.ajxpBootstrap.parameters.get("SERVER_PREFIX_URI")){
            path = window.ajxpBootstrap.parameters.get("SERVER_PREFIX_URI")+"plugins/editor.codemirror/CodeMirror/";
        }else if($$('base').length){
            path = $$('base')[0].readAttribute('href')+"plugins/editor.codemirror/CodeMirror/";
        }
		var extension = getFileExtension(fileName);
		var parserFile; var styleSheet;
		var parserConfig = {};
		switch(extension){
			case 'js':
			case 'json':
				parserFile = ["tokenizejavascript.js", "parsejavascript.js"];
				styleSheet = path+"css/jscolors.css";
				if(extension == 'json') parserConfig.json = true;
			break;
			case 'xml':
				parserFile = "parsexml.js";
				styleSheet = path+"css/xmlcolors.css";
			break;
			case 'css':
				parserFile = "parsecss.js";
				styleSheet = path+"css/csscolors.css";
			break;
			case 'html':
				parserFile = ["parsexml.js", "parsecss.js", "tokenizejavascript.js", "parsejavascript.js", "parsehtmlmixed.js"];
				styleSheet =[path+"css/xmlcolors.css", path+"css/jscolors.css", path+"css/csscolors.css"];
			break;
			case 'sparql':
				parserFile = "parsesparql.js";
				styleSheet = path+"css/sparqlcolors.css";
			break;
			case 'php':
			case 'phtml':
				parserFile = ["parsexml.js", "parsecss.js", "tokenizejavascript.js", "parsejavascript.js", "../contrib/php/js/tokenizephp.js", "../contrib/php/js/parsephp.js", "../contrib/php/js/parsephphtmlmixed.js"];
				styleSheet =[path+"css/xmlcolors.css", path+"css/jscolors.css", path+"css/csscolors.css", path+"/contrib/php/css/phpcolors.css"];				
			break;
			case 'py':
				parserFile = "../contrib/python/js/parsepython.js";
				styleSheet = path+"contrib/python/css/pythoncolors.css";
				ResourcesManager.prototype.loadCSSResource('plugins/editor.codemirror/css/linenumbers-py.css');
			break;
			case 'lua':
				parserFile = "../contrib/lua/js/parselua.js";
				styleSheet = path+"contrib/python/css/luacolors.css";
			break;
			case 'c#':
			    parserFile =  ["../contrib/csharp/js/tokenizecsharp.js", "../contrib/csharp/js/parsecsharp.js"];
			    styleSheet =  path+"contrib/csharp/css/csharpcolors.css";
			break;
			case 'java':
			case 'jsp':
			    parserFile =  ["../contrib/java/js/tokenizejava.js","../contrib/java/js/parsejava.js"];
			    styleSheet =  path+"contrib/java/css/javacolors.css";
			break;
			case 'sql':
			    parserFile =  "../contrib/sql/js/parsesql.js";
			    styleSheet =  path+"contrib/sql/css/sqlcolors.css";
			break;
			case 'xquery':
			    parserFile =  ["../contrib/xquery/js/tokenizexquery.js","../contrib/xquery/js/parsexquery.js"];
			    styleSheet =  path+"contrib/xquery/css/xquerycolors.css";
			break;
			default:
				parserFile = "parsedummy.js";
				styleSheet = path + '../css/dummycolors.css';
			break;
		}
		this.options = 	{
			path:path + 'js/',
			parserfile:parserFile,
			stylesheet:styleSheet,
			parserConfig:parserConfig,
			onChange : function(){ 				
				this.updateHistoryButtons();
				var sizes = this.codeMirror.historySize();
				if(sizes.undo){
					this.setModified(true);
				}else{
					this.setModified(false);
				}
			}.bind(this)
		};
		
        if(window.ajxpMobile){
              this.setFullScreen();
              window.setTimeout(this.setFullScreen.bind(this), 500);
        }

		this.initCodeMirror(false, function(){
			this.loadFileContent(fileName);
		}.bind(this));
		
		this.element.observe("editor:enterFS", function(e){
			this.currentCode = this.codeMirror.getCode();
            this.goingToFullScreen = true;
			this.destroyCodeMirror();
		}.bind(this) );

		this.element.observe("editor:enterFSend", function(e){
			this.initCodeMirror(true);
			this.codeMirror.setLineNumbers(this.codeMirror.lineNumbers);
		}.bind(this) );

		this.element.observe("editor:exitFS", function(e){
			this.currentCode = this.codeMirror.getCode();
			this.destroyCodeMirror();
		}.bind(this) );

		this.element.observe("editor:exitFSend", function(e){
			this.initCodeMirror();
			this.codeMirror.setLineNumbers(this.codeMirror.lineNumbers);
            this.goingToFullScreen = false;
		}.bind(this) );

		this.updateHistoryButtons();
		

        this.element.observe("editor:resize", function(event){
            if(this.goingToFullScreen) return;
            if(ajaxplorer._editorOpener){
                fitHeightToBottom($(this.element));
                fitHeightToBottom($(this.contentMainContainer), $(this.element));
                fitHeightToBottom(this.codeMirror.wrapping, this.contentMainContainer);
            }else{
                fitHeightToBottom($(this.contentMainContainer), $(modal.elementName));
                fitHeightToBottom($(this.element), $(modal.elementName));
                fitHeightToBottom(this.codeMirror.wrapping);
            }
        }.bind(this));

    },
	
	updateHistoryButtons: function(){
		var sizes = $H({undo:0,redo:0});
		if(this.codeMirror){
			try{
				sizes = $H(this.codeMirror.historySize());
			}catch(e){}
		}
		var actions = this.actions;
		sizes.each(function(pair){
			actions.get(pair.key+"Button")[(pair.value?'removeClassName':'addClassName')]('disabled');
		});
	},
	
	initCodeMirror : function(fsMode, onLoad){

		this.options.indentUnit = this.indentSize;
		this.options.textWrapping = this.textWrapping;
		this.options.lineNumbers = this.lineNumbers;
        if(window.ajxpBootstrap.parameters.get("SERVER_PREFIX_URI")){
            this.options.path = window.ajxpBootstrap.parameters.get("SERVER_PREFIX_URI")+"plugins/editor.codemirror/CodeMirror/js/";
        }

		this.options.onLoad = onLoad? onLoad : function(mirror){
			if(this.currentCode){
				var mod = this.isModified;
				mirror.setCode(this.currentCode);
				if(!mod){
					this.setModified(false);
				}
			}
		}.bind(this);		
		
		this.codeMirror = new CodeMirror(function(iFrame){
				this.contentMainContainer = iFrame;
				this.element.insert({bottom:iFrame});
				if(fsMode){
					fitHeightToBottom($(this.contentMainContainer));
				}else{
                    if(ajaxplorer._editorOpener){
                        fitHeightToBottom($(this.contentMainContainer), $(this.element));
                    }else{
                        fitHeightToBottom($(this.contentMainContainer), $(modal.elementName));
                        fitHeightToBottom($(this.element), $(modal.elementName));
                    }
				}
			}.bind(this), this.options);			
	},
		
	destroyCodeMirror : function(){
		if(this.contentMainContainer){
			this.contentMainContainer.remove();
		}
	},
	
	loadFileContent : function(fileName){
		
		this.currentFile = fileName;
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'get_content');
		connexion.addParameter('file', fileName);	
		connexion.onComplete = function(transp){
			this.parseTxt(transp);
			this.updateTitle(getBaseName(fileName));
		}.bind(this);
		this.setModified(false);
		this.setOnLoad(this.contentMainContainer);
		connexion.sendAsync();
	},
	
	prepareSaveConnexion : function(){
		var connexion = new Connexion();
		connexion.addParameter('get_action', 'put_content');
        connexion.addParameter('file', this.inputNode.getPath());
        connexion.onComplete = function(transp){
			this.parseXml(transp);
            ajaxplorer.fireNodeRefresh(this.inputNode);
		}.bind(this);
		this.setOnLoad(this.contentMainContainer);
		connexion.setMethod('put');		
		return connexion;
	},
	
	saveFile : function(){
		var connexion = this.prepareSaveConnexion();
		connexion.addParameter('content', this.codeMirror.getCode());		
		connexion.sendAsync();
	},
	
	parseXml : function(transport){
		if(parseInt(transport.responseText).toString() == transport.responseText){
			alert("Cannot write the file to disk (Error code : "+transport.responseText+")");
		}else{
			this.setModified(false);
		}
		this.removeOnLoad(this.contentMainContainer);
	},
	
	parseTxt : function(transport){	
		this.codeMirror.setCode(transport.responseText);
		this.setModified(false);
		this.codeMirror.clearHistory();
		this.updateHistoryButtons();
		this.removeOnLoad(this.contentMainContainer);
		
	}
});