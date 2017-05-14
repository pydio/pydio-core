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

import React from 'react'

const styles = {
    card: {
        backgroundColor: 'white'
    }
};

/**
 * Default InfoPanel Card
 */
let InfoPanelCard = React.createClass({

    propTypes: {
        title:React.PropTypes.string,
        actions:React.PropTypes.array
    },

    render: function(){
        let iconStyle = this.props.iconStyle || {};
        iconStyle = {...iconStyle, color:this.props.iconColor, float:'right'};
        let icon = this.props.icon ? <div style={iconStyle} className={"panelIcon mdi mdi-" + this.props.icon}/> : null;
        let title = this.props.title ? <div className="panelHeader">{icon}{this.props.title}</div> : null;
        let actions = this.props.actions ? <div className="panelActions">{this.props.actions}</div> : null;
        let rows, toolBar;
        if(this.props.standardData){
            rows = this.props.standardData.map(function(object){
                return (
                    <div className="infoPanelRow" key={object.key}>
                        <div className="infoPanelLabel">{object.label}</div>
                        <div className="infoPanelValue">{object.value}</div>
                    </div>
                );
            });
        }
        if(this.props.primaryToolbars){
            const themePalette = this.props.muiTheme.palette;
            const tBarStyle = {
                backgroundColor: themePalette.accent2Color
            };
            toolBar = (
                <PydioComponents.Toolbar
                    toolbarStyle={tBarStyle}
                    className="primaryToolbar"
                    renderingType="button-icon"
                    toolbars={this.props.primaryToolbars}
                    controller={this.props.pydio.getController()}
                />
            );
        }

        return (
            <MaterialUI.Paper zDepth={1} className="panelCard" style={{...this.props.style, ...styles.card}}>
                {title}
                <div className="panelContent" style={this.props.contentStyle}>
                    {this.props.children}
                    {rows}
                    {toolBar}
                </div>
                {actions}
            </MaterialUI.Paper>
        );
    }

});

InfoPanelCard = MaterialUI.Style.muiThemeable()(InfoPanelCard);
export {InfoPanelCard as default}
