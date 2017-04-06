const {FlatButton} = require('material-ui')

class ButtonsComputer{

    constructor(pydio, shareModel, buttonsUpdater, dismissCallback, getMessage){
        this.pydio = pydio;
        this._buttonsUpdater = buttonsUpdater;
        this._dismissCallback = dismissCallback;
        this._shareModel = shareModel;
        this._saveDisabled = false;
        this._getMessage = getMessage;
    }
    enableSave(){
        this._saveDisabled = false;
        this.modelUpdated();
    }
    disableSave(){
        this._saveDisabled = true;
        this.modelUpdated();
    }
    triggerModelSave(){
        this._shareModel.save();
    }
    triggerModelRevert(){
        this._shareModel.revertChanges();
    }
    disableAllShare(){
        this._shareModel.stopSharing(this._dismissCallback.bind(this));
    }
    modelUpdated(){
        this._buttonsUpdater(this.getButtons());
    }
    start(){
        this._modelObserver = this.modelUpdated.bind(this);
        this._disableSaveObserver = this.disableSave.bind(this);
        this._enableSaveObserver = this.enableSave.bind(this);
        this._shareModel.observe("status_changed", this._modelObserver);
        this._shareModel.observe('saving', this._disableSaveObserver);
        this._shareModel.observe('saved', this._enableSaveObserver);
    }
    stop(){
        this._shareModel.stopObserving("status_changed", this._modelObserver);
        this._shareModel.stopObserving('saving', this._disableSaveObserver);
        this._shareModel.stopObserving('saved', this._enableSaveObserver);
    }
    getButtons(){
        let buttons = [];
        if(this._shareModel.getStatus() == 'modified'){
            buttons.push(<a style={{cursor:'pointer',color:'rgba(0,0,0,0.53)'}} onClick={this.triggerModelRevert.bind(this)}>{this._getMessage('179')}</a>);
            buttons.push(<FlatButton secondary={true} disabled={this._saveDisabled} label={this._getMessage('53', '')} onClick={this.triggerModelSave.bind(this)}/>);
            buttons.push(<FlatButton secondary={false} label={this._getMessage('86', '')} onClick={this._dismissCallback.bind(this)}/>);
        }else{
            if((this._shareModel.hasActiveShares() && (this._shareModel.currentIsOwner())) || this._shareModel.getStatus() === 'error' || this.pydio.user.activeRepository === "ajxp_conf"){
                buttons.push(<FlatButton  disabled={this._saveDisabled} secondary={true} label={this._getMessage('6')} onClick={this.disableAllShare.bind(this)}/>);
            }
            buttons.push(<FlatButton secondary={false} label={this._getMessage('86', '')} onClick={this._dismissCallback.bind(this)}/>);
        }
        return buttons;
    }
}

export {ButtonsComputer as default}