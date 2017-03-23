import NavigationHelper from '../util/NavigationHelper'
import MenuItemListener from '../util/MenuItemListener'

const AdminLeftNav = React.createClass({

    propTypes:{
        rootNode:React.PropTypes.instanceOf(AjxpNode),
        contextNode:React.PropTypes.instanceOf(AjxpNode),
        dataModel:React.PropTypes.instanceOf(PydioDataModel)
    },

    componentDidMount: function(){
        this.refs.leftNav.close();
        MenuItemListener.getInstance().observe("item_changed", function(){
            this.forceUpdate();
        }.bind(this));
        global.setTimeout(this.checkForUpdates, 5000);
    },

    componentWillUnmount: function(){
        MenuItemListener.getInstance().stopObserving("item_changed");
    },

    checkForUpdates: function(){
        if(this.props.pydio.Controller.getActionByName("get_upgrade_path")){
            PydioApi.getClient().request({get_action:'get_upgrade_path'}, function(transp){
                var response = transp.responseJSON;
                var fakeNode = new AjxpNode("/admin/action.updater");
                var child = fakeNode.findInArbo(this.props.rootNode);
                if(child){
                    var length = 0;
                    if(response && response.packages.length) {
                        length = response.packages.length;
                    }
                    child.getMetadata().set('flag', length);
                    MenuItemListener.getInstance().notify("item_changed");
                }
            }.bind(this));
        }
    },

    openMenu: function(){
        if(this.refs.leftNav.state.open){
            this.cancelCloseBuffer();
        }
        this.refs.leftNav.toggle();
    },
    menuClicked:function(event, index, menuItem){
        if(menuItem.payload){
            this.props.dataModel.setSelectedNodes([]);
            this.props.dataModel.setContextNode(menuItem.payload);
        }
    },
    leftNavMouseOver:function(){
        this.cancelCloseBuffer();
        if(!this.refs.leftNav.state.open){
            this.refs.leftNav.toggle();
        }
    },
    leftNavMouseOut:function(){
        this.bufferClose();
    },

    leftNavScroll:function(){
        this.cancelCloseBuffer();
    },

    cancelCloseBuffer: function(){
        if(this.__closeTimer){
            global.clearTimeout(this.__closeTimer);
        }
    },

    bufferClose:function(time, callback){
        this.cancelCloseBuffer();
        this.__closeTimer = global.setTimeout(function(){
            if(this.isMounted() && this.refs.leftNav) this.refs.leftNav.close();
        }.bind(this), 500);
    },


    render: function(){
        var menuItems = [];
        var selectedIndex = NavigationHelper.buildNavigationItems(this.props.rootNode, this.props.contextNode, menuItems);

        var menuHeader = (
            <div onMouseOver={this.leftNavMouseOver} onMouseOut={this.leftNavMouseOut} onScroll={this.leftNavScroll} className="left-nav-menu-scroller">
                <ReactMUI.Menu onItemClick={this.menuClicked} zDepth={0} menuItems={menuItems} selectedIndex={selectedIndex}/>
            </div>
        );
        return <ReactMUI.LeftNav className="admin-main-nav" docked={true} isInitiallyOpen={false} menuItems={[]} ref="leftNav" header={menuHeader}/>
    }

});

export {AdminLeftNav as default}