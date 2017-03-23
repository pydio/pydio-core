class NavigationHelper{

    static buildNavigationItems(rootNode, contextNode, items){

        if(rootNode.getMetadata().get('component')){
            items.push({
                text            : pydio.MessageHash['ajxp_admin.menu.0'],
                payload         : rootNode,
                iconClassName   : rootNode.getMetadata().get('icon_class')
            });
        }/*else{
            items.push({
                text:pydio.MessageHash['ajxp_admin.menu.0'],
                payload:rootNode,
                iconClassName:"icon-dashboard"
            });
        }*/
        var index = 0;
        var selectedIndex = 0;
        if(contextNode == rootNode) selectedIndex = 0;
        rootNode.getChildren().forEach(function(header){
            if(!header.getChildren().size && header.getMetadata().get('component')) {
                items.push({
                    text: header.getLabel(),
                    payload: header,
                    iconClassName: header.getMetadata().get('icon_class')
                });
                index++;
                if (contextNode == header) {
                    selectedIndex = index;
                }
            }else{
                if(header.getLabel()){
                    items.push({
                        type: ReactMUI.MenuItem.Types.SUBHEADER,
                        text:header.getLabel()
                    });
                    index++;
                }
                header.getChildren().forEach(function(child){
                    if(!child.getLabel()) return;
                    var label = child.getLabel();
                    if(child.getMetadata().get('flag')){
                        label = <span>{child.getLabel()} <span className="menu-flag">{child.getMetadata().get('flag')}</span> </span>;
                    }
                    items.push({
                        text:label,
                        payload:child,
                        iconClassName:child.getMetadata().get('icon_class')
                    });
                    index++;
                    if(contextNode == child){
                        selectedIndex = index;
                    }
                });
            }
        });
        return selectedIndex;

    }

}

export {NavigationHelper as default}