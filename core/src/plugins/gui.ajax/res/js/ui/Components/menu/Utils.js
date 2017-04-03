function pydioActionsToItems(actions = []){
    let items = [];
    let lastIsSeparator = false;
    actions.map(function(action, index){
        if(action.separator) {
            if(lastIsSeparator) return;
            items.push({type:ReactMUI.MenuItem.Types.SUBHEADER, text:''});
            lastIsSeparator = true;
            return;
        }
        lastIsSeparator = false;
        if(action.subMenu){
            let subItems;
            if(action.subMenuBeforeShow){
                let subActions = action.subMenuBeforeShow();
                subItems   = pydioActionsToItems(subActions);
            }else{
                subItems = action.subMenu;
            }
            let iconLabel = (
                <span>
                        <span className={"mui-menu-item-icon " + action.icon_class}></span>
                        <span>{action.raw_name?action.raw_name:action.name}</span>
                    </span>
            );
            items.push({
                type:ReactMUI.MenuItem.Types.NESTED,
                text: iconLabel,
                iconClassName:action.icon_class,
                items: subItems
            });
        }else{
            let payload;
            if(action.callback) {
                payload = action.callback;
            }else{
                payload = function(){pydio.Controller.fireAction(action_id);};
            }
            items.push({
                payload: payload,
                text: action.raw_name?action.raw_name:action.name,
                iconClassName:action.icon_class
            });
        }
    }.bind(this));
    if(lastIsSeparator){
        items = items.slice(0, items.length - 1);
    }
    return items;
}

export default {

    pydioActionsToItems : pydioActionsToItems
}
