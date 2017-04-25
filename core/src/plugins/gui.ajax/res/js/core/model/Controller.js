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
 * The latest code can be found at <https://pydio.com>.
 */

/**
 * Singleton class that manages all actions. Can be called directly using pydio.getController().
 */
"use strict";

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function, not " + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

var Controller = (function (_Observable) {
    _inherits(Controller, _Observable);

    /**
     * Standard constructor
     * @param pydioObject Pydio
     * @param dataModelElementId
     */

    function Controller(pydioObject) {
        var dataModelElementId = arguments.length <= 1 || arguments[1] === undefined ? null : arguments[1];

        _classCallCheck(this, Controller);

        _Observable.call(this);
        this._pydioObject = pydioObject;
        this._registeredKeys = new Map();
        this.usersEnabled = pydioObject.Parameters.get("usersEnabled");

        this.subMenus = [];
        this.actions = new Map();
        this.defaultActions = new Map();
        this.toolbars = new Map();
        this._guiActions = new Map();

        this.contextChangedObs = (function (event) {
            window.setTimeout((function () {
                this.fireContextChange();
            }).bind(this), 0);
        }).bind(this);
        this.selectionChangedObs = (function (event) {
            window.setTimeout((function () {
                this.fireSelectionChange();
            }).bind(this), 0);
        }).bind(this);

        if (dataModelElementId) {
            this.localDataModel = true;
            try {
                this._dataModel = document.getElementById(dataModelElementId).ajxpPaneObject.getDataModel();
            } catch (e) {}
            if (this._dataModel) {
                this._connectDataModel();
            } else {
                this._pydioObject.observeOnce("datamodel-loaded-" + dataModelElementId, (function () {
                    this._dataModel = document.getElementById(dataModelElementId).ajxpPaneObject.getDataModel();
                    this._connectDataModel();
                }).bind(this));
            }
        } else {
            this.localDataModel = false;
            this._connectDataModel();
        }

        if (this.usersEnabled) {
            this._pydioObject.observe("user_logged", (function (user) {
                this.setUser(user);
            }).bind(this));
            if (this._pydioObject.user) {
                this.setUser(this._pydioObject.user);
            }
        }
    }

    Controller.prototype.publishActionEvent = function publishActionEvent(eventName, data) {
        this._pydioObject.fire(eventName, data);
    };

    Controller.prototype._connectDataModel = function _connectDataModel() {
        if (this.localDataModel) {
            this._dataModel.observe("context_changed", this.contextChangedObs);
            this._dataModel.observe("selection_changed", this.selectionChangedObs);
            this.loadActionsFromRegistry();
            this._pydioObject.observe("registry_loaded", (function (registry) {
                this.loadActionsFromRegistry(registry);
            }).bind(this));
        } else {
            this._pydioObject.observe("context_changed", this.contextChangedObs);
            this._pydioObject.observe("selection_changed", this.selectionChangedObs);
            this._dataModel = this._pydioObject.getContextHolder();
        }
    };

    Controller.prototype.updateGuiActions = function updateGuiActions(actions) {
        actions.forEach((function (v, k) {
            this._guiActions.set(k, v);
        }).bind(this));
    };

    Controller.prototype.deleteFromGuiActions = function deleteFromGuiActions(actionName) {
        this._guiActions["delete"](actionName);
    };

    Controller.prototype.refreshGuiActionsI18n = function refreshGuiActionsI18n() {
        this._guiActions.forEach(function (value, key) {
            value.refreshFromI18NHash();
        });
    };

    Controller.prototype.getBackgroundTasksManager = function getBackgroundTasksManager() {
        if (!Controller._bgManager) {
            Controller._bgManager = new BackgroundTasksManager(this);
        }
        return Controller._bgManager;
    };

    Controller.prototype.getDataModel = function getDataModel() {
        return this._dataModel;
    };

    Controller.prototype.destroy = function destroy() {
        if (this.localDataModel && this._dataModel) {
            this._dataModel.stopObserving("context_changed", this.contextChangedObs);
            this._dataModel.stopObserving("selection_changed", this.selectionChangedObs);
        }
    };

    Controller.prototype.getMessage = function getMessage(messageId) {
        try {
            return this._pydioObject.MessageHash[messageId];
        } catch (e) {
            return messageId;
        }
    };

    Controller.prototype.uiInsertForm = function uiInsertForm(formId, formCode) {
        if (this._pydioObject.UI) {
            this._pydioObject.UI.insertForm(formId, formCode);
        }
    };

    Controller.prototype.uiRemoveForm = function uiRemoveForm(formId) {
        if (this._pydioObject.UI) {
            this._pydioObject.UI.removeForm(formId);
        }
    };

    Controller.prototype.uiGetModal = function uiGetModal() {
        if (this._pydioObject && this._pydioObject.UI) {
            return this._pydioObject.UI.modal;
        }
        return null;
    };

    Controller.prototype.uiMountComponents = function uiMountComponents(componentsNodes) {
        if (this._pydioObject && this._pydioObject.UI) {
            return this._pydioObject.UI.mountComponents(componentsNodes);
        }
    };

    /**
     * COMPATIBILITY METHD
     * @param xmlDoc
     * @returns {*}
     */

    Controller.prototype.parseXmlMessage = function parseXmlMessage(xmlDoc) {
        Logger.debug("Controller.parseXmlMessage() is deprecated, use PydioApi instead");
        return PydioApi.getClient().parseXmlMessage(xmlDoc);
    };

    /**
     * Submits a form using Connexion class.
     * @param formName String The id of the form
     * @param post Boolean Whether to POST or GET
     * @param completeCallback Function Callback to be called on complete
     */

    Controller.prototype.submitForm = function submitForm(formName, post, completeCallback) {
        Logger.debug("Controller.submitForm() is deprecated, use PydioApi instead");
        return PydioApi.getClient().submitForm(formName, post, completeCallback);
    };

    /**
     * Stores the currently logged user object
     * @param oUser User User instance
     */

    Controller.prototype.setUser = function setUser(oUser) {
        this.oUser = oUser;
        if (oUser != null && oUser.id != 'guest' && oUser.getPreference('lang') != null && oUser.getPreference('lang') != "" && oUser.getPreference('lang') != this._pydioObject.currentLanguage && !oUser.lock) {
            this._pydioObject.loadI18NMessages(oUser.getPreference('lang'));
        }
    };

    /**
     * Filter the actions given the srcElement passed as arguments.
     * @param actionsSelectorAtt String An identifier among selectionContext, genericContext, a webfx object id
        * @param ignoreGroups Array a list of groups to ignore
     * @returns Array
     */

    Controller.prototype.getContextActions = function getContextActions(actionsSelectorAtt, ignoreGroups) {
        var contextActions = [];
        var defaultGroup;
        var contextActionsGroup = new Map();
        this.actions.forEach((function (action) {
            if (!action.context.contextMenu) return;
            if (actionsSelectorAtt == 'selectionContext' && !action.context.selection) return;
            if (actionsSelectorAtt == 'directoryContext' && !action.context.dir) return;
            if (actionsSelectorAtt == 'genericContext' && action.context.selection) return;
            if (action.contextHidden || action.deny) return;
            action.context.actionBarGroup.split(',').forEach(function (barGroup) {
                if (!contextActionsGroup.has(barGroup)) {
                    contextActionsGroup.set(barGroup, []);
                }
            });
            var isDefault = false;
            if (actionsSelectorAtt == 'selectionContext') {
                // set default in bold
                var userSelection = this._dataModel;
                if (!userSelection.isEmpty()) {
                    var defaultAction = 'file';
                    if (userSelection.isUnique() && (userSelection.hasDir() || userSelection.hasMime(['ajxp_browsable_archive']))) {
                        defaultAction = 'dir';
                    }
                    if (this.defaultActions.get(defaultAction) && action.options.name == this.defaultActions.get(defaultAction)) {
                        isDefault = true;
                    }
                }
            }
            action.context.actionBarGroup.split(',').forEach(function (barGroup) {
                var menuItem = {
                    name: action.getKeyedText(),
                    alt: action.options.title,
                    action_id: action.options.name,
                    image_unresolved: action.options.src,
                    isDefault: isDefault,
                    callback: (function (e) {
                        this.apply();
                    }).bind(action)
                };
                if (action.options.icon_class) {
                    menuItem.icon_class = action.options.icon_class;
                }
                if (action.options.subMenu) {
                    menuItem.subMenu = [];
                    if (action.subMenuItems.staticOptions) {
                        menuItem.subMenu = action.subMenuItems.staticOptions;
                    }
                    if (action.subMenuItems.dynamicBuilder) {
                        menuItem.subMenuBeforeShow = action.subMenuItems.dynamicBuilder;
                    }
                }
                contextActionsGroup.get(barGroup).push(menuItem);
                if (isDefault) {
                    defaultGroup = barGroup;
                }
            });
        }).bind(this));
        var first = true,
            keys = [];
        for (var _iterator = contextActionsGroup.keys(), _isArray = Array.isArray(_iterator), _i = 0, _iterator = _isArray ? _iterator : _iterator[Symbol.iterator]();;) {
            var _ref;

            if (_isArray) {
                if (_i >= _iterator.length) break;
                _ref = _iterator[_i++];
            } else {
                _i = _iterator.next();
                if (_i.done) break;
                _ref = _i.value;
            }

            var k = _ref;

            if (defaultGroup && k == defaultGroup) continue;
            keys.push(k);
        }
        keys.sort();
        if (defaultGroup && contextActionsGroup.has(defaultGroup)) {
            keys.unshift(defaultGroup);
        }
        keys.each(function (key) {
            var value = contextActionsGroup.get(key);
            if (!first) {
                contextActions.push({ separator: true });
            }
            if (ignoreGroups && ignoreGroups.indexOf(key) != -1) {
                return;
            }
            first = false;
            value.forEach(function (mItem) {
                contextActions.push(mItem);
            });
        });
        return contextActions;
    };

    /**
     * Generic method to get actions for a given component part.
     * @param ajxpClassName String 
     * @param widgetId String
     * @returns []
     */

    Controller.prototype.getActionsForAjxpWidget = function getActionsForAjxpWidget(ajxpClassName, widgetId) {
        var actions = [];
        this.actions.forEach(function (action) {
            if (action.context.ajxpWidgets && (action.context.ajxpWidgets.indexOf(ajxpClassName + '::' + widgetId) != -1 || action.context.ajxpWidgets.indexOf(ajxpClassName) != -1) && !action.deny) actions.push(action);
        });
        return actions;
    };

    /**
     * Finds a default action and fires it.
     * @param defaultName String ("file", "dir", "dragndrop", "ctrldragndrop")
     */

    Controller.prototype.fireDefaultAction = function fireDefaultAction(defaultName) {
        var actionName = this.defaultActions.get(defaultName);
        if (actionName) {
            arguments[0] = actionName;
            if (actionName == "ls") {
                var action = this.actions.get(actionName);
                if (action) action.enable(); // Force enable on default action
            }
            this.fireAction.apply(this, arguments);
        }
    };

    /**
     * Fire an action based on its name
     * @param actionName String The name of the action
     */

    Controller.prototype.fireAction = function fireAction(actionName) {
        var action = this.actions.get(actionName);
        if (action != null) {
            var args = Array.from(arguments).slice(1);
            action.apply(args);
        }
    };

    /**
     * Registers an accesskey for a given action. 
     * @param key String The access key
     * @param actionName String The name of the action
     * @param optionnalCommand String An optionnal argument 
     * that will be passed to the action when fired.
     */

    Controller.prototype.registerKey = function registerKey(key, actionName, optionnalCommand) {
        if (optionnalCommand) {
            actionName = actionName + "::" + optionnalCommand;
        }
        this._registeredKeys.set(key.toLowerCase(), actionName);
    };

    /**
     * Remove all registered keys.
     */

    Controller.prototype.clearRegisteredKeys = function clearRegisteredKeys() {
        this._registeredKeys = new Map();
    };

    /**
     * Triggers an action by its access key.
     * @param event Event The key event (will be stopped)
     * @param keyName String A key name
     */

    Controller.prototype.fireActionByKey = function fireActionByKey(event, keyName) {
        if (this._registeredKeys.get(keyName)) {
            if (this._registeredKeys.get(keyName).indexOf("::") !== -1) {
                var parts = this._registeredKeys.get(keyName).split("::");
                this.fireAction(parts[0], parts[1]);
            } else {
                this.fireAction(this._registeredKeys.get(keyName));
            }
            try {
                event.preventDefault();
                event.stopPropagation();
            } catch (e) {
                Logger.error("Error trying to stop event propagation");
            }
        }
    };

    /**
     * Complex function called when drag'n'dropping. Basic checks of who is child of who.
     * @param fileName String The dragged element 
     * @param destDir String The drop target node path
     * @param destNodeName String The drop target node name
     * @param copy Boolean Copy or Move
     */

    Controller.prototype.applyDragMove = function applyDragMove(fileName, destDir, destNodeName, copy) {
        if (!copy && (!this.defaultActions.has('dragndrop') || this.getDefaultAction('dragndrop').deny) || copy && (!this.defaultActions.has('ctrldragndrop') || this.getDefaultAction('ctrldragndrop').deny)) {
            return;
        }
        var fileNames;
        if (fileName == null) fileNames = this._dataModel.getFileNames();else fileNames = [fileName];
        // Check that dest is not the direct parent of source, ie current rep!
        if (destDir == this._dataModel.getContextNode().getPath()) {
            this._pydioObject.displayMessage('ERROR', MessageHash[203]);
            return;
        }
        // Check that dest is not child of source it self
        for (var i = 0; i < fileNames.length; i++) {
            if (destDir.lastIndexOf(fileNames[i], 0) === 0) {
                this._pydioObject.displayMessage('ERROR', MessageHash[202]);
                return;
            }
        }
        var params = {};
        params['get_action'] = this.defaultActions.get(copy ? 'ctrldragndrop' : 'dragndrop');
        params['nodes[]'] = fileNames;
        params['dest'] = destDir;
        params['dir'] = this._dataModel.getContextNode().getPath();
        PydioApi.getClient().request(params, (function (transport) {
            this.parseXmlMessage(transport.responseXML);
        }).bind(PydioApi.getClient()));
    };

    /**
     * Get the action defined as default for a given default string
     * @param defaultName String
     * @returns Action
     */

    Controller.prototype.getDefaultAction = function getDefaultAction(defaultName) {
        if (this.defaultActions.has(defaultName)) {
            return this.actions.get(this.defaultActions.get(defaultName));
        }
        return null;
    };

    /**
     * Spreads a selection change to all actions and to registered components 
     * by triggering ajaxplorer:actions_refreshed event.
     */

    Controller.prototype.fireSelectionChange = function fireSelectionChange() {
        this.actions.forEach((function (action) {
            action.fireSelectionChange(this._dataModel);
        }).bind(this));
        if (this.localDataModel) {
            this.notify("actions_refreshed");
        } else {
            this._pydioObject.fire("actions_refreshed");
        }
    };

    /**
     * Spreads a context change to all actions and to registered components 
     * by triggering ajaxplorer:actions_refreshed event.
     */

    Controller.prototype.fireContextChange = function fireContextChange() {
        this.actions.forEach((function (action) {
            action.fireContextChange(this._dataModel, this.usersEnabled, this.oUser);
        }).bind(this));
        if (this.localDataModel) {
            this.notify("actions_refreshed");
        } else {
            this._pydioObject.fire("actions_refreshed");
        }
    };

    /**
     * Remove all actions
     */

    Controller.prototype.removeActions = function removeActions() {
        this.actions.forEach(function (action) {
            action.remove();
        });
        this.actions = new Map();
        this.clearRegisteredKeys();
    };

    /**
     * Create actions from XML Registry
     * @param registry DOMDocument
     */

    Controller.prototype.loadActionsFromRegistry = function loadActionsFromRegistry() {
        var registry = arguments.length <= 0 || arguments[0] === undefined ? null : arguments[0];

        if (!registry) {
            registry = pydio.getXmlRegistry();
        }
        this.removeActions();
        this.parseActions(registry);
        this._guiActions.forEach((function (act) {
            this.registerAction(act);
        }).bind(this));
        if (this.localDataModel) {
            this.notify("actions_loaded");
        } else {
            this._pydioObject.fire("actions_loaded", this.actions);
        }
        this.fireContextChange();
        this.fireSelectionChange();
    };

    /**
     * Registers an action to this manager (default, accesskey).
     * @param action Action
     */

    Controller.prototype.registerAction = function registerAction(action) {
        var actionName = action.options.name;
        this.actions.set(actionName, action);
        if (action.defaults) {
            for (var key in action.defaults) {
                if (action.defaults.hasOwnProperty(key)) {
                    this.defaultActions.set(key, actionName);
                }
            }
        }
        if (action.options.hasAccessKey) {
            this.registerKey(action.options.accessKey, actionName);
        }
        if (action.options.specialAccessKey) {
            this.registerKey("key_" + action.options.specialAccessKey, actionName);
        }
        action.setManager(this);
    };

    /**
     * Parse an XML action node and registers the action
     * @param documentElement DOMNode The node to parse
     */

    Controller.prototype.parseActions = function parseActions(documentElement) {
        var actions = XMLUtils.XPathSelectNodes(documentElement, "actions/action");
        for (var i = 0; i < actions.length; i++) {
            if (actions[i].nodeName != 'action') continue;
            if (actions[i].getAttribute('enabled') == 'false') continue;
            var newAction = new Action();
            newAction.setManager(this);
            newAction.createFromXML(actions[i]);
            this.registerAction(newAction);
        }
    };

    /**
     * Find an action by its name
     * @param actionName String
     * @returns Action
     */

    Controller.prototype.getActionByName = function getActionByName(actionName) {
        return this.actions.get(actionName);
    };

    return Controller;
})(Observable);
