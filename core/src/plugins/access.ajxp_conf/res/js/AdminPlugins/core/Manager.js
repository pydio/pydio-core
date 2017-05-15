/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <https://pydio.com>.
 */

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