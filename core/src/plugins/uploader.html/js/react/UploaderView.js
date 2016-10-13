(function(global){
    
    var DropUploader = React.createClass({

        onDrop: function(files, event, sourceComponent){
            var items, files;
            if(event.dataTransfer){
                items = event.dataTransfer.items || [];
                files = event.dataTransfer.files;
            }else if(event.target){
                files = event.target.files;
            }
            let contextNode = global.pydio.getContextHolder().getContextNode();
            UploaderModel.Store.getInstance().handleDropEventResults(items, files, contextNode);
        },

        onFolderPicked: function(files){
            let contextNode = global.pydio.getContextHolder().getContextNode();
            UploaderModel.Store.getInstance().handleFolderPickerResult(files, contextNode);
        },

        start: function(e){
            e.preventDefault();
            UploaderModel.Store.getInstance().processNext();
        },
        clear: function(e){
            e.preventDefault();
            UploaderModel.Store.getInstance().clearAll();
        },
        toggleOptions: function(e){
            e.preventDefault();
            let crtOptions = this.state && this.state.options ? this.state.options : false;
            this.setState({options:!crtOptions});
        },

        openFilePicker: function(e){
            e.preventDefault();
            this.refs.dropzone.open();
        },

        openFolderPicker: function(e){
            e.preventDefault();
            this.refs.dropzone.openFolderPicker();
        },

        render: function(){

            let options;
            if(this.state && this.state.options){
                let dismiss = function(e){
                    this.toggleOptions(e);
                    if(UploaderModel.Configs.getInstance().getOptionAsBool('DEFAULT_AUTO_START', 'upload_auto_send', true)){
                        UploaderModel.Store.getInstance().processNext();
                    }
                }.bind(this);
                options = <UploadOptionsPane onDismiss={dismiss}/>
            }
            let folderButton, startButton;
            let e = global.document.createElement('input');
            e.setAttribute('type', 'file');
            if('webkitdirectory' in e){
                folderButton = <ReactMUI.RaisedButton label="Pick Folder" onClick={this.openFolderPicker}/>;
            }
            e = null;
            let configs = UploaderModel.Configs.getInstance();
            if(!configs.getOptionAsBool('DEFAULT_AUTO_START', 'upload_auto_send', true)){
                startButton = <ReactMUI.FlatButton label="Start" onClick={this.start} secondary={true}/>
            }
            return (
                <div style={{position:'relative'}}>
                    <div className="react-mui-context uploader-action-bar">
                        <ReactMUI.FlatButton style={{float: 'right'}} label="Options"  onClick={this.toggleOptions}/>
                        <ReactMUI.RaisedButton secondary={true} label="Pick File" onClick={this.openFilePicker}/>
                        {folderButton}
                        {startButton}
                        <ReactMUI.FlatButton label="Clear List" onClick={this.clear}/>
                    </div>
                    <PydioForm.FileDropZone
                        ref="dropzone"
                        multiple={true}
                        enableFolders={true}
                        supportClick={false}
                        onDrop={this.onDrop}
                        onFolderPicked={this.onFolderPicked}
                        style={{width:'100%'}}
                    >
                        <TransfersList/>
                    </PydioForm.FileDropZone>
                    {options}
                </div>
            );

        }

    });

    var TransferFile = React.createClass({

        propTypes: {
            item: React.PropTypes.object.isRequired,
            className:React.PropTypes.string
        },

        componentDidMount: function(){
            this.props.item.observe('progress', function(value){
                this.setState({progress: value});
            }.bind(this));
            this.props.item.observe('status', function(value){
                this.setState({status: value});
            }.bind(this));
        },

        getInitialState: function(){
            return {
                progress: this.props.item.getProgress(),
                status: this.props.item.getStatus()
            };
        },

        abortTransfer: function(){
            UploaderModel.Store.getInstance().stopOrRemoveItem(this.props.item);
        },

        render: function(){
            let style;
            var messageIds = {
                "new" : 433,
                "loading":434,
                "loaded":435,
                "error":436
            };
            let statusMessage = this.props.item.getStatus();
            let stopButton;
            if(statusMessage === 'loading'){
                stopButton = <span className="stop-button icon-stop" onClick={this.abortTransfer}/>;
            }else{
                stopButton = <span className="stop-button mdi mdi-close" onClick={this.abortTransfer}/>;
            }
            if(statusMessage === 'error' && this.props.item.getErrorMessage()){
                statusMessage = this.props.item.getErrorMessage();
            }
            if(global.pydio.MessageHash[messageIds[statusMessage]]){
                statusMessage = global.pydio.MessageHash[messageIds[statusMessage]];
            }
            if(this.props.item.getRelativePath()){
                var relativeMessage = <span className="path">{this.props.item.getRelativePath()}</span>;
            }
            if(this.state && this.state.progress){
                style = {width: this.state.progress + '%'};
            }
            return (
                <div className={"file-row upload-" + this.props.item.getStatus() + " " + (this.props.className?this.props.className:"")}>
                    <span className="mdi mdi-file"/> {this.props.item.getFile().name}
                    {relativeMessage}
                    <span className="status">{statusMessage}</span>
                    {stopButton}
                    <div className="uploader-pgbar" style={style}/>
                </div>
            );
        }
    });

    var TransferFolder = React.createClass({

        propTypes: {
            item: React.PropTypes.object.isRequired
        },

        render: function(){
            let statusMessage;
            if(this.props.item.getStatus() === 'loaded'){
                statusMessage = 'Created';
            }
            return (
                <div className={"folder-row upload-" + this.props.item.getStatus() + " " + (this.props.className?this.props.className:"")}>
                    <span className="mdi mdi-folder"/> {this.props.item.getPath()} <span className="status">{statusMessage}</span>
                </div>
            );
        }
    });

    var TransfersList = React.createClass({

        componentDidMount: function(){
            let store = UploaderModel.Store.getInstance();
            this._storeObserver = function(){
                if(!this.isMounted()) return;
                this.setState({items: store.getItems()});
            }.bind(this);
            store.observe("update", this._storeObserver);
            store.observe("auto_close", function(){
                pydio.UI.modal.dismiss();
            });
            this.setState({items: store.getItems()});
        },

        componentWillUnmount: function(){
            if(this._storeObserver){
                UploaderModel.Store.getInstance().stopObserving("update", this._storeObserver);
                UploaderModel.Store.getInstance().stopObserving("auto_close");
            }
        },

        renderSection: function(accumulator, items, title = "", className=""){
            if(title && items.length){
                accumulator.push(<div className={className + " header"}>{title}</div>);
            }
            items.sort(function(a, b){
                let aType = a instanceof UploaderModel.FolderItem? 'folder' : 'file';
                let bType = b instanceof UploaderModel.FolderItem? 'folder' : 'file';
                if(aType === bType){
                    return 0;
                }else{
                    return aType === 'folder' ? -1 : 1;
                }
            });
            items.forEach(function(f){
                if(f instanceof UploaderModel.FolderItem){
                    accumulator.push( <TransferFolder key={f.getId()} item={f} className={className}/> );
                }else{
                    accumulator.push( <TransferFile key={f.getId()} item={f} className={className}/> );
                }
            });
        },

        render: function(){
            let items = [];
            if(this.state && this.state.items){
                this.renderSection(items, this.state.items.processing, 'Processing', 'section-processing');
                this.renderSection(items, this.state.items.pending, 'Pending', 'section-pending');
                this.renderSection(items, this.state.items.processed, 'Processed', 'section-processed');
            }
            return (
                <div id="upload_files_list" className={UploaderModel.Configs.getInstance().getOptionAsBool('UPLOAD_SHOW_PROCESSED', 'upload_show_processed', false) ? 'show-processed' : ''}>
                    {items}
                </div>
            )
        }
    });

    var UploadOptionsPane = React.createClass({

        propTypes: {
            onDismiss: React.PropTypes.func.isRequired
        },

        getInitialState: function(){

            let configs = UploaderModel.Configs.getInstance();
            return {
                configs: configs
            };

        },

        updateField: function(fName, event){
            if(fName === 'autostart'){
                let toggleStart = this.state.configs.getOptionAsBool('DEFAULT_AUTO_START', 'upload_auto_send', true);
                toggleStart = !toggleStart;
                this.state.configs.updateOption('upload_auto_send', toggleStart, true);
            }else if(fName === 'autoclose'){
                let toggleStart = this.state.configs.getOptionAsBool('DEFAULT_AUTO_CLOSE', 'upload_auto_close', true);
                toggleStart = !toggleStart;
                this.state.configs.updateOption('upload_auto_close', toggleStart, true);
            }else if(fName === 'existing'){
                this.state.configs.updateOption('upload_existing', event.target.getSelectedValue());
            }else if(fName === 'show_processed'){
                let toggleShowProcessed = this.state.configs.getOptionAsBool('UPLOAD_SHOW_PROCESSED', 'upload_show_processed', false);
                toggleShowProcessed = !toggleShowProcessed;
                this.state.configs.updateOption('upload_show_processed', toggleShowProcessed, true);
            }
            this.setState({random: Math.random()});
        },

        radioChange: function(e, newValue){
            this.state.configs.updateOption('upload_existing', newValue);
            this.setState({random: Math.random()});
        },
        
        render: function(){

            let maxUpload = this.state.configs.getOption('UPLOAD_MAX_SIZE');
            let maxUploadMessage = MessageHash[282] + ': ' + PathUtils.roundFileSize(maxUpload, '');
            let toggleStart = this.state.configs.getOptionAsBool('DEFAULT_AUTO_START', 'upload_auto_send');
            let toggleClose = this.state.configs.getOptionAsBool('DEFAULT_AUTO_CLOSE', 'upload_auto_close');
            let toggleShowProcessed = this.state.configs.getOptionAsBool('UPLOAD_SHOW_PROCESSED', 'upload_show_processed', false);
            let overwriteType = this.state.configs.getOption('DEFAULT_EXISTING', 'upload_existing');

            return (
                <div className="upload-options-pane react-mui-context">
                    <span className="close-options mdi mdi-close" onClick={this.props.onDismiss}></span>
                    <div className="option-row">{maxUploadMessage}</div>
                    <div className="option-row"><ReactMUI.Toggle label="Start uploading automatically" labelPosition="right" toggled={toggleStart} defaultToggled={toggleStart} onToggle={this.updateField.bind(this, 'autostart')}/></div>
                    <div className="option-row"><ReactMUI.Toggle label="Close panel after upload is finished" labelPosition="right"  toggled={toggleClose} onToggle={this.updateField.bind(this, 'autoclose')}/></div>
                    <div className="option-row"><ReactMUI.Toggle label="Show/hide processed files" labelPosition="right"  toggled={toggleShowProcessed} onToggle={this.updateField.bind(this, 'show_processed')}/></div>
                    <div className="option-row">
                        <div style={{marginBottom: 10}}>If a file with the same name exists</div>
                        <ReactMUI.RadioButtonGroup ref="group" name="shipSpeed" defaultSelected={overwriteType} onChange={this.radioChange}>
                            <ReactMUI.RadioButton value="alert" label="Stop upload and alert me"/>
                            <ReactMUI.RadioButton value="rename" label="Rename new file automatically"/>
                            <ReactMUI.RadioButton value="overwrite" label="Overwrite existing file"/>
                        </ReactMUI.RadioButtonGroup>
                    </div>
                </div>
            );
        }

    });

    var ns = global.UploaderView || {};
    ns.DropUploader = DropUploader;
    global.UploaderView = ns;

})(window);