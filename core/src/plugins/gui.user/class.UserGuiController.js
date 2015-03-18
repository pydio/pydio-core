/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
Class.create("UserGuiController", AjxpPane, {

    _currentAction: null,

    initialize: function(element){
        try{
            this._currentAction = document.location.pathname.substr('/user/'.length).split('/')[0];
            ajaxplorer.actionBar.fireAction(this._currentAction);
        }catch(e){
            if(console) console.log(e);
        }

    }

});

document.observe('ajaxplorer:loaded', function(){
    if(!ajaxplorer.UIG) ajaxplorer.UIG = new UserGuiController($('user-gui-controller'));
});
