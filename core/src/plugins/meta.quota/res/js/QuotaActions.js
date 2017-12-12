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

    let pydio = global.pydio;
    let MessageHash = global.pydio.MessageHash;

    class Model extends Observable{

        constructor(){
            super();
            this.usage = '';
            this.total = '';
            this.startListening();

            pydio.observe('repository_list_refreshed', function(){
                if(!this.quotaEnabled() && this._pe){
                    this._pe.stop();
                }else{
                    this.loadQuota();
                }
            }.bind(this));
        }

        quotaEnabled(){
            return !! pydio.getController().getActionByName('monitor_quota');
        }

        getText(){
            if(!this.usage || !this.quotaEnabled()){
                return '';
            }
            return PathUtils.roundFileSize(this.usage, MessageHash["byte_unit_symbol"]) + "/"
                + PathUtils.roundFileSize(this.total, MessageHash["byte_unit_symbol"]);
        }

        getUsage(){
            return this.usage;
        }

        getTotal(){
            return this.total;
        }

        static getInstance(){
            if(!Model._INSTANCE){
                Model._INSTANCE = new Model();
            }
            return Model._INSTANCE;
        }

        loadQuota(){
            if(!this.quotaEnabled()) return;
            PydioApi.getClient().request({get_action:'monitor_quota'}, (transport) => {
                if(!this.quotaEnabled()) return;
                const data = transport.responseJSON;
                this.usage = data.USAGE;
                this.total = data.TOTAL;
                this.notify('update');
            });
        }

        startListening(){
            const configs = pydio.getPluginConfigs("mq");
            if(configs){
                pydio.observe("server_message", (event) => {
                    const newValue = XMLUtils.XPathSelectSingleNode(event, "/tree/metaquota");
                    if(newValue){
                        this.usage = parseInt(newValue.getAttribute("usage"));
                        this.total = parseInt(newValue.getAttribute("total"));
                        this.notify('update');
                    }
                });
            }else{
                this._pe = new PeriodicalExecuter(function(pe){
                    if(!this.quotaEnabled()) {
                        pe.stop();
                        return;
                    }
                    this.loadQuota();
                }, 20);
            }
            this.loadQuota();
        }

        getState(){
            return {
                text: this.getText(),
                total: this.getTotal(),
                usage: this.getUsage()
            };
        }

    }

    class Callbacks{

        static computeQuota(manager, args){

        }
        
        

    }

    class Listeners {

        static init() {
            Model.getInstance();
        }

    }

    class QuotaPanel extends React.Component{


        constructor(props){
            super(props);
            const model = Model.getInstance();
            this.state = model.getState();
            this._observer = () => {
                this.setState(model.getState());
            };
            model.observe('update', this._observer);
        }

        componentWillUnmount(){
            Model.getInstance().stopObserving('update', this._observer);
        }

        render(){
            let model = Model.getInstance();
            return (
                <PydioWorkspaces.InfoPanelCard title={this.props.pydio.MessageHash['meta.quota.4']} icon="speedometer" iconColor="#1565c0">
                    <div>{model.getText()}</div>
                    <div style={{paddingBottom: 20}}>
                        <MaterialUI.LinearProgress mode="determinate" min={0} max={model.getTotal()} value={model.getUsage()}/>
                    </div>
                </PydioWorkspaces.InfoPanelCard>
            );
        }

    }

    global.QuotaActions = {
        Callbacks: Callbacks,
        Listeners:Listeners,
        Model: Model,
        QuotaPanel: QuotaPanel
    };

})(window)