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

    const {ActionDialogMixin,CancelButtonProviderMixin,SubmitButtonProviderMixin, Loader} = require('pydio').requireLib('boot');

    let pydio = global.pydio;

    class Callbacks{

        static alertButton() {
            var confs = pydio.getPluginConfigs("ajxp_plugin[@name='skeleton']");
            var target = confs.get('CUSTOM_BUTTON_TARGET');
            if(window.confirm(MessageHash['skeleton.3'].replace('%s', target))){
                window.open(target, "my_popup");
            }
        }

        static frameButton(){
            pydio.UI.openComponentInModal('SkeletonActions', 'Dialog');
        }

    }

    /**
     * Sample Dialog class used for reference only, ready to be
     * copy/pasted :-)
     */
    const SkeletonDialog = React.createClass({

        mixins:[
            ActionDialogMixin,
            CancelButtonProviderMixin,
            SubmitButtonProviderMixin
        ],

        getDefaultProps: function(){
            return {
                dialogTitle: "Demonstration Dialog",
                dialogIsModal: true
            };
        },
        submit(){
            this.dismiss();
        },
        componentDidMount: function(){
            PydioApi.getClient().request({get_action:'my_skeleton_button_frame'}, (transport)=>{
                this.setState({content: transport.responseText});
            });
        },
        render: function(){

            if(!this.state || !this.state.content){
                return <Loader/>;
            }
            return (
                <div dangerouslySetInnerHTML={{__html:this.state.content}}/>
            );
        }

    });

    const Footer = React.createClass({

        render: function(){
            return <div style={{zIndex: 1500, position:'absolute', bottom:0, left: 0, right: 0, padding:10, backgroundColor:'white'}}>Test Content</div>
        }

    });

    global.SkeletonActions = {
        Callbacks: Callbacks,
        Dialog   : SkeletonDialog,
        Template : Footer
    };

})(window)