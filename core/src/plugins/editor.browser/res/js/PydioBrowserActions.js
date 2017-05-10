const React = require('react');
const {TextField} = require('material-ui');
const {ActionDialogMixin, CancelButtonProviderMixin, SubmitButtonProviderMixin} = require('pydio').requireLib('boot')
const PydioApi = require('pydio/http/api');

class Callbacks{
    static createLink(){
        pydio.UI.openComponentInModal('PydioBrowserActions', 'CreateLinkDialog');
    }
}

const CreateLinkDialog = React.createClass({

    mixins:[
        ActionDialogMixin,
        CancelButtonProviderMixin,
        SubmitButtonProviderMixin
    ],

    getDefaultProps: function(){
        return {
            dialogSize: 'sm',
            dialogTitleId: 'openbrowser.4'
        }
    },

    submit: function(){
        const name = this.refs.name.getValue();
        const url = this.refs.url.getValue();
        if(!name || !url) return;
        PydioApi.getClient().request({
            get_action: 'mkfile',
            dir       : this.props.pydio.getContextHolder().getContextNode().getPath(),
            filename  : name + '.url',
            content   : url
        }, () => {
            this.dismiss();
        });
    },

    render(){

        const mess = this.props.pydio.MessageHash;
        return (
            <div>
                <TextField ref="url" floatingLabelText={mess['openbrowser.6']} fullWidth={true} hintText="https://..."  onKeyDown={(e) => {this.submitOnEnterKey(e)}} />
                <TextField ref="name" floatingLabelText={mess['openbrowser.8']} fullWidth={true} onKeyDown={(e) => {this.submitOnEnterKey(e)}}/>
            </div>
        );
    }

});

window.PydioBrowserActions = {
    Callbacks,
    CreateLinkDialog
};
