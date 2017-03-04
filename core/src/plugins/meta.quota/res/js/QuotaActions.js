(function(global){

    let pydio = global.pydio;
    let MessageHash = global.pydio.MessageHash;

    class Model{

        constructor(){
            this.usage = '';
            this.total = '';
            this.startListening();

            pydio.observe('repository_list_refreshed', function(){
                if(!this.quotaEnabled() && this._pe){
                    this._pe.stop();
                }else{
                    this.loadQuota();
                }
            }.bind(this));
        }

        quotaEnabled(){
            return !! pydio.getController().getActionByName('monitor_quota');
        }

        getText(){
            if(!this.usage || !this.quotaEnabled()){
                return '';
            }
            return PathUtils.roundFileSize(this.usage, MessageHash["byte_unit_symbol"]) + "/"
                + PathUtils.roundFileSize(this.total, MessageHash["byte_unit_symbol"]);
        }

        getUsage(){
            return this.usage;
        }

        getTotal(){
            return this.total;
        }

        static getInstance(){
            if(!Model._INSTANCE){
                Model._INSTANCE = new Model();
            }
            return Model._INSTANCE;
        }

        loadQuota(){
            if(!this.quotaEnabled()) return;
            PydioApi.getClient().request({get_action:'monitor_quota'}, function(transport){
                if(!this.quotaEnabled()) return;
                const data = transport.responseJSON;
                this.usage = data.USAGE;
                this.total = data.TOTAL;
            }.bind(this));
        }

        startListening(){
            var configs = pydio.getPluginConfigs("mq");
            if(configs){
                pydio.observe("server_message", function(event){
                    var newValue = XMLUtils.XPathSelectSingleNode(event, "/tree/metaquota");
                    if(newValue){
                        this.usage = parseInt(newValue.getAttribute("usage"));
                        this.total = parseInt(newValue.getAttribute("total"));
                    }
                });
            }else{
                this._pe = new PeriodicalExecuter(function(pe){
                    if(!this.quotaEnabled()) {
                        pe.stop();
                        return;
                    }
                    this.loadQuota();
                }, 20);
            }
            this.loadQuota();
        }

    }

    class Callbacks{

        static computeQuota(manager, args){

        }
        
        

    }

    class Listeners {

        static init() {
            Model.getInstance();
        }

    }

    const QuotaPanel = React.createClass({

        render: function(){
            let model = Model.getInstance();
            return (
                <PydioDetailPanes.InfoPanelCard title={this.props.pydio.MessageHash['meta.quota.4']}>
                    <div>{model.getText()}</div>
                    <div style={{paddingBottom: 20}}>
                        <MaterialUI.LinearProgress mode="determinate" min={0} max={model.getTotal()} value={model.getUsage()}/>
                    </div>
                </PydioDetailPanes.InfoPanelCard>
            );
        }

    }) ;

    global.QuotaActions = {
        Callbacks: Callbacks,
        Listeners:Listeners,
        Model: Model,
        QuotaPanel: QuotaPanel
    };

})(window)