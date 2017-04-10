const React = require('react')
const ReactDOM = require('react-dom')
const {Menu, Paper} = require('material-ui')
import Utils from './Utils'

export default React.createClass({

    propTypes: {
        menuItems: React.PropTypes.array.isRequired,
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
        const node = ReactDOM.findDOMNode(this.refs.menuContainer);
        if(node.contains(event.target) || node === event.target ){
            return;
        }

        this.setState({showMenu: false});
        if(this.props.onMenuClosed) this.props.onMenuClosed();

    },
    componentDidMount: function(){
        this._observer = this.hideMenu;
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
        this.hideMenu();
    },
    render: function(){

        let style = this.state.style || {};
        style = {...style, zIndex: 1000};
        const menu = Utils.itemsToMenu(this.state.menuItems, this.menuClicked, false, {desktop:true, display:'right', width: 250});
        if(this.state.showMenu) {
            return <Paper ref="menuContainer" className="menu-positioner" style={style}>{menu}</Paper>
        }else{
            return null;
        }
    }

});
