/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */
Class.create("AjxpInstaller", AjxpPane, {

    initialize: function($super, element, options){
        $super(element, options);
        modal.setCloseValidation(function(){
            return false;
        });
        this.formManager = new FormManager();
        this.formElement = this.htmlElement.down("#the_form");
        this.formElement.ajxpPaneObject = this;
        this.initForm();
    },

    initForm : function(){
        var params = new Hash();
        params.set("get_action", "load_installer_form");
        var connexion = new Connexion();
        connexion.setParameters(params);
        connexion.onComplete = function(transport){
            var params = this.formManager.parseParameters(transport.responseXML, "//global_param");
            this.formManager.createParametersInputs(this.formElement,  params, true, false, false, false, false, true);
            this.formElement.SF_accordion.observe("animation-finished", function(){
                modal.refreshDialogPosition();
            });
            this.formElement.select("select").invoke("observe", "change", function(){
                modal.refreshDialogPosition();
            });
            var observer = function(){
                var passValidating = (this.formElement.down('input[name="ADMIN_USER_PASS"]').getValue() == this.formElement.down('input[name="ADMIN_USER_PASS2"]').getValue());
                passValidating = passValidating && (this.formElement.down('input[name="ADMIN_USER_PASS"]').PROTOPASS.strength > 0);
                if(!passValidating){
                    this.formElement.down('input[name="ADMIN_USER_PASS"]').addClassName("SF_failed");
                    this.formElement.down('input[name="ADMIN_USER_PASS2"]').addClassName("SF_failed");
                }else{
                    this.formElement.down('input[name="ADMIN_USER_PASS"]').removeClassName("SF_failed");
                    this.formElement.down('input[name="ADMIN_USER_PASS2"]').removeClassName("SF_failed");
                }

                var missing = this.formManager.serializeParametersInputs(this.formElement, new Hash(), '', true);
                missing = (missing > 0 || !this.formElement.down('select[name="STORAGE_TYPE"]').getValue() || !passValidating);

                if(!missing){
                    this.htmlElement.select('.SF_inlineButton').last().removeClassName("disabled");
                }else{
                    this.htmlElement.select('.SF_inlineButton').last().addClassName("disabled");
                }
            }.bind(this);
            this.formManager.observeFormChanges(this.formElement, observer, 50);
            this.formElement.select('select').invoke("observe", "change", function(){
                this.formManager.observeFormChanges(this.formElement, observer, 50);
            }.bind(this));
            this.updateAndBindButton(this.htmlElement.select('.SF_inlineButton').last());
            this.bindPassword(this.formElement.down('input[name="ADMIN_USER_PASS"]').up('div.accordion_content'));
            this.formElement.ajxpPaneObject.observe("after_replicate_row", function(newRow){
                this.bindPassword(newRow);
            }.bind(this));
            this.htmlElement.down("#start_button").observe("click", function(){
                this.htmlElement.down(".installerWelcome").update("Click on each section to edit parameters");
                new Effect.Appear(this.formElement, {afterFinish : function(){
                    this.formElement.SF_accordion.activate(this.formElement.down('.accordion_toggle'));
                }.bind(this)});
            }.bind(this));
        }.bind(this);
        connexion.sendAsync();
    },

    bindPassword : function(contentDiv){
        var passes = contentDiv.select('input[type="password"]');
        var container = new Element("div", {className:'SF_element'});
        passes[1].up('div.SF_element').insert({after:container});
        var p = new Protopass(passes[0], {
            barContainer:container,
            barPosition:'bottom'
        });
        passes[0].PROTOPASS = p;
    },

    updateAndBindButton : function(startButton){
        //startButton.removeClassName('SF_input').addClassName('largeButton');
        startButton.stopObserving("click");
        startButton.observe("click", function(){
            if(startButton.hasClassName("disabled")) return;
            var conn = new Connexion();
            var params = new Hash({get_action: "apply_installer_form"});
            this.formManager.serializeParametersInputs(this.formElement, params);
            conn.setParameters(params);
            conn.onComplete = function(transport){
                if(transport.responseText == "OK"){
                    document.location.reload(true);
                }
            };
            conn.sendAsync();
        }.bind(this));
    }

});