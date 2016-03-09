(function(global) {

    /*********************************************
    /* ShareNotification Object
    /*
    /* Handling the display and actions for the
    /* notification coming with a local or remote
    /* share
    /*********************************************/
    class ShareNotificationDispatcher extends Observable {

        // Init
        constructor(options){
            super();
            this.setStatus('idle');
        }

        getStatus() {
            return this._status;
        }
        // GENERIC: STATUS / LOAD / SAVE
        setStatus(status) {
            this._status = status;

            this.notify('status_changed', {
                status: status
            });
        }

        static getClient(){
            if(ShareNotificationDispatcher._client) return ShareNotificationDispatcher._client;
            var client = new ShareNotificationDispatcher();
            ShareNotificationDispatcher._client = client;
            return client;
        }
    }


    // Globals
    var ReactDispatcher = global.ReactDispatcher || {};
    ReactDispatcher['ShareNotificationDispatcher'] = ShareNotificationDispatcher;
    global.ReactDispatcher = ReactDispatcher;
    global.ReactShareNotificationDispatcher = ShareNotificationDispatcher;  // Set for dependencies management

})(window);