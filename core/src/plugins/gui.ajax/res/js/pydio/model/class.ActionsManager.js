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

/**
 * Singleton class that manages all actions. Can be called directly using ajaxplorer.actionBar.
 */
Class.create("ActionsManager", {

    /**
     * Standard constructor
     * @param bUsersEnabled Boolen Whether users management is enabled or not
     * @param dataModelElementId
     */
	initialize: function(bUsersEnabled, dataModelElementId)
	{
		this._registeredKeys = new Hash();
		this.usersEnabled = bUsersEnabled;
		
		this.bgManager = new BackgroundManager(this);		
		this.subMenus = [];				
		this.actions = new Hash();
		this.defaultActions = new Hash();
		this.toolbars = new Hash();

        this.contextChangedObs = function(event){
            window.setTimeout(function(){
                this.fireContextChange();
            }.bind(this), 0);
        }.bind(this);
        this.selectionChangedObs = function(event){
            window.setTimeout(function(){
                this.fireSelectionChange();
            }.bind(this), 0);
        }.bind(this);

        if(dataModelElementId){
            this.localDataModel = true;
            try{
                this._dataModel = $(dataModelElementId).ajxpPaneObject.getDataModel();
            }catch(e){}
            if(this._dataModel) {
                this._connectDataModel();
            }else{
                document.observeOnce("ajaxplorer:datamodel-loaded-" + dataModelElementId, function(){
                    this._dataModel = $(dataModelElementId).ajxpPaneObject.getDataModel();
                    this._connectDataModel();
                }.bind(this));
            }
        }else{
            this.localDataModel = false;
            this._connectDataModel();
        }

        /*
        if(this._dataModel){
            this._dataModel.observe("context_changed", this.contextChangedObs);
            this._dataModel.observe("selection_changed", this.selectionChangedObs);
            this.localDataModel = true;
        }else{
            document.observe("ajaxplorer:context_changed", this.contextChangedObs);
            document.observe("ajaxplorer:selection_changed", this.selectionChangedObs);
            this._dataModel = ajaxplorer.getContextHolder();
            this.localDataModel = false;
        }
        */

        if(this.usersEnabled){
            document.observe("ajaxplorer:user_logged", function(event){
                if(event.memo && event.memo.getPreference){
                    this.setUser(event.memo);
                }else{
                    this.setUser(null);
                }
            }.bind(this));
            if(ajaxplorer.user) {
                this.setUser(ajaxplorer.user);
            }
        }

	},

    _connectDataModel: function(){
        if(this.localDataModel){
            this._dataModel.observe("context_changed", this.contextChangedObs);
            this._dataModel.observe("selection_changed", this.selectionChangedObs);
            this.loadActionsFromRegistry();
            document.observe("ajaxplorer:registry_loaded", function(event){
                this.loadActionsFromRegistry(event.memo);
            }.bind(this));
        }else{
            document.observe("ajaxplorer:context_changed", this.contextChangedObs);
            document.observe("ajaxplorer:selection_changed", this.selectionChangedObs);
            this._dataModel = ajaxplorer.getContextHolder();
        }
    },

    getDataModel:function(){
        return this._dataModel;
    },

    destroy: function(){
        if(this.localDataModel && this._dataModel){
            this._dataModel.stopObserving("context_changed", this.contextChangedObs);
            this._dataModel.stopObserving("selection_changed", this.selectionChangedObs);
        }
    },

	/**
	 * Stores the currently logged user object
	 * @param oUser User User instance
	 */
	setUser: function(oUser)
	{	
		this.oUser = oUser;
		if(oUser != null && ajaxplorer  && oUser.id != 'guest' && oUser.getPreference('lang') != null 
			&& oUser.getPreference('lang') != ""
			&& oUser.getPreference('lang') != ajaxplorer.currentLanguage
            && !oUser.lock
            )
		{
			ajaxplorer.loadI18NMessages(oUser.getPreference('lang'));
		}
	},
			
	/**
	 * Filter the actions given the srcElement passed as arguments. 
	 * @param srcElement String An identifier among selectionContext, genericContext, a webfx object id
     * @param ignoreGroups Array a list of groups to ignore
	 * @returns Array
	 */
	getContextActions: function(srcElement, ignoreGroups)
	{		
		var actionsSelectorAtt = 'selectionContext';
		if(srcElement.id && (srcElement.hasClassName('table_rows_container') ||  srcElement.hasClassName('selectable_div')))
		{
			actionsSelectorAtt = 'genericContext';
		}
		//else if(srcElement.id.substring(0,5)=='webfx')
		//{
		//	actionsSelectorAtt = 'directoryContext';
		//}
		var contextActions = $A();
		var defaultGroup;
        var contextActionsGroup = {};
		this.actions.each(function(pair){
			var action = pair.value;
			if(!action.context.contextMenu) return;
			if(actionsSelectorAtt == 'selectionContext' && !action.context.selection) return;
			if(actionsSelectorAtt == 'directoryContext' && !action.context.dir) return;
			if(actionsSelectorAtt == 'genericContext' && action.context.selection) return;
			if(action.contextHidden || action.deny) return;
            $A(action.context.actionBarGroup.split(',')).each(function(barGroup){
                if(!contextActionsGroup[barGroup]){
                    contextActionsGroup[barGroup] = $A();
                }
            });
            var isDefault = false;
			if(actionsSelectorAtt == 'selectionContext'){
				// set default in bold
				var userSelection = this._dataModel;
				if(!userSelection.isEmpty()){
					var defaultAction = 'file';
					if(userSelection.isUnique() && (userSelection.hasDir() || userSelection.hasMime(['ajxp_browsable_archive']))){
						defaultAction = 'dir';
					}
					if(this.defaultActions.get(defaultAction) && action.options.name == this.defaultActions.get(defaultAction)){
						isDefault = true;
					}
				}
			}
            $A(action.context.actionBarGroup.split(',')).each(function(barGroup){
                var menuItem = {
				name:action.getKeyedText(),
				alt:action.options.title,
                action_id:action.options.name,
				image:resolveImageSource(action.options.src, '/images/actions/ICON_SIZE', 16),
				isDefault:isDefault,
				callback:function(e){this.apply();}.bind(action)
                };
                if(action.options.icon_class){
                    menuItem.icon_class = action.options.icon_class;
                }
                if(action.options.subMenu){
                    menuItem.subMenu = [];
                    if(action.subMenuItems.staticOptions){
                        menuItem.subMenu = action.subMenuItems.staticOptions;
                    }
                    if(action.subMenuItems.dynamicBuilder){
                        menuItem.subMenuBeforeShow = action.subMenuItems.dynamicBuilder;
                    }
                }
                //contextActions.push(menuItem);
                contextActionsGroup[barGroup].push(menuItem);
                if(isDefault){
                    defaultGroup = barGroup;
                }
            });
		}.bind(this));
        var first = true;
        contextActionsGroup = $H(contextActionsGroup);
        contextActionsGroup = contextActionsGroup.sortBy(function(p){
            if(defaultGroup && p.key == defaultGroup) return 'aaaa';
            return p.key;
        });
		contextActionsGroup.each(function(pair){
            if(!first){
                contextActions.push({separator:true});
            }
            if(ignoreGroups && ignoreGroups.indexOf(pair.key) != -1){
                return;
            }
            first = false;
            pair.value.each(function(mItem){
                contextActions.push(mItem);
            });
        });
		return contextActions;
	},
	
	/**
	 * DEPRECATED, use getActionsForAjxpWidget instead!
	 * @returns $A()
	 */
	getInfoPanelActions:function(){
		var actions = $A([]);
		this.actions.each(function(pair){
			var action = pair.value;
			if(action.context.infoPanel && !action.deny) actions.push(action);
		});
		return actions;
	},
	
	/**
	 * Generic method to get actions for a given component part.
	 * @param ajxpClassName String 
	 * @param widgetId String
	 * @returns $A()
	 */
	getActionsForAjxpWidget:function(ajxpClassName, widgetId){
		var actions = $A([]);
		this.actions.each(function(pair){
			var action = pair.value;
			if(action.context.ajxpWidgets && (action.context.ajxpWidgets.include(ajxpClassName+'::'+widgetId)||action.context.ajxpWidgets.include(ajxpClassName)) && !action.deny) actions.push(action);
		});
		return actions;		
	},
	
	/**
	 * Finds a default action and fires it.
	 * @param defaultName String ("file", "dir", "dragndrop", "ctrldragndrop")
	 */
	fireDefaultAction: function(defaultName){
		var actionName = this.defaultActions.get(defaultName); 
		if(actionName != null){
			arguments[0] = actionName;
			if(actionName == "ls"){
				var action = this.actions.get(actionName);
				if(action) action.enable(); // Force enable on default action
			}
			this.fireAction.apply(this, arguments);
		}
	},
	
	/**
	 * Fire an action based on its name
	 * @param buttonAction String The name of the action
	 */
	fireAction: function (buttonAction)	{		
		var action = this.actions.get(buttonAction);
		if(action != null) {
			var args = $A(arguments);
			args.shift();
			action.apply(args);
		}
	},
	
	/**
	 * Registers an accesskey for a given action. 
	 * @param key String The access key
	 * @param actionName String The name of the action
	 * @param optionnalCommand String An optionnal argument 
	 * that will be passed to the action when fired.
	 */
	registerKey: function(key, actionName, optionnalCommand){		
		if(optionnalCommand){
			actionName = actionName + "::" + optionnalCommand;
		}
		this._registeredKeys.set(key.toLowerCase(), actionName);
	},
	
	/**
	 * Remove all registered keys.
	 */
	clearRegisteredKeys: function(){
		this._registeredKeys = new Hash();
	},
	/**
	 * Triggers an action by its access key.
	 * @param event Event The key event (will be stopped)
	 * @param keyName String A key name
	 */
	fireActionByKey: function(event, keyName)
	{	
		if(this._registeredKeys.get(keyName) && !ajaxplorer.blockShortcuts)
		{
			if(this._registeredKeys.get(keyName).indexOf("::")!==false){
				var parts = this._registeredKeys.get(keyName).split("::");
				this.fireAction(parts[0], parts[1]);
			}else{
				this.fireAction(this._registeredKeys.get(keyName));
			}
			Event.stop(event);
		}
	},
	
	/**
	 * Complex function called when drag'n'dropping. Basic checks of who is child of who.
	 * @param fileName String The dragged element 
	 * @param destDir String The drop target node path
	 * @param destNodeName String The drop target node name
	 * @param copy Boolean Copy or Move
	 */
	applyDragMove: function(fileName, destDir, destNodeName, copy)
	{
		if((!copy && (!this.defaultActions.get('dragndrop') || this.getDefaultAction('dragndrop').deny)) ||
			(copy && (!this.defaultActions.get('ctrldragndrop')||this.getDefaultAction('ctrldragndrop').deny))){
			return;
		}
        var fileNames;
		if(fileName == null) fileNames = this._dataModel.getFileNames();
		else fileNames = [fileName];
		if(destNodeName != null)
		{
			// Check that dest is not a child of the source
			if(this.checkDestIsChildOfSource(fileNames, destNodeName)){
				ajaxplorer.displayMessage('ERROR', MessageHash[202]);
				return;
			}
		}
        // Check that dest is not the source it self
        for(var i=0; i<fileNames.length;i++)
        {
            if(fileNames[i] == destDir){
                if(destNodeName != null) ajaxplorer.displayMessage('ERROR', MessageHash[202]);
                 return;
            }
        }
        // Check that dest is not the direct parent of source, ie current rep!
        if(destDir == this._dataModel.getContextNode().getPath()){
            if(destNodeName != null) ajaxplorer.displayMessage('ERROR', MessageHash[203]);
            return;
        }
		var connexion = new Connexion();
		if(copy){
			connexion.addParameter('get_action', this.defaultActions.get('ctrldragndrop'));
		}else{
			connexion.addParameter('get_action', this.defaultActions.get('dragndrop'));
		}
        connexion.addParameter('nodes[]', fileNames);
		connexion.addParameter('dest', destDir);
		connexion.addParameter('dir', this._dataModel.getContextNode().getPath());
		connexion.onComplete = function(transport){this.parseXmlMessage(transport.responseXML);}.bind(this);
		connexion.sendAsync();
	},
	
	/**
	 * Get the action defined as default for a given default string
	 * @param defaultName String
	 * @returns Action
	 */
	getDefaultAction : function(defaultName){
		if(this.defaultActions.get(defaultName)){
			return this.actions.get(this.defaultActions.get(defaultName));
		}
		return null;
	},
	
	/**
	 * Detects whether a destination is child of the source 
	 * @param srcNames String|Array One or many sources pathes
	 * @param destNodeName String the destination
	 * @returns Boolean
	 */
	checkDestIsChildOfSource: function(srcNames, destNodeName)
	{
		if(typeof srcNames == "string"){
			srcNames = [srcNames];
		}
		var destNode = webFXTreeHandler.all[destNodeName];
		while(destNode.parentNode){
			for(var i=0; i<srcNames.length;i++){
				if(destNode.filename == srcNames[i]){				
					return true;
				}
			}
			destNode = destNode.parentNode;
		}
		return false;
	},
		
	/**
	 * Submits a form using Connexion class.
	 * @param formName String The id of the form
	 * @param post Boolean Whether to POST or GET
	 * @param completeCallback Function Callback to be called on complete
	 */
	submitForm: function(formName, post, completeCallback)
	{
		var connexion = new Connexion();
		if(post){
			connexion.setMethod('POST');
		}
		$(formName).getElements().each(function(fElement){
			var fValue = fElement.getValue();
			if(fElement.name == 'get_action' && fValue.substr(0,4) == 'http'){			
				fValue = getBaseName(fValue);
			}
			if(fElement.type == 'radio' && !fElement.checked) return;
			connexion.addParameter(fElement.name, fValue);
		});
		if(this._dataModel.getContextNode()){
			connexion.addParameter('dir', this._dataModel.getContextNode().getPath());
		}
		if(completeCallback){
			connexion.onComplete = completeCallback;
		}else{
			connexion.onComplete = function(transport){this.parseXmlMessage(transport.responseXML);}.bind(this) ;
		}
		connexion.sendAsync();
	},
	
	/**
	 * Standard parser for server XML answers
	 * @param xmlResponse DOMDocument 
	 */
	parseXmlMessage: function(xmlResponse)
	{
		var messageBox = ajaxplorer.messageBox;
		if(xmlResponse == null || xmlResponse.documentElement == null) return;
		var childs = xmlResponse.documentElement.childNodes;	
		
		var reloadNodes = [];
        var error = false;
		
		for(var i=0; i<childs.length;i++)
		{
			if(childs[i].tagName == "message")
			{

				var messageTxt = "No message";
				if(childs[i].firstChild) messageTxt = childs[i].firstChild.nodeValue;
				ajaxplorer.displayMessage(childs[i].getAttribute('type'), messageTxt);
                if(childs[i].getAttribute('type') == 'ERROR') error = true;

			}else if(childs[i].tagName == "prompt"){

                var message = XPathSelectSingleNode(childs[i], "message").firstChild.nodeValue;
                var jsonData = XPathSelectSingleNode(childs[i], "data").firstChild.nodeValue;
                var json = jsonData.evalJSON();
                var dialogContent = new Element('div').update(json["DIALOG"]);
                modal.showSimpleModal(modal.messageBox?modal.messageBox:document.body, dialogContent, function(){
                    // ok callback;
                    if(json["OK"]["GET_FIELDS"]){
                        var params = $H();
                        $A(json["OK"]["GET_FIELDS"]).each(function(fName){
                            params.set(fName, dialogContent.down('input[name="'+fName+'"]').getValue());
                        });
                        var conn = new Connexion();
                        conn.setParameters(params);
                        if(json["OK"]["EVAL"]){
                            conn.onComplete = function(){
                                eval(json["OK"]["EVAL"]);
                            };
                        }
                        conn.sendAsync();
                    }else{
                        if(json["OK"]["EVAL"]){
                            eval(json["OK"]["EVAL"]);
                        }
                    }
                    return true;
                }, function(){
                    // cancel callback
                    if(json["CANCEL"]["EVAL"]){
                        eval(json["CANCEL"]["EVAL"]);
                    }
                    return true;
                });
                throw new Error();

            }
            else if(childs[i].tagName == "reload_instruction")
			{
				var obName = childs[i].getAttribute('object');
				if(obName == 'data')
				{
					var node = childs[i].getAttribute('node');				
					if(node){
						reloadNodes.push(node);
					}else{
						var file = childs[i].getAttribute('file');
						if(file){
							this._dataModel.setPendingSelection(file);
						}
						reloadNodes.push(this._dataModel.getContextNode());
					}
				}
				else if(obName == 'repository_list')
				{
					ajaxplorer.reloadRepositoriesList();
				}
			}
            else if(childs[i].nodeName == 'nodes_diff'){
                var dm = this._dataModel;
                var removes = XPathSelectNodes(childs[i], "remove/tree");
                var adds = XPathSelectNodes(childs[i], "add/tree");
                var updates = XPathSelectNodes(childs[i], "update/tree");
                if(removes && removes.length){
                    removes.each(function(r){
                        var p = r.getAttribute("filename");
                        var fake = new AjxpNode(p);
                        var n = fake.findInArbo(dm.getRootNode(), undefined);
                        if(n){
                            n.getParent().removeChild(n);
                        }
                    });
                }
                if(adds && adds.length && dm.getAjxpNodeProvider().parseAjxpNode){
                    adds.each(function(tree){
                        var newNode = dm.getAjxpNodeProvider().parseAjxpNode(tree);
                        var parentFake = new AjxpNode(getRepName(newNode.getPath()));
                        var parent = parentFake.findInArbo(dm.getRootNode(), undefined);
                        if(!parent && getRepName(newNode.getPath()) == "") parent = dm.getRootNode();
                        if(parent){
                            parent.addChild(newNode);
                            if(dm.getContextNode() == parent && !window.currentLightBox){
                                dm.setSelectedNodes([newNode], {});
                            }
                        }
                    });
                }
                if(updates && updates.length && dm.getAjxpNodeProvider().parseAjxpNode){
                    updates.each(function(tree){
                        var newNode = dm.getAjxpNodeProvider().parseAjxpNode(tree);
                        var original = newNode.getMetadata().get("original_path");
                        var fake, n;
                        if(original && original != newNode.getPath()
                            && getRepName(original) != getRepName(newNode.getPath())){
                            // Node was really moved to another folder
                            fake = new AjxpNode(original);
                            n = fake.findInArbo(dm.getRootNode(), undefined);
                            if(n){
                                n.getParent().removeChild(n);
                            }
                            var parentFake = new AjxpNode(getRepName(newNode.getPath()));
                            var parent = parentFake.findInArbo(dm.getRootNode(), undefined);
                            if(!parent && getRepName(newNode.getPath()) == "") parent = dm.getRootNode();
                            if(parent){
                                newNode.getMetadata().set("original_path", undefined);
                                parent.addChild(newNode);
                            }
                        }else{
                            fake = new AjxpNode(original);
                            n = fake.findInArbo(dm.getRootNode(), undefined);
                            if(n){
                                newNode._isLoaded = n._isLoaded;
                                n.replaceBy(newNode, "override");
                                if(!window.currentLightBox) dm.setSelectedNodes([n], {});
                            }
                        }
                    });
                }
            }
			else if(childs[i].tagName == "logging_result")
			{
				if(childs[i].getAttribute("secure_token")){
                    var regex = new RegExp('.*?[&\\?]' + 'minisite_session' + '=(.*?)&.*');
                    var val = window.ajxpServerAccessPath.replace(regex, "$1");
                    var minisite_session = ( val == window.ajxpServerAccessPath ? false : val );

					Connexion.SECURE_TOKEN = childs[i].getAttribute("secure_token");
					var parts = window.ajxpServerAccessPath.split("?secure_token");
					window.ajxpServerAccessPath = parts[0] + "?secure_token=" + Connexion.SECURE_TOKEN;
                    if(minisite_session) window.ajxpServerAccessPath += "&minisite_session=" + minisite_session;
					ajxpBootstrap.parameters.set('ajxpServerAccess', window.ajxpServerAccessPath);
				}
                if($("generic_dialog_box") && $("generic_dialog_box").down(".ajxp_login_error")){
                    $("generic_dialog_box").down(".ajxp_login_error").remove();
                }
				var result = childs[i].getAttribute('value');
                var errorId = false;
				if(result == '1')
				{
                    try{
                        modal.setCloseValidation(null);
                        hideLightBox(true);
                        if(childs[i].getAttribute('remember_login') && childs[i].getAttribute('remember_pass')){
                            var login = childs[i].getAttribute('remember_login');
                            var pass = childs[i].getAttribute('remember_pass');
                            storeRememberData(login, pass);
                        }
                    }catch(e){
                        if(console) console.log('Error after login, could prevent registry loading!', e);
                    }
					ajaxplorer.loadXmlRegistry();
				}
				else if(result == '0' || result == '-1')
				{
                    errorId = 285;
				}
				else if(result == '2')
				{					
					ajaxplorer.loadXmlRegistry();
				}
				else if(result == '-2')
				{
                    errorId = 285;
				}
				else if(result == '-3')
				{
                    errorId = 366;
				}
				else if(result == '-4')
				{
                    errorId = 386;
				}
                if(errorId){
                    error = true;
                    if($("generic_dialog_box") && $("generic_dialog_box").visible() && $("generic_dialog_box").down("div.dialogLegend")){
                        $("generic_dialog_box").down("div.dialogLegend").insert({bottom:'<div class="ajxp_login_error" style="background-color: #D33131;display: block;font-size: 9px;color: white;border-radius: 3px;padding: 2px 6px;">'+MessageHash[errorId]+'</div>'});
                        Effect.ErrorShake($("generic_dialog_box").down('.ajxp_login_error'));
                    }else{
                        alert(MessageHash[errorId]);
                    }
                }

			}else if(childs[i].tagName == "trigger_bg_action"){
				var name = childs[i].getAttribute("name");
				var messageId = childs[i].getAttribute("messageId");
				var parameters = new Hash();
				for(var j=0;j<childs[i].childNodes.length;j++){
					var paramChild = childs[i].childNodes[j];
					if(paramChild.tagName == 'param'){
						parameters.set(paramChild.getAttribute("name"), paramChild.getAttribute("value"));
					}
				}
				this.bgManager.queueAction(name, parameters, messageId);
				this.bgManager.next();
			}

		}
		if(reloadNodes.length){
			this._dataModel.multipleNodesReload(reloadNodes);
		}
        return !error;
	},
	
	/**
	 * Spreads a selection change to all actions and to registered components 
	 * by triggering ajaxplorer:actions_refreshed event.
	 */
	fireSelectionChange: function(){
		var userSelection = null;
        userSelection = this._dataModel;
        if(userSelection.isEmpty()) userSelection = null;
		this.actions.each(function(pair){
			pair.value.fireSelectionChange(userSelection);
		});
        if(this.localDataModel){
            this.notify("actions_refreshed");
        }else{
            document.fire("ajaxplorer:actions_refreshed");
        }
	},
	
	/**
	 * Spreads a context change to all actions and to registered components 
	 * by triggering ajaxplorer:actions_refreshed event.
	 */
	fireContextChange: function(){
        var crtNode = this._dataModel.getContextNode();
		this.actions.each(function(pair){
			pair.value.fireContextChange(this.usersEnabled, 
									 this.oUser, 									 
									 crtNode);
		}.bind(this));
        if(this.localDataModel){
            this.notify("actions_refreshed");
        }else{
            document.fire("ajaxplorer:actions_refreshed");
        }
	},
			
	/**
	 * Remove all actions
	 */
	removeActions: function(){
		this.actions.each(function(pair){
			pair.value.remove();
		});
		this.actions = new Hash();
		this.clearRegisteredKeys();
	},
	
	/**
	 * Create actions from XML Registry
	 * @param registry DOMDocument
	 */
	loadActionsFromRegistry : function(registry){
        if(!registry){
            registry = ajaxplorer.getXmlRegistry();
        }
		this.removeActions();		
		this.parseActions(registry);
		if(ajaxplorer && ajaxplorer.guiActions){
			ajaxplorer.guiActions.each(function(pair){
				var act = pair.value;
				this.registerAction(act);
			}.bind(this));
		}
        if(this.localDataModel){
            this.notify("actions_loaded");
        }else{
            document.fire("ajaxplorer:actions_loaded", this.actions);
        }
		this.fireContextChange();
		this.fireSelectionChange();		
	},
	
	/**
	 * Registers an action to this manager (default, accesskey).
	 * @param action Action
	 */
	registerAction : function(action){
		var actionName = action.options.name;
		this.actions.set(actionName, action);
		if(action.defaults){
			for(var key in action.defaults) this.defaultActions.set(key, actionName);
		}
		if(action.options.hasAccessKey){
			this.registerKey(action.options.accessKey, actionName);
		}
		if(action.options.specialAccessKey){
			this.registerKey("key_" + action.options.specialAccessKey, actionName);
		}
		action.setManager(this);
	},
	
	/**
	 * Parse an XML action node and registers the action
	 * @param documentElement DOMNode The node to parse
	 */
	parseActions: function(documentElement){		
		var actions = XPathSelectNodes(documentElement, "actions/action");
		for(var i=0;i<actions.length;i++){
			if(actions[i].nodeName != 'action') continue;
            if(actions[i].getAttribute('enabled') == 'false') continue;
			var newAction = new Action();
			newAction.createFromXML(actions[i]);
			this.registerAction(newAction);
		}
	},
	/**
	 * Find an action by its name
	 * @param actionName String
	 * @returns Action
	 */
	getActionByName : function(actionName){
		return this.actions.get(actionName);		
	},
	
	/**
	 * Utilitary to get FlashVersion, should probably be removed from here!
	 * @returns String
	 */
	getFlashVersion: function()
	{
		if (!this.pluginVersion) {
			var x;
			if(navigator.plugins && navigator.mimeTypes.length){
				x = navigator.plugins["Shockwave Flash"];
				if(x && x.description) x = x.description;
			} else if (Prototype.Browser.IE){
				try {
					x = new ActiveXObject("ShockwaveFlash.ShockwaveFlash");
					x = x.GetVariable("$version");
				} catch(e){}
			}
			this.pluginVersion = (typeof(x) == 'string') ? parseInt(x.match(/\d+/)[0]) : 0;
		}
		return this.pluginVersion;
	}
});