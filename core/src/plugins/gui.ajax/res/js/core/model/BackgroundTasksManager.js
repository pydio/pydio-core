/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * Manage background tasks
 */
'use strict';

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError('Cannot call a class as a function'); } }

function _inherits(subClass, superClass) { if (typeof superClass !== 'function' && superClass !== null) { throw new TypeError('Super expression must either be null or a function, not ' + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

var BackgroundTasksManager = (function (_Observable) {
	_inherits(BackgroundTasksManager, _Observable);

	/**
  * Constructor
  * @param actionManager ActionManager
  */

	function BackgroundTasksManager(actionManager) {
		_classCallCheck(this, BackgroundTasksManager);

		_Observable.call(this);
		this.queue = [];
		this.actionManager = actionManager;
		this.working = false;
	}

	/**
  * Add an action to the queue
  * @param actionName String Name of the action
  * @param parameters Object Parameters of the action, NOT HASH
  * @param messageId String An i18n id of the message to be displayed during the action
  */

	BackgroundTasksManager.prototype.queueAction = function queueAction(actionName, parameters, messageId) {
		var actionDef = {};
		actionDef['name'] = actionName;
		actionDef['messageId'] = messageId;
		actionDef['parameters'] = parameters;
		this.queue.push(actionDef);
	};

	/**
  * Processes the next action in the queue
  */

	BackgroundTasksManager.prototype.next = function next() {
		if (!this.queue.length) {
			this.finished();
			return;
		}
		if (this.working) return;
		var actionDef = this.queue[0];
		if (actionDef['name'] == "javascript_instruction" && actionDef['parameters']['callback']) {
			var cb = actionDef['parameters']['callback'];
			this.notify("update_message", actionDef['messageId']);
			this.queue.shift();
			cb();
			this.working = false;
			this.next();
		} else {
			var client = PydioApi.getClient();
			var params = { get_action: actionDef['name'] };
			for (var k in actionDef['parameters']) {
				if (actionDef['parameters'].hasOwnProperty(k)) params[k] = actionDef['parameters'][k];
			}
			client.request(params, (function (transport) {
				var xmlResponse = transport.responseXML;
				if (xmlResponse == null || xmlResponse.documentElement == null) {
					this.working = false;
					this.next();
					return;
				}
				this.parseAnswer(transport.responseXML);
				this.working = false;
			}).bind(this), null, { method: 'POST' });
			this.notify("update_message", actionDef['messageId']);
			this.queue.shift();
			this.working = true;
		}
	};

	/**
  * Parses the response. Should probably use the actionBar parser instead.
  * @param xmlResponse XMLDocument
  */

	BackgroundTasksManager.prototype.parseAnswer = function parseAnswer(xmlResponse) {
		var childs = xmlResponse.documentElement.childNodes;
		var delay = 0;
		for (var i = 0; i < childs.length; i++) {
			if (childs[i].tagName == "message") {
				var type = childs[i].getAttribute('type');
				if (type != 'SUCCESS') {
					this.interruptOnError(childs[i].firstChild.nodeValue);
				}
			} else if (childs[i].nodeName == 'nodes_diff') {
				var dm = this.actionManager.getDataModel();
				if (dm.getAjxpNodeProvider().parseAjxpNodesDiffs) {
					dm.getAjxpNodeProvider().parseAjxpNodesDiffs(childs[i], dm, !window.currentLightBox);
				}
			} else if (childs[i].nodeName == "trigger_bg_action") {
				var name = childs[i].getAttribute("name");
				var messageId = childs[i].getAttribute("messageId");
				delay = parseInt(childs[i].getAttribute("delay"));
				var parameters = {};
				for (var j = 0; j < childs[i].childNodes.length; j++) {
					var paramChild = childs[i].childNodes[j];
					if (paramChild.tagName == 'param') {
						parameters[paramChild.getAttribute("name")] = paramChild.getAttribute("value");
					} else if (paramChild.tagName == 'clientCallback') {
						var callbackCode = paramChild.firstChild.nodeValue;
						var callback = new Function(callbackCode);
					}
				}
				if (name == "reload_node") {
					if (delay) {
						window.setTimeout((function () {
							this.actionManager.getDataModel().requireContextChange(this.actionManager.getDataModel().getContextNode(), true);
							this.next();
						}).bind(this), delay * 1000);
						return;
					}
					this.actionManager.getDataModel().requireContextChange(this.actionManager.getDataModel().getContextNode(), true);
				} else if (name == "info_message") {
					this.notify("update_message", messageId);
				} else if (name == "javascript_instruction" && callback) {
					parameters["callback"] = callback;
					this.queueAction('javascript_instruction', parameters, messageId);
				} else {
					this.queueAction(name, parameters, messageId);
				}
			}
		}
		this.working = false;
		if (delay) {
			window.setTimeout(this.next.bind(this), delay * 1000);
		} else {
			this.next();
		}
	};

	/**
  * Interrupt the task on error
  * @param errorMessage String
  */

	BackgroundTasksManager.prototype.interruptOnError = function interruptOnError(errorMessage) {
		if (this.queue.length) this.queue = [];
		this.notify("update_message_error", errorMessage);
		this.working = false;
	};

	/**
  * All tasks are processed
  */

	BackgroundTasksManager.prototype.finished = function finished() {
		this.working = false;
		this.notify("tasks_finished");
	};

	/**
  * Create a stub action with not parameter.
  */

	BackgroundTasksManager.prototype.addStub = function addStub() {
		this.queueAction('local_to_remote', {}, 'Stubing a 10s bg action');
	};

	return BackgroundTasksManager;
})(Observable);
