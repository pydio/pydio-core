import Utils from './Utils'
import PopupMenu from './PopupMenu'

export default React.createClass({

    propTypes:{
        buttonTitle: React.PropTypes.oneOfType([React.PropTypes.string,React.PropTypes.object]).isRequired,
        className: React.PropTypes.string,
        menuItems:React.PropTypes.array,
        selectionContext:React.PropTypes.string,
        toolbars:React.PropTypes.array,
        raised:React.PropTypes.bool,
        direction: React.PropTypes.oneOf(['left', 'right'])
    },

    getInitialState: function(){
        return {showMenu: false, menuItems: []};
    },

    showMenu: function(event){
        let menuItems;
        if(this.props.menuItems){
            menuItems = this.props.menuItems;
        }else{
            let actions = this.props.pydio.Controller.getContextActions('genericContext', null, this.props.toolbars);
            menuItems = Utils.pydioActionsToItems(actions);
        }
        this.setState({
            showMenu: true,
            menuItems: menuItems,
            anchor: event.currentTarget
        })
    },

    menuClicked: function(event, index, object){
        object.payload();
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
        if(this.props.raised){
            button = <MaterialUI.RaisedButton {...props} style={this.props.buttonStyle} labelStyle={this.props.buttonLabelStyle}/>;
        }else{
            button = <MaterialUI.FlatButton {...props}/>;
        }
        return (
            <span id={this.props.id} className={this.props.className}>
                {button}
                <MaterialUI.Popover
                    className="menuPopover"
                    open={this.state.showMenu}
                    anchorEl={this.state.anchor}
                    anchorOrigin={{horizontal: this.props.direction || 'left', vertical: 'bottom'}}
                    targetOrigin={{horizontal: this.props.direction || 'left', vertical: 'top'}}
                    onRequestClose={() => {this.setState({showMenu: false})}}
                >
                    <ReactMUI.Menu
                        onItemClick={this.menuClicked}
                        menuItems={this.state.menuItems}
                    />
                </MaterialUI.Popover>
            </span>
        );
    }

});
