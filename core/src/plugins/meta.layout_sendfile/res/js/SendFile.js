(function(global){

    class Upload extends Observable{

        constructor(item){
            super();
            this._item = item;
            this._id = Math.random();

            item.observe('status', function(){
                if(item.getStatus() === 'loaded' && item.xhr && item.xhr.responseXML){
                    this.uploadFinished(item.xhr.responseXML);
                }
            }.bind(this));
        }

        uploadFinished(response){
            const addedNode = XMLUtils.XPathSelectSingleNode(response, "tree/nodes_diff/add/tree");
            if(!addedNode) {
                this.notify('error', 'No file added');
            }
            const newFileName = addedNode.getAttribute('filename');
            this._node = new AjxpNode(newFileName, true, PathUtils.getBasename(newFileName));
            this.notify('uploaded', this._node);
            this.share();
        }

        share(){
            this._shareModel = new ReactModel.Share(global.pydio, this._node);
            this._shareModel.observe('saved', function(){
                this.notify('shared', this._shareModel.getPublicLinks());
            }.bind(this));
            this._shareModel.togglePublicLink();
        }

        getPublicLink(){
            if(this._shareModel){
                const links = this._shareModel.getPublicLinks();
                if(links && links.length) return links[0];
            }
            return null;
        }

        getShareModel(){
            return this._shareModel;
        }

        getId(){
            return this._id;
        }

        getLabel(){
            return this._item.getFile().name;
        }

        getUploadItem(){
            return this._item;
        }

        getNode(){
            return this._node;
        }

    }

    let Progress = React.createClass({

        getInitialState: function(){
            return {progress: 0, status: ''};
        },

        componentDidMount: function(){
            this.props.item.observe('progress', function(value){
                this.setState({progress: value});
            }.bind(this));
            this.props.item.observe('status', function(value){
                this.setState({status: value});
            }.bind(this));
        },

        render: function(){
            return (
                <MaterialUI.CircularProgress
                    max={this.props.shared ? 100: 130}
                    size={this.props.size}
                    thickness={10}
                    mode="determinate"
                    value={this.state.progress}
                    style={{position:'absolute', top: 0, left: 0}}
                />
            );
        }

    });

    let UploadCard = React.createClass({

        mixins:[PydioReactUI.PydioContextConsumerMixin],

        propTypes: {
            entry: React.PropTypes.object,
            size: React.PropTypes.number,
            display: React.PropTypes.oneOf('big', 'large')
        },

        childContextTypes: {
            messages:React.PropTypes.object,
            getMessage:React.PropTypes.func
        },

        getChildContext: function() {
            // Override default namespace for Share dialog components
            const context = this.context;
            return {
                messages: context.messages,
                getMessage: function(messageId){
                    try{
                        return context.getMessage(messageId, 'share_center');
                    }catch(e){
                        return messageId;
                    }
                }
            };
        },

        render: function(){
            const {entry, size, display} = this.props;
            let boxWidth = 0;
            let comp, circle;
            if(entry.upload.getPublicLink()){
                comp = (
                    <MaterialUI.Paper zDepth={2} style={{width: 320, padding:'20px 0', marginLeft: 8}}>
                        <ShareDialog.PublicLinkField
                            linkData={entry.upload.getPublicLink()}
                            shareModel={entry.upload.getShareModel()}
                            editAllowed={false}
                            showMailer={() => {}}
                        />
                    </MaterialUI.Paper>
                );
                boxWidth += 320;
            }
            if(display === 'large'){
                const pgress = <Progress item={entry.upload.getUploadItem()} size={size} shared={entry.link}/>;
                circle = (
                    <Circle size={size} translucid={false} progress={pgress}>{entry.upload.getLabel()}</Circle>
                );
                boxWidth += size;
            }
            return(
                <div style={{display:'flex', alignItems:'center', margin:'0 auto', width: boxWidth, transition:DOMUtils.getBeziersTransition()}}>
                    <div style={{flex: 1}}>
                        {circle}
                    </div>
                    <div style={{flex: 1}}>
                        {comp}
                    </div>
                </div>
            );
        }
    });

    let Circle = React.createClass({
        render: function(){
            const {size, children, translucid, progress} = this.props;
            let style = {
                width: size,
                height: size,
                margin:'0 auto',
                display:'flex',
                alignItems: 'center',
                position: 'relative',
                textAlign:'center'
            };
            if(translucid){
                style = {...style, backgroundColor: 'rgba(0,0,0,0.53)', color: 'white'};
            }
            let padding = progress ? 12 : 10;
            const innerSize = size - padding*2;
            return (
                <MaterialUI.Paper
                    zDepth={2}
                    circle={true}
                    style={style}>
                    {progress}
                    <div style={{position:'absolute', top: padding, left: padding, height: innerSize, width: innerSize,
                        display: 'flex', flexDirection: 'column', justifyContent: 'center',
                        overflow: 'hidden', borderRadius: '50%', fontSize: 20, lineHeight: '30px'}}>
                        {children}
                    </div>
                </MaterialUI.Paper>
            );
        }
    });

    let SendFileTemplate = React.createClass({

        getInitialState: function(){
            return {added: new Map()};
        },

        componentDidMount: function(){
            this._listener = function(item){
                let upload = new Upload(item);
                const {added} = this.state;
                added.set(upload.getId(), {upload: upload, transfer: true});
                this.setState({added: added});

                upload.observe('uploaded', function(node){
                    const {added} = this.state;
                    added.set(upload.getId(), {upload: upload, transfer: true});
                    this.setState({added: added});
                }.bind(this));

                upload.observe('shared', function(links){
                    if(links.length){
                        const {added} = this.state;
                        added.set(upload.getId(), {upload: upload, transfer: false, link: links[0].public_link});
                        this.setState({added: added});
                    }
                }.bind(this));

            }.bind(this);
            UploaderModel.Store.getInstance().observe('item_added', this._listener);
        },

        componentWillUnmount: function(){
            UploaderModel.Store.getInstance().stopObserving('item_added', this._listener);
        },

        onDrop: function(files, event, source){
            fileDroppedOrPicked(event);
        },

        render: function(){

            const {connectDropTarget, isOver, canDrop} = this.props;

            let centerElement;
            let otherElements = [];
            const bigSize = 180;
            const smallSize = 120;

            if(!this.state.added.size){
                centerElement = (
                    <Circle key="starter" size={bigSize} translucid={!isOver}>
                        <PydioForm.FileDropZone
                            ref="dropzone"
                            multiple={true}
                            enableFolders={false}
                            supportClick={true}
                            onDrop={this.onDrop}
                            style={{width:'100%', borderWidth:0, height: 'auto'}}
                        >
                        Drop files anywhere or click to browse your computer
                        </PydioForm.FileDropZone>
                    </Circle>
                );
            }else{
                let index = 0, max = this.state.added.size;
                this.state.added.forEach(function(entry, key){
                    if(index < max - 1){
                        otherElements.push( <UploadCard display="small" size={smallSize} key={key} entry={entry}/> );
                    }else{
                        centerElement = <UploadCard size={bigSize} entry={entry} display="large"/>;
                    }
                    index ++;
                });

            }

            const style = Object.assign({
                display:'flex',
                alignItems:'center',
                height: '100%'
            }, this.props.style);

            return connectDropTarget(
                <div style={style}>
                    <div style={{flex: 1, textAlign:'center'}}>{centerElement}</div>
                    <div style={{position:'absolute', bottom:0, left:0, display:'flex'}}>{otherElements}</div>
                </div>
            );
        }

    });

    function fileDroppedOrPicked(event, monitor = null){

        let items, files;
        if(monitor){
            let dataTransfer = monitor.getItem().dataTransfer;
            if (dataTransfer.items.length && dataTransfer.items[0] && (dataTransfer.items[0].getAsEntry || dataTransfer.items[0].webkitGetAsEntry)) {
                items = dataTransfer.items;
            }
        }else if(event.dataTransfer){
            items = event.dataTransfer.items || [];
            files = event.dataTransfer.files;
        }else if(event.target){
            files = event.target.files;
        }
        if(window['UploaderModel'] && global.pydio.getController().getActionByName('upload')){

            let contextNode = global.pydio.getContextHolder().getContextNode();
            UploaderModel.Store.getInstance().handleDropEventResults(items, files, contextNode);
            UploaderModel.Store.getInstance().processNext();

        }

    }

    if(window.ReactDND){

        const fileTarget = {
            drop: function (props, monitor) {

                fileDroppedOrPicked(null, monitor);
                return;

                let dataTransfer = monitor.getItem().dataTransfer;
                let passItems;
                if (dataTransfer.items.length && dataTransfer.items[0] && (dataTransfer.items[0].getAsEntry || dataTransfer.items[0].webkitGetAsEntry)) {
                    passItems = dataTransfer.items;
                }
                if(window['UploaderModel'] && global.pydio.getController().getActionByName('upload')){
                    UploaderModel.Store.getInstance().handleDropEventResults(passItems, dataTransfer.files, global.pydio.getContextHolder().getContextNode());
                    if(!UploaderModel.Store.getInstance().getAutoStart()){
                        global.pydio.getController().fireAction('upload');
                    }
                }
            }
        };

        let DropTemplate = ReactDND.DropTarget(ReactDND.HTML5Backend.NativeTypes.FILE, fileTarget, function (connect, monitor) {
            return {
                connectDropTarget: connect.dropTarget(),
                isOver: monitor.isOver(),
                canDrop: monitor.canDrop()
            };
        })(SendFileTemplate);

        SendFileTemplate = ReactDND.DragDropContext(ReactDND.HTML5Backend)(DropTemplate);
    }

    global.SendFile = {
        Template: SendFileTemplate
    };

})(window);