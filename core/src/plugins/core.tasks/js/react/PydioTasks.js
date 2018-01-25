(function(global){

    class Task{

        constructor(data){
            this._internal = data;
        }

        getId(){
            return this._internal['id'];
        }

        getUserId(){
            return this._internal['userId'];
        }

        getWorkspaceId(){
            return this._internal['wsId'];
        }

        isStoppable(){
            return this._internal['flags'] & Task.FLAG_STOPPABLE;
        }

        isResumable(){
            return this._internal['flags'] & Task.FLAG_RESUMABLE;
        }

        hasProgress(){
            return this._internal['flags'] & Task.FLAG_HAS_PROGRESS;
        }

        getProgress(){
            return this._internal['progress'];
        }

        getLabel(){
            return this._internal['label'];
        }

        getStatus(){
            return this._internal['status'];
        }

        getStatusMessage(){
            return this._internal['statusMessage'];
        }

        getClassName(){
            return this._internal['className'];
        }

        getData(){
            return this._internal;
        }

        pause(){
            TaskAPI.updateTaskStatus(this, Task.STATUS_INTERRUPT);
        }

        stop(){
            TaskAPI.updateTaskStatus(this, Task.STATUS_COMPLETE);
        }

        hasOpenablePane(){
            return false;
        }
        openDetailPane(){}

    }

    Task.STATUS_PENDING = 1;
    Task.STATUS_RUNNING = 2;
    Task.STATUS_COMPLETE = 4;
    Task.STATUS_FAILED = 8;
    Task.STATUS_PAUSED = 16;
    Task.STATUS_INTERRUPT = 64;

    Task.FLAG_STOPPABLE = 1;
    Task.FLAG_RESUMABLE = 2;
    Task.FLAG_HAS_PROGRESS = 4;

    class AlertTask extends Task{

        constructor(label, statusMessage){
            super({
                id              : 'local-alert-task-' + Math.random(),
                userId          : global.pydio.user.id,
                wsId            : global.pydio.user.activeRepository,
                label           : label,
                status          : PydioTasks.Task.STATUS_PENDING,
                statusMessage   : statusMessage,
                className       : 'alert-task'
            });
        }

        show(){
            this._timer = global.setTimeout(function(){
                this.updateStatus(PydioTasks.Task.STATUS_COMPLETE);
            }.bind(this), 7000);
            PydioTasks.Store.getInstance().enqueueLocalTask(this);
        }

        updateStatus(status, statusMessage = ''){
            this._internal['status'] = status;
            this._internal['statusMessage'] = statusMessage;
            this.notifyMainStore();
        }

        notifyMainStore(){
            PydioTasks.Store.getInstance().notify("tasks_updated");
        }

        hasOpenablePane(){
            return true;
        }
        openDetailPane(){
            AlertTask.close();
        }

        static setCloser(click){
            AlertTask.__CLOSER = click;
        }

        static close(){
            AlertTask.__CLOSER();
        }

    }

    class TaskAPI{
        
        static createTask(task, targetUsers = null, targetRepositories = null){
            let params = {
                "get_action":"task_create",
                "task":JSON.stringify(task.getData())
            };
            if(targetUsers && targetRepositories){
                params['target-users'] = targetUsers;
                params['target-repositories'] = targetRepositories;
            }
            PydioApi.getClient().request(params);
        }

        static loadTasks(callback, params = null){
            if(!global.pydio.user){
                callback([]);
                return;
            }
            if(params){
                params['get_action'] = 'tasks_list';
            }else{
                params = {get_action:'tasks_list'};
            }
            PydioApi.getClient().request(params, function(transport){
                if(transport.responseJSON){
                    let tasks = transport.responseJSON.map(function(taskData){
                        return new Task(taskData);
                    });
                    callback(tasks);
                }
            }.bind(this), null, {discrete: true});
        }

        static loadAdminTasks(userScope = null, repoScope = null, callback){
            let params = {get_action:"tasks_list"};
            if(userScope){
                params["scope"] = "user";
                params["user_id"] = userScope;
            }else if(repoScope){
                params["scope"] = "repository";
                params["repo_id"] = repoScope;
            }else{
                params["scope"] = "global";
            }
            TaskAPI.loadTasks(callback, params);
        }

        static updateTaskStatus(task, status){
            PydioApi.getClient().request({
                "get_action":"task_toggle_status",
                "taskId":task.getData()['id'],
                "status": status
            });

        }

    }

    var STORE_INSTANCE = null;

    class TaskStore extends Observable{

        static getInstance(){
            if (!STORE_INSTANCE) STORE_INSTANCE = new TaskStore();
            return STORE_INSTANCE;
        }

        constructor(){
            super();
            this._crtPoll   = 10;

            this._quickPoll = 2;
            this._longPoll  = 15;
            this._pollSteps = 1.4;
            // Start listening to server messages
            global.pydio.observe("server_message", function(xml){
                var task = XMLUtils.XPathSelectSingleNode(xml, 'tree/task');
                if(task){
                    let data = task.getAttribute("data");
                    let t = JSON.parse(data);
                    if( t instanceof Object && t.id){
                        let taskObject = new Task(t);
                        if (this._tasksList === undefined){
                            this._tasksList = new Map();
                        }
                        this._tasksList.set(t.id, taskObject);
                        this.notify("tasks_updated", taskObject);
                        global.pydio.notify("poller.frequency", {value:2});
                    }
                }
                var taskList = XMLUtils.XPathSelectSingleNode(xml, 'tree/taskList');
                if(taskList){
                    let jsonData = taskList.firstChild.nodeValue; // CDATA
                    let tasks = JSON.parse(jsonData);
                    if(tasks instanceof Object){
                        let taskMap = new Map();
                        tasks.map(function(t){
                            let task = new Task(t);
                            taskMap.set(task.getId(), task);
                        });
                        this._tasksList = taskMap;
                        this.notifyAndSetPollerSpeed(tasks);
                    }
                }
            }.bind(this));

            global.pydio.observe("registry_loaded", function(){

                this.getTasks(true);

            }.bind(this));
        }

        getTasks(forceRefresh = false){
            if(this._tasksList == undefined || forceRefresh){
                this._tasksList = new Map();
                TaskAPI.loadTasks(function(tasks){
                    let taskMap = new Map();
                    tasks.map(function(t){taskMap.set(t.getId(), t)});
                    this._tasksList = taskMap;
                    this.notifyAndSetPollerSpeed(tasks);
                }.bind(this));
            }
            // Add local tasks
            if(this._localTasks){
                this._localTasks.forEach(function(lT){
                    this._tasksList.set(lT.getId(), lT);
                }.bind(this));
            }
            return this._tasksList;
        }

        notifyAndSetPollerSpeed(tasks){
            this.notify("tasks_updated");
            if(tasks.length){
                this._crtPoll = this._quickPoll;
                global.pydio.notify("poller.frequency", {value:this._quickPoll});
            }else{
                this._crtPoll *= this._pollSteps;
                if(this._crtPoll >= this._longPoll){
                    global.pydio.notify("poller.frequency", {});
                }else{
                    global.pydio.notify("poller.frequency", {value:this._crtPoll});
                }
            }
        }

        enqueueLocalTask(task){
            if(!this._localTasks) {
                this._localTasks = new Map();
            }
            this._localTasks.set(task.getId(), task);
            this.notify("tasks_updated");
        }

        static enqueueActionTask(label, action, parameters = {}, nodes = [], flags = Task.FLAG_STOPPABLE, targetUsers = null, targetRepositories = null){
            let task = {
                label: label,
                flags: flags,
                status: 1,
                statusMessage : '',
                action: action,
                parameters: parameters,
                nodes: nodes
            };
            TaskAPI.createTask(new Task(task), targetUsers, targetRepositories);
        }

    }

    var TaskAction = React.createClass({

        getInitialState: function(){
            return {showProgress: true};
        },

        showAction: function(){
            this.setState({showProgress: false});
        },

        showProgress: function(){
            this.setState({showProgress: true});
        },

        render: function(){
            let t = this.props.task;

            let actions;
            if(t.getStatus() === Task.STATUS_RUNNING && t.isStoppable()){
                actions = (<span className="mdi mdi-stop" onClick={t.pause.bind(t)}/>);
            }else{
                actions = (<span className="mdi mdi-close" onClick={t.stop.bind(t)}/>);
            }
            if(this.state.showProgress && t.hasProgress() && t.getStatus() !== Task.STATUS_FAILED){
                actions = (
                    <MaterialUI.CircularProgress mode="determinate" value={t.getProgress()} size={26} style={{marginTop:8}}/>
                );
            }
            return <div className="task_actions" onMouseOver={this.showAction} onMouseOut={this.showProgress}>{actions}</div>;
        }

    });

    var TaskEntry = React.createClass({

        propTypes: {
            task: React.PropTypes.instanceOf(Task),
            adminDisplayScope: React.PropTypes.bool,
            showFull: React.PropTypes.bool
        },

        render: function(){
            let t = this.props.task;
            let scopeInfo;
            if(this.props['adminDisplayScope'] && this.props.adminDisplayScope === 'repository'){
                scopeInfo = "["+ this.props.task.getUserId() +"] ";
            }
            let click, clickStyle;
            if(this.props.task.hasOpenablePane()){
                click = this.props.task.openDetailPane;
                clickStyle = {cursor:'pointer'};
            }
            let customClassName = this.props.task.getClassName() || '';
            if(this.props.showFull){
                customClassName += ' show-full';
            }
            return (
                <div className={"task " + "task-status-" + this.props.task.getStatus() + " " + customClassName}>
                    <div className="task_texts" onClick={click} style={clickStyle}>
                        <div className="task_label">{scopeInfo}{t.getLabel()}</div>
                        <div className="status_message" title={t.getStatusMessage()}>{t.getStatusMessage()}</div>
                    </div>
                    <TaskAction task={t}/>
                </div>
            );
        }
    });

    var TasksPanel = React.createClass({

        refreshTasks: function(){
            if(!this.isMounted()){
                return;
            }
            this.setState({
                tasks: TaskStore.getInstance().getTasks()
            });
        },

        getInitialState(){
            return {
                tasks: TaskStore.getInstance().getTasks(),
                mouseOver: false
            };
        },

        componentDidMount: function(){
            TaskStore.getInstance().observe("tasks_updated", this.refreshTasks);
        },

        componentWillUnmount: function(){
            TaskStore.getInstance().stopObserving("tasks_updated");
        },

        onMouseOver: function(){
            this.setState({mouseOver: true});
        },

        onMouseOut: function(){
            this.setState({mouseOver: false});
        },

        render: function(){
            let tasks = [];
            this.state.tasks.forEach(function(t){
                if(t.getStatus() === Task.STATUS_COMPLETE) return;
                tasks.push(<TaskEntry key={t.getId()} task={t} showFull={this.state.mouseOver}/>);
            }.bind(this));
            let className = "pydio-tasks-panel";
            let heightStyle;
            if(!tasks.length){
                className += " invisible";
            }else{
                heightStyle = {height: this.state.mouseOver ? 'auto' : Math.min(tasks.length * 61, 180)};
            }
            return (
                <MaterialUI.Paper zDepth={2} onMouseOver={this.onMouseOver} onMouseOut={this.onMouseOut} className={className} style={heightStyle}>
                    {tasks}
                </MaterialUI.Paper>
            );
        }
    });

    // Export TaskStore
    var ns = global.PydioTasks || {};
    ns.Store = TaskStore;
    ns.Task = Task;
    ns.AlertTask = AlertTask;
    ns.API = TaskAPI;
    ns.Panel = TasksPanel;
    ns.TaskEntry = TaskEntry;
    global.PydioTasks = ns;

})(window);
