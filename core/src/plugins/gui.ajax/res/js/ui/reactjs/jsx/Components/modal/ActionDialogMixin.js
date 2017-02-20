export default {
    getTitle: function(){
        return this.props.dialogTitleId ? window.pydio.MessageHash[this.props.dialogTitleId] : this.props.dialogTitle;
    },
    isModal: function(){
        return this.props.dialogIsModal || false;
    },
    dismiss: function(){
        this.props.dismiss();
    },
    getDialogClassName: function(){
        return this.props.dialogClassName || 'dialog-max-420';
    }
};
