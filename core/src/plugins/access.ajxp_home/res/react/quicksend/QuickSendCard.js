import ColorPaper from '../board/ColorPaper'
const React = require('react')
const {CardTitle, CircularProgress} = require('material-ui')
const {DynamicGridItemMixin} = require('pydio').requireLib('components')
const {FileDropZone} = require('pydio').requireLib('form')

export default React.createClass({

    mixins: [DynamicGridItemMixin],

    statics:{
        gridWidth:2,
        gridHeight:10,
        builderDisplayName:'Quick Upload',
        builderFields:[]
    },


    fileDroppedOrPicked: function(event, monitor = null){

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

        let uploadItems = [];
        if(window['UploaderModel'] && global.pydio.getController().getActionByName('upload')){
            UploaderModel.Store.getInstance().handleDropEventResults(items, files, new AjxpNode('/'), uploadItems);
        }
        return uploadItems;
    },

    onDrop: function(files, event, source){
        const items = this.fileDroppedOrPicked(event);
        console.log(items);
        this.setState({uploadItems: items});
        this.props.pydio.UI.openComponentInModal('WelcomeComponents', 'WorkspacePickerDialog', {
            onWorkspaceTouchTap: this.targetWorkspaceSelected.bind(this),
            legend : (files && files[0] ? <div style={{fontSize:13, padding: 16, backgroundColor:'#FFEBEE'}}>Selected file for upload: {files[0].name}</div> : undefined)
        });
    },

    targetWorkspaceSelected: function(wsId){
        console.log(wsId);
        const contextNode = new AjxpNode('/');
        contextNode.getMetadata().set('repository_id', wsId);
        const {uploadItems} = this.state;
        if(window['UploaderModel'] && global.pydio.getController().getActionByName('upload')){
            const instance = UploaderModel.Store.getInstance();
            uploadItems.forEach((item) => {
                item.updateRepositoryId(wsId);
                item.observe('status', () => {
                    this.setState({working:(item.getStatus() === 'loading') });
                });
                instance.pushFile(item);
            });
            instance.processNext();
        }
    },

    render: function(){
        const title = <CardTitle title="Quick Upload"/>;
        const working = this.state && this.state.working;

        return (
            <ColorPaper zDepth={1} {...this.props} paletteIndex={0} getCloseButton={this.getCloseButton} >
                <div style={{display:'flex', alignItems: 'center', height: '100%'}}>
                    <div style={{padding: 16, fontSize: 16, width: 100}}>Drop a file here from your desktop</div>
                    <div style={{textAlign:'center', padding:18, flex:1}}>
                        {working &&
                            <CircularProgress size={80} thickness={4} color="white"/>
                        }
                        {!working &&
                            <FileDropZone
                                ref="dropzone"
                                multiple={true}
                                enableFolders={false}
                                supportClick={true}
                                onDrop={this.onDrop}
                                style={{width:'100%', borderWidth:0, height: 'auto', borderRadius:'50%', border: '4px solid white', fontSize:56, padding: 20}}
                                dragActiveStyle={{border: '4px dashed white'}}
                            >
                                <span className="mdi mdi-cloud-upload"/>
                            </FileDropZone>
                        }
                    </div>
                </div>
            </ColorPaper>
        );
    }

});
