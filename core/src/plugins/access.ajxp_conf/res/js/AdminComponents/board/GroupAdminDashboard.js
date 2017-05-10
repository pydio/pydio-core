import React from 'react'
import {RaisedButton, FlatButton} from 'material-ui'
import {MessagesConsumerMixin} from '../util/Mixins'

const GroupAdminDashboard = React.createClass({

    mixins:[MessagesConsumerMixin],

    renderLink: function(node){

        var label = <span><span className={node.iconClass + ' button-icon'}></span> {node.label}</span>
        return(
            <span style={{display:'inline-block', margin:'0 5px'}}>
                <RaisedButton
                    key={node.path}
                    secondary={true}
                    onTouchTap={function(){pydio.goTo(node.path);}}
                    label={label}
                />
                </span>
        );

    },

    render: function(){

        var baseNodes = [
            {
                path:'/data/users',
                label:this.context.getMessage('249', ''),
                iconClass:'icon-user'
            },{
                path:'/data/repositories',
                label:this.context.getMessage('250', ''),
                iconClass:'icon-hdd'
            }];
        return (
            <div style={{width:'100%', height:'100%'}}>
                <ReactMUI.Paper zDepth={1} style={{margin:10}}>
                    <div style={{padding:10}}>{this.context.getMessage('home.67')}</div>
                    <div style={{padding:10, textAlign:'center'}}>
                        {baseNodes.map(function(n){return this.renderLink(n); }.bind(this))}
                        <br/>
                        <FlatButton
                            label={this.context.getMessage('home.68')}
                            secondary={true}
                            onTouchTap={function(){pydio.triggerRepositoryChange("ajxp_home");}}
                        />
                    </div>
                </ReactMUI.Paper>
            </div>
        );
    }

});

export {GroupAdminDashboard as default}