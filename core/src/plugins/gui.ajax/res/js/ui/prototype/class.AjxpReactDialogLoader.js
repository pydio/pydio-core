/*
 * Copyright 2007-2015 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <http://pyd.io/>.
 */

/**
 * Simple Wrapper for React Component
 */
Class.create('AjxpReactDialogLoader', AjxpPane, {

    reactComponent:null,
    componentNamespace: null,
    componentName: null,
    options: null,
    rootNodeId:null,

    initialize: function(componentNamespace, componentName, options){
        this.componentNamespace = componentNamespace;
        this.componentName = componentName;
        this.options = options;
        if(!this.options) this.options = {};
        this.options.pydio = pydio;
    },

    /**
     *
     * @param sFormId
     * @param bSkipButtons
     * @param bOkButtonOnly
     * @param bUseNextButton
     */
    openDialog: function(sFormId, bSkipButtons, bOkButtonOnly, bUseNextButton){
        this.rootNodeId = sFormId;
        modal.showDialogForm('Get',
            sFormId,
            this.dialogLoaded.bind(this),
            this.submit.bind(this),
            this.dialogWillClose.bind(this),
            bOkButtonOnly,
            bSkipButtons,
            bUseNextButton
        );
    },

    cancel: function(oForm){
        console.log('cancel');

    },

    submit: function(oForm){
        console.log('submit');

    },

    dismiss: function(oForm){
        this.dialogWillClose(oForm);
        hideLightBox();
    },

    dialogLoaded: function(oForm){

        this.options.closeAjxpDialog = function(){
            this.dismiss(oForm);
        }.bind(this);

        var namespacesToLoad = [this.componentNamespace];
        if(this.options.dependencies){
            this.options.dependencies.forEach(function(d){
                namespacesToLoad.push(d);
            });
        }
        ResourcesManager.loadClassesAndApply(namespacesToLoad, function(){
            this.reactComponent = React.render(
                React.createElement(window[this.componentNamespace][this.componentName], this.options),
                oForm.down('#'+this.rootNodeId));
        }.bind(this));
    },

    dialogWillClose: function(oForm){
        console.log('close');
        this.reactComponent = null;
        React.unmountComponentAtNode(oForm.down('#'+this.rootNodeId));
    }

});