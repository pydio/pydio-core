const SmartBanner = require('smart-app-banner');

export default React.createClass({

    componentDidMount: function(){

        // @TODO
        // PASS THIS URL TO THE NATIVE APP FOR AUTO REGISTRATION OF THE SERVER
        var currentHref = document.location.href;
        $("ajxpserver-redir").href = cleanURL(currentHref).replace("http://", "ajxpserver://").replace("https://", "ajxpservers://");
        if(currentHref.indexOf("#") > -1){
            currentHref = currentHref.substr(0, currentHref.indexOf("#"));
        }


        global.ajxpMobile = true;
        global.pydioSmartBanner = new SmartBanner({
            daysHidden: 15,   // days to hide banner after close button is clicked (defaults to 15)
            daysReminder: 90, // days to hide banner after "VIEW" button is clicked (defaults to 90)
            appStoreLanguage: 'us', // language code for the App Store (defaults to user's browser language)
            title: 'Pydio Pro',
            author: 'Abstrium SAS',
            button: 'VIEW',
            store: {
                ios: 'On the App Store',
                android: 'In Google Play'
            },
            price: {
                ios: '0,99€',
                android: '0,99€'
            }
            // , theme: '' // put platform type ('ios', 'android', etc.) here to force single theme on all device
            // , icon: '' // full path to icon image if not using website icon image
            // , force: 'ios' // Uncomment for platform emulation
        });

    },

    render: function(){
        return null;
    }

});