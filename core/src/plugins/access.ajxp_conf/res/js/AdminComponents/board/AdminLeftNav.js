const React = require('react')
const {Menu} = require('material-ui')
const {muiThemeable} = require('material-ui/styles')

import NavigationHelper from '../util/NavigationHelper'
import MenuItemListener from '../util/MenuItemListener'
const AjxpNode = require('pydio/model/node')
const PydioDataModel = require('pydio/model/data-model')

let AdminLeftNav = React.createClass({

    propTypes:{
        rootNode        : React.PropTypes.instanceOf(AjxpNode),
        contextNode     : React.PropTypes.instanceOf(AjxpNode),
        dataModel       : React.PropTypes.instanceOf(PydioDataModel)
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
                const response = transp.responseJSON;
                const fakeNode = new AjxpNode("/admin/action.updater");
                const child = fakeNode.findInArbo(this.props.rootNode);
                if(child){
                    let length = 0;
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

    onMenuChange: function(event, node){
        this.props.dataModel.setSelectedNodes([]);
        this.props.dataModel.setContextNode(node);
    },

    render: function(){

        const {pydio, rootNode, contextNode, muiTheme} = this.props;

        const menuItems = NavigationHelper.buildNavigationItems(pydio, rootNode, muiTheme.palette);

        const menuHeader = (
            <div onMouseOver={this.leftNavMouseOver} onMouseOut={this.leftNavMouseOut} onScroll={this.leftNavScroll} className="left-nav-menu-scroller">
                <Menu onChange={this.onMenuChange} width={256} style={{maxWidth:256}} value={contextNode}>{menuItems}</Menu>
            </div>
        );
        return <ReactMUI.LeftNav className="admin-main-nav" docked={true} isInitiallyOpen={false} menuItems={[]} ref="leftNav" header={menuHeader}/>
    }

});

AdminLeftNav = muiThemeable()(AdminLeftNav);
export {AdminLeftNav as default}