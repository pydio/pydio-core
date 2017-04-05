(function(global){

    const pydio = global.pydio;
    const MessageHash = pydio.MessageHash;

    class LocalNodeProvider{


        /**
         *
         * @param node AjxpNode
         * @param nodeCallback Function
         * @param childCallback Function
         */
        loadNode(node, nodeCallback, childCallback){
            node.setLoaded(true);
            nodeCallback(node);
            if(childCallback){
                node.getChildren().forEach(function(n){
                    childCallback(n);
                });
            }
        }

        loadLeafNodeSync(node, callback){}

    }

    class Model extends Observable{

        constructor(){
            super();
            const provider = new LocalNodeProvider();
            this.dm = new PydioDataModel(true);
            this.dm.setAjxpNodeProvider(provider);
            this.root = new AjxpNode("/", false, "Cart", "folder.png", provider);
            this.dm.setRootNode(this.root);
            this.__maxChildren = 100;
        }

        static getInstance(){
            if(!Model._INSTANCE){
                Model._INSTANCE = new Model();
            }
            return Model._INSTANCE;
        }

        localNodeFromRemoteNode(n){

            if(this.root.findChildByPath(n.getPath())) return;
            var newNode = new AjxpNode(n.getPath(), n.isLeaf(), n.getLabel(), n.getIcon());
            let mapCopy = new Map();
            n.getMetadata().forEach(function(v, k){mapCopy.set(k, v)});
            newNode.setMetadata(mapCopy);
            this.root.addChild(newNode);
            this.notify("update");

        }

        recurseLeafs(node){

            if(this.root.getChildren().size > this.__maxChildren) {
                pydio.displayMessage('ERROR', 'Stopping recursion: please do not select more than ' + this.__maxChildren + ' at once!');
                throw $break;
            }

            if(node.isLoaded()){
                node.getChildren().forEach(function(n){
                    if(n.isLeaf()){
                        this.localNodeFromRemoteNode(n);
                    }else{
                        this.recurseLeafs(n);
                    }
                }.bind(this));
            }else{
                node.observeOnce("loaded", function(){
                    this.recurseLeafs(node);
                }.bind(this));
                node.load();
            }


        }

        buildSelection(){
            let sel = {}, i = 0;
            this.root.getChildren().forEach(function(n){
                var key = "file_" + i;
                sel[key] = n.getPath();
                i++;
            });
            return sel;
        }

        buildZipName(){
            let zipName = global.prompt(MessageHash['action.cart.14'], 'Cart.zip');
            if(!zipName) return null;
            var index=1;
            var buff = zipName;
            while(pydio.getContextHolder().fileNameExists(zipName + ".zip", true, pydio.getContextHolder().getRootNode())){
                zipName = buff + "-" + index; index ++ ;
            }
            return zipName;
        }

        clearContent(){
            this.root.clear();
            this.notify("update");
        }

        removeNode(node){
            this.root.removeChild(node);
            this.notify("update");
        }

        downloadContent(){
            const sel = this.buildSelection();
            if(!Object.keys(sel).length) return;
            let params = {...sel, dir:'__AJXP_ZIP_FLAT__/', archive_name:'Cart.zip'};
            PydioApi.getClient().downloadSelection(null, 'download', params);
        }

        compressContentAndShare(){
            const sel = this.buildSelection();
            if(!Object.keys(sel).length) return;
            const zipName = this.buildZipName();
            if(!zipName) return;
            let params = {...sel, get_action: 'compress', compress_flat:'true', dir:'/', archive_name:zipName};
            PydioApi.getClient().request(params, function(transport){
                const success = PydioApi.getClient().parseXmlMessage(transport.responseXML);
                if(success){
                    const contextHolder = pydio.getContextHolder();
                    contextHolder.setPendingSelection(zipName);
                    contextHolder.requireContextChange(contextHolder.getRootNode());
                }
            });
        }

        getDataModel(){
            return this.dm;
        }
    }

    let CartPanel = React.createClass({

        clearContent: function(){
            Model.getInstance().clearContent();
        },

        download: function(){
            Model.getInstance().downloadContent();
        },

        zipAndShare: function(){
            Model.getInstance().compressContentAndShare();
        },

        renderActions: function(node){
            return <MaterialUI.IconButton
                first={true}
                iconClassName="mdi mdi-close"
                iconStyle={{color: 'rgba(0,0,0,0.23)', iconHoverColor:'rgba(0,0,0,0.53)'}}
                tooltip={MessageHash['action.cart.16']}
                tooltipPosition="bottom-left"
                onTouchTap={() => {Model.getInstance().removeNode(node)}}
                />
        },

        renderIcon: function(node){
            return <PydioWorkspaces.FilePreview style={{height: 36, width: 36, margin: '8px 15px', borderRadius: '50%'}} node={node} loadThumbnail={true} richPreview={false}/>
        },

        render: function(){

            const dataModel = Model.getInstance().getDataModel();
            let disabled = false;
            if(!dataModel.getRootNode().getChildren().size){
                disabled = true;
            }
            const iconStyle = {
                color: 'rgba(255,255,255,.83)',
                iconHoverColor: 'rgba(255,255,255,1)',
            };

            return (
                <div style={{width: 320, height: 400}} className="vertical_layout">
                    <MaterialUI.Toolbar>
                        <MaterialUI.ToolbarGroup firstChild={true}>
                            <MaterialUI.IconButton iconClassName="mdi mdi-file-plus" iconStyle={iconStyle} disabled={pydio.getContextHolder().isEmpty()} tooltipPosition="bottom-right" tooltip={MessageHash['action.cart.2']} onTouchTap={Callbacks.addCurrentSelection}/>
                            <MaterialUI.IconButton iconClassName="mdi mdi-delete" iconStyle={iconStyle} disabled={disabled}  tooltip={MessageHash['action.cart.3']} onTouchTap={this.clearContent}/>
                        </MaterialUI.ToolbarGroup>
                        <span style={{flex: 1}}/>
                        <MaterialUI.ToolbarGroup lastChild={true}>
                            <MaterialUI.IconButton iconClassName="mdi mdi-download" iconStyle={iconStyle}  disabled={disabled} tooltip={MessageHash['action.cart.7']} onTouchTap={this.download}/>
                            <MaterialUI.IconButton iconClassName="mdi mdi-archive" iconStyle={iconStyle}  disabled={disabled} tooltip={MessageHash['action.cart.11']} tooltipPosition="bottom-left" onTouchTap={this.zipAndShare}/>
                        </MaterialUI.ToolbarGroup>
                    </MaterialUI.Toolbar>
                    <PydioComponents.NodeListCustomProvider
                        presetDataModel={dataModel}
                        actionBarGroups={[]}
                        elementHeight={PydioComponents.SimpleList.HEIGHT_ONE_LINE}
                        hideToolbar={true}
                        entryRenderActions={this.renderActions}
                        entryRenderIcon={this.renderIcon}
                    />
                </div>
            );
        }
    });

    if(global.ReactDND){
        const FakeDndBackend = function(){
            return{
                setup:function(){},
                teardown:function(){},
                connectDragSource:function(){},
                connectDragPreview:function(){},
                connectDropTarget:function(){}
            };
        };
        CartPanel = ReactDND.DragDropContext(FakeDndBackend)(CartPanel);
    }


    const CartMounter = React.createClass({

        componentDidMount: function(){
            this.updateActions();
            Model.getInstance().observe('update', this.updateActions);
        },

        componentWillUnmount: function(){
            this.getPydioActions(true).map(function(key){
                pydio.getController().deleteFromGuiActions(key);
            }.bind(this));
            Model.getInstance().stopObserving('update', this.updateActions);
        },

        updateActions: function(){
            pydio.getController().updateGuiActions(this.getPydioActions());
            pydio.getController().notify("actions_refreshed");
        },

        getInitialState: function(){
            return {panel: <CartPanel/>};
        },

        getPydioActions: function(keysOnly = false){
            if(keysOnly){
                return ['openCartPanel'];
            }
            const size = Model.getInstance().getDataModel().getRootNode().getChildren().size;
            var openCartPanel = new Action({
                name:'openCartPanel',
                icon_class: size ? 'mdi mdi-cart' : 'mdi mdi-cart-outline',
                text_id:'action.cart.10',
                title_id:'action.cart.10',
                text:MessageHash['action.cart.10'],
                title:MessageHash['action.cart.10'],
                hasAccessKey:false,
                subMenu:true,
                subMenuUpdateImage:true
            }, {
                selection:false,
                dir:true,
                actionBar:true,
                actionBarGroup:'display_toolbar',
                contextMenu:false,
                infoPanel:false
            }, {}, {}, {
                popoverContent:this.state.panel
            });
            let buttons = new Map();
            buttons.set('openCartPanel', openCartPanel);
            return buttons;
        },

        render: function(){
            return null;
        }


    });

    class Callbacks{

        static addCurrentSelection(){

            const model = Model.getInstance();
            pydio.getContextHolder().getSelectedNodes().map(function(n){
                if(n.isLeaf()){
                    model.localNodeFromRemoteNode(n);
                }else{
                    model.recurseLeafs(n);
                }
            });


        }

    }

    global.PydioCart = {
        Callbacks: Callbacks,
        CartPanel: CartPanel,
        CartMounter: CartMounter
    };

})(window);