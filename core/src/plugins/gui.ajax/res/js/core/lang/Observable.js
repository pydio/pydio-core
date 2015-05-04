'use strict';

var _classCallCheck = function (instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError('Cannot call a class as a function'); } };

var Observable = (function () {
    function Observable() {
        _classCallCheck(this, Observable);
    }

    Observable.prototype._objectEventSetup = function _objectEventSetup(event_name) {
        this._observers = this._observers || {};
        this._observers[event_name] = this._observers[event_name] || [];
    };

    Observable.prototype.observe = function observe(event_name, observer) {
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
    };

    Observable.prototype.stopObserving = function stopObserving(event_name, observer) {
        this._objectEventSetup(event_name);
        if (event_name && observer) this._observers[event_name] = this._observers[event_name].filter(function (o) {
            return o != observer;
        });else if (event_name) {
            this._observers[event_name] = [];
        } else {
            this._observers = {};
        }
    };

    Observable.prototype.observeOnce = function observeOnce(event_name, outer_observer) {
        var inner_observer = (function () {
            outer_observer.apply(this, arguments);
            this.stopObserving(event_name, inner_observer);
        }).bind(this);
        this._objectEventSetup(event_name);
        this._observers[event_name].push(inner_observer);
    };

    Observable.prototype.notify = function notify(event_name) {
        this._objectEventSetup(event_name);
        var collected_return_values = [];
        var args = Array.from(arguments).slice(1);
        for (var i = 0; i < this._observers[event_name].length; ++i) {
            collected_return_values.push(this._observers[event_name][i].apply(this._observers[event_name][i], args) || null);
        }
        return collected_return_values;
    };

    return Observable;
})();