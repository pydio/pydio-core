/*
 * Copyright 2007-2016 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <https://pydio.com/>.
 *
 */
/**
 *
 * Utils to compute password strength
 *
 */
"use strict";

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

var PassUtils = (function () {
    function PassUtils() {
        _classCallCheck(this, PassUtils);
    }

    PassUtils.getOptions = function getOptions() {
        if (PassUtils.Options) {
            return PassUtils.Options;
        }
        PassUtils.Options = {
            pydioMessages: [379, 380, 381, 382, 383, 384, 385],
            messages: ["Unsafe password word!", "Too short", "Very weak", "Weak", "Medium", "Strong", "Very strong"],
            colors: ["#f00", "#999", "#C70F0F", "#C70F0F", "#FF8432", "#279D00", "#279D00"],
            scores: [10, 15, 30, 40],
            common: ["password", "123456", "123", "1234", "mypass", "pass", "letmein", "qwerty", "monkey", "asdfgh", "zxcvbn", "pass"],
            minchar: 8
        };
        return PassUtils.Options;
    };

    PassUtils.checkPasswordStrength = function checkPasswordStrength(value, callback) {
        // Update with Pydio options
        PassUtils.getOptions();
        if (!PassUtils.Options.pydioMinChar && window.pydio) {
            var pydioMin = parseInt(window.pydio.getPluginConfigs("core.auth").get("PASSWORD_MINLENGTH"));
            PassUtils.Options.pydioMinChar = true;
            if (pydioMin) {
                PassUtils.Options.minchar = pydioMin;
            }
        }
        var options = PassUtils.Options;
        var strength = PassUtils.getPasswordScore(value, options.minchar);
        if (strength == -200) {
            callback(0, 0);
        } else {
            if (strength < 0 && strength > -199) {
                callback(1, 10);
            } else {
                if (strength <= options.scores[0]) {
                    callback(2, 10);
                } else {
                    if (strength > options.scores[0] && strength <= options.scores[1]) {
                        callback(3, 25);
                    } else if (strength > options.scores[1] && strength <= options.scores[2]) {
                        callback(4, 55);
                    } else if (strength > options.scores[2] && strength <= options.scores[3]) {
                        callback(5, 80);
                    } else {
                        callback(6, 98);
                    }
                }
            }
        }
    };

    PassUtils.getPasswordScore = function getPasswordScore(value, minchar) {

        var strength = 0;
        if (value.length < minchar) {
            strength = strength - 100;
        } else {
            if (value.length >= minchar && value.length <= minchar + 2) {
                strength = strength + 6;
            } else {
                if (value.length >= minchar + 3 && value.length <= minchar + 4) {
                    strength = strength + 12;
                } else {
                    if (value.length >= minchar + 5) {
                        strength = strength + 18;
                    }
                }
            }
        }
        if (value.match(/[a-z]/)) {
            strength = strength + 1;
        }
        if (value.match(/[A-Z]/)) {
            strength = strength + 5;
        }
        if (value.match(/\d+/)) {
            strength = strength + 5;
        }
        if (value.match(/(.*[0-9].*[0-9].*[0-9])/)) {
            strength = strength + 7;
        }
        if (value.match(/.[!,@,#,$,%,^,&,*,?,_,~]/)) {
            strength = strength + 5;
        }
        if (value.match(/(.*[!,@,#,$,%,^,&,*,?,_,~].*[!,@,#,$,%,^,&,*,?,_,~])/)) {
            strength = strength + 7;
        }
        if (value.match(/([a-z].*[A-Z])|([A-Z].*[a-z])/)) {
            strength = strength + 2;
        }
        if (value.match(/([a-zA-Z])/) && value.match(/([0-9])/)) {
            strength = strength + 3;
        }
        if (value.match(/([a-zA-Z0-9].*[!,@,#,$,%,^,&,*,?,_,~])|([!,@,#,$,%,^,&,*,?,_,~].*[a-zA-Z0-9])/)) {
            strength = strength + 3;
        }
        var common = ["password", "123456", "123", "1234", "mypass", "pass", "letmein", "qwerty", "monkey", "asdfgh", "zxcvbn", "pass"];
        if (common.indexOf(value.toLowerCase()) !== -1) {
            strength = -200;
        }
        return strength;
    };

    return PassUtils;
})();
