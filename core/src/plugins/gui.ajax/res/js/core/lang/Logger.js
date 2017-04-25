"use strict";

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

var Logger = (function () {
    function Logger() {
        _classCallCheck(this, Logger);
    }

    Logger.log = function log(message) {
        if (window.console) console.log(message);
    };

    Logger.error = function error(message) {
        if (window.console) console.error(message);
    };

    Logger.debug = function debug(message) {
        if (window.console) console.debug(message);
    };

    return Logger;
})();
