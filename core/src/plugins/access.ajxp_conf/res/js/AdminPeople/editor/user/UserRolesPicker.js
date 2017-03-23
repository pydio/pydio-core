import {RoleMessagesConsumerMixin} from '../util/MessagesMixin'

export default React.createClass({

    mixins:[RoleMessagesConsumerMixin],

    propTypes: {
        availableRoles: React.PropTypes.array,
        rolesDetails: React.PropTypes.object,
        currentRoles:React.PropTypes.array,
        controller: React.PropTypes.object
    },

    onChange: function(e, selectedIndex, menuItem){
        var newRole = menuItem.payload;
        if(newRole == -1) return;
        var newRoles = this.props.currentRoles.slice();
        newRoles.push(newRole);
        this.props.controller.updateUserRoles(newRoles);
    },

    remove: function(roleId){
        var newRoles = LangUtils.arrayWithout(this.props.currentRoles, this.props.currentRoles.indexOf(roleId));
        this.props.controller.updateUserRoles(newRoles);
    },

    orderUpdated:function(oldId, newId, currentValues){
        var ordered = currentValues.map(function(o){return o.payload;});
        this.props.controller.orderUserRoles(ordered);
    },

    render: function(){

        var groups=[], manual=[], users=[];
        var currentRoles = this.props.currentRoles;
        var details = this.props.rolesDetails;
        currentRoles.map(function(r){
            if(r.startsWith('AJXP_GRP_/')){
                if(r == 'AJXP_GRP_/') {
                    groups.push('/ ' + this.context.getMessage('user.25', 'ajxp_admin'));
                }else {
                    groups.push(this.context.getMessage('user.26', 'ajxp_admin').replace('%s', r.substr('AJXP_GRP_'.length)));
                }
            }else if(r.startsWith('AJXP_USR_/')){
                users.push(this.context.getMessage('user.27', 'ajxp_admin'));
            }else{
                if(!details[r]){
                    return;
                }
                let label = details[r].label;
                if(details[r].sticky) label += ' [' + this.context.getMessage('19') + ']'; // always overrides
                manual.push({payload:r, text:label});
            }
        }.bind(this));

        var addableRoles = [{text:this.context.getMessage('20'), payload:-1}];
        this.props.availableRoles.map(function(r){
            if(currentRoles.indexOf(r) == -1) addableRoles.push({text:details[r].label, payload:r});
        });

        return (
            <div className="user-roles-picker">
                <h1>Manage roles {this.props.loadingMessage ? ' ('+this.context.getMessage('21')+')':''}
                    <div className="roles-picker-menu">
                        <ReactMUI.DropDownMenu menuItems={addableRoles} onChange={this.onChange} selectedIndex={0}/>
                    </div>
                </h1>
                <div className="roles-list">
                    {groups.map(function(g){
                        return <ReactMUI.Paper zDepth={0} key={"group-"+g}><div className="role-item role-item-group">{g}</div></ReactMUI.Paper>;
                    })}
                    <PydioComponents.SortableList
                        key="sortable"
                        values={manual}
                        removable={true}
                        onRemove={this.remove}
                        onOrderUpdated={this.orderUpdated}
                        itemClassName="role-item role-item-sortable"
                    />
                    {users.map(function(u){
                        return <ReactMUI.Paper zDepth={0} key={"user-"+u}><div className="role-item role-item-user">{u}</div></ReactMUI.Paper>;
                    })}
                </div>
            </div>
        );

    }

});
