(function(global){

    class Task{

        constructor(data){
            this._internal = data;
        }

        getId(){
            return this._internal['id'];
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

        getData(){
            return this._internal;
        }

        pause(){
            TaskAPI.updateTaskStatus(this, Task.STATUS_PAUSED);
        }

        stop(){
            TaskAPI.updateTaskStatus(this, Task.STATUS_COMPLETE);
        }

    }

    Task.STATUS_PENDING = 1;
    Task.STATUS_RUNNING = 2;
    Task.STATUS_COMPLETE = 4;
    Task.STATUS_FAILED = 8;
    Task.STATUS_PAUSED = 16;

    Task.FLAG_STOPPABLE = 1;
    Task.FLAG_RESUMABLE = 2;
    Task.FLAG_HAS_PROGRESS = 4;

    class TaskAPI{
        
        static createTask(task){
            PydioApi.getClient().request({
                "get_action":"task_create",
                "task":JSON.stringify(task.getData())
            });
        }

        static loadTasks(callback){
            PydioApi.getClient().request({
                "get_action":"tasks_list"
            }, function(transport){
                if(transport.responseJSON){
                    let tasks = transport.responseJSON.map(function(taskData){
                        return new Task(taskData);
                    });
                    callback(tasks);
                }
            }.bind(this));

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
            // Start listening to server messages
            global.pydio.observe("server_message", function(xml){
                var task = XMLUtils.XPathSelectSingleNode(xml, 'tree/task');
                if(task){
                    let data = task.getAttribute("data");
                    let t = JSON.parse(data);
                    if( t instanceof Object && t.id){
                        let taskObject = new Task(t);
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
                        this.notify("tasks_updated");
                        if(tasks.length){
                            global.pydio.notify("poller.frequency", {value:2});
                        }else{
                            global.pydio.notify("poller.frequency", {});
                        }
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
                    this.notify("tasks_updated");
                    if(tasks.length){
                        global.pydio.notify("poller.frequency", {value:2});
                    }else{
                        global.pydio.notify("poller.frequency", {});
                    }
                }.bind(this));
                // Add local tasks
                if(this._localTasks){
                    this._localTasks.forEach(function(lT){
                        this._tasksList.set(lT.getId(), lT);
                    }.bind(this));
                }
            }
            return this._tasksList;
        }

        static enqueueActionTask(label, action, parameters = {}, nodes = [], flags = Task.FLAG_STOPPABLE){
            let task = {
                label: label,
                flags: flags,
                status: 1,
                statusMessage : '',
                action: action,
                parameters: parameters,
                nodes: nodes
            };
            TaskAPI.createTask(new Task(task));
        }

        static enqueueLocalTask(task){
            if(!this._localTasks) {
                this._localTasks = new Map();
            }
            this._localTasks.set(task.getId(), task);
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
            if(t.getStatus() == Task.STATUS_RUNNING){
                if(t.isStoppable()){
                    actions = (<span className="icon-stop" onClick={t.pause.bind(t)}/>);
                }
            }else{
                actions = (<span className="mdi mdi-close-circle-outline" onClick={t.stop.bind(t)}/>);
            }
            if(this.state.showProgress && t.hasProgress()){
                actions = (
                    <div className="radial-progress">
                        <div className={"pie-wrapper pie-wrapper--solid progress-" + t.getProgress()}></div>
                    </div>
                );
            }
            return <div className="task_actions" onMouseOver={this.showAction} onMouseOut={this.showProgress}>{actions}</div>;
        }

    });

    var TaskEntry = React.createClass({
        render: function(){
            let t = this.props.task;
            let actions;
            if(t.getStatus() == Task.STATUS_RUNNING){
                if(t.isStoppable()){
                    actions = (<span className="icon-stop" onClick={t.pause.bind(t)}/>);
                }
            }else{
                actions = (<span className="mdi mdi-close-circle-outline" onClick={t.stop.bind(t)}/>);
            }
            if(t.hasProgress()){
                actions = (
                    <div className="radial-progress">
                        <div className={"pie-wrapper pie-wrapper--solid progress-" + t.getProgress()}></div>
                    </div>
                );
            }
            return (
                <div className="task">
                    <div className="task_texts">
                        <div className="task_label">{t.getLabel()}</div>
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
                tasks: TaskStore.getInstance().getTasks()
            };
        },

        componentDidMount: function(){
            TaskStore.getInstance().observe("tasks_updated", this.refreshTasks.bind(this));
        },

        componentWillUnmount: function(){
            TaskStore.getInstance().stopObserving("tasks_updated");
        },

        render: function(){
            let tasks = [];
            this.state.tasks.forEach(function(t){
                if(t.getStatus() == Task.STATUS_COMPLETE) return;
                tasks.push(<TaskEntry task={t}/>);
            });
            let className = "pydio-tasks-panel";
            let heightStyle;
            if(!tasks.length){
                className += " invisible";
            }else{
                heightStyle = {height: Math.min(tasks.length * 60, 180)};
            }
            return (
                <div className={className} style={heightStyle}>
                    {tasks}
                </div>
            );
        }
    });

    // Export TaskStore
    var ns = global.PydioTasks || {};
    ns.Store = TaskStore;
    ns.Task = Task;
    ns.Panel = TasksPanel;
    global.PydioTasks = ns;

})(window);
