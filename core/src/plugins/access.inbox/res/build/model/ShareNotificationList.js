"use strict";

var _createClass = (function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; })();

var _get = function get(_x4, _x5, _x6) { var _again = true; _function: while (_again) { var object = _x4, property = _x5, receiver = _x6; _again = false; if (object === null) object = Function.prototype; var desc = Object.getOwnPropertyDescriptor(object, property); if (desc === undefined) { var parent = Object.getPrototypeOf(object); if (parent === null) { return undefined; } else { _x4 = parent; _x5 = property; _x6 = receiver; _again = true; desc = parent = undefined; continue _function; } } else if ("value" in desc) { return desc.value; } else { var getter = desc.get; if (getter === undefined) { return undefined; } return getter.call(receiver); } } };

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function, not " + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

(function (global) {

    /**************************************************
    /* ShareNotificationList Object
    /*
    /* Handling the display of Notification Collection
    /**************************************************/

    var ShareNotificationList = (function (_Observable) {
        _inherits(ShareNotificationList, _Observable);

        // Init

        function ShareNotificationList(pydio, options) {
            _classCallCheck(this, ShareNotificationList);

            _get(Object.getPrototypeOf(ShareNotificationList.prototype), "constructor", this).call(this);
            this._data = { shares: [] };
            this._pydio = pydio;
            this.options = options || {};

            this.load();
            this._pydio.observe("registry_part_loaded", (function () {
                this.load();
            }).bind(this));
        }

        // Globals

        // Getters / Setters

        _createClass(ShareNotificationList, [{
            key: "getShares",
            value: function getShares() {
                if (!this._data["shares"]) return [];
                return this._data["shares"];
            }
        }, {
            key: "getSharesByStatus",
            value: function getSharesByStatus(status) {
                var currentShares = this.getShares(),
                    shares = [],
                    share;

                for (var i in currentShares) {
                    share = currentShares[i];
                    if (typeof share.getShareStatus === 'function' && (typeof status == 'number' && share.getShareStatus() == status || typeof status == 'object' && status.indexOf(share.getShareStatus()) > -1)) {
                        shares.push(currentShares[i]);
                    }
                }

                return shares;
            }

            // Actions
        }, {
            key: "load",
            value: function load() {
                if (ReactDispatcher.ShareNotificationDispatcher.getClient().getStatus() == 'loading') return;
                ReactDispatcher.ShareNotificationDispatcher.getClient().setStatus('loading');

                ShareNotificationList.loadShares(this.options, (function (transport) {
                    if (transport.responseJSON && transport.responseJSON.shares) {
                        this._data.shares = [];

                        var shares = transport.responseJSON.shares;

                        for (var i = 0; i < shares.length; i++) {
                            var share = new ReactModel.ShareNotification(shares[i]);
                            this._data.shares.push(share);
                        }

                        ReactDispatcher.ShareNotificationDispatcher.getClient().setStatus('idle');
                    }
                }).bind(this));
            }

            // Static (eq Client)
        }], [{
            key: "loadShares",
            value: function loadShares(defaultOptions) {
                var completeCallback = arguments.length <= 1 || arguments[1] === undefined ? null : arguments[1];
                var errorCallback = arguments.length <= 2 || arguments[2] === undefined ? null : arguments[2];
                var settings = arguments.length <= 3 || arguments[3] === undefined ? {} : arguments[3];

                var options = Object.assign(defaultOptions, {
                    get_action: 'load_shares'
                });

                PydioApi.getClient().request(options, completeCallback, errorCallback, settings);
            }
        }]);

        return ShareNotificationList;
    })(Observable);

    var ReactModel = global.ReactModel || {};
    ReactModel['ShareNotificationList'] = ShareNotificationList;
    global.ReactModel = ReactModel;
    global.ReactModelShareNotificationList = ShareNotificationList; // Set for dependencies management
})(window);
