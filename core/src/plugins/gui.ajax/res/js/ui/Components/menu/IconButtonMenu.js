import PopupMenu from './PopupMenu'

export default React.createClass({

    propTypes: {
        buttonTitle: React.PropTypes.string.isRequired,
        buttonClassName: React.PropTypes.string.isRequired,
        className: React.PropTypes.string,
        direction: React.PropTypes.oneOf(['right', 'left']),
        menuItems: React.PropTypes.array.isRequired,
        onMenuClicked: React.PropTypes.func.isRequired
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

    menuClicked:function(event, index, menuItem){
        this.props.onMenuClicked(menuItem);
        this.setState({showMenu: false});
    },

    render: function(){
        return (
            <span className={"toolbars-button-menu " + (this.props.className ? this.props.className  : '')}>
                    <MaterialUI.IconButton
                        ref="menuButton"
                        tooltip={this.props.buttonTitle}
                        iconClassName={this.props.buttonClassName}
                        onTouchTap={this.showMenu}
                        iconStyle={this.props.buttonStyle}
                    />
                    <MaterialUI.Popover
                        open={this.state.showMenu}
                        anchorEl={this.state.anchor}
                        anchorOrigin={{horizontal: this.props.direction || 'right', vertical: 'bottom'}}
                        targetOrigin={{horizontal: this.props.direction || 'right', vertical: 'top'}}
                        onRequestClose={() => {this.setState({showMenu: false})}}
                    >
                        <ReactMUI.Menu
                            onItemClick={this.menuClicked}
                            menuItems={this.props.menuItems}
                        />
                    </MaterialUI.Popover>
                </span>
        );
    }

});

