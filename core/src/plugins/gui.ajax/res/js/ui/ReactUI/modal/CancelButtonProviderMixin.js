export default {
    getCancelCallback(){
        if(this.cancel){
            return this.cancel;
        }else{
            return this.props.onDismiss;
        }
    }
};
