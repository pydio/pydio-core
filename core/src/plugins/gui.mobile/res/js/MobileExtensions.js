const SmartBanner = require('smart-app-banner');

export default React.createClass({

    componentDidMount: function(){

        global.ajxpMobile = true;
        global.pydioSmartBanner = new SmartBanner({
            daysHidden: 15,   // days to hide banner after close button is clicked (defaults to 15)
            daysReminder: 90, // days to hide banner after "VIEW" button is clicked (defaults to 90)
            appStoreLanguage: 'us', // language code for the App Store (defaults to user's browser language)
            title: 'MyPage',
            author: 'MyCompany LLC',
            button: 'VIEW',
            store: {
                android: 'In Google Play'
            },
            price: {
                ios: 'FREE',
                android: 'FREE'
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