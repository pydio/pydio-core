'use strict';

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError('Cannot call a class as a function'); } }

var LangUtils = (function () {
    function LangUtils() {
        _classCallCheck(this, LangUtils);
    }

    LangUtils.objectMerge = function objectMerge(obj1, obj2) {
        for (var k in obj2) {
            if (obj2.hasOwnProperty(k)) {
                obj1[k] = obj2[k];
            }
        }
        return obj1;
    };

    LangUtils.parseUrl = function parseUrl(data) {
        var matches = $A();
        //var e=/((http|ftp):\/)?\/?([^:\/\s]+)((\/\w+)*\/)([\w\-\.]+\.[^#?\s]+)(#[\w\-]+)?/;
        var detect = /(((ajxp\.)(\w+)):\/)?\/?([^:\/\s]+)((\/\w+)*\/)(.*)(#[\w\-]+)?/g;
        var results = data.match(detect);
        if (results && results.length) {
            var e = /^((ajxp\.(\w+)):\/)?\/?([^:\/\s]+)((\/\w+)*\/)(.*)(#[\w\-]+)?$/;
            for (var i = 0; i < results.length; i++) {
                if (results[i].match(e)) {
                    matches.push({ url: RegExp['$&'],
                        protocol: RegExp.$2,
                        host: RegExp.$4,
                        path: RegExp.$5,
                        file: RegExp.$7,
                        hash: RegExp.$8 });
                }
            }
        }
        return matches;
    };

    LangUtils.computeStringSlug = function computeStringSlug(value) {
        for (var i = 0, len = LangUtils.slugTable.length; i < len; i++) value = value.replace(LangUtils.slugTable[i].re, LangUtils.slugTable[i].ch);

        // 1) met en bas de casse
        // 2) remplace les espace par des tirets
        // 3) enleve tout les caratères non alphanumeriques
        // 4) enlève les doubles tirets
        return value.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '').replace(/\-{2,}/g, '-');
    };

    LangUtils.forceJSONArrayToObject = function forceJSONArrayToObject(container, value) {
        if (container[value] instanceof Array) {
            // Clone
            var copy = container[value].slice(0);
            container[value] = {};
            for (var i = 0; i < copy.length; i++) {
                container[value][i] = copy[i];
            }
        }
    };

    LangUtils.mergeObjectsRecursive = function mergeObjectsRecursive(source, destination) {
        var newObject = {},
            property;
        for (property in source) {
            if (source.hasOwnProperty(property)) {
                //if (source[property] === null) continue;
                if (destination.hasOwnProperty(property)) {
                    if (source[property] instanceof Object && destination instanceof Object) {
                        newObject[property] = LangUtils.mergeObjectsRecursive(source[property], destination[property]);
                    } else {
                        newObject[property] = destination[property];
                    }
                } else {
                    if (source[property] instanceof Object) {
                        newObject[property] = LangUtils.mergeObjectsRecursive(source[property], {});
                    } else {
                        newObject[property] = source[property];
                    }
                }
            }
        }
        for (property in destination) {
            if (destination.hasOwnProperty(property) && !newObject.hasOwnProperty(property) /*&& destination[property] !== null*/) {
                if (destination[property] instanceof Object) {
                    newObject[property] = LangUtils.mergeObjectsRecursive(destination[property], {});
                } else {
                    newObject[property] = destination[property];
                }
            }
        }
        return newObject;
    };

    return LangUtils;
})();

LangUtils.slugTable = [{ re: /[\xC0-\xC6]/g, ch: 'A' }, { re: /[\xE0-\xE6]/g, ch: 'a' }, { re: /[\xC8-\xCB]/g, ch: 'E' }, { re: /[\xE8-\xEB]/g, ch: 'e' }, { re: /[\xCC-\xCF]/g, ch: 'I' }, { re: /[\xEC-\xEF]/g, ch: 'i' }, { re: /[\xD2-\xD6]/g, ch: 'O' }, { re: /[\xF2-\xF6]/g, ch: 'o' }, { re: /[\xD9-\xDC]/g, ch: 'U' }, { re: /[\xF9-\xFC]/g, ch: 'u' }, { re: /[\xC7-\xE7]/g, ch: 'c' }, { re: /[\xD1]/g, ch: 'N' }, { re: /[\xF1]/g, ch: 'n' }];