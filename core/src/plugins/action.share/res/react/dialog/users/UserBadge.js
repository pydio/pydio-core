const React = require('react')
const {MenuItem, IconMenu} = require('material-ui')

const UserBadge = React.createClass({
    propTypes: {
        label: React.PropTypes.string,
        avatar: React.PropTypes.string,
        type:React.PropTypes.string,
        menus: React.PropTypes.object
    },

    renderMenu: function(){
        if (!this.props.menus || !this.props.menus.length) {
            return null;
        }
        const menuItems = this.props.menus.map(function(m){
            let rightIcon;
            if(m.checked){
                rightIcon = <span className="icon-check"/>;
            }
            return (
                <MenuItem
                    primaryText={m.text}
                    onTouchTap={m.callback}
                    rightIcon={rightIcon}/>
            );
        });
        const iconStyle = {fontSize: 18};
        return(
            <IconMenu
                iconButtonElement={<MaterialUI.IconButton style={{padding: 16}} iconStyle={iconStyle} iconClassName="icon-ellipsis-vertical"/>}
                anchorOrigin={{horizontal: 'right', vertical: 'top'}}
                targetOrigin={{horizontal: 'right', vertical: 'top'}}
            >
                {menuItems}
            </IconMenu>
        );
    },

    render: function () {
        var avatar;
        if(this.props.type == 'group') {
            avatar = <span className="avatar icon-group"/>;
        }else if(this.props.type == 'temporary') {
            avatar = <span className="avatar icon-plus"/>;
        }else if(this.props.type == 'remote_user'){
            avatar = <span className="avatar icon-cloud"/>;
        }else{
            avatar = <span className="avatar icon-user"/>;
        }
        var menu = this.renderMenu();
        return (
            <div className={"user-badge user-type-" + this.props.type}>
                {avatar}
                <span className="user-badge-label">{this.props.label}</span>
                {this.props.children}
                {menu}
            </div>
        );
    }
});

export {UserBadge as default}