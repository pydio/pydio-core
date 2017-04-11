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