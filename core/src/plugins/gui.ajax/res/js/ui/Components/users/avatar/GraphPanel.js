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

const {Component, PropTypes} = require('react')
import UsersList from '../addressbook/UsersList'
const {Divider} = require('material-ui')
const {UsersApi} = require('pydio/http/users-api')
const {PydioContextConsumer} = require('pydio').requireLib('boot')


/**
 * Display information about user or team relations
 */
class GraphPanel extends Component{

    render(){

        const {graph, userLabel, pydio, getMessage} = this.props;

        let elements = [];
        if(graph.teams && graph.teams.length){
            const onDeleteAction = function(parentItem, team){
                UsersApi.removeUserFromTeam(team[0].id, this.props.userId, (response) => {
                    if(response.message) pydio.UI.displayMessage('SUCCESS', response.message);
                    this.props.reloadAction();
                });
            }.bind(this);
            elements.push(
                <div key="teams">
                    <Divider/>
                    <UsersList subHeader={getMessage(581).replace('%s', graph.teams.length)} onItemClicked={()=>{}} item={{leafs: graph.teams}} mode="inner" onDeleteAction={onDeleteAction}/>
                </div>
            )
        }
        if(graph.source && Object.keys(graph.source).length){
            elements.push(
                <div key="source">
                    {elements.length ? <Divider/> : null}
                    <div style={{padding: 16}}>{getMessage(601).replace('%1', userLabel).replace('%2', Object.keys(graph.source).length)}</div>
                </div>
            )
        }
        if(graph.target && Object.keys(graph.target).length){
            elements.push(
                <div key="target">
                    {elements.length ? <Divider/> : null}
                    <div style={{padding: 16}}>{getMessage(602).replace('%1', userLabel).replace('%2', Object.keys(graph.target).length)}</div>
                </div>
            )
        }
        return <div>{elements}</div>;
    }

}

GraphPanel = PydioContextConsumer(GraphPanel);
export {GraphPanel as default}