(function(global){

    var initUploaderExtension = function(){

        var dropzones = $$('[ajxpClass="FilesList"]');
        if(!dropzones.length) {
            return;
        }

        var dropzone = dropzones[0];
        dropzone.addClassName('droparea');
        var selector = '#content_pane,div.webfx-tree-item,.ajxpNodeProvider';
        var dragOverFunc = function(event) {
            var el = Event.findElement(event, selector);
            if(el.hasClassName('ajxpNodeProvider') && el.ajxpNode.isLeaf()){
                el = Event.findElement(event, '#content_pane');
            }
            el.addClassName("dropareaHover");
            event.preventDefault();
        };
        var dropFunc = function(event) {
            event.preventDefault();
            var el = Event.findElement(event, selector);
            if(el.hasClassName('ajxpNodeProvider') && el.ajxpNode.isLeaf()){
                el = Event.findElement(event, '#content_pane');
            }
            el.removeClassName("dropareaHover");
            var items = event.dataTransfer.items || [];
            var files = event.dataTransfer.files;
            ResourcesManager.loadClassesAndApply(["UploaderModel"], function(){
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
            });
        };
        var enterFunc = function(){
            var el = Event.findElement(event, selector);
            if(el.hasClassName('ajxpNodeProvider') && el.ajxpNode.isLeaf()){
                el = Event.findElement(event, '#content_pane');
            }
            el.addClassName("dropareaHover");
        };
        var leaveFunc = function(){
            var el = Event.findElement(event, selector);
            if(el.hasClassName('ajxpNodeProvider') && el.ajxpNode.isLeaf()){
                el = Event.findElement(event, '#content_pane');
            }
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
    }

    var ns = global.UploaderGlobalListener || {};
    ns.initUploaderExtension = initUploaderExtension;
    global.UploaderGlobalListener = ns;

})(window);