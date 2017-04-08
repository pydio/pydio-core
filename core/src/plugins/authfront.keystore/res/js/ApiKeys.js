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
                const primaryText = item.DEVICE_DESC + ' - ' + item.DEVICE_OS;
                const secondaryText = deviceId;
                const leftIcon = <MaterialUI.FontIcon className="mdi mdi-laptop" style={{color:this.props.muiTheme.palette.primary1Color}}/>
                const rightIcon = <MaterialUI.IconButton iconClassName="mdi mdi-key-minus" onTouchTap={remove}  iconStyle={{color:'rgba(0,0,0,0.53)'}}/>
                keys.push(
                    <MaterialUI.ListItem
                        key={deviceId}
                        primaryText={primaryText}
                        secondaryText={secondaryText}
                        disabled={true}
                        leftIcon={leftIcon}
                        rightIconButton={rightIcon}
                    />);
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
            let list = this.state.loaded ? <MaterialUI.List>{keys}</MaterialUI.List> : <PydioReactUI.Loader/>;
            return (
                <div>
                    <MaterialUI.Toolbar>
                        <div style={{color: 'white', padding: '17px 0px', marginLeft: -10, fontWeight: 500}}>{mess['keystore.9']}</div>
                        <div style={{flex:1}}></div>
                        <MaterialUI.ToolbarGroup lastChild={true}>
                            <MaterialUI.IconButton tooltip={mess['keystore.3']} tooltipPosition="bottom-left" iconClassName="mdi mdi-key-plus" onTouchTap={this.generateKey} iconStyle={{color:'white'}}/>
                            <MaterialUI.IconButton tooltip={mess['keystore.5']} tooltipPosition="bottom-left" iconClassName="mdi mdi-key-remove" onTouchTap={() => {this.removeKey();}} iconStyle={{color:'white'}}/>
                        </MaterialUI.ToolbarGroup>
                    </MaterialUI.Toolbar>
                    {list}
                </div>
            );
        }

    });

    global.ApiKeys = {
        Panel: MaterialUI.Style.muiThemeable()(Panel)
    };

})(window);