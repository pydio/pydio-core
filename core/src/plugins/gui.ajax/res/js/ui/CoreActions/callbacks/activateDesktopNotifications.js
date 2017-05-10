import {global, MessageHash} from '../globals'

export default function(){

    if(global.Notification){
        alert(MessageHash["notification_center.12"]);
        global.Notification.requestPermission(function(grant) {
            ['default', 'granted', 'denied'].indexOf(grant) === true
        });
    }else{
        global.alert(MessageHash["notification_center.13"]);
    }

}