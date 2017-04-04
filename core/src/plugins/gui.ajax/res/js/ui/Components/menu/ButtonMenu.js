import Utils from './Utils'
const React = require('react')
const {Menu} = require('material-ui')
const Controller = require('pydio/model/controller')

const ButtonMenu = React.createClass({

    propTypes:{
        buttonTitle : React.PropTypes.oneOfType([React.PropTypes.string,React.PropTypes.object]).isRequired,
        menuItems   : React.PropTypes.array.isRequired,
        className   : React.PropTypes.string,
        raised      : React.PropTypes.bool,
        direction   : React.PropTypes.oneOf(['left', 'right'])
    },

    getInitialState: function(){
        return {showMenu: false};
    },


    showMenu: function(event){
        this.setState({
            showMenu: true,
            anchor: event.currentTarget
        })
    },

    menuClicked: function(event, index, object){
        //object.payload();
        this.setState({showMenu: false});
    },

    render: function(){
        let label = <span>{this.props.buttonTitle} <span className="icon-caret-down"></span></span>
        let button;
        const props = {
            primary: this.props.primary,
            secondary: this.props.secondary,
            disabled: this.props.disabeld,
            label: label,
            onTouchTap: this.showMenu
        };
        const {menuItems} = this.props;
        const {showMenu, anchor} = this.state;
        if(menuItems.length){
            if(this.props.raised){
                button = <MaterialUI.RaisedButton {...props} style={this.props.buttonStyle} labelStyle={this.props.buttonLabelStyle}/>;
            }else{
                button = <MaterialUI.FlatButton {...props} style={this.props.buttonStyle} labelStyle={this.props.buttonLabelStyle}/>;
            }
        }
        return (
            <span id={this.props.id} className={this.props.className}>
                {button}
                <MaterialUI.Popover
                    className="menuPopover"
                    open={showMenu}
                    anchorEl={anchor}
                    anchorOrigin={{horizontal: this.props.direction || 'left', vertical: 'bottom'}}
                    targetOrigin={{horizontal: this.props.direction || 'left', vertical: 'top'}}
                    onRequestClose={() => {this.setState({showMenu: false})}}
                >
                    {Utils.itemsToMenu(menuItems, this.menuClicked.bind(this))}
                </MaterialUI.Popover>
            </span>
        );
    }

});

import MenuItemsConsumer from './MenuItemsConsumer'

export default MenuItemsConsumer(ButtonMenu)