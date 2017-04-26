const React = require('react')
const {List, ListItem} = require('material-ui')
const {ActionDialogMixin, CancelButtonProviderMixin} = require('pydio').requireLib('boot')
const {WorkspacesListMaterial} = require('pydio').requireLib('workspaces')

const WorkspacePickerDialog = React.createClass({

    mixins: [
        ActionDialogMixin,
        CancelButtonProviderMixin
    ],

    getDefaultProps: function(){
        return {
            dialogTitleId: 'user_home.90',
            dialogSize: 'sm',
            dialogPadding: false,
            dialogIsModal: true,
            dialogScrollBody: true
        };
    },

    submit: function(){
        this.dismiss();
    },

    workspaceTouchTap: function(wsId){
        this.dismiss();
        this.props.onWorkspaceTouchTap(wsId);
    },

    render: function(){

        const {pydio} = this.props;
        return (
            <div style={{width:'100%', height: '100%', display:'flex', flexDirection:'column'}}>
                {this.props.legend}
                <WorkspacesListMaterial
                    pydio={pydio}
                    workspaces={pydio.user ? pydio.user.getRepositoriesList() : []}
                    showTreeForWorkspace={false}
                    onWorkspaceTouchTap={this.workspaceTouchTap}
                    filterByType={'entries'}
                    sectionTitleStyle={{display:'none'}}
                    style={{flex:1, overflowY: 'auto', maxHeight:400}}
                />
            </div>
        );

    }

});

export {WorkspacePickerDialog as default}