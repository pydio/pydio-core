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

const React = require('react')
const {FlatButton} = require('material-ui')
import Dashboard from '../board/Dashboard'
const XMLUtils = require('pydio/util/xml')
const LangUtils = require('pydio/util/lang')
const AjxpNode = require('pydio/model/node')
const Pydio = require('pydio')
const {ActionDialogMixin} = Pydio.requireLib('boot')
const {Manager} = Pydio.requireLib('form')
const {MessagesConsumerMixin} = window.AdminComponents;

const TaskEditor = React.createClass({

    mixins:[MessagesConsumerMixin, ActionDialogMixin],

    propTypes: {
        pydio:React.PropTypes.instanceOf(Pydio).isRequired,
        node:React.PropTypes.instanceOf(AjxpNode),
    },

    getDefaultProps: function(){
        return {
            dialogSize: 'md',
            dialogPadding:0,
            dialogScrollBody:true
        }
    },

    getButtons: function(updater = null){
        if(updater) this._buttonsUpdater = updater;
        return [
            <FlatButton default={true} onTouchTap={this.dismiss} label="Close"/>,
            this.previousButton(),
            this.nextSaveButton(),
        ]

    },

    updateActionDescription: function(actionList, actionValue){
        if(actionList.has(actionValue)){
            this.setState({
                currentActionData:actionList.get(actionValue)
            });
        }
    },

    onParameterChange: function(paramName, newValue, oldValue, additionalFormData){
        if(paramName === "schedule"){

            this.setState({cron:newValue});

        }else if(paramName === "action_name"){
            if(this._actionsList) {
                this.updateActionDescription(this._actionsList, newValue);
            } else{
                PydioApi.getClient().request({get_action:"list_all_plugins_actions"}, function(t){
                    if(!t.responseJSON || !t.responseJSON.LIST) return;
                    let _actionsList = new Map();
                    for(let plugin in t.responseJSON.LIST){
                        if(!t.responseJSON.LIST.hasOwnProperty(plugin)) continue;
                        t.responseJSON.LIST[plugin].map(function(a){
                            _actionsList.set(a.action, a);
                        });
                    }
                    this.updateActionDescription(_actionsList, newValue);
                    this._actionsList = _actionsList;
                }.bind(this));
            }
        }

    },

    onFormChange: function(newValues, dirty, removeValues){
        this.setState({values:newValues});
    },

    save: function(){
        let post = this.refs.formPanel.getValuesForPOST(this.state.values, '');
        post['get_action'] = 'scheduler_addTask';
        if(this.state.node){
            post['task_id'] = this.state.node.getMetadata().get('task_id');
        }
        PydioApi.getClient().request(post, function(){
            this.close();
            if(Dashboard.getInstance()){
                Dashboard.getInstance().refreshTasks();
            }
        }.bind(this));
    },

    previousButton: function(){

        if(this.state.node || this.state.tab === 0){
            return null;
        }

        let prevTab = function(){
            this.setState({tab: this.state.tab-1}, () => {
                this.refs.formPanel.externallySelectTab(this.state.tab);
                if(this._buttonsUpdater) this._buttonsUpdater(this.getButtons());
            });
        }.bind(this);
        return <FlatButton secondary={true} onTouchTap={prevTab} label="Previous"/>;
    },


    nextSaveButton: function(){
        if(this.state.node || this.state.tab === 2){
            return <FlatButton secondary={true} onTouchTap={this.save} label="Save"/>;
        }
        let nextTab = function(){
            this.setState({tab: this.state.tab+1}, () => {
                this.refs.formPanel.externallySelectTab(this.state.tab);
                if(this._buttonsUpdater) this._buttonsUpdater(this.getButtons());
            });
        }.bind(this);
        return <FlatButton secondary={true} onTouchTap={nextTab} label="Next"/>;
    },

    tabChange: function(tabIndex, tab){
        this.setState({tab:tabIndex}, () => {
            if(this._buttonsUpdater) this._buttonsUpdater(this.getButtons());
        });
    },

    getInitialState:function(){
        if(!this.props.node){
            return {
                tab:0,
                values:{
                    schedule:'0 3 * * *',
                    repository_id:'ajxp_conf',
                    user_id:this.props.pydio.user.id
                },
                cron:'0 3 * * *'
            };
        }else{
            let values = this.props.node.getMetadata();
            let objValues = {};
            let parameters;
            values.forEach(function(v,k){
                if(k === 'parameters'){
                    parameters = JSON.parse(v);
                }else{
                    objValues[k] = v;
                }
            });
            if(parameters){
                let i = 0;
                for(let k in parameters){
                    if(!parameters.hasOwnProperty(k)) continue;
                    objValues['param_name' + (i > 0 ? '_'+i  : '')] = k;
                    objValues['param_value' + (i > 0 ? '_'+i  : '')] = parameters[k];
                    i++;
                };
            }
            return {tab:0, values: objValues, cron:values.get('schedule'), node:this.props.node};
        }
    },

    getMessage: function(messId){
        return this.props.pydio.MessageHash[messId];
    },

    close: function(){
        this.props.onDismiss();
    },

    render: function(){

        if(!this._params){
            const definitionNode = XMLUtils.XPathSelectSingleNode(this.props.pydio.getXmlRegistry(), 'actions/action[@name="scheduler_addTask"]/processing/standardFormDefinition');
            this._params = Manager.parseParameters(definitionNode, "param");
        }
        let params = [];
        // Clone this._params
        this._params.map(function(o){
            let copy = LangUtils.deepCopy(o);
            if(copy.name == 'action_name' && this.state.currentActionData){
                if(this.state.currentActionData.parameters){
                    let actionParams = this.state.currentActionData.parameters;
                    let descParams = [];
                    actionParams.map(function(p){
                        descParams.push( (p.name === 'nodes' ? 'file': p.name) + ' (' + p.description + ')');
                    });
                    copy.description = "Declared Parameters : " + descParams.join(', ');
                }else{
                    copy.description = "No Declared Parameters";
                }
            }
            params.push(copy);
        }.bind(this));
        if(this.state.cron){
            params.push({
                name:'cron_legend',
                type:'legend',
                group:this.getMessage('action.scheduler.2'),
                description:'Current CRON: ' + Cronstrue.toString(this.state.cron)
            });
        }
        let tabs = [
            {label:'Schedule', groups:[1]},
            {label:'Action', groups:[2,0]},
            {label:'Context', groups:[3]}
        ];
        return (<div>
            <PydioForm.FormPanel
                ref="formPanel"
                parameters={params}
                values={this.state.values}
                depth={-1}
                tabs={tabs}
                onTabChange={this.tabChange}
                onChange={this.onFormChange}
                onParameterChange={this.onParameterChange}
            />
        </div>);
    }

});
export {TaskEditor as default}