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

(function(global){

    let DisclaimerDialog = React.createClass({

        mixins:[
            PydioReactUI.ActionDialogMixin,
            PydioReactUI.SubmitButtonProviderMixin
        ],

        getDefaultProps: function(){
            return {
                dialogTitle: global.pydio.MessageHash['disclaimer.3'],
                dialogIsModal: true,
                dialogScrollBody: true,
                dialogSize:'lg'
            };
        },

        getInitialState: function(){
            return {disclaimer:'', accepted: false}
        },

        submit(){
            PydioApi.getClient().request({
                get_action:'validate_disclaimer',
                validate: this.state.accepted ? 'true' : 'false'
            }, function(t){
                if(this.state.accepted){
                    global.setTimeout(function(){
                        global.pydio.loadXmlRegistry();
                    }, 400);
                    this.dismiss();
                }
            }.bind(this));
        },
        onCheck(object, isChecked){
            this.setState({accepted: isChecked});
        },
        componentDidMount: function(){
            PydioApi.getClient().request({
                get_action:'load_disclaimer'
            },function(transport){
                var resp = transport.responseText;
                var state = resp.substring(0, resp.indexOf(":")) == "yes" ? true : false;
                var text = resp.substring(resp.indexOf(":")+1);
                this.setState({
                    disclaimer: text,
                    accepted: state
                });
            }.bind(this));
        },
        render: function(){
            let content = function(){
                return {__html:this.state.disclaimer}
            }.bind(this);
            let messages = global.pydio.MessageHash;
            return (
                <div style={{width: '100%'}}>
                    <div id="disclaimer_content" style={{height: 350, overflow:'auto', margin:'15px 0', padding: 10}} dangerouslySetInnerHTML={content()}></div>
                    <div style={{paddingBottom: 20}}>
                        <MaterialUI.Checkbox label={messages['disclaimer.4']} onCheck={this.onCheck} checked={this.state.accepted}/>
                    </div>
                </div>

            );
        }

    });

    class Callbacks{

        static validate(){

            pydio.UI.openComponentInModal('DisclaimerActions', 'DisclaimerDialog', {});

        }

    }

    global.DisclaimerActions = {
        DisclaimerDialog: DisclaimerDialog,
        Callbacks: Callbacks
    };

})(window)