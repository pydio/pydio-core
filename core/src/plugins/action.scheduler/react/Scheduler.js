(function(global) {

    var MessagesConsumerMixin = AdminComponents.MessagesConsumerMixin;

    var Board = React.createClass({

        mixins:[MessagesConsumerMixin],

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

        showTaskCreator: function(){
            global.pydio.Controller.fireAction("scheduler_addTask");
        },

        runAllTasks: function(){
            global.pydio.Controller.fireAction("scheduler_runAll");
        },

        showCronExpression: function(){
            global.pydio.Controller.fireAction("scheduler_generateCronExpression");
        },

        render: function(){

            return (
                <div className="main-layout-nav-to-stack workspaces-board">
                    <div className="left-nav vertical-layout" style={{width:'100%', backgroundColor:'white'}}>
                        <ReactMUI.Paper zDepth={0} className="vertical-layout layout-fill">
                            <div className="vertical-layout workspaces-list layout-fill">
                                <h1 className="hide-on-vertical-layout">{this.context.getMessage('18', 'action.scheduler')}</h1>
                                <div className="button-container">
                                    <ReactMUI.FlatButton primary={true} label={'+ ' + this.context.getMessage('8', 'action.scheduler')} onClick={this.showTaskCreator}/>
                                    <ReactMUI.FlatButton secondary={true} label={this.context.getMessage('15', 'action.scheduler')} onClick={this.runAllTasks} />
                                    <ReactMUI.FlatButton secondary={true} label={this.context.getMessage('20', 'action.scheduler')} onClick={this.showCronExpression} />
                                </div>
                                <ReactPydio.SimpleList
                                    node={this.props.currentNode}
                                    dataModel={this.props.dataModel}
                                    className="scheduler-list layout-fill"
                                    actionBarGroups={['get']}
                                    infineSliceCount={1000}
                                    tableKeys={this.keys}
                                    elementHeight={{
                                        "max-width:480px":201,
                                        "(min-width:480px) and (max-width:760px)":80,
                                        "min-width:760px":ReactPydio.SimpleList.HEIGHT_ONE_LINE
                                    }}
                                />

                            </div>
                        </ReactMUI.Paper>
                    </div>
                </div>
            );

        }

    });

    global.Scheduler = {
        Board: Board
    };

})(window);