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

export default React.createClass({

    mixins:[AdminComponents.MessagesConsumerMixin],

    propTypes:{
        dataModel:React.PropTypes.instanceOf(PydioDataModel).isRequired,
        rootNode:React.PropTypes.instanceOf(AjxpNode).isRequired,
        currentNode:React.PropTypes.instanceOf(AjxpNode).isRequired,
        openSelection:React.PropTypes.func,
        filter:React.PropTypes.string
    },

    reload:function(){
        this.refs.list.reload();
    },

    renderListIcon:function(node){
        var letters = node.getLabel().split(" ").map(function(word){return word.substr(0,1)}).join("");
        return <span className="letter_badge">{letters}</span>;
    },

    renderSecondLine: function(node){
        if(!node.getMetadata().get("template_name")){
            return this.context.getMessage('ws.5') + ": " + node.getMetadata().get("slug") + " / " + node.getMetadata().get("accessLabel");
        }else{
            return this.context.getMessage('ws.5') + ": " + node.getMetadata().get("slug") + " / Template " + node.getMetadata().get("template_name");
        }
    },

    filterNodes:function(node){
        if(! this.props.filter ) return true;
        if(['ajxp_conf','ajxp_home','admin'].indexOf(node.getMetadata().get('accessType')) !== -1){
            return false;
        }
        if( this.props.filter == 'workspaces'){
            return !(node.getMetadata().get('is_template') == 'true');
        }else if(this.props.filter == 'templates'){
            return node.getMetadata().get('is_template') == 'true';
        }
        return true;
    },

    render:function(){
        return (
            <PydioComponents.SimpleList
                ref="list"
                node={this.props.currentNode}
                dataModel={this.props.dataModel}
                className="workspaces-list"
                actionBarGroups={[]}
                entryRenderIcon={this.renderListIcon}
                entryRenderSecondLine={this.renderSecondLine}
                openEditor={this.props.openSelection}
                infineSliceCount={1000}
                filterNodes={this.filterNodes}
                elementHeight={PydioComponents.SimpleList.HEIGHT_TWO_LINES}
            />
        );
    }

});
