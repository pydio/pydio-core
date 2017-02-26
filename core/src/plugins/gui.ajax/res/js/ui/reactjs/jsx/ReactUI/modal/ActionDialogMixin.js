export default {
    getTitle: function(){
        return this.props.dialogTitleId ? window.pydio.MessageHash[this.props.dialogTitleId] : this.props.dialogTitle;
    },
    isModal: function(){
        return this.props.dialogIsModal || false;
    },
    dismiss: function() {
        return this.props.onDismiss()
    },
    getSize: function() {
        return this.props.dialogSize || 'md'
    },
    getPadding: function() {
        return typeof this.props.dialogPadding !== "undefined" ? this.props.dialogPadding : true
    },
    scrollBody: function(){
        return this.props.dialogScrollBody || false;
    }
};
