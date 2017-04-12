import {RoleMessagesConsumerMixin} from '../util/MessagesMixin'
import RightsSelector from './RightsSelector'

export default React.createClass({

    mixins:[RoleMessagesConsumerMixin],

    propTypes:{
        id:React.PropTypes.string,
        label:React.PropTypes.string,
        role:React.PropTypes.object,
        roleParent:React.PropTypes.object,
        pluginsFilter:React.PropTypes.func,
        paramsFilter:React.PropTypes.func,
        toggleEdit:React.PropTypes.func,
        editMode:React.PropTypes.bool,
        titleOnly:React.PropTypes.bool,
        editOnly:React.PropTypes.bool,
        noParamsListEdit:React.PropTypes.bool,
        uniqueScope:React.PropTypes.bool,
        showModal:React.PropTypes.func,
        hideModal:React.PropTypes.func,
        Controller:React.PropTypes.object,
        showPermissionMask:React.PropTypes.bool,
        supportsFolderBrowsing:React.PropTypes.bool
    },

    onAclChange:function(newValue, oldValue){
        this.props.Controller.updateAcl(this.props.id, newValue);
    },

    onMaskChange:function(values){
        this.props.Controller.updateMask(this.props.id, values);
    },

    getInitialState:function(){
        return {displayMask: false};
    },

    toggleDisplayMask: function(){
        this.setState({displayMask:!this.state.displayMask});
    },

    render: function(){
        var wsId = this.props.id;
        var parentAcls = (this.props.roleParent && this.props.roleParent.ACL) ?  this.props.roleParent.ACL : {};
        var acls = (this.props.role && this.props.role.ACL) ?  this.props.role.ACL : {};
        var label = this.props.label;
        var inherited = false;
        if(!acls[wsId] && parentAcls[wsId]){
            label += ' ('+ this.context.getPydioRoleMessage('38') +')';
            inherited = true;
        }
        var secondLine, action;
        var aclString = acls[wsId] || parentAcls[wsId];
        if(!aclString) aclString = "";
        action = <RightsSelector
            acl={aclString}
            onChange={this.onAclChange}
            hideLabels={true}
        />;
        if(this.props.showPermissionMask && (aclString.indexOf('r') != -1 || aclString.indexOf('w') != -1 )){

            var toggleButton = <ReactMUI.FontIcon
                className={"icon-" + (this.state.displayMask ? "minus" : "plus")}
                onClick={this.toggleDisplayMask}
                style={{cursor:'pointer', padding: '0 8px'}}
            />;
            label = (
                <div>
                    {label} {toggleButton}
                </div>
            );
            if(this.state.displayMask){
                var parentMask = this.props.roleParent.MASKS &&  this.props.roleParent.MASKS[wsId] ? this.props.roleParent.MASKS[wsId] : {};
                var mask = this.props.role.MASKS &&  this.props.role.MASKS[wsId] ? this.props.role.MASKS[wsId] : {};
                action = null;
                var aclObject;
                if(aclString){
                    aclObject = {
                        read:aclString.indexOf('r') != -1,
                        write:aclString.indexOf('w') != -1
                    };
                }

                if(this.props.supportsFolderBrowsing){
                    secondLine = (
                        <ReactMUI.Paper zDepth={1} style={{margin: '8px 20px', backgroundColor:'white', color:'rgba(0,0,0,0.87)'}}>
                            <EnterpriseComponents.PermissionMaskEditor
                                workspaceId={wsId}
                                parentMask={parentMask}
                                mask={mask}
                                onMaskChange={this.onMaskChange}
                                showModal={this.props.showModal}
                                hideModal={this.props.hideModal}
                                globalWorkspacePermissions={aclObject}
                            />
                        </ReactMUI.Paper>
                    );
                }else{
                    secondLine = (
                        <ReactMUI.Paper zDepth={1} style={{margin: '8px 20px', backgroundColor:'white', color:'rgba(0,0,0,0.87)'}}>
                            <EnterpriseComponents.PermissionMaskEditorFree
                                workspaceId={wsId}
                                parentMask={parentMask}
                                mask={mask}
                                onMaskChange={this.onMaskChange}
                                showModal={this.props.showModal}
                                hideModal={this.props.hideModal}
                                globalWorkspacePermissions={aclObject}
                            />
                        </ReactMUI.Paper>
                    );
                }
            }

        }

        return (
            <PydioComponents.ListEntry
                className={ (inherited ? "workspace-acl-entry-inherited " : "") + "workspace-acl-entry"}
                firstLine={label}
                secondLine={secondLine}
                actions={action}
            />
        );
    }

});

