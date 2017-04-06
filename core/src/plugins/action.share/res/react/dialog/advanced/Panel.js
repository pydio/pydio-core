const React = require('react');
import LabelDescriptionPanel from './LabelDescriptionPanel'
import NotificationPanel from './NotificationPanel'
import PublicLinkTemplate from './PublicLinkTemplate'
import VisibilityPanel from './VisibilityPanel'
import ShareContextConsumer from '../ShareContextConsumer'
const ShareModel = require('pydio').requireLib('ReactModelShare');
const {Divider} = require('material-ui')
import Title from '../main/title'

let Panel =  React.createClass({

    propTypes:{
        pydio:React.PropTypes.instanceOf(Pydio),
        shareModel:React.PropTypes.instanceOf(ShareModel)
    },

    render: function(){

        const layoutData = ShareModel.compileLayoutData(this.props.pydio, this.props.shareModel.getNode());
        const st = {padding: '0px 16px'};
        let layoutPane, visibilityPanel;
        if(!this.props.shareModel.getNode().isLeaf() && layoutData.length > 1 && this.props.shareModel.hasPublicLink()){
            layoutPane = <PublicLinkTemplate {...this.props} linkData={this.props.shareModel.getPublicLinks()[0]} layoutData={layoutData}  style={st}/>;
        }
        if(!this.props.shareModel.currentRepoIsUserScope()){
            visibilityPanel = <VisibilityPanel  {...this.props}  style={{...st, paddingBottom: 16}}/>;
        }
        return (
            <div style={this.props.style}>
                <Title style={{padding: '16px 16px 0'}}>{this.props.getMessage('486', '')}</Title>
                <LabelDescriptionPanel {...this.props} style={{...st, marginTop: -10}}/>
                <NotificationPanel {...this.props} style={st}/>
                {layoutPane}
                {visibilityPanel && <Divider style={{marginTop: 16}}/>}
                {visibilityPanel}
            </div>
        );
    }
});

Panel = ShareContextConsumer(Panel);
export {Panel as default}