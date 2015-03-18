/*  
Copyright (c) 2010 Bermi Ferrer Martinez
 
Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:
 
The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.
 
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/
/* Simple password strength checker for prototype.
 *  (c) 2010 Bermi Ferrer
 *
 *  Inspired by http://plugins.jquery.com/project/pstrength
 *
 *  Distributable under the terms of an MIT-style license.
 *  For details, see the git site: 
 *  http://github.com/bermi/protopass
 *
 *--------------------------------------------------------------------------*/

var Protopass = Class.create({
    initialize : function(item, options) {
        this.item = $(item);
        this.field_name = this.item.id;
        this.options = {
            messages: ["Unsafe password word!", "Too short", "Very weak", "Weak", "Medium", "Strong", "Very strong"],
            colors: ["#f00", "#999", "#f00", "#c06", "#f60", "#3c0", "#2c0"],
            scores: [10, 15, 30, 40],
            common: ["password", "123456", "123", "1234", "mypass", "pass", "letmein", "qwerty", "monkey", "asdfgh", "zxcvbn", "pass"],
            minchar: 8,
            barContainer : this.item,
            barPosition : 'after',
            labelWidth: 39
        };
        if(window.MessageHash){
        	var keys = [379,380,381,382,383,384,385];
        	for(var key in keys){
                if(keys.hasOwnProperty(key)){
            		this.options.messages[key] = window.MessageHash[keys[key]];
                }
        	}
        }
        if(window.ajxpBootstrap && parseInt(window.ajaxplorer.getPluginConfigs("core.auth").get("PASSWORD_MINLENGTH"))){
        	this.options.minchar = parseInt(window.ajaxplorer.getPluginConfigs("core.auth").get("PASSWORD_MINLENGTH"));
        }
        Object.extend(this.options, options || { });
        var ins = {};
        var string = "<div class=\"password-strength-info\" style=\"float:left; padding-top: 2px; width:"+this.options.labelWidth+"%;text-align:right;\" id=\""+this.field_name+"_text\"></div>";
        string += "<div style=\"float:left;width:"+(100-this.options.labelWidth - (Prototype.Browser.IE?7:0))+"%;\"><div class=\"password-strength-bar\" id=\""+this.field_name+"_bar\" style=\""+(Prototype.Browser.IE?"":"margin-top:5px;")+"height:0px; width: 0px;\"></div></div>";
        ins[this.options.barPosition] = string;
        this.options.barContainer.insert(ins);

        this.bar = $(this.field_name + "_bar");
        this.feedback_text = $(this.field_name + "_text");

        this.item.observe('keyup', function () {
            this.checkUserPasswordStrength();
        }.bind(this));
        if(modal){
	        this.observeOnce('strength_change', function(){
	        	modal.refreshDialogAppearance();
	        });
        }
    },
    
    checkUserPasswordStrength: function () {
        var options = this.options;
        var value = this.item.value;

        var strength = this.getPasswordScore(value, options);
        this.strength = strength;

        if (strength == -200) {
            this.displayPasswordStrengthFeedback(0, 0);
        } else {
            if (strength < 0 && strength > -199) {
                this.displayPasswordStrengthFeedback(1, 10);
            } else {
                if (strength <= options.scores[0]) {
                    this.displayPasswordStrengthFeedback(2, 10);
                } else {
                    if (strength > options.scores[0] && strength <= options.scores[1]) {
                        this.displayPasswordStrengthFeedback(3, 25);
                    } else if (strength > options.scores[1] && strength <= options.scores[2]) {
                        this.displayPasswordStrengthFeedback(4, 55);
                    } else if (strength > options.scores[2] && strength <= options.scores[3]) {
                        this.displayPasswordStrengthFeedback(5, 80);
                    } else {
                        this.displayPasswordStrengthFeedback(6, 98);
                    }
                }
            }
        }
        this.notify('strength_change');
    },

    displayPasswordStrengthFeedback: function(setting_index, percent_rate){
        this.feedback_text.innerHTML = "<span style='color: " + this.options.colors[setting_index] + ";'>" + this.options.messages[setting_index] + " &nbsp;&nbsp; </span>";
        this.bar.setStyle('height: '+(Prototype.Browser.IE?"5px":"8px;")+'; width:'+percent_rate+'%;background-color:'+this.options.colors[setting_index]);
        this.options.barContainer.show();
    },
    
    getPasswordScore: function (value, options) {
        var strength = 0;
        if (value.length < options.minchar) {
            strength = (strength - 100);
        } else {
            if (value.length >= options.minchar && value.length <= (options.minchar + 2)) {
                strength = (strength + 6);
            } else {
                if (value.length >= (options.minchar + 3) && value.length <= (options.minchar + 4)) {
                    strength = (strength + 12);
                } else {
                    if (value.length >= (options.minchar + 5)) {
                        strength = (strength + 18);
                    }
                }
            }
        }
        if (value.match(/[a-z]/)) {
            strength = (strength + 1);
        }
        if (value.match(/[A-Z]/)) {
            strength = (strength + 5);
        }
        if (value.match(/\d+/)) {
            strength = (strength + 5);
        }
        if (value.match(/(.*[0-9].*[0-9].*[0-9])/)) {
            strength = (strength + 7);
        }
        if (value.match(/.[!,@,#,$,%,^,&,*,?,_,~]/)) {
            strength = (strength + 5);
        }
        if (value.match(/(.*[!,@,#,$,%,^,&,*,?,_,~].*[!,@,#,$,%,^,&,*,?,_,~])/)) {
            strength = (strength + 7);
        }
        if (value.match(/([a-z].*[A-Z])|([A-Z].*[a-z])/)) {
            strength = (strength + 2);
        }
        if (value.match(/([a-zA-Z])/) && value.match(/([0-9])/)) {
            strength = (strength + 3);
        }
        if (value.match(/([a-zA-Z0-9].*[!,@,#,$,%,^,&,*,?,_,~])|([!,@,#,$,%,^,&,*,?,_,~].*[a-zA-Z0-9])/)) {
            strength = (strength + 3);
        }
        for (var i = 0; i < options.common.length; i++) {
            if (value.toLowerCase() == options.common[i]) {
                strength = -200
            }
        }
        return strength;
    }
});
