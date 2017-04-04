const {Divider, Menu, MenuItem, FontIcon} = require('material-ui')

function pydioActionsToItems(actions = []){
    let items = [];
    let lastIsSeparator = false;
    actions.map(function(action, index){
        if(action.separator) {
            if(lastIsSeparator) return;
            items.push(action);
            lastIsSeparator = true;
            return;
        }
        lastIsSeparator = false;
        const label = action.raw_name?action.raw_name:action.name;
        const iconClass = action.icon_class;
        let payload;
        if(action.subMenu){
            const subItems = action.subMenuBeforeShow ? pydioActionsToItems(action.subMenuBeforeShow()) : action.subMenu;
            items.push({
                text: label,
                iconClassName:iconClass,
                subItems: subItems
            });
        }else{
            items.push({
                text: label,
                iconClassName:iconClass,
                payload: action.callback
            });
        }
    }.bind(this));
    if(lastIsSeparator){
        items = items.slice(0, items.length - 1);
    }
    if(items.length && items[0] && items[0].separator){
        items.shift();
    }
    return items;
}

function itemsToMenu(items, closeMenuCallback, subItemsOnly = false, props = {display:'normal'}){

    const menuItems = items.map((item) => {

        if(item.separator) return <Divider/>;

        let subItems, payload;
        if(item.subItems){
            subItems = itemsToMenu(item.subItems, closeMenuCallback, true);
        }else if(item.payload){
            payload = () => {
                item.payload();
                closeMenuCallback();
            };
        }

        return (
            <MenuItem
                primaryText={item.text}
                leftIcon={props.display !== 'compact' && item.iconClassName ? <FontIcon className={item.iconClassName} style={{fontSize:16, padding:5}} color="rgba(0,0,0,0.33)"/> : null}
                rightIcon={subItems && subItems.length ? <FontIcon className="mdi mdi-menu-right"/> : null}
                onTouchTap={payload}
                menuItems={subItems}
            />

        );

    });

    if(subItemsOnly) {
        return menuItems;
    } else {
        return <Menu desktop={props.display === 'compact'} width={256}>{menuItems}</Menu>
    }

}

export default {pydioActionsToItems, itemsToMenu}
