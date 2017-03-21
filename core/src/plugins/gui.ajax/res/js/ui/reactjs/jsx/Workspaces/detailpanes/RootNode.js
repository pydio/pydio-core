import InfoPanelCard from './InfoPanelCard'

export default React.createClass({

    componentDidMount: function(){

        if(!this.props.pydio.user) {
            return;
        }
        let cacheService = MetaCacheService.getInstance();
        cacheService.registerMetaStream('workspace.info', 'MANUAL_TRIGGER');
        let oThis = this;
        const render = function(data){
            oThis.setState({...data['core.users']});
        };
        const repoKey = pydio.user.getActiveRepository();
        if(cacheService.hasKey('workspace.info', repoKey)){
            render(cacheService.getByKey('workspace.info', repoKey));
        }else{
            FuncUtils.bufferCallback("ajxp_load_repo_info_timer", 300,function(){
                if(!oThis.isMounted()) return;
                oThis.setState({loading: true});
                PydioApi.getClient().request({get_action:'load_repository_info'}, function(transport){
                    oThis.setState({loading: false});
                    if(transport.responseJSON){
                        var data = transport.responseJSON;
                        if(!data['core.users']['groups']){
                            data['core.users']['groups'] = 0;
                        }
                    }
                    cacheService.setKey('workspace.info', repoKey, data);
                    render(data);
                }, null, {discrete:true});
            });
        }

    },

    render: function () {

        const messages = this.props.pydio.MessageHash;
        let internal = messages[531];
        let external = messages[532];
        let shared = messages[527];

        let content, panelData;
        if(this.state && this.state.loading){
            content = <PydioReactUI.Loader/>;
        }else if(this.state && this.state.users){
            panelData = [
                {key: 'internal', label:internal, value:this.state.users},
                {key: 'external', label:internal, value:this.state.groups}
            ];
        }

        return (
            <InfoPanelCard title="Workspace Users" standardData={panelData} icon="account-multiple-outline" iconColor="00838f">{content}</InfoPanelCard>
        );

    }

});