'use strict';

var _createClass = (function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ('value' in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; })();

var _get = function get(_x4, _x5, _x6) { var _again = true; _function: while (_again) { var object = _x4, property = _x5, receiver = _x6; _again = false; if (object === null) object = Function.prototype; var desc = Object.getOwnPropertyDescriptor(object, property); if (desc === undefined) { var parent = Object.getPrototypeOf(object); if (parent === null) { return undefined; } else { _x4 = parent; _x5 = property; _x6 = receiver; _again = true; desc = parent = undefined; continue _function; } } else if ('value' in desc) { return desc.value; } else { var getter = desc.get; if (getter === undefined) { return undefined; } return getter.call(receiver); } } };

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError('Cannot call a class as a function'); } }

function _inherits(subClass, superClass) { if (typeof superClass !== 'function' && superClass !== null) { throw new TypeError('Super expression must either be null or a function, not ' + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

(function (global) {

    /*********************************************
    /* ShareNotification Object
    /*
    /* Handling the display and actions for the
    /* notification coming with a local or remote
    /* share
    /*********************************************/

    var ShareNotification = (function (_Observable) {
        _inherits(ShareNotification, _Observable);

        // Init

        function ShareNotification(share, options) {
            _classCallCheck(this, ShareNotification);

            _get(Object.getPrototypeOf(ShareNotification.prototype), 'constructor', this).call(this);
            this._data = {};

            this.setShareStatus(share.status);
            this.setOwner(share.owner);
            this.setLabel(share.label);
            this.setCreationDate(share.cr_date);
            this.setActions(share.actions);

            this.options = options || {};
        }

        // Globals

        // Getters / Setters

        _createClass(ShareNotification, [{
            key: 'setShareStatus',
            value: function setShareStatus(status) {
                this._data['status'] = status;
            }
        }, {
            key: 'setOwner',
            value: function setOwner(owner) {
                this._data['owner'] = owner;
            }
        }, {
            key: 'setLabel',
            value: function setLabel(label) {
                this._data['label'] = label;
            }
        }, {
            key: 'setCreationDate',
            value: function setCreationDate(crDate) {
                this._data['cr_date'] = crDate;
            }
        }, {
            key: 'setActions',
            value: function setActions(actions) {
                this._data['actions'] = actions;
            }
        }, {
            key: 'getShareStatus',
            value: function getShareStatus() {
                return this._data['status'];
            }
        }, {
            key: 'getOwner',
            value: function getOwner() {
                return this._data['owner'];
            }
        }, {
            key: 'getLabel',
            value: function getLabel() {
                return this._data['label'];
            }
        }, {
            key: 'getCreationDate',
            value: function getCreationDate() {
                return this._data['cr_date'];
            }
        }, {
            key: 'getActions',
            value: function getActions() {
                return this._data['actions'];
            }
        }, {
            key: 'getFormattedDate',
            value: function getFormattedDate() {
                var crDate = new Date();

                crDate.setTime(this.getCreationDate() * 1000);

                return formatDate(crDate);
            }

            // Actions
        }, {
            key: 'loadAction',
            value: function loadAction(options) {

                var statusOnSuccess = options.statusOnSuccess;

                delete options['statusOnSuccess'];

                ShareNotification.loadAction(options, (function (transport) {
                    if (statusOnSuccess) {
                        // Transition the status of the share
                        this.setShareStatus(statusOnSuccess);
                        ReactDispatcher.ShareNotificationDispatcher.getClient().setStatus('idle');
                    }
                }).bind(this));
            }

            // Static (eq Client)
        }], [{
            key: 'loadAction',
            value: function loadAction(options) {
                var completeCallback = arguments.length <= 1 || arguments[1] === undefined ? null : arguments[1];
                var errorCallback = arguments.length <= 2 || arguments[2] === undefined ? null : arguments[2];
                var settings = arguments.length <= 3 || arguments[3] === undefined ? {} : arguments[3];

                PydioApi.getClient().request(options, completeCallback, errorCallback, settings);
            }
        }]);

        return ShareNotification;
    })(Observable);

    var ReactModel = global.ReactModel || {};
    ReactModel['ShareNotification'] = ShareNotification;
    global.ReactModel = ReactModel;
    global.ReactModelShareNotification = ShareNotification; // Set for dependencies management
})(window);
