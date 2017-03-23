const Dashboard = React.createClass({

    mixins:[AdminComponents.MessagesConsumerMixin],

    keys: {
        'label':{label:'Label', message:'action.scheduler.12'},
        'schedule':{label:'Schedule', message:'action.scheduler.2'},
        'action_name':{label:'Action', message:'action.scheduler.1'},
        'repository_id':{label:'Workspace', message:'action.scheduler.4s'},
        'user_id':{label:'User(s)', message:'action.scheduler.17'},
        'NEXT_EXECUTION':{label:'Next Execution', message:'action.scheduler.3'},
        'LAST_EXECUTION':{label:'Last Execution', message:'action.scheduler.14'},
        'STATUS':{label:'Status', message:'action.scheduler.13'}
    },

    refreshTasks: function(){
        FuncUtils.bufferCallback('reload_task_list', 500, function(){
            if(this.refs && this.refs.list){
                this.refs.list.reload();
            }
        }.bind(this));
    },

    statics: {
        getInstance: function(){
            return Dashboard.__INSTANCE__
        }
    },

    componentDidMount: function(){
        PydioTasks.Store.getInstance().observe("tasks_updated", this.refreshTasks.bind(this));
        PydioApi.getClient().request({get_action:'scheduler_checkConfig'}, function(t){
            if(this.isMounted()) this.setState({config_ok:t.responseJSON['OK']});
        }.bind(this));
        Dashboard.__INSTANCE__ = this;
    },

    componentWillUnmount: function(){
        PydioTasks.Store.getInstance().stopObserving("tasks_updated");
        Dashboard.__INSTANCE__ = null;
    },

    showTaskCreator: function(){
        pydio.Controller.fireAction("scheduler_addTask");
    },

    runAllTasks: function(){
        pydio.Controller.fireAction("scheduler_runAll");
    },

    showCronExpression: function(){
        pydio.Controller.fireAction("scheduler_generateCronExpression");
    },

    render: function(){

        let error = null;
        if(this.state && this.state['config_ok'] !== undefined && this.state.config_ok === false){
            let eLink = <a onClick={function(){pydio.goTo('/parameters/core');}}>{this.context.getMessage('scheduler.3', 'ajxp_admin')}</a>;
            let messageParts = this.context.getMessage('scheduler.2', 'ajxp_admin').split('%1');
            error = <div className="plugin-doc-error"><span className="icon-warning-sign"></span> {messageParts[0]}{eLink}{messageParts[1]}</div>
        }

        return (
            <div className="main-layout-nav-to-stack workspaces-board">
                <div className="left-nav vertical-layout" style={{width:'100%', backgroundColor:'white'}}>
                    <ReactMUI.Paper zDepth={0} className="vertical-layout layout-fill">
                        <div className="vertical-layout workspaces-list layout-fill">
                            <h1 className="hide-on-vertical-layout">{this.context.getMessage('18', 'action.scheduler')}</h1>
                            {error}
                            <div className="plugin-doc-pane">
                                {this.context.getMessage('scheduler.1', 'ajxp_admin')}
                            </div>
                            <div className="button-container">
                                <ReactMUI.FlatButton primary={true} label={'+ ' + this.context.getMessage('8', 'action.scheduler')} onClick={this.showTaskCreator}/>
                                <ReactMUI.FlatButton secondary={true} label={this.context.getMessage('15', 'action.scheduler')} onClick={this.runAllTasks} />
                                <ReactMUI.FlatButton secondary={true} label={this.context.getMessage('20', 'action.scheduler')} onClick={this.showCronExpression} />
                            </div>
                            <PydioComponents.SimpleList
                                ref="list"
                                node={this.props.currentNode}
                                dataModel={this.props.dataModel}
                                className="scheduler-list layout-fill"
                                actionBarGroups={['get']}
                                infineSliceCount={1000}
                                tableKeys={this.keys}
                                computeActionsForNode={true}
                                elementHeight={{
                                    "max-width:480px":201,
                                    "(min-width:480px) and (max-width:760px)":80,
                                    "min-width:760px":PydioComponents.SimpleList.HEIGHT_ONE_LINE
                                }}
                            />

                        </div>
                    </ReactMUI.Paper>
                </div>
            </div>
        );

    }

});

export {Dashboard as default}