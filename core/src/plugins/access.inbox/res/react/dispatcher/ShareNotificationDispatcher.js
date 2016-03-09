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

            this._active = true;

            this.setStatus('idle');
        }

        start() {
            if (this._active) {
                console.warn('Dispatcher already active');
            }

            this._active = true;
            this.setStatus('idle');
        }

        stop() {
            this._active = false;
        }

        isActive() {
            return this._active;
        }

        getStatus() {
            return this._status;
        }

        // GENERIC: STATUS / LOAD / SAVE
        setStatus(status) {
            if (!this.isActive()) return;

            this._status = status;

            this.notify('status_changed', {
                status: status
            });
        }

        observe(eventName, observer) {
            super.observe(eventName, observer);

            this.start();
        }

        stopObserving(eventName, observer) {
            super.stopObserving(eventName, observer);

            if (! this.hasObservers()) {
                this.stop();
            }
        }

        static getClient() {
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