(function(global){

    let pydio = global.pydio;

    class Callbacks{

        static runAll(){
            var connexion = new Connexion();
            connexion.setParameters(new Hash({get_action:'scheduler_runAll'}));
            connexion.onComplete = function(transport){
                PydioApi.getClient().parseXmlMessage(transport.responseXML);
            };
            connexion.sendAsync();
        }
        
        static generateCron() {
            
            modal.showDialogForm('', 'scheduler_cronExpression', function(oForm){
                var connexion = new Connexion();
                connexion.setParameters(new Hash({get_action:'scheduler_generateCronExpression'}));
                connexion.onComplete = function(transport){
                    $("cron_expression").setValue(transport.responseText);
                    $("cron_expression").select();
                };
                connexion.sendAsync();
            }, function(oForm){
                hideLightBox();
            }, null, true);
            
        }
        
        static addTask(manager, args) {
            
            modal.showDialogForm('', 'scheduler-task-form', function(oForm){
                this.currentFormManager = new FormManager();
                var xmlDefinition = pydio.getXmlRegistry();
                var node = XMLUtils.XPathSelectSingleNode(xmlDefinition, 'actions/action[@name="scheduler_addTask"]/processing/standardFormDefinition');

                this.currentFormManager.params = this.currentFormManager.parseParameters(node, "param");

                if(args && args[0]){
                    var tId = args[0].getPath();
                    tId = PathUtils.getBasename(tId);
                    this.currentFormManager.task_id = tId;
                    var conn = new Connexion();
                    conn.setParameters(new Hash({get_action:'scheduler_loadTask',task_id:tId}));
                    var values;
                    conn.onComplete = function(transport){
                        values = $H(transport.responseJSON);
                        this.currentFormManager.createParametersInputs(
                            $('scheduler-task-form'),
                            this.currentFormManager.params,
                            true,
                            values
                        );
                    }.bind(this);
                    conn.sendAsync();
                }else{
                    this.currentFormManager.createParametersInputs(
                        $('scheduler-task-form'),
                        this.currentFormManager.params,
                        true
                    );
                }
                
            }, function(oForm){

                /**
                 * @author Jordi Salvat i Alabart - with thanks to <a href="www.salir.com">Salir.com</a>.
                 */
                var regStr = "^\\s*((\\*(\/\\d+)?|([0-5]?\\d)(-([0-5]?\\d)(\/\\d+)?)?(,([0-5]?\\d)(-([0-5]?\\d)(\/\\d+)?)?)*)\\s+(\\*(\/\\d+)?|([01]?\\d|2[0-3])(-([01]?\\d|2[0-3])(\/\\d+)?)?(,([01]?\\d|2[0-3])(-([01]?\\d|2[0-3])(\/\\d+)?)?)*)\\s+(\\*(\/\\d+)?|(0?[1-9]|[12]\\d|3[01])(-(0?[1-9]|[12]\\d|3[01])(\/\\d+)?)?(,(0?[1-9]|[12]\\d|3[01])(-(0?[1-9]|[12]\\d|3[01])(\/\\d+)?)?)*)\\s+(\\*(\/\\d+)?|([1-9]|1[012])(-([1-9]|1[012])(\/\\d+)?)?(,([1-9]|1[012])(-([1-9]|1[012])(\/\\d+)?)?)*|jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)\\s+(\\*(\/\\d+)?|([0-7])(-([0-7])(\/\\d+)?)?(,([0-7])(-([0-7])(\/\\d+)?)?)*|mon|tue|wed|thu|fri|sat|sun)\\s*$|(@yearly|@annually|@monthly|@weekly|@daily|@midnight|@hourly)\\s*$)";

                /*
                 form validation
                 */
                var schLabel = $('scheduler-task-form').select('[name=label]')[0];
                // $('scheduler-task-form').select('[name=schedule]')[0].removeClassName('SF_failed')
                var schSch = $('scheduler-task-form').select('[name=schedule]')[0];
                var schUser = $('scheduler-task-form').select('[name=user_id]')[0];
                var schRepoId = $('scheduler-task-form').select('[name=repository_id]')[0];
                var schActionName = $('scheduler-task-form').select('[name=action_name]')[0];

                schSch.value = schSch.value.trim();
                /* check null value */

                var errorMsg = "";

                if( schLabel.value === "" ||
                    schSch.value === "" ||
                    schUser.value === "" ||
                    schRepoId.value === "" ||
                    schActionName.value === "loading"){
                    (schLabel.value === "" )?schLabel.addClassName("SF_failed"):schLabel.removeClassName("SF_failed");
                    (schSch.value === "")?schSch.addClassName("SF_failed"):schSch.removeClassName("SF_failed");
                    (schUser.value === "")?schUser.addClassName("SF_failed"):schUser.removeClassName("SF_failed");
                    (schRepoId.value === "")?schRepoId.addClassName("SF_failed"):schRepoId.removeClassName("SF_failed");
                    (schActionName.value === "")?schActionName.addClassName("SF_failed"):schActionName.removeClassName("SF_failed");
                    alert("Some fields should not be empty");
                    return 0;
                }
                /* check schedule format */
                if(schSch.value.match(regStr) != null){
                    schSch.removeClassName("SF_failed");
                }else{
                    schSch.addClassName("SF_failed");
                    alert("Check format of Schedule");
                    return 0;
                }

                var values = new Hash({});
                this.currentFormManager.serializeParametersInputs(
                    $('scheduler-task-form'),
                    values,
                    '');

                var conn = new Connexion();
                values.set('get_action', 'scheduler_addTask');
                if(this.currentFormManager.task_id){
                    values.set('task_id',this.currentFormManager.task_id);
                }
                conn.setParameters(values);
                conn.sendAsync();
                conn.onComplete = function(transport){
                    var res = PydioApi.getClient().parseXmlMessage(transport.responseXML);
                    if(res) hideLightBox();
                };
                
            });
            
        }

        static runTask(manager, args){

            var userSelection;
            if(args && args.length){
                userSelection = args[0];
            }else{
                userSelection =  pydio.getUserSelection();
            }
            var taskId = PathUtils.getBasename(userSelection.getUniqueNode().getPath());
            var connexion = new Connexion();
            connexion.setParameters(new Hash({
                get_action:'scheduler_runTask',
                task_id:taskId
            }));
            connexion.onComplete = function(transport){
                PydioApi.getClient().parseXmlMessage(transport.responseXML);
            };
            connexion.sendAsync();

        }

        static editTask(manager, args){
            
            var userSelection;
            if(args && args.length){
                userSelection = args[0];
            }else{
                userSelection =  pydio.getUserSelection();
            }
            pydio.getController().fireAction('scheduler_addTask', userSelection.getUniqueNode());
            
        }

        static removeTask(manager, args){

            var userSelection;
            if(args && args.length){
                userSelection = args[0];
            }else{
                userSelection =  pydio.getUserSelection();
            }
            var conn = new Connexion();
            conn.setParameters($H({ get_action : 'scheduler_removeTask', task_id: PathUtils.getBasename(userSelection.getUniqueNode().getPath()) }));
            conn.onComplete = function(transport){
                PydioApi.getClient().parseXmlMessage(transport.responseXML);
            };
            conn.sendAsync();

        }
        
        static stopTask(manager, args){

            var userSelection;
            if(args && args.length){
                userSelection = args[0];
            }else{
                userSelection =  pydio.getUserSelection();
            }
            var taskId = userSelection.getUniqueNode().getMetadata().get("task_id");
            var task = new PydioTasks.Task({id:taskId});
            task.stop();
            
        }
        
    }

    global.SchedulerActions = {
        Callbacks: Callbacks
    };

})(window)