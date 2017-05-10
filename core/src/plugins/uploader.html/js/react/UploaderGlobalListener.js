(function(global){

    var sendEventToUploader = function(items, files, el){

        var passItems, contextNode;
        if (items.length && items[0] && (items[0].getAsEntry || items[0].webkitGetAsEntry)) {
            passItems = items;
        }
        if(el.ajxpNode) {
            contextNode = el.ajxpNode;
        } else {
            contextNode = pydio.getContextHolder().getContextNode();
        }
        UploaderModel.Store.getInstance().handleDropEventResults(passItems, files, contextNode);
        if(!UploaderModel.Store.getInstance().getAutoStart()){
            pydio.getController().fireAction('upload');
        }

    };

    var createWorkspaceMenu = function(event, items, files, el){

        let targetNode = new AjxpNode('/', false, '');
        let data = [];
        let uploaderStore = UploaderModel.Store.getInstance();
        uploaderStore.handleDropEventResults(items, files, targetNode, data);

        let menuItems = [];
        global.pydio.user.getRepositoriesList().forEach(function(repository){

            if (repository.getId().indexOf('ajxp_') === 0 || repository.getId() === 'inbox' || !repository.allowCrossRepositoryCopy) return;
            if (repository.hasContentFilter() || repository.getOwner()) return;
            if (repository.getAccessStatus() === 'declined') return;

            let repoId = repository.getId();
            menuItems.push({
                name:repository.getLabel(),
                alt:repository.getDescription(),
                icon_class:'mdi mdi-upload',
                callback: function(e){
                    data.forEach(function(item){
                        item.updateRepositoryId(repoId);
                        if(item instanceof UploaderModel.FolderItem) uploaderStore.pushFolder(item);
                        else uploaderStore.pushFile(item);
                    });
                }
            });
        });

        let contextMenu = new Proto.Menu({
            menuTitle:global.pydio.MessageHash['user_home.78'],
            selector: '', // context menu will be shown when element with class name of "contextmenu" is clicked
            className: 'menu desktop home_upload', // this is a class which will be attached to menu container (used for css styling)
            menuItems: menuItems,
            fade:false,
            zIndex:2000,
            forceCheckHeight:true
        });
        contextMenu.show(event);

    };

    var initialized = false;

    var initUploaderExtension = function(){
return;
        if(initialized) return;
        else initialized = true;
        let mainElement = global.pydio.Parameters.get('MAIN_ELEMENT');
        let fullZone = $(mainElement);
        let listZones = $$('[ajxpClass="FilesList"]');
        let listZone;
        let listZoneSelector;
        if(listZones.length){
            listZone = listZones[0];
            listZoneSelector = '#' + listZone.id;
            listZone.addClassName('droparea');
        }
        let dropzone;
        if(fullZone)  dropzone = fullZone;
        else if(listZone) dropzone = listZone;
        else return;

        let findElement = function(event){
            var el = Event.findElement(event, selector);
            if(el.hasClassName('ajxpNodeProvider')){
                if(Event.findElement(event, '.class-FetchedResultPane')){
                    return (listZone ? listZone : dropzone);
                }
                if(el.hasClassName('ajxpNodeLeaf') || el.ajxpNode.isLeaf() || el.ajxpNode.getAjxpMime() === 'ajxp_recycle'){
                    if(listZoneSelector) el = Event.findElement(event, listZoneSelector);
                    else el = Event.findElement(event, '[ajxpClass="FilesList"]');
                }
            }
            if(!el) {
                return (listZone ? listZone : dropzone);
            }
            return el;
        }

        var selector = '#'+mainElement+(listZoneSelector?','+listZoneSelector:'')+',div.webfx-tree-item,.ajxpNodeProvider';
        var dragOverFunc = function(event) {
            let el = findElement(event);
            el.addClassName("dropareaHover");
            event.preventDefault();
        };
        var dropFunc = function(event) {
            event.preventDefault();
            event.stopPropagation();
            let el = findElement(event);
            el.removeClassName("dropareaHover");
            var items = event.dataTransfer.items || [];
            var files = event.dataTransfer.files;
            ResourcesManager.loadClassesAndApply(["UploaderModel"], function() {
                if (el.hasClassName('ajxp_ws-welcome')) {
                    createWorkspaceMenu(event, items, files, el);
                } else {
                    sendEventToUploader(items, files, el);
                }
            });
        };
        var enterFunc = function(event){
            let el = findElement(event);
            el.addClassName("dropareaHover");
        };
        var leaveFunc = function(event){
            let el = findElement(event);
            el.removeClassName("dropareaHover");
        };
        AjxpDroppables.dragOverHook = dragOverFunc;
        AjxpDroppables.dropHook = dropFunc;
        AjxpDroppables.dragEnterHook = enterFunc;
        AjxpDroppables.dragLeaveHook = leaveFunc;
        dropzone.addEventListener("dragover", dragOverFunc, true);
        dropzone.addEventListener("drop", dropFunc, true);
        dropzone.addEventListener("dragenter", enterFunc, true);
        dropzone.addEventListener("dragleave", leaveFunc, true);
        document.observeOnce("ajaxplorer:trigger_repository_switch", function(){
            initialized = false;
            dropzone.removeClassName('droparea');
            dropzone.removeEventListener("dragover", dragOverFunc, true);
            dropzone.removeEventListener("drop", dropFunc, true);
            dropzone.removeEventListener("dragenter", enterFunc, true);
            dropzone.removeEventListener("dragleave", leaveFunc, true);
            AjxpDroppables.dragOverHook = null;
            AjxpDroppables.dropHook = null;
            AjxpDroppables.dragEnterHook = null;
            AjxpDroppables.dragLeaveHook = null;
        });
    };

    var ns = global.UploaderGlobalListener || {};
    ns.initUploaderExtension = initUploaderExtension;
    global.UploaderGlobalListener = ns;

})(window);