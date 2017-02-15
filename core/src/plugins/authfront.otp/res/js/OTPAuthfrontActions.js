(function(global){

    let pydio = global.pydio;

    class Callbacks{

        static setupScreen(){

            modal.showDialogForm('', 'otp_setup_screen', function(oForm){

                PydioApi.getClient().request({get_action:"otp_show_setup_screen"}, function(t){
                    if(t.responseJSON){
                        modal.getForm().down("#google_otp").setValue(t.responseJSON.key);
                        React.render(
                            React.createElement(ReactQRCode, {
                                size:200,
                                value:t.responseJSON.qrcode,
                                level:'L'
                            }),
                            modal.getForm().down("#qrcode")
                        );
                    }
                });
                modal.refreshDialogPosition();

            }, function(oForm){

                if(!modal.getForm().down("#google_otp_verification").getValue()){
                    pydio.displayMessage('ERROR', 'Please set up verification code');
                    return false;
                }
                PydioApi.getClient().request({
                    get_action:"otp_show_setup_screen",
                    step:"verify",
                    otp:modal.getForm().down("#google_otp_verification").getValue()
                }, function(t){
                    if(t.responseJSON && t.responseJSON.RESULT === "OK"){
                        location.reload();
                    }
                });

            }, null, true);

        }

    }

    global.OTPAuthfrontActions = {
        Callbacks: Callbacks
    };

})(window)