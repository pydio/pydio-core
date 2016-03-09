'use strict';

var _createClass = (function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ('value' in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; })();

var _get = function get(_x4, _x5, _x6) { var _again = true; _function: while (_again) { var object = _x4, property = _x5, receiver = _x6; _again = false; if (object === null) object = Function.prototype; var desc = Object.getOwnPropertyDescriptor(object, property); if (desc === undefined) { var parent = Object.getPrototypeOf(object); if (parent === null) { return undefined; } else { _x4 = parent; _x5 = property; _x6 = receiver; _again = true; desc = parent = undefined; continue _function; } } else if ('value' in desc) { return desc.value; } else { var getter = desc.get; if (getter === undefined) { return undefined; } return getter.call(receiver); } } };

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError('Cannot call a class as a function'); } }

function _inherits(subClass, superClass) { if (typeof superClass !== 'function' && superClass !== null) { throw new TypeError('Super expression must either be null or a function, not ' + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

(function (global) {
    var ShareList = (function (_Observable) {
        _inherits(ShareList, _Observable);

        function ShareList(pydio) {
            _classCallCheck(this, ShareList);

            _get(Object.getPrototypeOf(ShareList.prototype), 'constructor', this).call(this);
            this._status = 'idle';
            this._data = {};
            this._pydio = pydio;
            this.load();
        }

        _createClass(ShareList, [{
            key: 'getStatus',
            value: function getStatus() {
                return this._status;
            }
        }, {
            key: 'revertChanges',
            value: function revertChanges() {
                this._setStatus('idle');
            }

            /*****************************************/
            /*  SHARES
             /*****************************************/
        }, {
            key: 'getShares',
            value: function getShares() {
                if (!this._data["shares"]) return [];
                return this._data["shares"];
            }

            /*********************************/
            /* GENERIC: STATUS / LOAD / SAVE */
            /*********************************/
        }, {
            key: '_setStatus',
            value: function _setStatus(status) {
                this._status = status;
                this.notify('status_changed', {
                    status: status,
                    shareList: this
                });
            }
        }, {
            key: 'load',
            value: function load() {
                if (this._status == 'loading') return;
                this._setStatus('loading');

                ShareList.loadShares((function (transport) {
                    if (transport.responseJSON) {
                        this._data = transport.responseJSON;
                        this._setStatus('idle');
                    }
                }).bind(this), function () {});
            }
        }], [{
            key: 'loadShares',
            value: function loadShares() {
                var completeCallback = arguments.length <= 0 || arguments[0] === undefined ? null : arguments[0];
                var errorCallback = arguments.length <= 1 || arguments[1] === undefined ? null : arguments[1];
                var settings = arguments.length <= 2 || arguments[2] === undefined ? {} : arguments[2];

                var options = {
                    get_action: 'load_shares'
                };

                PydioApi.getClient().request(options, completeCallback, errorCallback, settings);
            }
        }]);

        return ShareList;
    })(Observable);

    var ReactModel = global.ReactModel || {};
    ReactModel['ShareList'] = ShareList;
    global.ReactModel = ReactModel;
    // Set for dependencies management
    global.ReactModelShareList = ShareList;
})(window);
