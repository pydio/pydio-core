(function(global) {

    class ContextMenuModel extends Observable{

        super(){
            this._currentNode = null;
            this._position    = null;
        }

        static getInstance(){
            if(!ContextMenuModel.__INSTANCE) {
                ContextMenuModel.__INSTANCE = new ContextMenuModel();
            }
            return ContextMenuModel.__INSTANCE;
        }

        openAtPosition(clientX, clientY){
            this._currentNode = null;
            this._position    = {x: clientX, y: clientY};
            this.notify("open");
        }

        openNodeAtPosition(node, clientX, clientY){
            this._currentNode = node;
            this._position    = {x: clientX, y: clientY};
            this.notify("open", node);
        }

        getNode(){
            return this._currentNode;
        }

        getPosition(){
            return this._position;
        }

        close(){
            this._currentNode = null;
            this.notify("close");
        }

    }

    let ContextMenuNodeProviderMixin = {

        contextMenuNodeResponder: function(event){

            event.preventDefault();
            event.stopPropagation();
            ContextMenuModel.getInstance().openNodeAtPosition(this.props.node, event.clientX, event.clientY);

        },

        contextMenuResponder: function(event){

            event.preventDefault();
            event.stopPropagation();
            ContextMenuModel.getInstance().openAtPosition(event.clientX, event.clientY);

        }

    };

    let pydioActionsToItems = function(actions){
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
    };

    const MFB = React.createClass({

        getDefaultProps: function(){
            return {
                toolbarGroups: ['mfb'],
                highlight: 'upload'
            };
        },

        getInitialState: function(){
            return {actions: []};
        },

        actionsChange: function(){
            let controller = global.pydio.Controller;
            let actions = controller.getContextActions('genericContext', null, this.props.toolbarGroups);
            this.setState({actions: actions});
        },
        
        componentDidMount: function(){
            this._listener = this.actionsChange.bind(this);
            global.pydio.observe("context_changed", this._listener);
        },
        
        componentWillUnmount: function(){
            global.pydio.stopObserving("context_changed", this._listener);
        },

        close: function(e){
            this.refs.menu.toggleMenu(e);
        },

        render: function(){

            if(!this.state.actions.length){
                return null;
            }

            let children = [];
            let close = this.close.bind(this);
            let hl = null, hlName = this.props.highlight;
            this.state.actions.map(function(a){
                if(a.separator) return;
                let cb = function(e){
                    close(e);
                    a.callback();
                };
                if(hlName && a.action_id === hlName){
                    hl = <ReactMFB.ChildButton icon={a.icon_class} label={a.alt} onClick={cb}/>;
                } else{
                    children.push(<ReactMFB.ChildButton icon={a.icon_class} label={a.alt} onClick={cb}/>);
                }
            });
            if(hl) children.push(hl);

            return (
                <ReactMFB.Menu effect="slidein" position="tl" icon="mdi mdi-file" ref="menu">
                    <ReactMFB.MainButton iconResting="mdi mdi-plus" iconActive="mdi mdi-close"/>
                    {children}
                    <span className="hiddenOverlay"/>
                </ReactMFB.Menu>
            );
        }
    });

    const PopupMenu = React.createClass({

        propTypes: {
            menuItems: React.PropTypes.array.isRequired,
            onMenuClicked: React.PropTypes.func.isRequired,
            onExternalClickCheckElements: React.PropTypes.func,
            className: React.PropTypes.string,
            style:React.PropTypes.object,
            onMenuClosed: React.PropTypes.func
        },

        getInitialState(){
            return {showMenu:false, menuItems:this.props.menuItems};
        },
        showMenu: function (style = null, menuItems = null) {
            this.setState({
                showMenu: true,
                style: style,
                menuItems:menuItems?menuItems:this.state.menuItems
            });
        },
        hideMenu: function(event){
            if(!event){
                this.setState({showMenu: false});
                if(this.props.onMenuClosed) this.props.onMenuClosed();
                return;
            }
            let hide = true;
            if(this.props.onExternalClickCheckElements){
                let elements = this.props.onExternalClickCheckElements();
                for(let i = 0; i < elements.length ; i ++){
                    if(elements[i].contains(event.target) || elements[i] === event.target ){
                        hide = false;
                        break;
                    }
                }
            }
            if(hide){
                this.setState({showMenu: false});
                if(this.props.onMenuClosed) this.props.onMenuClosed();
            }
        },
        componentDidMount: function(){
            this._observer = this.hideMenu.bind(this);
        },
        componentWillUnmount: function(){
            document.removeEventListener('click', this._observer, false);
        },
        componentWillReceiveProps: function(nextProps){
            if(nextProps.menuItems){
                this.setState({menuItems:nextProps.menuItems});
            }
        },
        componentDidUpdate: function(prevProps, nextProps){
            if(this.state.showMenu){
                document.addEventListener('click', this._observer, false);
            }else{
                document.removeEventListener('click', this._observer, false);
            }
        },

        menuClicked:function(event, index, menuItem){
            this.props.onMenuClicked(menuItem);
            this.hideMenu();
        },
        render: function(){

            if(this.state.showMenu) {
                if(this.state.style){
                    return (
                        <div style={this.state.style} className="menu-positioner">
                        <ReactMUI.Menu
                            onItemClick={this.menuClicked}
                            menuItems={this.state.menuItems}
                        />
                        </div>
                    );
                }
                return (
                    <ReactMUI.Menu
                        onItemClick={this.menuClicked}
                        menuItems={this.state.menuItems}
                    />
                );
            }else{
                return null;
            }
        }

    });

    const IconButtonMenu = React.createClass({

        propTypes: {
            buttonTitle: React.PropTypes.string.isRequired,
            buttonClassName: React.PropTypes.string.isRequired,
            className: React.PropTypes.string,
            menuItems: React.PropTypes.array.isRequired,
            onMenuClicked: React.PropTypes.func.isRequired
        },

        collectElements: function(){
            return [this.refs['menuButton'].getDOMNode()];
        },

        showMenu: function(){
            this.refs['menu'].showMenu();
        },

        render: function(){
            return (
                <span className={"toolbars-button-menu " + (this.props.className ? this.props.className  : '')}>
                    <ReactMUI.IconButton
                        ref="menuButton"
                        tooltip={this.props.buttonTitle}
                        iconClassName={this.props.buttonClassName}
                        onClick={this.showMenu}
                    />
                    <PopupMenu
                        ref="menu"
                        menuItems={this.props.menuItems}
                        onMenuClicked={this.props.onMenuClicked}
                        onExternalClickCheckElements={this.collectElements}
                    />
                </span>
            );
        }

    });

    const ContextMenu = React.createClass({

        statics:{
            MENU_ITEM_HEIGHT: 48,
            MENU_SEP_HEIGHT: 9,
            MENU_VERTICAL_PADDING: 8,
            MENU_WIDTH: 250
        },

        modelOpen: function(node){
            let position = ContextMenuModel.getInstance().getPosition();
            let items;
            if(node){
                let dm = pydio.getContextHolder();
                if(dm.isUnique() && dm.getUniqueNode() === node){
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
            this.refs['menu'].showMenu({
                top: position.y,
                left: position.x
            }, items);
        },

        computeMenuItems: function(context){
            let actions = global.pydio.Controller.getContextActions(context, ['inline', 'info_panel', 'info_panel_share']);
            return pydioActionsToItems(actions);
        },

        menuClicked: function(object){
            object.payload();
        },

        computeVisiblePosition: function(position, items){
            let menuHeight  = ContextMenu.MENU_VERTICAL_PADDING * 2;
            items.map(function(it){
                if(it.type === ReactMUI.MenuItem.Types.SUBHEADER) menuHeight += ContextMenu.MENU_SEP_HEIGHT;
                else menuHeight += ContextMenu.MENU_ITEM_HEIGHT;
            });
            let menuWidth   = ContextMenu.MENU_WIDTH;
            let windowW     = Math.max(document.documentElement.clientWidth, window.innerWidth || 0);
            let windowH     = Math.max(document.documentElement.clientHeight, window.innerHeight || 0);
            if(position.x + menuWidth > windowW) position.x = Math.max(position.x - menuWidth, 10);
            if(position.y + menuHeight > windowH) position.y = Math.max(position.y - menuHeight, 10);
            return position;
        },

        onMenuClosed: function(){
            ContextMenuModel.getInstance().close();
        },

        componentDidMount: function(){
            if(global.pydio.UI.contextMenu){
                // Make sure "contextmenu" events are not stopped
                // by proto.menu.
                // TO BE REMOVED when no more PrototypeJS.
                global.pydio.UI.contextMenu.destroy();
                delete global.pydio.UI.contextMenu;
            }
            this._modelOpen = this.modelOpen.bind(this);
            ContextMenuModel.getInstance().observe("open", this._modelOpen);
        },

        componentWillUnmount: function(){
            ContextMenuModel.getInstance().stopObserving("open", this._modelOpen);
        },

        render: function(){
            return (
                <PopupMenu
                    ref="menu"
                    menuItems={[]}
                    onMenuClicked={this.menuClicked}
                    onMenuClosed={this.props.onMenuClosed}
                />
            );
        }
    });

    const ButtonMenu = React.createClass({

        propTypes:{
            buttonTitle: React.PropTypes.string.isRequired,
            className: React.PropTypes.string,
            menuItems:React.PropTypes.array,
            selectionContext:React.PropTypes.string,
            toolbars:React.PropTypes.array.isRequired,
            raised:React.PropTypes.boolean
        },

        showMenu: function(){
            if(this.props.menuItems){
                this.refs['menu'].showMenu(null, this.props.menuItems);
            }else{
                let actions = this.props.pydio.Controller.getContextActions('genericContext', null, this.props.toolbars);
                let items   = pydioActionsToItems(actions);
                this.refs['menu'].showMenu(null, items);
            }
        },

        menuClicked: function(object){
            object.payload();
        },

        render: function(){
            let label = <span>{this.props.buttonTitle} <span className="icon-caret-down"></span></span>
            let button;
            if(this.props.raised){
                button = <ReactMUI.RaisedButton
                        primary={this.props.primary}
                        secondary={this.props.secondary}
                        disabled={this.props.disabled}
                        label={label}
                        onClick={this.showMenu}
                    />
                ;
            }else{
                button = <ReactMUI.FlatButton
                    primary={this.props.primary}
                    secondary={this.props.secondary}
                    disabled={this.props.disabled}
                    label={label}
                    onClick={this.showMenu}
                />;

            }
            return (
                <span id={this.props.id} className={this.props.className}>
                    {button}
                    <PopupMenu
                        ref="menu"
                        menuItems={[]}
                        onMenuClicked={this.menuClicked}
                    />
                </span>
            );
        }

    });

    const Toolbar = React.createClass({

        propTypes:{
            toolbars:React.PropTypes.array,
            groupOtherList:React.PropTypes.array,
            renderingType:React.PropTypes.string,
            controller:React.PropTypes.instanceOf(Controller)
        },

        componentDidMount: function(){
            this._observer = function(){
                this.setState({
                    groups:this.props.controller.getToolbarsActions(this.props.toolbars, this.props.groupOtherList)
                });
            }.bind(this);
            if(this.props.controller === pydio.Controller){
                pydio.observe("actions_refreshed", this._observer);
            }else{
                this.props.controller.observe("actions_refreshed", this._observer);
            }
        },

        componentWillUnmount: function(){
            if(this.props.controller === pydio.Controller){
                pydio.stopObserving("actions_refreshed", this._observer);
            }else {
                this.props.controller.stopObserving("actions_refreshed", this._observer);
            }
        },

        getInitialState: function(){
            return {
                groups:this.props.controller.getToolbarsActions(this.props.toolbars, this.props.groupOtherList)
            };
        },

        getDefaultProps:function(){
            return {
                controller: global.pydio.Controller,
                renderingType:'button',
                groupOtherList:[]
            }
        },

        render: function(){
            let groups = this.state.groups
            let actions = [];
            let toolbars = this.props.toolbars;
            if(this.props.groupOtherList.length){
                toolbars = toolbars.concat(['MORE_ACTION']);
            }
            let renderingType = this.props.renderingType;
            toolbars.map(function(barName){
                if(!groups.has(barName)) return;
                groups.get(barName).map(function(action){
                    if(action.deny) return;
                    let menuItems = null;
                    let menuTitle = null;
                    let menuIcon  = null;

                    if(barName === 'MORE_ACTION') {
                        let subItems = action.subMenuItems.dynamicItems;
                        let items = [];
                        subItems.map(function (obj) {
                            if (obj.separator) {
                                items.push(obj);
                            } else if (obj.actionId) {
                                items.push(obj.actionId.getMenuData());
                            }
                        });
                        menuTitle = "More";
                        menuItems = pydioActionsToItems(items);
                        menuIcon  = "icon icon-plus";
                    }else if(action.subMenuItems.staticItems){
                        menuTitle = action.options.text;
                        menuItems = pydioActionsToItems(action.subMenuItems.staticItems);
                        menuIcon  = action.options.icon_class;
                    }else if(action.subMenuItems.dynamicBuilder){
                        menuTitle = action.options.text;
                        menuIcon  = action.options.icon_class;
                        menuItems = pydioActionsToItems(action.subMenuItems.dynamicBuilder());
                    }else{
                        menuTitle = action.options.text;
                        menuIcon  = action.options.icon_class;
                    }
                    let id = 'action-' + action.options.name;
                    if(renderingType === 'button-icon'){
                        menuTitle = <span className="button-icon"><span className={"button-icon-icon " + menuIcon}></span><span className="button-icon-label">{menuTitle}</span></span>;
                    }
                    if(menuItems){
                        if(renderingType === 'button' || renderingType === 'button-icon'){
                            actions.push(<ButtonMenu
                                className={id}
                                buttonTitle={menuTitle}
                                menuItems={menuItems}/>);
                        }else{
                            actions.push(<IconButtonMenu
                                className={id}
                                onMenuClicked={function(object){object.payload()}}
                                buttonClassName={menuIcon}
                                buttonTitle={menuTitle}
                                menuItems={menuItems}/>);
                        }
                    }else{
                        let click = function(synthEvent){action.apply();};
                        if(renderingType === 'button' || renderingType === 'button-icon'){
                            actions.push(<ReactMUI.FlatButton
                                className={id}
                                onClick={click}
                                label={menuTitle}/>);
                        }else{
                            actions.push(<ReactMUI.IconButton
                                className={menuIcon + ' ' + id}
                                onClick={click}
                                label={menuTitle}/>);
                        }
                    }
                });
            });
            let cName = this.props.className ? this.props.className : '';
            cName += ' ' + 'toolbar';
            return <div className={cName} id={this.props.id}>{actions}</div>
        }

    });

    let ns = global.Toolbars || {};
    ns.MFB = MFB;
    ns.IconButtonMenu = IconButtonMenu;
    ns.ButtonMenu = ButtonMenu;
    ns.ContextMenu = ContextMenu;
    ns.Toolbar = Toolbar;
    ns.ContextMenuNodeProviderMixin = ContextMenuNodeProviderMixin;
    global.Toolbars = ns;

})(window);