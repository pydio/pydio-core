(function(global){

    var ns = global.InboxCellModifier ||{};

    function updateCell(element, ajxpNode, type, metadataDef, ajxpNodeObject){

        if(element != null){
            var nodeMetaValue = ajxpNode.getMetadata().get('share_meta_type');
            var nodeMetaLabel;
            if(nodeMetaValue == "0") nodeMetaLabel = "Invitation";
            else if(nodeMetaValue == "1") nodeMetaLabel = "Share";
            else if(nodeMetaValue == "2") nodeMetaLabel = "Error";
            if(element.down('.text_label')){
                element.down('.text_label').update(nodeMetaLabel);
            }
            var mainElement;
            if(element.up('.ajxpNodeProvider')){
                mainElement = element.up('.ajxpNodeProvider');
            }else if(ajxpNodeObject){
                mainElement = ajxpNodeObject;
            }else{
                console.log(element, ajxpNodeObject);
            }
            if(mainElement){
                mainElement.addClassName('share_meta_type_' + nodeMetaValue);
            }

            if(type == 'row'){
                element.writeAttribute("data-sorter_value", nodeMetaValue);
            }else{
                element.writeAttribute("data-"+metadataDef.attributeName+"-sorter_value", nodeMetaValue);
            }

            var obj = $('content_pane').ajxpPaneObject;
            var col = obj.columnsDef.detect(function(c){
                return c.attributeName == "share_meta_type";
            }.bind(this));
            if(col){
                var index = obj.columnsDef.indexOf(col);
                obj._sortableTable.sort(index, false);
                obj._sortableTable.updateHeaderArrows();
            }
        }


    }

    ns.updateCell = updateCell;
    global.InboxCellModifier = ns;

})(window);