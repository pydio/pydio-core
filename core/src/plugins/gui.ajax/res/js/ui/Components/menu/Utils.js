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

function itemsToMenu(items, closeMenuCallback, subItemsOnly = false, menuProps = {}){

    menuProps = {
        display:'normal',
        width: 216,
        desktop: false,
        autoWidth: false,
        ...menuProps
    };

    const menuItems = items.map((item, index) => {

        if(item.separator) return <Divider key={"divider" + index}/>;

        let subItems, payload;
        if(item.subItems){
            subItems = itemsToMenu(item.subItems, closeMenuCallback, true);
        }else if(item.payload){
            payload = () => {
                item.payload();
                closeMenuCallback();
            };
        }

        let leftIcon, rightIcon;
        if(menuProps.display === 'normal'){
            leftIcon = item.iconClassName ? <FontIcon className={item.iconClassName + ' menu-icons'} style={{fontSize:16, padding:5}} color="rgba(0,0,0,0.33)"/> : null;
        }else if(menuProps.display === 'right'){
            rightIcon = item.iconClassName ? <FontIcon className={item.iconClassName + ' menu-icons'} style={{fontSize:16, padding:5}} color="rgba(0,0,0,0.33)"/> : null;
        }
        rightIcon = subItems && subItems.length ? <FontIcon className='mdi mdi-menu-right menu-icons'/> : rightIcon;

        return (
            <MenuItem
                key={item.text}
                primaryText={item.text}
                leftIcon={leftIcon}
                rightIcon={rightIcon}
                onTouchTap={payload}
                menuItems={subItems}
            />

        );

    });

    if(subItemsOnly) {
        return menuItems;
    } else {
        return <Menu {...menuProps}>{menuItems}</Menu>
    }

}

export default {pydioActionsToItems, itemsToMenu}
