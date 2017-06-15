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

import {RoleMessagesConsumerMixin} from '../util/MessagesMixin'

export default React.createClass({

    mixins:[RoleMessagesConsumerMixin],

    propTypes:{
        userId:React.PropTypes.string.isRequired,
        sharedWorkspaces:React.PropTypes.object,
        workspacesDetails:React.PropTypes.object
    },

    render: function(){
        return (
            <div className="vertical-layout" style={{padding:16,height:'100%'}}>
                <h2>{this.context.getMessage('52')}</h2>
                <ReactMUI.Paper zDepth={1} className="workspace-activity-block layout-fill vertical-layout">
                    <PydioComponents.NodeListCustomProvider
                        title={this.context.getMessage('ws.25', 'ajxp_admin')}
                        nodeProviderProperties={{
                            get_action:"sharelist-load",
                            user_id:this.props.userId,
                            user_context:"user"
                        }}
                        tableKeys={{
                            shared_element_parent_repository_label:{label:this.context.getMessage('ws.39', 'ajxp_admin'), width:'20%'},
                            original_path:{label:this.context.getMessage('ws.41', 'ajxp_admin'), width:'80%'},
                            share_type_readable:{label:this.context.getMessage('ws.40', 'ajxp_admin'), width:'15%'}
                        }}
                        actionBarGroups={['share_list_toolbar-selection', 'share_list_toolbar']}
                        groupByFields={['share_type_readable','shared_element_parent_repository_label']}
                        defaultGroupBy="shared_element_parent_repository_label"
                        elementHeight={PydioComponents.SimpleList.HEIGHT_ONE_LINE}
                    />
                </ReactMUI.Paper>
            </div>
        );
    }
});
