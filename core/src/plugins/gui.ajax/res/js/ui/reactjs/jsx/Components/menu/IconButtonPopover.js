export default React.createClass({

    propTypes: {
        buttonTitle: React.PropTypes.string.isRequired,
        buttonClassName: React.PropTypes.string.isRequired,
        className: React.PropTypes.string,
        direction: React.PropTypes.oneOf(['right', 'left']),
        popoverContent: React.PropTypes.object.isRequired
    },

    getInitialState: function(){
        return {showPopover: false};
    },

    showPopover: function(event){
        this.setState({
            showPopover: true,
            anchor: event.currentTarget
        })
    },

    render: function(){
        return (
            <span className={"toolbars-button-menu " + (this.props.className ? this.props.className  : '')}>
                <MaterialUI.IconButton
                    ref="menuButton"
                    tooltip={this.props.buttonTitle}
                    iconClassName={this.props.buttonClassName}
                    onTouchTap={this.showPopover}
                    iconStyle={this.props.buttonStyle}
                />
                <MaterialUI.Popover
                    open={this.state.showPopover}
                    anchorEl={this.state.anchor}
                    anchorOrigin={{horizontal: this.props.direction || 'right', vertical: 'bottom'}}
                    targetOrigin={{horizontal: this.props.direction || 'right', vertical: 'top'}}
                    onRequestClose={() => {this.setState({showPopover: false})}}
                >
                    {this.props.popoverContent}
                </MaterialUI.Popover>
            </span>
        );
    }

});

