(function(global){

    class Loader{

        hookAfterDelete(){
            // Modify the Delete window
            // Uses only pure-JS
            document.observe("ajaxplorer:afterApply-delete", function(){
                try{
                    var u = pydio.getContextHolder().getUniqueNode();
                    if(u.getMetadata().get("ajxp_shared")){
                        var f = document.querySelectorAll("#generic_dialog_box #delete_message")[0];
                        var alert = f.querySelectorAll("#share_delete_alert");
                        if(!alert.length){
                            var message;
                            if(u.isLeaf()){
                                message = global.MessageHash["share_center.158"];
                            }else{
                                message = global.MessageHash["share_center.157"];
                            }
                            f.innerHTML += "<div id='share_delete_alert' style='padding-top: 10px;color: rgb(192, 0, 0);'><span style='float: left;display: block;height: 60px;margin: 4px 7px 4px 0;font-size: 2.4em;' class='icon-warning-sign'></span>"+message+"</div>";
                        }
                    }
                }catch(e){
                    if(console) console.log(e);
                }
            });
        }

        static loadInfoPanel(container, node){
            if(!Loader.INSTANCE){
                Loader.INSTANCE = new Loader();
                Loader.INSTANCE.hookAfterDelete();
            }
            var mainCont = container.querySelectorAll("#ajxp_shared_info_panel .infoPanelTable")[0];
            React.render(
                React.createElement(InfoPanel, {pydio:global.pydio, node:node}),
                mainCont
            );
        }
    }

    var InfoPanel = React.createClass({

        propTypes: {
            node:React.PropTypes.instanceOf(AjxpNode),
            pydio:React.PropTypes.instanceOf(Pydio)
        },

        getInitialState: function(){
            return {
                status:'loading',
                copyMessage:null,
                model : new ReactModel.Share(this.props.pydio, this.props.node)
            };
        },
        componentDidMount:function(){
            this.state.model.observe("status_changed", this.modelUpdated);
            this.attachClipboard();
        },
        componentDidUpdate:function(){
            this.attachClipboard();
        },

        attachClipboard:function(){
            if(this._clip){
                this._clip.destroy();
            }
            if(!this.refs['copy-button']) {
                return;
            }
            this._clip = new Clipboard(this.refs['copy-button'].getDOMNode(), {
                text: function(trigger) {
                    var linkData = this.state.model.getPublicLinks()[0];
                    return linkData['public_link'];
                }.bind(this)
            });
            this._clip.on('success', function(){
                this.setState({copyMessage:this.getMessage('192')}, this.clearCopyMessage);
            }.bind(this));
            this._clip.on('error', function(){
                var copyMessage;
                if( global.navigator.platform.indexOf("Mac") === 0 ){
                    copyMessage = this.getMessage('144');
                }else{
                    copyMessage = this.getMessage('143');
                }
                this.refs['input'].getDOMNode().focus();
                this.setState({copyMessage:copyMessage}, this.clearCopyMessage);
            }.bind(this));
        },
        clearCopyMessage:function(){
            global.setTimeout(function(){
                this.setState({copyMessage:''});
            }.bind(this), 3000);
        },

        modelUpdated: function(){
            if(this.isMounted()){
                this.setState({status:this.state.model.getStatus()});
            }
        },

        getMessage: function(id){
            try{
                return this.props.pydio.MessageHash['share_center.' + id];
            }catch(e){
                return id;
            }
        },

        render: function(){
            if(this.state.model.hasPublicLink()){
                var linkData = this.state.model.getPublicLinks()[0];
                var isExpired = linkData["is_expired"];
                var select = function(e){
                    e.currentTarget.select();
                };
                if(this.state.copyMessage){
                    var setHtml = function(){
                        return {__html:this.state.copyMessage};
                    }.bind(this);
                    var copyMessage = <div className="copy-message" dangerouslySetInnerHTML={setHtml()}/>;
                }
                var linkField = (
                    <div className="infoPanelRow">
                        <div className="infoPanelLabel">{this.getMessage('121')}</div>
                        <div className="infoPanelValue" style={{position:'relative'}}>
                            <input
                                ref="input"
                                type="text"
                                className={"share_info_panel_link" + (isExpired?" share_info_panel_link_expired":"")}
                                readOnly={true}
                                onClick={select}
                                value={linkData['public_link']}
                            />
                            <span ref="copy-button" title={this.getMessage('191')} className="copy-button icon-paste"/>
                            {copyMessage}
                        </div>
                    </div>
                );
            }
            var users = this.state.model.getSharedUsers();
            var sharedUsersEntries = [], remoteUsersEntries = [];
            if(users.length){
                sharedUsersEntries = users.map(function(u){
                    var rights = [];
                    if(u.RIGHT.indexOf('r') !== -1) rights.push('Read');
                    if(u.RIGHT.indexOf('w') !== -1) rights.push('Modify');
                    return (
                        <div key={u.ID} className="uUserEntry">
                            <span className="uLabel">{u.LABEL}</span>
                            <span className="uRight">{rights.join(' & ')}</span>
                        </div>
                    );
                });
            }
            var ocsLinks = this.state.model.getOcsLinks();
            if(ocsLinks.length){
                remoteUsersEntries = ocsLinks.map(function(link){
                    var i = link['invitation'];
                    var status;
                    if(!i){
                        status = '214';
                    }else {
                        if(i.STATUS == 1){
                            status = '211';
                        }else if(i.STATUS == 2){
                            status = '212';
                        }else if(i.STATUS == 4){
                            status = '213';
                        }
                    }
                    status = this.getMessage(status);

                    return (
                        <div key={"remote-"+link.hash} className="uUserEntry">
                            <span className="uLabel">{i.USER} @ {i.HOST}</span>
                            <span className="uStatus">{status}</span>
                        </div>
                    );
                }.bind(this));
            }
            if(sharedUsersEntries.length || remoteUsersEntries.length){
                var sharedUsersBlock = (
                    <div className="infoPanelRow">
                        <div className="infoPanelLabel">{this.getMessage('54')}</div>
                        <div className="infoPanelValue">
                            {sharedUsersEntries}
                            {remoteUsersEntries}
                        </div>
                    </div>
                );
            }

            return (
                <div>
                    {linkField}
                    {sharedUsersBlock}
                </div>
            );
        }

    });

    global.ShareInfoPanel = {};
    global.ShareInfoPanel.loader = Loader.loadInfoPanel;


})(window);
