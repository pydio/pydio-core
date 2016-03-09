'use strict';

var _createClass = (function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ('value' in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; })();

var _get = function get(_x, _x2, _x3) { var _again = true; _function: while (_again) { var object = _x, property = _x2, receiver = _x3; _again = false; if (object === null) object = Function.prototype; var desc = Object.getOwnPropertyDescriptor(object, property); if (desc === undefined) { var parent = Object.getPrototypeOf(object); if (parent === null) { return undefined; } else { _x = parent; _x2 = property; _x3 = receiver; _again = true; desc = parent = undefined; continue _function; } } else if ('value' in desc) { return desc.value; } else { var getter = desc.get; if (getter === undefined) { return undefined; } return getter.call(receiver); } } };

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

    var ShareNotificationDispatcher = (function (_Observable) {
        _inherits(ShareNotificationDispatcher, _Observable);

        // Init

        function ShareNotificationDispatcher(options) {
            _classCallCheck(this, ShareNotificationDispatcher);

            _get(Object.getPrototypeOf(ShareNotificationDispatcher.prototype), 'constructor', this).call(this);
            this.setStatus('idle');
        }

        // Globals

        _createClass(ShareNotificationDispatcher, [{
            key: 'getStatus',
            value: function getStatus() {
                return this._status;
            }

            // GENERIC: STATUS / LOAD / SAVE
        }, {
            key: 'setStatus',
            value: function setStatus(status) {
                this._status = status;

                this.notify('status_changed', {
                    status: status
                });
            }
        }], [{
            key: 'getClient',
            value: function getClient() {
                if (ShareNotificationDispatcher._client) return ShareNotificationDispatcher._client;
                var client = new ShareNotificationDispatcher();
                ShareNotificationDispatcher._client = client;
                return client;
            }
        }]);

        return ShareNotificationDispatcher;
    })(Observable);

    var ReactDispatcher = global.ReactDispatcher || {};
    ReactDispatcher['ShareNotificationDispatcher'] = ShareNotificationDispatcher;
    global.ReactDispatcher = ReactDispatcher;
    global.ReactShareNotificationDispatcher = ShareNotificationDispatcher; // Set for dependencies management
})(window);
