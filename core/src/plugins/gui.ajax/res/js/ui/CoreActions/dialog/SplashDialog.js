const React = require('react')
const PydioApi = require('pydio/http/api')
const BootUI = require('pydio/http/resources-manager').requireLib('boot');
const {ActionDialogMixin, SubmitButtonProviderMixin, Loader} = BootUI;

const SplashDialog = React.createClass({

    mixins:[
        ActionDialogMixin,
        SubmitButtonProviderMixin
    ],

    getDefaultProps: function(){
        return {
            dialogTitle: '',
            dialogSize:'lg',
            dialogIsModal: false,
            dialogPadding: false,
            dialogScrollBody: true
        };
    },
    submit(){
        this.dismiss();
    },

    getInitialState: function(){
        return {aboutContent: null};
    },

    componentDidMount: function(){

        PydioApi.getClient().request({
            get_action:'display_doc',
            doc_file:'CREDITS'
        }, function(transport){
            this.setState({
                aboutContent: transport.responseText
            });
        }.bind(this));

    },

    render: function(){
        if(!this.state.aboutContent){
            return <Loader style={{minHeight: 200}}/>;
        }else{
            let ct = () => {return {__html: this.state.aboutContent}};
            return <div style={{fontSize:13, padding: '0 10px'}} dangerouslySetInnerHTML={ct()}/>;
        }
    }

});

export default SplashDialog