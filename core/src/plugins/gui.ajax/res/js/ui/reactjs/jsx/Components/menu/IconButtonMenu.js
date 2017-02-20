import PopupMenu from './PopupMenu'

export default React.createClass({

    propTypes: {
        buttonTitle: React.PropTypes.string.isRequired,
        buttonClassName: React.PropTypes.string.isRequired,
        className: React.PropTypes.string,
        menuItems: React.PropTypes.array.isRequired,
        onMenuClicked: React.PropTypes.func.isRequired
    },

    collectElements: function(){
        return [ReactDOM.findDOMNode(this.refs['menuButton'])];
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

