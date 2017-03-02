(function(global){

    const Panel = React.createClass({

        componentDidMount: function(){
            this.loadKeys();
        },

        getInitialState: function(){
            return {loaded: false, keys: {}}
        },

        generateAllowed: function(){
            return this.props.pydio.getPluginConfigs("authfront.keystore").get("USER_GENERATE_KEYS");
        },

        loadKeys: function(){
            this.setState({loaded: false});
            PydioApi.getClient().request({
                get_action: 'keystore_list_tokens'
            }, function(transport) {
                if (transport.responseJSON){
                    this.setState({keys: transport.responseJSON});
                }
                this.setState({loaded: true});
            }.bind(this));
        },

        removeKey: function(k){

            if(!window.confirm(MessageHash['keystore.7'])){
                return;
            }
            let params = {get_action:'keystore_revoke_tokens'};
            if(k){
                params['key_id'] = k;
            }
            PydioApi.getClient().request(params, () => {this.loadKeys()});

        },

        generateKey: function(){

            if(!this.generateAllowed()) return;

            PydioApi.getClient().request({
                get_action:"keystore_generate_auth_token"
            }, function(transport){
                const data = transport.responseJSON;
                this.setState({
                    newKey:'Token : ' + data['t'] + '<br> Private : ' + data['p']
                });
                this.loadKeys();
            }.bind(this))

        },


        render: function(){
            let keys = [];
            for(let k in this.state.keys){
                if(!this.state.keys.hasOwnProperty(k)) continue;
                let item = this.state.keys[k];
                let remove = function(){
                    this.removeKey(k);
                }.bind(this);
                const deviceId = item['DEVICE_ID'] || 'No Id';
                keys.push(
                    <li>{item.DEVICE_DESC + ' - ' + item.DEVICE_OS} ({deviceId})<span className="mdi mdi-close" onClick={remove}/></li>
                );
            }
            let mess = this.props.pydio.MessageHash;
            let tokenResult;
            if(this.state.newKey){
                tokenResult = (
                    <div id="token_results">
                        <span className="mdi mdi-close" onClick={() => {this.setState({newKey: null})}}></span>
                        <div id="token_results_content">{this.state.newKey}</div>
                    </div>
                );
            }
            let list = this.state.loaded ? <ul>{keys}</ul> : <PydioReactUI.Loader/>;
            return (
                <div>
                    <div>
                        <MaterialUI.RaisedButton label={mess['keystore.3']} onTouchTap={this.generateKey}/>
                        <MaterialUI.RaisedButton label={mess['keystore.5']} onTouchTap={() => {this.removeKey();}}/>
                    </div>
                    <div>
                        <h4>{mess['keystore.9']}</h4>
                        {list}
                    </div>
                </div>
            );
        }

    });

    global.ApiKeys = {
        Panel: Panel
    };

})(window);