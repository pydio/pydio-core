/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
const PassUtils = require('pydio/util/pass');
const PydioDataModel = require('pydio/model/data-model');
import React from 'react'
import {TextField} from 'material-ui'

const CreateUserForm = React.createClass({

    propTypes:{
        dataModel: React.PropTypes.instanceOf(PydioDataModel),
        openRoleEditor: React.PropTypes.func
    },

    mixins:[
        AdminComponents.MessagesConsumerMixin,
        PydioReactUI.ActionDialogMixin,
        PydioReactUI.CancelButtonProviderMixin,
        PydioReactUI.SubmitButtonProviderMixin
    ],

    getDefaultProps: function(){
        return {
            dialogSize:'sm',
            dialogTitleId: 'ajxp_admin.user.19'
        }
    },

    getInitialState: function(){
        const passState = PassUtils.getState();
        return {
            step:1,
            ...passState
        }
    },

    checkPassword:function(){
        const value1 = this.refs.pass.getValue();
        const value2 = this.refs.passconf.getValue();
        this.setState(PassUtils.getState(value1, value2, this.state));
    },

    submit: function(dialog){
        if(!this.state.valid){
            this.props.pydio.UI.displayMessage('ERROR', this.state.passErrorText || this.state.confirmErrorText);
            return;
        }

        let parameters = {};
        const ctx = this.props.dataModel.getUniqueNode() || this.props.dataModel.getContextNode();
        parameters['get_action'] = 'create_user';
        parameters['new_user_login'] = this.refs.user_id.getValue();
        parameters['new_user_pwd'] = this.refs.pass.getValue();
        const currentPath = ctx.getPath();
        if(currentPath.startsWith("/data/users")){
            parameters['group_path'] = currentPath.substr("/data/users".length);
        }
        PydioApi.getClient().request(parameters, function(transport){
            const xml = transport.responseXML;
            const message = XMLUtils.XPathSelectSingleNode(xml, "//reload_instruction");
            if(message){
                let node = new AjxpNode(currentPath + "/"+ parameters['new_user_login'], true);
                node.getMetadata().set("ajxp_mime", "user");
                //global.pydio.UI.openCurrentSelectionInEditor(node);
                this.props.openRoleEditor(node);
                let currentNode = global.pydio.getContextNode();
                if(global.pydio.getContextHolder().getSelectedNodes().length){
                    currentNode = global.pydio.getContextHolder().getSelectedNodes()[0];
                }
                currentNode.reload();
            }
        }.bind(this));
        this.dismiss();
    },

    render: function(){
        const ctx = this.props.dataModel.getUniqueNode() || this.props.dataModel.getContextNode();
        const currentPath = ctx.getPath();
        let path;
        if(currentPath.startsWith("/data/users")){
            path = currentPath.substr("/data/users".length);
            if(path){
                path = <div>{this.context.getMessage('ajxp_admin.user.20').replace('%s', path)}</div>;
            }
        }
        return (
            <div>
                {path}
                <TextField
                    ref="user_id"
                    fullWidth={true}
                    floatingLabelText={this.context.getMessage('ajxp_admin.user.21')}
                />
                <TextField
                    ref="pass"
                    type="password"
                    fullWidth={true}
                    floatingLabelText={this.context.getMessage('ajxp_admin.user.22')}
                    onChange={this.checkPassword}
                    errorText={this.state.passErrorText || this.state.passHintText}
                />
                <TextField
                    ref="passconf"
                    type="password"
                    fullWidth={true}
                    floatingLabelText={this.context.getMessage('ajxp_admin.user.23')}
                    onChange={this.checkPassword}
                    errorText={this.state.confirmErrorText}
                />
            </div>
        );
    }
});

export {CreateUserForm as default}