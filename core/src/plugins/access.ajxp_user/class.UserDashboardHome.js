Class.create("UserDashboardHome", AjxpPane, {

    _formManager: null,

    initialize: function($super, oFormObject, editorOptions){

        $super(oFormObject, editorOptions);

        oFormObject.down("#welcome").update("Welcome, " + ajaxplorer.user.getPreference("USER_DISPLAY_NAME") + "!");

        var wsElement = oFormObject.down('#workspaces_list');

        ajaxplorer.user.repositories.each(function(pair){
            var repoId = pair.key;
            var repoObject = pair.value;
            if(repoObject.getAccessType() == 'ajxp_user') return;

            var repoEl = new Element('li').update("<h3>"+repoObject.getLabel() + "</h3><h4>" + repoObject.getDescription()+"</h4>");
            wsElement.insert(repoEl);
            repoEl.observe("click", function(e){
                var target = Event.findElement(e, "li");
                target.nextSiblings().invoke('removeClassName', 'selected');
                target.previousSiblings().invoke('removeClassName', 'selected');
                target.addClassName('selected');
                oFormObject.down('#go_to_ws').removeClassName("disabled");
                oFormObject.down('#go_to_ws').CURRENT_REPO_ID = repoId;
            });
        });

        oFormObject.down('#go_to_ws').observe("click", function(e){
            var target = e.target;
            if(!target.CURRENT_REPO_ID) return;
            ajaxplorer.triggerRepositoryChange(target.CURRENT_REPO_ID);
        });

        var notificationElement = oFormObject.down("#notifications_center");
        var notifCenter = ajaxplorer.NotificationLoaderInstance;
        notifCenter.ajxpNode.observe("loaded", function(){
            notifCenter.childrenToMenuItems(function(obj){
                var a = new Element('li', {title:obj['alt']}).update(obj['name']);
                notificationElement.insert(a);
                var img = obj.pFactory.generateBasePreview(obj.ajxpNode);
                a.IMAGE_ELEMENT = img;
                a.insert({top:img});
                obj.pFactory.enrichBasePreview(obj.ajxpNode, a);
            });
        });

        var clicker = function(){
            if(oFormObject.down("#notifications_center").hasClassName('folded')){
                oFormObject.down("#workspaces_center").setStyle({marginLeft: '15%'});
                oFormObject.down("#notifications_center").setStyle({width: '30%'});
            }else{
                oFormObject.down("#workspaces_center").setStyle({marginLeft: '30%'});
                oFormObject.down("#notifications_center").setStyle({width: '0'});
            }
            oFormObject.down("#notifications_center").toggleClassName('folded');
        };
        oFormObject.down("#close-icon").observe("click", clicker);

        window.setTimeout(clicker, 4000);
    },

    resize: function($super, size){

        $super(size);

        fitHeightToBottom(this.htmlElement.down('#workspaces_list'), this.htmlElement, 90);
    }


});