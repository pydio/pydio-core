(function(global){

    var LeftPanel = React.createClass({

        propTypes:{
            pydio:React.PropTypes.instanceOf(Pydio)
        },

        getInitialState:function(){
            return {meta_filter:null};
        },

        handleChange: function(event) {
            var value = event.target.value;
            if(value) value += '*';
            document.getElementById('content_pane').ajxpPaneObject.addMetadataFilter('text', value);
        },

        filterByShareMetaType(type, event){
            if(type == '-1'){
                type = '';
            }
            this.setState({meta_filter:type});
            document.getElementById('content_pane').ajxpPaneObject.addMetadataFilter('share_meta_type', type);
        },

        render: function(){
            var messages = this.props.pydio.MessageHash;
            return (
                <div className="inbox-left-panel">
                    <h3 className="colorcode-folder">{messages['inbox_driver.6']}</h3>
                    <div>{messages['inbox_driver.7']}</div>
                    <h4>{messages['inbox_driver.8']}</h4>
                    <div>
                        <h5>{messages['inbox_driver.9']}</h5>
                        <input type="text" placeholder="Filter..." onChange={this.handleChange}/>
                    </div>
                    <div style={{paddingTop:20}}>
                        <h5><span className="clear" onClick={this.filterByShareMetaType.bind(this, '-1')}>{messages['inbox_driver.11']}</span>
                            {messages['inbox_driver.10']}
                        </h5>
                        <span className={(this.state.meta_filter === '0'?'active':'') + " share_meta_filter"} onClick={this.filterByShareMetaType.bind(this, '0')}>{messages['inbox_driver.1p']}</span>
                        <span className={(this.state.meta_filter === '1'?'active':'') + " share_meta_filter"} onClick={this.filterByShareMetaType.bind(this, '1')}>{messages['inbox_driver.2p']}</span>
                        <span className={(this.state.meta_filter === '2'?'active':'') + " share_meta_filter"} onClick={this.filterByShareMetaType.bind(this, '2')}>{messages['inbox_driver.3p']}</span>
                    </div>
                </div>
            );
        }

    });


    function filesListCellModifier(element, ajxpNode, type, metadataDef, ajxpNodeObject){

        var messages = global.pydio.MessageHash;
        if(element != null){
            var nodeMetaValue = ajxpNode.getMetadata().get('share_meta_type');
            var nodeMetaLabel;
            if(nodeMetaValue == "0") nodeMetaLabel = messages['inbox_driver.1'];
            else if(nodeMetaValue == "1") nodeMetaLabel = messages['inbox_driver.2'];
            else if(nodeMetaValue == "2") nodeMetaLabel = messages['inbox_driver.3'];
            if(element.down('.text_label')){
                element.down('.text_label').update(nodeMetaLabel);
            }
            var mainElement;
            if(element.up('.ajxpNodeProvider')){
                mainElement = element.up('.ajxpNodeProvider');
            }else if(ajxpNodeObject){
                mainElement = ajxpNodeObject;
            }else{
                console.log(element, ajxpNodeObject);
            }
            if(mainElement){
                mainElement.addClassName('share_meta_type_' + nodeMetaValue);
            }

            if(type == 'row'){
                element.writeAttribute("data-sorter_value", nodeMetaValue);
            }else{
                element.writeAttribute("data-"+metadataDef.attributeName+"-sorter_value", nodeMetaValue);
            }

            var obj = document.getElementById('content_pane').ajxpPaneObject;
            var colIndex;
            obj.columnsDef.map(function(c, index){
                if (c.attributeName == "share_meta_type") {
                    colIndex = index;
                }
            }.bind(this));
            if(colIndex !== undefined){
                obj._sortableTable.sort(colIndex, false);
                obj._sortableTable.updateHeaderArrows();
            }
        }


    }

    let pydio = global.pydio;

    class Callbacks{

        static download(){
            PydioApi.getClient().downloadSelection(pydio.getUserSelection(), 'download');
        }

        static copyInbox(){

            const {MessageHash} = pydio;
            pydio.user.write = false;
            const selection = pydio.getUserSelection();
            const submit = function(path, wsId = null){
                global.FSActions.Callbacks.applyCopyOrMove('copy', selection, path, wsId);
            };
            pydio.UI.openComponentInModal('FSActions', 'TreeDialog', {
                isMove: false,
                dialogTitle:MessageHash[159],
                submitValue:submit
            });

        }

        static acceptInvitation(){
            const remoteShareId = pydio.getUserSelection().getUniqueNode().getMetadata().get("remote_share_id");
            PydioApi.getClient().request({get_action:'accept_invitation', remote_share_id:remoteShareId}, function(){
                pydio.fireContextRefresh();
            });
        }

        static rejectInvitation(){
            const remoteShareId = pydio.getUserSelection().getUniqueNode().getMetadata().get("remote_share_id");
            PydioApi.getClient().request({get_action:'reject_invitation', remote_share_id:remoteShareId}, function(){
                pydio.fireContextRefresh();
            });
        }

        static rejectRemoteShare(){
            const remoteShareId = pydio.getUserSelection().getUniqueNode().getMetadata().get("remote_share_id");
            PydioApi.getClient().request({get_action:'reject_invitation', remote_share_id:remoteShareId}, function(){
                pydio.fireContextRefresh();
            });
        }
        
        static copyContextListener(){
            this.rightsContext.write = true;
            const ajxpUser = pydio.user;
            if(ajxpUser && ajxpUser.canCrossRepositoryCopy() && ajxpUser.hasCrossRepositories()){
                this.rightsContext.write = false;
                pydio.getController().defaultActions['delete']('ctrldragndrop');
                pydio.getController().defaultActions['delete']('dragndrop');
            }
        }


    }

    global.InboxWidgets = {
        filesListCellModifier,
        LeftPanel,
        Callbacks
    };

})(window);