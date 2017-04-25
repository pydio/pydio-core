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
 * A "Command" object, encapsulating its callbacks, display attributes, etc.
 */
'use strict';

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError('Cannot call a class as a function'); } }

function _inherits(subClass, superClass) { if (typeof superClass !== 'function' && superClass !== null) { throw new TypeError('Super expression must either be null or a function, not ' + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

var Action = (function (_Observable) {
	_inherits(Action, _Observable);

	/**
  * Standard constructor
  */

	function Action() {
		_classCallCheck(this, Action);

		_Observable.call(this);
		this.__DEFAULT_ICON_PATH = "/images/actions/ICON_SIZE";
		this.options = LangUtils.objectMerge({
			name: '',
			src: '',
			icon_class: '',
			text: '',
			title: '',
			text_id: '',
			title_id: '',
			hasAccessKey: false,
			accessKey: '',
			subMenu: false,
			subMenuUpdateImage: false,
			subMenuUpdateTitle: false,
			callbackCode: '',
			callbackDialogNode: null,
			callback: function callback() {},
			prepareModal: false,
			listeners: [],
			activeCondition: null,
			formId: undefined,
			formCode: undefined
		}, arguments[0] || {});

		this.context = LangUtils.objectMerge({
			selection: true,
			dir: false,
			allowedMimes: [],
			evalMetadata: '',
			root: true,
			inZip: true,
			recycle: false,
			behaviour: 'hidden',
			actionBar: false,
			actionBarGroup: 'default',
			contextMenu: false,
			ajxpWidgets: null,
			infoPanel: false
		}, arguments[1] || {});

		this.selectionContext = LangUtils.objectMerge({
			dir: false,
			file: true,
			recycle: false,
			behaviour: 'disabled',
			allowedMimes: [],
			evalMetadata: '',
			unique: true,
			multipleOnly: false,
			enableRoot: false
		}, arguments[2] || {});

		this.rightsContext = LangUtils.objectMerge({
			noUser: true,
			userLogged: true,
			guestLogged: false,
			read: false,
			write: false,
			adminOnly: false
		}, arguments[3] || {});

		this.subMenuItems = LangUtils.objectMerge({
			staticItems: null,
			dynamicItems: null,
			dynamicBuilderCode: null
		}, arguments[4] || {});

		this.elements = [];
		this.contextHidden = false;
		this.deny = false;
		if (this.context.subMenu) {
			if (!this.options.actionBar) {
				alert('Warning, wrong action definition. Cannot use a subMenu if not displayed in the actionBar!');
			}
		}
	}

	Action.prototype._evalScripts = function _evalScripts(data, localScopeMetadata) {
		var metadata = localScopeMetadata;
		return eval(data);
	};

	/**
  * Sets the manager for this action
  * @param manager ActionsManager
  */

	Action.prototype.setManager = function setManager(manager) {
		this.manager = manager;
		if (this.options.subMenu) {
			if (this.subMenuItems.staticItems) {
				this.buildSubmenuStaticItems();
			}
			if (this.subMenuItems.dynamicItems || this.subMenuItems.dynamicBuilderCode) {
				this.prepareSubmenuDynamicBuilder();
			}
		}
		if (this.options.listeners['init']) {
			try {
				window.listenerContext = this;
				if (typeof this.options.listeners['init'] == "string") {
					this._evalScripts(this.options.listeners['init']);
				} else {
					this.options.listeners['init']();
				}
			} catch (e) {
				Logger.error('Error while evaluating init script for action ' + this.options.name);
			}
		}
	};

	/**
  * Execute the action code
  */

	Action.prototype.apply = function apply() {
		if (this.deny) return;
		this.manager.publishActionEvent("beforeApply-" + this.options.name);
		if (this.options.prepareModal) {
			var modal = this.manager.uiGetModal();
			if (modal) {
				modal.prepareHeader(this.options.title, this.options.src, this.options.icon_class);
			}
		}
		window.actionArguments = [];
		window.actionManager = this.manager;
		if (arguments[0]) window.actionArguments = arguments[0];
		if (this.options.callbackCode) {
			try {
				this._evalScripts(this.options.callbackCode);
			} catch (e) {
				Logger.error(e);
			}
		} else if (this.options.callbackDialogNode && this.options.callbackDialogNode.getAttribute("dialogOpenForm")) {
			var node = this.options.callbackDialogNode;
			var dialogFormId = node.getAttribute("dialogOpenForm");
			var okButtonOnly = node.getAttribute("dialogOkButtonOnly") === "true";
			var skipButtons = node.getAttribute("dialogSkipButtons") === "true";

			var onOpenFunc = null;var onCompleteFunc = null;var onCancelFunc = null;
			var onOpenNode = XMLUtils.XPathSelectSingleNode(node, "dialogOnOpen");
			if (onOpenNode && onOpenNode.firstChild) onOpenFunc = new Function("oForm", onOpenNode.firstChild.nodeValue);
			var onCompleteNode = XMLUtils.XPathSelectSingleNode(node, "dialogOnComplete");
			if (onCompleteNode && onCompleteNode.firstChild) {
				var completeCode = onCompleteNode.firstChild.nodeValue;
				if (onCompleteNode.getAttribute("hideDialog") === "true") {
					completeCode += "hideLightBox(true);";
				}
				onCompleteFunc = new Function("oForm", completeCode);
			}
			var onCancelNode = XMLUtils.XPathSelectSingleNode(node, "dialogOnCancel");
			if (onCancelNode && onCancelNode.firstChild) onCancelFunc = new Function("oForm", onCancelNode.firstChild.nodeValue);

			this.options.callback = (function () {
				var modal = this.manager.uiGetModal();
				if (modal) {
					modal.showDialogForm('Dialog', dialogFormId, onOpenFunc, onCompleteFunc, onCancelFunc, okButtonOnly, skipButtons);
				}
			}).bind(this);
			this.options.callback();
			this.options.callbackDialogNode = null;
		} else if (this.options.callbackDialogNode && this.options.callbackDialogNode.getAttribute("components")) {
			var components = XMLUtils.XPathSelectNodes(this.options.callbackDialogNode, "component");
			this.manager.uiMountComponents(components);
		} else if (this.options.callback) {
			this.options.callback();
		}
		if (this.options.subMenu && arguments[0] && arguments[0][0]) {
			this.notify("submenu_active", arguments[0][0]);
		}
		window.actionArguments = null;
		window.actionManager = null;
		this.manager.publishActionEvent("afterApply-" + this.options.name);
	};

	/**
  * Updates the action status on context change
     * @param PydioDataModel dataModel
     * @param boolean usersEnabled
     * @param string crtUser
  * @returns void
  */

	Action.prototype.fireContextChange = function fireContextChange(dataModel, usersEnabled, crtUser) {

		var crtIsRecycle = false;
		var crtInZip = false;
		var crtIsRoot = false;
		var crtAjxpMime = '';
		var crtIsReadOnly = false;

		var crtNode = dataModel.getContextNode();
		if (crtNode) {
			crtIsRecycle = crtNode.getAjxpMime() == "ajxp_recycle";
			crtInZip = crtNode.hasAjxpMimeInBranch("ajxp_browsable_archive");
			crtIsRoot = crtNode.isRoot();
			crtAjxpMime = crtNode.getAjxpMime();
			crtIsReadOnly = crtNode.hasMetadataInBranch("ajxp_readonly", "true");
		}

		if (this.options.listeners["contextChange"]) {
			window.listenerContext = this;
			try {
				this._evalScripts(this.options.listeners["contextChange"]);
			} catch (e) {
				Logger.error("Error while evaluating script for contextChange event - action " + this.options.name);
			}
		}
		var rightsContext = this.rightsContext;
		if (!rightsContext.noUser && !usersEnabled) {
			return this.hideForContext();
		}
		if (rightsContext.userLogged == 'only' && crtUser == null || rightsContext.guestLogged && rightsContext.guestLogged == 'hidden' && crtUser != null && crtUser.id == 'guest') {
			return this.hideForContext();
		}
		if (rightsContext.userLogged == 'hidden' && crtUser != null && !(crtUser.id == 'guest' && rightsContext.guestLogged && rightsContext.guestLogged == 'show')) {
			return this.hideForContext();
		}
		if (rightsContext.adminOnly && (crtUser == null || !crtUser.isAdmin)) {
			return this.hideForContext();
		}
		if (rightsContext.read && crtUser != null && !crtUser.canRead()) {
			return this.hideForContext();
		}
		if (rightsContext.write && crtUser != null && !crtUser.canWrite()) {
			return this.hideForContext();
		}
		if (rightsContext.write && crtIsReadOnly) {
			return this.hideForContext();
		}
		if (this.context.allowedMimes.length) {
			if (this.context.allowedMimes.indexOf("*") == -1 && this.context.allowedMimes.indexOf(crtAjxpMime) == -1) {
				return this.hideForContext();
			}
			if (this.context.allowedMimes.indexOf("^" + crtAjxpMime) != -1) {
				return this.hideForContext();
			}
		}
		if (this.context.recycle) {
			if (this.context.recycle == 'only' && !crtIsRecycle) {
				return this.hideForContext();
			}
			if (this.context.recycle == 'hidden' && crtIsRecycle) {
				return this.hideForContext();
			}
		}
		if (!this.context.inZip && crtInZip) {
			return this.hideForContext();
		}
		if (!this.context.root && crtIsRoot) {
			return this.hideForContext();
		}

		this.showForContext(dataModel);
	};

	/**
  * Upates the action status on selection change
  */

	Action.prototype.fireSelectionChange = function fireSelectionChange() {
		if (this.options.listeners["selectionChange"]) {
			window.listenerContext = this;
			try {
				this._evalScripts(this.options.listeners["selectionChange"]);
			} catch (e) {
				Logger.error("Error while evaluating script for selectionChange event - action " + this.options.name);
			}
		}
		if (this.options.activeCondition) {
			try {
				var result = this.options.activeCondition();
				if (result === false) this.disable();else if (result === true) this.enable();
			} catch (e) {
				Logger.error("Error while evaluating activeCondition() script for action " + this.options.name);
			}
		}
		if (this.contextHidden || !this.context.selection) {
			return;
		}
		var userSelection = arguments[0];
		var hasRoot = false;
		if (userSelection != null) {
			hasRoot = userSelection.selectionHasRootNode();
			var bUnique = userSelection.isUnique();
			var bFile = userSelection.hasFile();
			var bDir = userSelection.hasDir();
			var bRecycle = userSelection.isRecycle();
		}
		var selectionContext = this.selectionContext;
		if (selectionContext.allowedMimes.length) {
			if (selectionContext.behaviour == 'hidden') this.hide();else this.disable();
		}
		if (selectionContext.evalMetadata && userSelection && userSelection.isUnique()) {
			var result = this._evalScripts(selectionContext.evalMetadata, userSelection.getUniqueNode().getMetadata());
			if (!result) {
				if (selectionContext.behaviour == 'hidden') this.hide();else this.disable();
				return;
			}
		}
		if (!selectionContext.enableRoot && hasRoot) {
			return this.disable();
		}
		if (selectionContext.unique && !bUnique) {
			return this.disable();
		}
		if (selectionContext.multipleOnly && bUnique) {
			return this.disable();
		}
		if ((selectionContext.file || selectionContext.dir) && !bFile && !bDir) {
			return this.disable();
		}
		if (selectionContext.dir && !selectionContext.file && bFile || !selectionContext.dir && selectionContext.file && bDir) {
			return this.disable();
		}
		if (!selectionContext.recycle && bRecycle) {
			return this.disable();
		}
		if (this.rightsContext.write && userSelection.hasReadOnly()) {
			return this.disable();
		}
		if (selectionContext.allowedMimes.length && userSelection && selectionContext.allowedMimes.indexOf('*') == -1 && !userSelection.hasMime(selectionContext.allowedMimes)) {
			if (selectionContext.behaviour == 'hidden') return this.hide();else return this.disable();
		}
		if (selectionContext.allowedMimes.length && userSelection && selectionContext.allowedMimes.indexOf("^") !== -1) {
			var forbiddenValueFound = false;
			selectionContext.allowedMimes.forEach(function (m) {
				if (m.indexOf("^") == -1) return;
				if (userSelection.hasMime([m.replace("^", "")])) {
					forbiddenValueFound = true;
					//throw $break;
				}
			});
			if (forbiddenValueFound) {
				if (selectionContext.behaviour == 'hidden') return this.hide();else return this.disable();
			}
		}
		this.show();
		this.enable();
	};

	/**
  * Parses an XML fragment to configure this action
  * @param xmlNode Node XML Fragment describing the action
  */

	Action.prototype.createFromXML = function createFromXML(xmlNode) {

		this.options.name = xmlNode.getAttribute('name');
		for (var i = 0; i < xmlNode.childNodes.length; i++) {
			var node = xmlNode.childNodes[i];
			var defaultAttributes = {
				dir: "dirDefault",
				file: "fileDefault",
				dragndrop: "dragndropDefault",
				ctrldragndrop: "ctrlDragndropDefault",
				expire: "expireDefault"
			};
			for (var key in defaultAttributes) {
				if (!defaultAttributes.hasOwnProperty(key)) continue;
				var value = defaultAttributes[key];
				if (xmlNode.getAttribute(value) && xmlNode.getAttribute(value) == "true") {
					if (!this.defaults) this.defaults = {};
					this.defaults[key] = true;
				}
			}
			var j;
			if (node.nodeName == "processing") {
				var clientFormData = {};
				for (j = 0; j < node.childNodes.length; j++) {
					var processNode = node.childNodes[j];
					if (processNode.nodeName == "clientForm") {
						// TODO: Filtering by theme
						if (!processNode.getAttribute("theme") || pydio.Parameters.get('theme') == processNode.getAttribute("theme")) {
							clientFormData.formId = processNode.getAttribute("id");
							clientFormData.formCode = processNode.firstChild.nodeValue;
						}
					} else if (processNode.nodeName == "clientCallback") {
						if (processNode.getAttribute('prepareModal') && processNode.getAttribute('prepareModal') == "true") {
							this.options.prepareModal = true;
						}
						if (processNode.getAttribute('dialogOpenForm') || processNode.getAttribute("components")) {
							this.options.callbackDialogNode = processNode;
						} else if (processNode.firstChild) {
							this.options.callbackCode = processNode.firstChild.nodeValue.trim();
						}
					} else if (processNode.nodeName == "clientListener" && processNode.firstChild) {
						this.options.listeners[processNode.getAttribute('name')] = processNode.firstChild.nodeValue.trim();
					} else if (processNode.nodeName == "activeCondition" && processNode.firstChild) {
						this.options.activeCondition = new Function(processNode.firstChild.nodeValue.trim());
					}
				}
				if (clientFormData.formId) {
					this.options.formId = clientFormData.formId;
					this.options.formCode = clientFormData.formCode;
					if (this.options.formCode && this.options.formId) {
						this.manager.uiInsertForm(this.options.formId, this.options.formCode);
					}
				}
			} else if (node.nodeName == "gui") {
				this.options.text_id = node.getAttribute('text');
				this.options.title_id = node.getAttribute('title');
				this.options.text = this.manager.getMessage(node.getAttribute('text')) || 'not_found';
				this.options.title = this.manager.getMessage(node.getAttribute('title')) || 'not_found';
				this.options.src = node.getAttribute('src');
				this.options.icon_class = node.getAttribute('iconClass');
				if (node.getAttribute('hasAccessKey') && node.getAttribute('hasAccessKey') == "true") {
					this.options.accessKey = node.getAttribute('accessKey');
					this.options.hasAccessKey = true;
				}
				if (node.getAttribute('specialAccessKey')) {
					this.options.specialAccessKey = node.getAttribute('specialAccessKey');
				}
				for (j = 0; j < node.childNodes.length; j++) {
					if (node.childNodes[j].nodeName == "context") {
						this.attributesToObject(this.context, node.childNodes[j]);
						if (this.context.ajxpWidgets) {
							this.context.ajxpWidgets = this.context.ajxpWidgets.split(',');
						} else {
							this.context.ajxpWidgets = [];
						}
						// Backward compatibility
						if (this.context.infoPanel) this.context.ajxpWidgets.push('InfoPanel');
						if (this.context.actionBar) this.context.ajxpWidgets.push('ActionsToolbar');
					} else if (node.childNodes[j].nodeName == "selectionContext") {
						this.attributesToObject(this.selectionContext, node.childNodes[j]);
					}
				}
			} else if (node.nodeName == "rightsContext") {
				this.attributesToObject(this.rightsContext, node);
			} else if (node.nodeName == "subMenu") {
				this.options.subMenu = true;
				if (node.getAttribute("updateImageOnSelect") && node.getAttribute("updateImageOnSelect") == "true") {
					this.options.subMenuUpdateImage = true;
				}
				if (node.getAttribute("updateTitleOnSelect") && node.getAttribute("updateTitleOnSelect") == "true") {
					this.options.subMenuUpdateTitle = true;
				}
				for (j = 0; j < node.childNodes.length; j++) {
					if (node.childNodes[j].nodeName == "staticItems" || node.childNodes[j].nodeName == "dynamicItems") {
						this.subMenuItems[node.childNodes[j].nodeName] = [];
						for (var k = 0; k < node.childNodes[j].childNodes.length; k++) {
							if (node.childNodes[j].childNodes[k].nodeName.startsWith("item")) {
								var item = {};
								for (var z = 0; z < node.childNodes[j].childNodes[k].attributes.length; z++) {
									var attribute = node.childNodes[j].childNodes[k].attributes[z];
									item[attribute.nodeName] = attribute.value;
								}
								this.subMenuItems[node.childNodes[j].nodeName].push(item);
							}
						}
					} else if (node.childNodes[j].nodeName == "dynamicBuilder") {
						this.subMenuItems.dynamicBuilderCode = node.childNodes[j].firstChild.nodeValue;
					}
				}
			}
		}
		if (!this.options.hasAccessKey) return;
		if (this.options.accessKey == '' || !this.manager.getMessage(this.options.accessKey) || this.options.text.indexOf(this.manager.getMessage(this.options.accessKey)) == -1) {
			this.options.accessKey = this.options.text.charAt(0);
		} else {
			this.options.accessKey = this.manager.getMessage(this.options.accessKey);
		}
	};

	/**
  * Creates the submenu items
  */

	Action.prototype.buildSubmenuStaticItems = function buildSubmenuStaticItems() {
		var menuItems = [];
		if (this.subMenuItems.staticItems) {
			this.subMenuItems.staticItems.forEach(function (item) {
				var itemText = this.manager.getMessage(item.text);
				if (item.hasAccessKey && (item.hasAccessKey == 'true' || item.hasAccessKey === true) && this.manager.getMessage(item.accessKey)) {
					itemText = this.getKeyedText(this.manager.getMessage(item.text), true, this.manager.getMessage(item.accessKey));
					if (!this.subMenuItems.accessKeys) this.subMenuItems.accessKeys = [];
					this.manager.registerKey(this.manager.getMessage(item.accessKey), this.options.name, item.command);
				}
				menuItems.push({
					name: itemText,
					alt: this.manager.getMessage(item.title),
					image_unresolved: item.src,
					icon_class: item.icon_class,
					isDefault: item.isDefault ? true : false,
					callback: (function () {
						this.apply([item]);
					}).bind(this)
				});
			}, this);
		}
		this.subMenuItems.staticOptions = menuItems;
	};

	/**
  * Caches some data for dynamically built menus
  */

	Action.prototype.prepareSubmenuDynamicBuilder = function prepareSubmenuDynamicBuilder() {
		this.subMenuItems.dynamicBuilder = (function (protoMenu) {
			var menuItems;
			setTimeout((function () {
				if (this.subMenuItems.dynamicBuilderCode) {
					window.builderContext = this;
					window.builderProtoMenu = protoMenu;
					this._evalScripts(this.subMenuItems.dynamicBuilderCode);
					menuItems = this.builderMenuItems || [];
				} else {
					menuItems = [];
					this.subMenuItems.dynamicItems.forEach(function (item) {
						if (item.separator) {
							menuItems.push(item);
							return;
						}
						var action = this.manager.actions.get(item['actionId']);
						if (action.deny) return;
						var itemData = {
							name: action.getKeyedText(),
							alt: action.options.title,
							icon_class: action.options.icon_class,
							image_unresolved: action.options.src,
							callback: (function () {
								this.apply();
							}).bind(action)
						};
						if (action.options.subMenu) {
							itemData.subMenu = [];
							if (action.subMenuItems.staticOptions) {
								itemData.subMenu = action.subMenuItems.staticOptions;
							}
							if (action.subMenuItems.dynamicBuilder) {
								itemData.subMenuBeforeShow = action.subMenuItems.dynamicBuilder;
							}
						}
						menuItems.push(itemData);
					}, this);
				}
				protoMenu.options.menuItems = menuItems;
				protoMenu.refreshList();
			}).bind(this), 0);
		}).bind(this);
	};

	/**
  * Refresh icon image source
  * @param newSrc String The image source. Can reference an image library
     * @param iconClass String Optional class to replace image
  */

	Action.prototype.setIconSrc = function setIconSrc(newSrc, iconClass) {
		this.options.src = newSrc;
		var previousIconClass = this.options.icon_class;
		this.notify("update_icon", { new_src: newSrc, new_class: iconClass, previous_class: previousIconClass });
		if (iconClass) {
			this.options.icon_class = iconClass;
		}
	};

	/**
  * Refresh the action label
  * @param newLabel String the new label
  * @param newTitle String the new tooltip
  */

	Action.prototype.setLabel = function setLabel(newLabel, newTitle) {
		this.options.text = this.manager.getMessage(newLabel);
		this.notify("update_label", this.getKeyedText());
		if (newTitle) {
			this.options.title = this.manager.getMessage(newTitle);
			this.notify("update_title", this.options.title);
		}
	};

	Action.prototype.refreshInstances = function refreshInstances() {}
	/*
  TODO : UI Stuff, should be bound
  */
	/*
 $$('#action_instance_'+this.options.name).each(function(instance){
     // Check img
     var img;
     if(instance.firstChild.nodeType == Node.ELEMENT_NODE && instance.firstChild.nodeName.toLowerCase()=="img"){
         img = instance.firstChild.cloneNode(true);
     }
     instance.update(this.getKeyedText());
     if(img){
         instance.insert({top:img});
     }
 }.bind(this));
 */

	/**
  * Grab its label from the i18n
  */
	;

	Action.prototype.refreshFromI18NHash = function refreshFromI18NHash() {
		this.setLabel(this.options.text_id, this.options.title_id);
	};

	/**
  * Return data necessary to build InfoPanel
  * @returns Object
  */

	Action.prototype.toInfoPanel = function toInfoPanel() {
		return this.options;
	};

	/**
  * Return necessary data to build contextual menu
  * @returns Object
  */

	Action.prototype.toContextMenu = function toContextMenu() {
		return this.options;
	};

	/**
  * Changes show/hide state
  */

	Action.prototype.hideForContext = function hideForContext() {
		this.hide();
		this.contextHidden = true;
	};

	/**
  * Changes show/hide state
  */

	Action.prototype.showForContext = function showForContext(dataModel) {
		this.contextHidden = false;
		this.show();
		if (this.selectionContext) {
			this.fireSelectionChange(dataModel);
		}
	};

	/**
  * Changes show/hide state
  * Notifies "hide" Event
  */

	Action.prototype.hide = function hide() {
		this.deny = true;
		this.notify('hide');
	};

	/**
  * Changes show/hide state
  * Notifies "show" Event 
  */

	Action.prototype.show = function show() {
		this.deny = false;
		this.notify('show');
	};

	/**
  * Changes enable/disable state
  * Notifies "disable" Event 
  */

	Action.prototype.disable = function disable() {
		this.deny = true;
		this.notify('disable');
	};

	/**
  * Changes enable/disable state
  * Notifies "enable" Event 
  */

	Action.prototype.enable = function enable() {
		this.deny = false;
		this.notify('enable');
	};

	/**
  * To be called when removing
  */

	Action.prototype.remove = function remove() {
		this.notify("remove");
		if (this.options.formId) {
			this.manager.uiRemoveForm(this.options.formId);
		}
	};

	/**
  * Create a text label with access-key underlined.
  * @param displayString String the label
  * @param hasAccessKey Boolean whether there is an accessKey or not
  * @param accessKey String The key to underline
  * @returns String
  */

	Action.prototype.getKeyedText = function getKeyedText(displayString, hasAccessKey, accessKey) {
		if (!displayString) {
			displayString = this.options.text;
		}
		if (!hasAccessKey) {
			hasAccessKey = this.options.hasAccessKey;
		}
		if (!accessKey) {
			accessKey = this.options.accessKey;
		}
		if (!hasAccessKey) return displayString;
		var keyPos = displayString.toLowerCase().indexOf(accessKey.toLowerCase());
		if (keyPos == -1) {
			return displayString + ' (<u>' + accessKey + '</u>)';
		}
		if (displayString.charAt(keyPos) != accessKey) {
			// case differ
			accessKey = displayString.charAt(keyPos);
		}
		var returnString = displayString.substring(0, displayString.indexOf(accessKey));
		returnString += '<u>' + accessKey + '</u>';
		returnString += displayString.substring(displayString.indexOf(accessKey) + 1, displayString.length);
		return returnString;
	};

	/**
  * Utilitary function to transform XML Node attributes into Object mapping keys.
  * @param object Object The target object
  * @param node Node The source node
  */

	Action.prototype.attributesToObject = function attributesToObject(object, node) {
		for (var key in object) {
			if (!object.hasOwnProperty(key) || !node.getAttribute(key)) continue;
			var value = node.getAttribute(key);
			if (value == 'true') value = true;else if (value == 'false') value = false;
			if (key == 'allowedMimes') {
				if (value && value.split(',').length) {
					value = value.split(',');
				} else {
					value = [];
				}
			}
			object[key] = value;
		}
	};

	return Action;
})(Observable);
