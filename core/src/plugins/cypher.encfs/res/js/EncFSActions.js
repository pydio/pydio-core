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

    const pydio = global.pydio;
    const MessageHash = pydio.MessageHash;

    class Callbacks{

        static createCypheredFolder(){


            const submitCallback = function(passValue){

                PydioApi.getClient().request({
                    get_action:'encfs.cypher_folder',
                    file:pydio.getUserSelection().getUniqueNode().getPath(),
                    pass: passValue
                }, function(transport){
                    PydioApi.getClient().parseXmlMessage(transport.responseXML);
                });

            };

            pydio.UI.openComponentInModal('EncFSActions', 'CypherDialog', {
                submitValue: submitCallback
            });

        }

        static cypherFolder(){

            PydioApi.getClient().request({
                get_action:'encfs.cypher_folder',
                file:pydio.getUserSelection().getUniqueNode().getPath()
            }, function(transport){
                PydioApi.getClient().parseXmlMessage(transport.responseXML);
            });

        }

        static uncypherFolder(){

            const submitCallback = function(passValue){

                PydioApi.getClient().request({
                    get_action:'encfs.uncypher_folder',
                    file:pydio.getUserSelection().getUniqueNode().getPath(),
                    pass: passValue
                }, function(transport){
                    PydioApi.getClient().parseXmlMessage(transport.responseXML);
                });

            };

            pydio.UI.openComponentInModal('PydioReactUI', 'PromptDialog', {
                submitValue: submitCallback,
                dialogTitleId: 'encfs.3',
                legendId: 'encfs.7',
                fieldLabelId: 'encfs.8',
                fieldType: 'password'
            });
        }

    }

    const CypherDialog = React.createClass({

        propTypes: {
            dialogTitleId:React.PropTypes.integer,
            legendId:React.PropTypes.integer,
            fieldLabelId:React.PropTypes.integer,
            fieldType: React.PropTypes.oneOf(['text', 'password']),
            submitValue:React.PropTypes.func.isRequired,
            defaultValue:React.PropTypes.string,
            defaultInputSelection:React.PropTypes.string
        },

        mixins:[
            PydioReactUI.ActionDialogMixin,
            PydioReactUI.CancelButtonProviderMixin,
            PydioReactUI.SubmitButtonProviderMixin
        ],

        getDefaultProps: function(){
            return {
                dialogTitle: MessageHash['encfs.2'],
                dialogIsModal: true
            };
        },
        submit(){
            const in1 = this.refs.input1.getValue();
            const in2 = this.refs.input2.getValue();
            if(in1 === in2){
                this.props.submitValue(in1);
                this.dismiss();
            }else{
                this.setState({error: MessageHash[238]});
            }
        },
        render: function(){
            const error = this.state && this.state.error ? (<div>{this.state.error}</div>) : null;
            return (
                <div>
                    <div className="dialogLegend">{MessageHash['encfs.10']}</div>
                    <MaterialUI.TextField
                        floatingLabelText={MessageHash['encfs.8']}
                        ref="input1"
                        onKeyDown={this.submitOnEnterKey}
                        type="password"
                    />
                    <MaterialUI.TextField
                        floatingLabelText={MessageHash['encfs.9']}
                        ref="input2"
                        onKeyDown={this.submitOnEnterKey}
                        type="password"
                    />
                    {error}
                </div>
            );
        }

    });


    global.EncFSActions = {
        Callbacks: Callbacks,
        CypherDialog: CypherDialog
    };

})(window)