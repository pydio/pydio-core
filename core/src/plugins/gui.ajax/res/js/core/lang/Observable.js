'use strict';

var _createClass = (function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ('value' in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; })();

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError('Cannot call a class as a function'); } }

var Observable = (function () {
    function Observable() {
        _classCallCheck(this, Observable);
    }

    _createClass(Observable, [{
        key: '_objectEventSetup',
        value: function _objectEventSetup(event_name) {
            this._observers = this._observers || {};
            this._observers[event_name] = this._observers[event_name] || [];
        }
    }, {
        key: 'observe',
        value: function observe(event_name, observer) {
            if (typeof event_name == 'string' && typeof observer != 'undefined') {
                this._objectEventSetup(event_name);
                if (this._observers[event_name].indexOf(observer) == -1) this._observers[event_name].push(observer);
            } else {
                for (var e in event_name) {
                    if (event_name.hasOwnProperty(e)) {
                        this.observe(e, event_name[e]);
                    }
                }
            }
        }
    }, {
        key: 'stopObserving',
        value: function stopObserving(event_name, observer) {
            this._objectEventSetup(event_name);
            if (event_name && observer) this._observers[event_name] = this._observers[event_name].filter(function (o) {
                return o != observer;
            });else if (event_name) {
                this._observers[event_name] = [];
            } else {
                this._observers = {};
            }
        }
    }, {
        key: 'observeOnce',
        value: function observeOnce(event_name, outer_observer) {
            var inner_observer = (function () {
                outer_observer.apply(this, arguments);
                this.stopObserving(event_name, inner_observer);
            }).bind(this);
            this._objectEventSetup(event_name);
            this._observers[event_name].push(inner_observer);
        }
    }, {
        key: 'notify',
        value: function notify(event_name) {
            this._objectEventSetup(event_name);
            var collected_return_values = [];
            var args = Array.from(arguments).slice(1);
            for (var i = 0; i < this._observers[event_name].length; ++i) {
                collected_return_values.push(this._observers[event_name][i].apply(this._observers[event_name][i], args) || null);
            }
            return collected_return_values;
        }
    }]);

    return Observable;
})();
