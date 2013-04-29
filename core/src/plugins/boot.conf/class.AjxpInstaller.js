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
        this.formManager = new FormManager();
        this.initForm();
    },

    initForm : function(){
        var params = new Hash();
        params.set("get_action", "load_installer_form");
        var connexion = new Connexion();
        connexion.setParameters(params);
        this.htmlElement.update('<div class="dialogLegend">This quick wizard will help you set up your AjaXplorer installation in a couple of minutes. Don\'t worry, you will be able to change all settings later while using the application.</div>');
        connexion.onComplete = function(transport){
            var params = this.formManager.parseParameters(transport.responseXML, "//global_param");
            this.formManager.createParametersInputs(this.htmlElement,  params, true);
            this.updateAndBindButton(this.htmlElement.select('.SF_inlineButton').last());
        }.bind(this);
        connexion.sendAsync();
    },

    updateAndBindButton : function(startButton){
        //startButton.removeClassName('SF_input').addClassName('largeButton');
        startButton.setStyle({fontSize: '19px'});
        startButton.stopObserving("click");
        startButton.observe("click", function(){
            var conn = new Connexion();
            var params = new Hash({get_action: "apply_installer_form"});
            this.formManager.serializeParametersInputs(this.htmlElement, params);
            conn.setParameters(params);
            conn.sendAsync();
        }.bind(this));
    }

});