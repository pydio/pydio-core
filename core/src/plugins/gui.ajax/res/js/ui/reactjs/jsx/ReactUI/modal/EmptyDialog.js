import ActionDialogMixin from './ActionDialogMixin'
import CancelButtonProviderMixin from './CancelButtonProviderMixin'
import SubmitButtonProviderMixin from './SubmitButtonProviderMixin'

/**
 * Sample Dialog class used for reference only, ready to be
 * copy/pasted :-)
 */
export default React.createClass({

    mixins:[
        ActionDialogMixin,
        CancelButtonProviderMixin,
        SubmitButtonProviderMixin
    ],

    getDefaultProps: function(){
        return {
            dialogTitle: "Title",
            dialogIsModal: true
        };
    },
    submit(){
        this.dismiss();
    },
    render: function(){
        return <div>Empty</div>;
    }

});

