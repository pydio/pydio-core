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

const {Component} = require('react')
const {MenuItem, Divider, Subheader, FontIcon} = require('material-ui')
const DOMUtils = require('pydio/util/dom')

function renderItem(palette, node, text = null, icon = null){

    const iconStyle = {
        fontSize: 20,
        lineHeight: '20px',
        color : palette.primary1Color,
        padding: 2
    };
    const flagStyle = {
        display: 'inline',
        backgroundColor: palette.accent1Color,
        color: 'white',
        height: 22,
        borderRadius: 10,
        padding: '0 5px',
        marginLeft: 5
    };

    let label = text || node.getLabel();
    if(node.getMetadata().get('flag')){
        label = <span>{node.getLabel()} <span style={flagStyle}>{node.getMetadata().get('flag')}</span> </span>;
    }

    return (
        <MenuItem
            value={node}
            primaryText={label}
            rightIcon={<FontIcon className={icon || node.getMetadata().get('icon_class')} style={iconStyle}/>}
        />);

}

class NavigationHelper{

    static buildNavigationItems(pydio, rootNode, palette){

        let items = [];

        if(rootNode.getMetadata().get('component')){
            items.push(renderItem(palette, rootNode, pydio.MessageHash['ajxp_admin.menu.0']));
        }
        rootNode.getChildren().forEach(function(header){
            if(!header.getChildren().size && header.getMetadata().get('component')) {
                items.push(renderItem(palette, header));
            }else{
                if(header.getLabel()){
                    items.push(<Divider/>);
                    items.push(<Subheader style={{transition:DOMUtils.getBeziersTransition()}} className="hideable-subheader">{header.getLabel()}</Subheader>)
                }
                header.getChildren().forEach(function(child){
                    if(!child.getLabel()) return;
                    items.push(renderItem(palette, child));
                });
            }
        });

        return items;

    }

}

export {NavigationHelper as default}