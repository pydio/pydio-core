export default {
    getSubmitCallback(){
        return this.submit;
    },
    submitOnEnterKey:function(event){
        if(event.key === 'Enter'){
            this.submit();
        }
    }
};

