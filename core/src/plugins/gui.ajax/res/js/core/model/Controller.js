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
 * Singleton class that manages all actions. Can be called directly using pydio.getController().
 */
"use strict";

var _createClass = (function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; })();

var _get = function get(_x3, _x4, _x5) { var _again = true; _function: while (_again) { var object = _x3, property = _x4, receiver = _x5; desc = parent = getter = undefined; _again = false; if (object === null) object = Function.prototype; var desc = Object.getOwnPropertyDescriptor(object, property); if (desc === undefined) { var parent = Object.getPrototypeOf(object); if (parent === null) { return undefined; } else { _x3 = parent; _x4 = property; _x5 = receiver; _again = true; continue _function; } } else if ("value" in desc) { return desc.value; } else { var getter = desc.get; if (getter === undefined) { return undefined; } return getter.call(receiver); } } };

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

        _get(Object.getPrototypeOf(Controller.prototype), "constructor", this).call(this);
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

    _createClass(Controller, [{
        key: "publishActionEvent",
        value: function publishActionEvent(eventName, data) {
            this._pydioObject.fire(eventName, data);
        }
    }, {
        key: "_connectDataModel",
        value: function _connectDataModel() {
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
        }
    }, {
        key: "updateGuiActions",
        value: function updateGuiActions(actions) {
            actions.forEach((function (v, k) {
                this._guiActions.set(k, v);
            }).bind(this));
        }
    }, {
        key: "deleteFromGuiActions",
        value: function deleteFromGuiActions(actionName) {
            this._guiActions["delete"](actionName);
        }
    }, {
        key: "refreshGuiActionsI18n",
        value: function refreshGuiActionsI18n() {
            this._guiActions.forEach(function (value, key) {
                value.refreshFromI18NHash();
            });
        }
    }, {
        key: "getBackgroundTasksManager",
        value: function getBackgroundTasksManager() {
            if (!Controller._bgManager) {
                Controller._bgManager = new BackgroundTasksManager();
            }
            return Controller._bgManager;
        }
    }, {
        key: "getDataModel",
        value: function getDataModel() {
            return this._dataModel;
        }
    }, {
        key: "destroy",
        value: function destroy() {
            if (this.localDataModel && this._dataModel) {
                this._dataModel.stopObserving("context_changed", this.contextChangedObs);
                this._dataModel.stopObserving("selection_changed", this.selectionChangedObs);
            }
        }
    }, {
        key: "getMessage",
        value: function getMessage(messageId) {
            try {
                return this._pydioObject.MessageHash[messageId];
            } catch (e) {
                return messageId;
            }
        }
    }, {
        key: "uiInsertForm",
        value: function uiInsertForm(formId, formCode) {
            if (this._pydioObject.UI) {
                this._pydioObject.UI.insertForm(formId, formCode);
            }
        }
    }, {
        key: "uiRemoveForm",
        value: function uiRemoveForm(formId) {
            if (this._pydioObject.UI) {
                this._pydioObject.UI.removeForm(formId);
            }
        }
    }, {
        key: "uiGetModal",
        value: function uiGetModal() {
            if (this._pydioObject && this._pydioObject.UI) {
                return this._pydioObject.UI.modal;
            }
            return null;
        }
    }, {
        key: "uiMountComponents",
        value: function uiMountComponents(componentsNodes) {
            if (this._pydioObject && this._pydioObject.UI) {
                return this._pydioObject.UI.mountComponents(componentsNodes);
            }
        }

        /**
         * COMPATIBILITY METHD
         * @param xmlDoc
         * @returns {*}
         */
    }, {
        key: "parseXmlMessage",
        value: function parseXmlMessage(xmlDoc) {
            Logger.log("Controller.parseXmlMessage() is deprecated, use PydioApi instead");
            if (window.console && window.console.trace) {
                Logger.log(console.trace());
            }
            return PydioApi.getClient().parseXmlMessage(xmlDoc);
        }

        /**
         * Submits a form using Connexion class.
         * @param formName String The id of the form
         * @param post Boolean Whether to POST or GET
         * @param completeCallback Function Callback to be called on complete
         */
    }, {
        key: "submitForm",
        value: function submitForm(formName, post, completeCallback) {
            Logger.log("Controller.submitForm() is deprecated, use PydioApi instead");
            return PydioApi.getClient().submitForm(formName, post, completeCallback);
        }

        /**
         * Stores the currently logged user object
         * @param oUser User User instance
         */
    }, {
        key: "setUser",
        value: function setUser(oUser) {
            this.oUser = oUser;
            if (oUser != null && oUser.id != 'guest' && oUser.getPreference('lang') != null && oUser.getPreference('lang') != "" && oUser.getPreference('lang') != this._pydioObject.currentLanguage && !oUser.lock) {
                this._pydioObject.loadI18NMessages(oUser.getPreference('lang'));
            }
        }

        /**
         * Filter the actions given the srcElement passed as arguments.
            * TODO: SIGNATURE CHANGED, FROM srcElement to actionsSelectorAtt
         * @param srcElement String An identifier among selectionContext, genericContext, a webfx object id
            * @param ignoreGroups Array a list of groups to ignore
         * @returns Array
         */
    }, {
        key: "getContextActions",
        value: function getContextActions(actionsSelectorAtt, ignoreGroups) {
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
            if (defaultGroup && contextActionsGroup.has(defaultGroup)) {
                keys.push(defaultGroup);
            }
            var _iteratorNormalCompletion = true;
            var _didIteratorError = false;
            var _iteratorError = undefined;

            try {
                for (var _iterator = contextActionsGroup.keys()[Symbol.iterator](), _step; !(_iteratorNormalCompletion = (_step = _iterator.next()).done); _iteratorNormalCompletion = true) {
                    var k = _step.value;

                    if (k == defaultGroup) continue;
                    keys.push(k);
                }
            } catch (err) {
                _didIteratorError = true;
                _iteratorError = err;
            } finally {
                try {
                    if (!_iteratorNormalCompletion && _iterator["return"]) {
                        _iterator["return"]();
                    }
                } finally {
                    if (_didIteratorError) {
                        throw _iteratorError;
                    }
                }
            }

            keys.sort();
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
        }

        /**
         * Generic method to get actions for a given component part.
         * @param ajxpClassName String 
         * @param widgetId String
         * @returns []
         */
    }, {
        key: "getActionsForAjxpWidget",
        value: function getActionsForAjxpWidget(ajxpClassName, widgetId) {
            var actions = [];
            this.actions.forEach(function (action) {
                if (action.context.ajxpWidgets && (action.context.ajxpWidgets.indexOf(ajxpClassName + '::' + widgetId) != -1 || action.context.ajxpWidgets.indexOf(ajxpClassName) != -1) && !action.deny) actions.push(action);
            });
            return actions;
        }

        /**
         * Finds a default action and fires it.
         * @param defaultName String ("file", "dir", "dragndrop", "ctrldragndrop")
         */
    }, {
        key: "fireDefaultAction",
        value: function fireDefaultAction(defaultName) {
            var actionName = this.defaultActions.get(defaultName);
            if (actionName) {
                arguments[0] = actionName;
                if (actionName == "ls") {
                    var action = this.actions.get(actionName);
                    if (action) action.enable(); // Force enable on default action
                }
                this.fireAction.apply(this, arguments);
            }
        }

        /**
         * Fire an action based on its name
         * @param actionName String The name of the action
         */
    }, {
        key: "fireAction",
        value: function fireAction(actionName) {
            var action = this.actions.get(actionName);
            if (action != null) {
                var args = Array.from(arguments).slice(1);
                action.apply(args);
            }
        }

        /**
         * Registers an accesskey for a given action. 
         * @param key String The access key
         * @param actionName String The name of the action
         * @param optionnalCommand String An optionnal argument 
         * that will be passed to the action when fired.
         */
    }, {
        key: "registerKey",
        value: function registerKey(key, actionName, optionnalCommand) {
            if (optionnalCommand) {
                actionName = actionName + "::" + optionnalCommand;
            }
            this._registeredKeys.set(key.toLowerCase(), actionName);
        }

        /**
         * Remove all registered keys.
         */
    }, {
        key: "clearRegisteredKeys",
        value: function clearRegisteredKeys() {
            this._registeredKeys = new Map();
        }

        /**
         * Triggers an action by its access key.
         * @param event Event The key event (will be stopped)
         * @param keyName String A key name
         */
    }, {
        key: "fireActionByKey",
        value: function fireActionByKey(event, keyName) {
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
        }

        /**
         * Complex function called when drag'n'dropping. Basic checks of who is child of who.
         * @param fileName String The dragged element 
         * @param destDir String The drop target node path
         * @param destNodeName String The drop target node name
         * @param copy Boolean Copy or Move
         */
    }, {
        key: "applyDragMove",
        value: function applyDragMove(fileName, destDir, destNodeName, copy) {
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
        }

        /**
         * Get the action defined as default for a given default string
         * @param defaultName String
         * @returns Action
         */
    }, {
        key: "getDefaultAction",
        value: function getDefaultAction(defaultName) {
            if (this.defaultActions.has(defaultName)) {
                return this.actions.get(this.defaultActions.get(defaultName));
            }
            return null;
        }

        /**
         * Spreads a selection change to all actions and to registered components 
         * by triggering ajaxplorer:actions_refreshed event.
         */
    }, {
        key: "fireSelectionChange",
        value: function fireSelectionChange() {
            var userSelection = null;
            userSelection = this._dataModel;
            if (userSelection.isEmpty()) userSelection = null;
            this.actions.forEach(function (action) {
                action.fireSelectionChange(userSelection);
            });
            if (this.localDataModel) {
                this.notify("actions_refreshed");
            } else {
                this._pydioObject.fire("actions_refreshed");
            }
        }

        /**
         * Spreads a context change to all actions and to registered components 
         * by triggering ajaxplorer:actions_refreshed event.
         */
    }, {
        key: "fireContextChange",
        value: function fireContextChange() {
            var crtNode = this._dataModel.getContextNode();
            this.actions.forEach((function (action) {
                action.fireContextChange(this.usersEnabled, this.oUser, crtNode);
            }).bind(this));
            if (this.localDataModel) {
                this.notify("actions_refreshed");
            } else {
                this._pydioObject.fire("actions_refreshed");
            }
        }

        /**
         * Remove all actions
         */
    }, {
        key: "removeActions",
        value: function removeActions() {
            this.actions.forEach(function (action) {
                action.remove();
            });
            this.actions = new Map();
            this.clearRegisteredKeys();
        }

        /**
         * Create actions from XML Registry
         * @param registry DOMDocument
         */
    }, {
        key: "loadActionsFromRegistry",
        value: function loadActionsFromRegistry() {
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
        }

        /**
         * Registers an action to this manager (default, accesskey).
         * @param action Action
         */
    }, {
        key: "registerAction",
        value: function registerAction(action) {
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
        }

        /**
         * Parse an XML action node and registers the action
         * @param documentElement DOMNode The node to parse
         */
    }, {
        key: "parseActions",
        value: function parseActions(documentElement) {
            var actions = XMLUtils.XPathSelectNodes(documentElement, "actions/action");
            for (var i = 0; i < actions.length; i++) {
                if (actions[i].nodeName != 'action') continue;
                if (actions[i].getAttribute('enabled') == 'false') continue;
                var newAction = new Action();
                newAction.setManager(this);
                newAction.createFromXML(actions[i]);
                this.registerAction(newAction);
            }
        }

        /**
         * Find an action by its name
         * @param actionName String
         * @returns Action
         */
    }, {
        key: "getActionByName",
        value: function getActionByName(actionName) {
            return this.actions.get(actionName);
        }
    }]);

    return Controller;
})(Observable);
