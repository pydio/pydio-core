import ActionDialogMixin from './ActionDialogMixin'
import CancelButtonProviderMixin from './CancelButtonProviderMixin'
import SubmitButtonProviderMixin from './SubmitButtonProviderMixin'

export default React.createClass({

    propTypes: {
        message: React.PropTypes.string.isRequired,
        validCallback: React.PropTypes.func.isRequired
    },

    mixins:[
        ActionDialogMixin,
        CancelButtonProviderMixin,
        SubmitButtonProviderMixin
    ],

    getDefaultProps: function(){
        return {
            dialogTitle: 'Confirm',
            dialogIsModal: true
        };
    },
    submit(){
        this.props.validCallback();
        this.dismiss();
    },
    render: function(){
        return <div>{this.props.message}</div>;
    }

});

