import PluginsList from './PluginsList'
import React from 'react'
import {RaisedButton} from 'material-ui'

const PluginsManager = React.createClass({

    mixins:[AdminComponents.MessagesConsumerMixin],

    clearCache: function(){
        PydioApi.getClient().request({
            get_action:'clear_plugins_cache'
        }, function(transp){
            this.refs.list.reload();
            global.pydio.fire("admin_clear_plugins_cache");
        }.bind(this));
    },

    render: function(){
        return (
            <div style={{height:'100%'}} className="vertical-layout">
                    <span style={{position:'absolute', marginTop:10, marginLeft:10}}>
                        <RaisedButton
                            label={this.context.getMessage('129', 'ajxp_conf')}
                            onTouchTap={this.clearCache}
                        />
                    </span>
                <PluginsList {...this.props} ref="list"/>
            </div>
        );
    }

});

export {PluginsManager as default}