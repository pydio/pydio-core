(function(global) {

    /*********************************************
    /* ShareNotification Object
    /*
    /* Handling the display and actions for the
    /* notification coming with a local or remote
    /* share
    /*********************************************/
    class ShareNotification extends Observable {

        // Init
        constructor(share, options){
            super();
            this._status  = share.status;
            this._data = {};

            this.setShareStatus(share.status);
            this.setOwner(share.owner);
            this.setLabel(share.label);
            this.setCreationDate(share.cr_date);
            this.setActions(share.actions);

            this.options  = options || {};

            this._setStatus('idle');
        }

        // GENERIC: STATUS / LOAD / SAVE
        _setStatus(status) {
            this._status = status;
            this.notify('status_changed', {
                status: status,
                share: this
            });
        }

        // Getters / Setters
        setShareStatus(status) {
            this._data['status'] = status;
        }

        setOwner(owner) {
            this._data['owner'] = owner;
        }

        setLabel(label) {
            this._data['label'] = label;
        }

        setCreationDate(crDate) {
            this._data['cr_date'] = crDate;
        }

        setActions(actions) {
            this._data['actions'] = actions;
        }

        getShareStatus() {
            return this._data['status'];
        }

        getOwner() {
            return this._data['owner'];
        }

        getLabel() {
            return this._data['label'];
        }

        getCreationDate() {
            return this._data['cr_date'];
        }

        getActions() {
            return this._data['actions'];
        }

        getFormattedDate() {
            var crDate = new Date();

            crDate.setTime(this.getCreationDate() * 1000);

            return formatDate(crDate);
        }

        // Actions
        loadAction(options) {
            ShareNotification.loadAction(
                options,
                function(transport){
                    this._setStatus('idle');
                }.bind(this));
        }

        // Static (eq Client)
        static loadAction(options, completeCallback=null, errorCallback=null, settings={}) {
            PydioApi.getClient().request(options, completeCallback, errorCallback, settings);
        }
    }


    // Globals
    var ReactModel = global.ReactModel || {};
    ReactModel['ShareNotification'] = ShareNotification;
    global.ReactModel = ReactModel;
    global.ReactModelShareNotification = ShareNotification;  // Set for dependencies management

})(window);