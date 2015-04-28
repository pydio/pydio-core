"use strict";

var _classCallCheck = function (instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } };

var _createClass = (function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; })();

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
 * Abstraction of the currently logged user. Can be a "fake" user when users management
 * system is disabled
 */

var User = (function () {

	/**
  * Constructor
  * @param id String The user unique id
  * @param xmlDef XMLNode Registry Fragment
  */

	function User(id, xmlDef) {
		_classCallCheck(this, User);

		/**
   * @var String
   */
		this.id = id;
		/**
   * @var String
   */
		this.activeRepository = undefined;
		/**
   * @var Boolean
   */
		this.read = false;
		/**
   * @var Boolean
   */
		this.write = false,
		/**
   * @var Boolean
   */
		this.crossRepositoryCopy = false,
		/**
   * @var Map()
   */
		this.preferences = new Map(),
		/**
   * @var Map()
   */
		this.repositories = new Map(),
		/**
   * @var Map()
   */
		this.crossRepositories = new Map(),
		/**
   * @var Map()
   */
		this.repoIcons = new Map(),
		/**
   * @var Map()
   */
		this.repoSearchEngines = new Map(),
		/**
   * @var Boolean
   */
		this.isAdmin = false;
		/**
   * @var String
   */
		this.lock = false;
		/**
   *
   * @type Map
   * @private
   */
		this._parsedJSONCache = new Map();

		if (xmlDef) this.loadFromXml(xmlDef);
	}

	_createClass(User, [{
		key: "setActiveRepository",

		/**
   * Set current repository
   * @param id String
   * @param read Boolean
   * @param write Boolean
   */
		value: function setActiveRepository(id, read, write) {
			this.activeRepository = id;
			this.read = read == "1";
			this.write = write == "1";
			if (this.repositories.has(id)) {
				this.crossRepositoryCopy = this.repositories.get(id).allowCrossRepositoryCopy;
			}
			if (this.crossRepositories.has(id)) {
				this.crossRepositories["delete"](id);
			}
		}
	}, {
		key: "getActiveRepository",

		/**
   * Gets the current active repository
   * @returns String
   */
		value: function getActiveRepository() {
			return this.activeRepository;
		}
	}, {
		key: "canRead",

		/**
   * Whether current repo is allowed to be read
   * @returns Boolean
   */
		value: function canRead() {
			return this.read;
		}
	}, {
		key: "canWrite",

		/**
   * Whether current repo is allowed to be written
   * @returns Boolean
   */
		value: function canWrite() {
			return this.write;
		}
	}, {
		key: "canCrossRepositoryCopy",

		/**
   * Whether current repo is allowed to be cross-copied
   * @returns Boolean
   */
		value: function canCrossRepositoryCopy() {
			return this.crossRepositoryCopy;
		}
	}, {
		key: "getPreference",

		/**
   * Get a user preference by its name
   * @returns Mixed
   */
		value: function getPreference(prefName, fromJSON) {
			if (fromJSON) {
				var test = this._parsedJSONCache.get(prefName);
				if (test !== undefined) {
					return test;
				}
			}
			var value = this.preferences.get(prefName);
			if (fromJSON && value) {
				try {
					if (typeof value == "object") return value;
					var parsed = JSON.parse(value);
					this._parsedJSONCache.set(prefName, parsed);
					return parsed;
				} catch (e) {
					if (console) {
						console.log("Error parsing JSON in preferences (" + prefName + "). You should contact system admin and clear user preferences.");
					} else {
						alert("Error parsing JSON in preferences. You should contact system admin and clear user preferences.");
					}
				}
			}
			return value;
		}
	}, {
		key: "getRepositoriesList",

		/**
   * Get all repositories 
   * @returns Map
   */
		value: function getRepositoriesList() {
			return this.repositories;
		}
	}, {
		key: "setPreference",

		/**
   * Set a preference value
   * @param prefName String
   * @param prefValue Mixed
   * @param toJSON Boolean Whether to convert the value to JSON representation
   */
		value: function setPreference(prefName, prefValue) {
			var toJSON = arguments[2] === undefined ? false : arguments[2];

			if (toJSON) {
				this._parsedJSONCache["delete"](prefName);
				try {
					prefValue = JSON.stringify(prefValue);
				} catch (e) {
					if (console) {
						var isCyclic = function (obj) {
							var seenObjects = [];

							function detect(obj) {
								if (obj && typeof obj === "object") {
									if (seenObjects.indexOf(obj) !== -1) {
										return true;
									}
									seenObjects.push(obj);
									for (var key in obj) {
										if (obj.hasOwnProperty(key) && detect(obj[key])) {
											console.log(obj, "cycle at " + key);
											return true;
										}
									}
								}
								return false;
							}
							return detect(obj);
						};

						console.log("Caught toJSON error " + e.message, prefValue, isCyclic(prefValue));
					}
					return;
				}
			}
			this.preferences.set(prefName, prefValue);
		}
	}, {
		key: "setRepositoriesList",

		/**
   * Set the repositories as a bunch
   * @param repoHash Map
   */
		value: function setRepositoriesList(repoHash) {
			this.repositories = repoHash;
			// filter repositories once for all
			this.crossRepositories = new Map();
			this.repositories.forEach((function (value, key) {
				if (value.allowCrossRepositoryCopy) {
					this.crossRepositories.set(key, value);
				}
			}).bind(this));
		}
	}, {
		key: "hasCrossRepositories",

		/**
   * Whether there are any repositories allowing crossCopy
   * @returns Boolean
   */
		value: function hasCrossRepositories() {
			return this.crossRepositories.size;
		}
	}, {
		key: "getCrossRepositories",

		/**
   * Get repositories allowing cross copy
   * @returns {Map}
   */
		value: function getCrossRepositories() {
			return this.crossRepositories;
		}
	}, {
		key: "getRepositoryIcon",

		/**
   * Get the current repository Icon
   * @param repoId String
   * @returns String
   */
		value: function getRepositoryIcon(repoId) {
			return this.repoIcon.get(repoId);
		}
	}, {
		key: "getRepoSearchEngine",

		/**
   * Get the repository search engine
   * @param repoId String
   * @returns String
   */
		value: function getRepoSearchEngine(repoId) {
			return this.repoSearchEngines.get(repoId);
		}
	}, {
		key: "savePreference",

		/**
   * Send the preference to the server for saving
   * @param prefName String
   */
		value: function savePreference(prefName) {
			if (!this.preferences.has(prefName)) {
				return;
			}var prefValue = this.preferences.get(prefName);
			window.setTimeout(function () {
				PydioApi.getClient().userSavePreference(prefName, prefValue);
			}, 250);
		}
	}, {
		key: "savePreferences",

		/**
   * Send all preferences to the server. If oldPass, newPass and seed are set, also save pass.
   * @param oldPass String
   * @param newPass String
   * @param seed String
   * @param onCompleteFunc Function
   */
		value: function savePreferences(oldPass, newPass, seed, onCompleteFunc) {
			if (oldPass && newPass) {
				PydioApi.getClient().userSavePassword(oldPass, newPass, seed, onCompleteFunc);
			} else {
				PydioApi.getClient().userSavePreferences(this.preferences, onCompleteFunc);
			}
		}
	}, {
		key: "loadFromXml",

		/**
   * Parse the registry fragment to load this user
   * @param userNodes DOMNode
   */
		value: function loadFromXml(userNodes) {

			var repositories = new Map();
			var i, j;
			for (i = 0; i < userNodes.length; i++) {
				if (userNodes[i].nodeName == "active_repo") {
					var activeNode = userNodes[i];
				} else if (userNodes[i].nodeName == "repositories") {
					for (j = 0; j < userNodes[i].childNodes.length; j++) {
						var repoChild = userNodes[i].childNodes[j];
						if (repoChild.nodeName == "repo") {
							var repository = new Repository(repoChild.getAttribute("id"), repoChild);
							repositories.set(repoChild.getAttribute("id"), repository);
						}
					}
					this.setRepositoriesList(repositories);
				} else if (userNodes[i].nodeName == "preferences") {
					for (j = 0; j < userNodes[i].childNodes.length; j++) {
						var prefChild = userNodes[i].childNodes[j];
						if (prefChild.nodeName == "pref") {
							var value = prefChild.getAttribute("value");
							if (!value && prefChild.firstChild) {
								// Retrieve value from CDATA
								value = prefChild.firstChild.nodeValue;
							}
							this.setPreference(prefChild.getAttribute("name"), value);
						}
					}
				} else if (userNodes[i].nodeName == "special_rights") {
					var attr = userNodes[i].getAttribute("is_admin");
					if (attr && attr == "1") this.isAdmin = true;
					if (userNodes[i].getAttribute("lock")) {
						this.lock = userNodes[i].getAttribute("lock");
					}
				}
			}
			// Make sure it happens at the end
			if (activeNode) {
				this.setActiveRepository(activeNode.getAttribute("id"), activeNode.getAttribute("read"), activeNode.getAttribute("write"));
			}
		}
	}]);

	return User;
})();