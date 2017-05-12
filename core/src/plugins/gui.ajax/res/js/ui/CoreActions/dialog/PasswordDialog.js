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
 * The latest code can be found at <https://pydio.com>.
 */

import {pydio} from '../globals'
const React = require('react')
const PydioApi = require('pydio/http/api')
const BootUI = require('pydio/http/resources-manager').requireLib('boot');
const {ActionDialogMixin, SubmitButtonProviderMixin, CancelButtonProviderMixin, AsyncComponent} = BootUI;

const PasswordDialog = React.createClass({

    mixins:[
        ActionDialogMixin,
        CancelButtonProviderMixin,
        SubmitButtonProviderMixin
    ],
    getInitialState: function(){
        return {passValid: false};
    },
    getDefaultProps: function(){
        return {
            dialogTitle: pydio.MessageHash[194],
            dialogIsModal: true
        };
    },
    submit(){
        if(!this.state.passValid){
            return false;
        }
        this.refs.passwordForm.getComponent().post(function(value){
            if(value) this.dismiss();
        }.bind(this));
    },

    passValidStatusChange: function(status){
        this.setState({passValid: status});
    },

    render: function(){

        return (
            <AsyncComponent
                namespace="UserAccount"
                componentName="PasswordForm"
                pydio={this.props.pydio}
                ref="passwordForm"
                onValidStatusChange={this.passValidStatusChange}
            />
        );
    }

});

export default PasswordDialog