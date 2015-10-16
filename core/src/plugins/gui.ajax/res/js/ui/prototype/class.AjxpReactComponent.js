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
Class.create('AjxpReactComponent', AjxpPane, {

    initialize: function($super, htmlElement, options){
        $super(htmlElement, options);

        var namespacesToLoad = [options.componentNamespace];
        if(options.dependencies){
            options.dependencies.forEach(function(d){
                namespacesToLoad.push(d);
            });
        }
        ResourcesManager.loadClassesAndApply(namespacesToLoad, function(){
            this.reactComponent = React.render(
                React.createElement(window[options.componentNamespace][options.componentName], {
                    pydio:pydio
                }),
                $(htmlElement)
            );
        }.bind(this));
    },

    destroy: function($super){
        this.reactComponent = null;
        React.unmountComponentAtNode(this.htmlElement);
        $super();
    }

});