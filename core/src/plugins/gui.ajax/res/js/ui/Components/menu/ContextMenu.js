import ContextMenuModel from 'pydio/model/context-menu'
import Utils from './Utils'
import PopupMenu from './PopupMenu'

(function(global){

    const dims = {
        MENU_ITEM_HEIGHT: 32, //48 if not display:compact
        MENU_SEP_HEIGHT: 16,
        MENU_VERTICAL_PADDING: 8,
        MENU_WIDTH: 250,
        OFFSET_VERTICAL: 8,
        OFFSET_HORIZONTAL: 8
    };

    export default React.createClass({


        modelOpen: function(node){
            let position = ContextMenuModel.getInstance().getPosition();
            let items;
            if(node){
                let dm = pydio.getContextHolder();
                if(dm.getSelectedNodes().indexOf(node) !== -1){
                    this.openMenu('selectionContext', position);
                }else{
                    pydio.observeOnce("actions_refreshed", function(dataModel){
                        this.openMenu('selectionContext', position);
                    }.bind(this));
                    dm.setSelectedNodes([node]);
                }
            }else{
                this.openMenu('genericContext', position);
            }
        },

        openMenu: function(context, position){
            let items = this.computeMenuItems(context);
            position = this.computeVisiblePosition(position, items);
            this._items = items;
            this.refs['menu'].showMenu({
                top: position.y,
                left: position.x
            }, items);
        },

        computeMenuItems: function(context){
            let actions = global.pydio.Controller.getContextActions(context, ['inline', 'info_panel', 'info_panel_share']);
            return Utils.pydioActionsToItems(actions);
        },

        computeVisiblePosition: function(position, items){
            let menuHeight  = dims.MENU_VERTICAL_PADDING * 2;
            items.map(function(it){
                if(it.separator) menuHeight += dims.MENU_SEP_HEIGHT;
                else menuHeight += dims.MENU_ITEM_HEIGHT;
            });
            let menuWidth   = dims.MENU_WIDTH;
            let windowW     = Math.max(document.documentElement.clientWidth, window.innerWidth || 0);
            let windowH     = Math.max(document.documentElement.clientHeight, window.innerHeight || 0);
            if(position.x + menuWidth > windowW) {
                position.x = Math.max(position.x - menuWidth, 10) - dims.OFFSET_HORIZONTAL;
            }else{
                position.x += dims.OFFSET_HORIZONTAL;
            }
            if(position.y + menuHeight > windowH) {
                position.y = Math.max(position.y - menuHeight, 10) - dims.OFFSET_VERTICAL;
            }else{
                position.y += dims.OFFSET_VERTICAL;
            }
            return position;
        },

        onMenuClosed: function(){
            ContextMenuModel.getInstance().close();
        },

        componentDidMount: function(){
            this._modelOpen = this.modelOpen;
            ContextMenuModel.getInstance().observe("open", this._modelOpen);
        },

        componentWillUnmount: function(){
            ContextMenuModel.getInstance().stopObserving("open", this._modelOpen);
        },

        render: function(){
            return (
                <PopupMenu
                    ref="menu"
                    menuItems={this._items || []}
                    onMenuClosed={this.props.onMenuClosed}
                />
            );
        }
    });


})(window);
