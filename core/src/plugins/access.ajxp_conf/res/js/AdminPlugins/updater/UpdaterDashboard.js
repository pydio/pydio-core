import PluginEditor from '../core/PluginEditor'
import {RaisedButton, Checkbox} from 'material-ui'

const UpdaterDashboard = React.createClass({

    mixins:[AdminComponents.MessagesConsumerMixin],


    getInitialState: function(){
        return {checks: -1};
    },

    componentDidMount:function(){
        this.checkForUpgrade();
    },

    checkForUpgrade: function(){
        this.setState({loading:true});
        PydioApi.getClient().request({get_action:'get_upgrade_path'}, function(transp){
            this.setState({loading:false});
            if(!this.isMounted()) return;
            var response = transp.responseJSON;
            var length = 0;
            if(response && response.packages.length){
                length = response.packages.length;
                this.setState({packages:response.packages});
                if(response.latest_note){
                    let latest = response.latest_note;
                    latest = pydio.Parameters.get('ajxpServerAccess')+"&get_action=display_upgrade_note&url=" + encodeURIComponent(latest);
                    this.setState({src:latest});
                }
            }else{
                this.setState({no_upgrade:true});
            }

            var node = pydio.getContextNode();
            node.getMetadata().set('flag', length);
            AdminComponents.MenuItemListener.getInstance().notify("item_changed");

        }.bind(this));
    },

    performUpgrade: function(){
        if(this.state.checks < 0){
            alert('Please select at least one package!');
            return;
        }
        if(confirm(this.context.getMessage('15', 'updater'))){
            var client = PydioApi.getClient();
            this.setState({src:''}, function(){
                this.setState({src: client._baseUrl + '?secure_token=' + client._secureToken  + '&get_action=perform_upgrade&package_index=' + this.state.checks});
            }.bind(this));

        }
    },

    onCheckStateChange: function(index, value){
        if(value) this.setState({checks: index});
        else this.setState({checks: index - 1});
    },

    render:function(){

        var list = null;
        const {packages, checks, loading} = this.state;
        if(packages){
            list = (
                <div style={{paddingBottom:30,paddingRight:5}}>
                        <span style={{float:'right'}}>
                            <RaisedButton primary={true} label={this.context.getMessage('4', 'updater')} onTouchTap={this.performUpgrade}/>
                        </span>
                    {this.context.getMessage('16', 'updater')}
                    <div style={{paddingLeft:30}}>{packages.map((p, index) => {
                        return <div><Checkbox style={{listStyle:'inherit'}} key={p} label={PathUtils.getBasename(p)} onCheck={(e,v)=> this.onCheckStateChange(index, v)} checked={index <= checks} /></div>
                    })}</div>
                    <br/>{this.context.getMessage('3', 'updater')}
                </div>
            );
        }else if(this.state && this.state.loading){
            list = (
                <div>{this.context.getMessage('17', 'updater')}</div>
            );
        }else{
            list = (
                <div>
                        <span style={{float:'right'}}>
                            <ReactMUI.RaisedButton secondary={true} label={this.context.getMessage('20', 'updater')} onClick={this.checkForUpgrade}/>
                        </span>
                    { (this.state && this.state.no_upgrade) ? this.context.getMessage('18', 'updater') : this.context.getMessage('19', 'updater') }
                </div>
            );
        }

        var updateCheckPane = (
            <div style={{padding:'0 20px'}}>
                <h3>{this.context.getMessage('2', 'updater')}</h3>
                <div style={{paddingBottom:20,paddingRight:5}}>{list}</div>
                <iframe
                    ref="iframe"
                    style={{width:'100%',height:400, border:'1px solid #ccc'}}
                    src={this.state?this.state.src:''}
                ></iframe>
            </div>
        );
        let version = pydio.Parameters.get("ajxpVersion");
        let additionalDescription;
        if(version == '##VERSION_NUMBER##'){
            additionalDescription = this.context.getMessage('21', 'updater');
        }else{
            additionalDescription = this.context.getMessage('22', 'updater').replace('%1', version).replace('%2', pydio.Parameters.get("ajxpVersionDate"));
        }
        return (
            <div className="update-checker" style={{height:'100%'}}>
                <PluginEditor
                    {...this.props}
                    additionalDescription={additionalDescription}
                    additionalPanes={{top:[updateCheckPane], bottom:[]}}
                />
            </div>
        );
    }

});

export {UpdaterDashboard as default}