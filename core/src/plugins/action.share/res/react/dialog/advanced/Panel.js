const React = require('react');
import LabelDescriptionPanel from './LabelDescriptionPanel'
import NotificationPanel from './NotificationPanel'
import PublicLinkTemplate from './PublicLinkTemplate'
import VisibilityPanel from './VisibilityPanel'
const ShareModel = require('pydio').requireLib('ReactModelShare');
const {Divider} = require('material-ui')

export default React.createClass({

    propTypes:{
        pydio:React.PropTypes.instanceOf(Pydio),
        shareModel:React.PropTypes.instanceOf(ShareModel)
    },

    render: function(){

        const layoutData = ShareModel.compileLayoutData(this.props.pydio, this.props.shareModel.getNode());
        const st = {padding: '0px 16px 16px'};
        let layoutPane, visibilityPanel;
        if(!this.props.shareModel.getNode().isLeaf() && layoutData.length > 1 && this.props.shareModel.hasPublicLink()){
            layoutPane = <PublicLinkTemplate {...this.props} linkData={this.props.shareModel.getPublicLinks()[0]} layoutData={layoutData}  style={st}/>;
        }
        if(!this.props.shareModel.currentRepoIsUserScope()){
            visibilityPanel = <VisibilityPanel  {...this.props}  style={st}/>;
        }
        return (
            <div>
                <LabelDescriptionPanel {...this.props} style={st}/>
                <Divider/>
                <NotificationPanel {...this.props} style={st}/>
                {layoutPane && <Divider/>}
                {layoutPane}
                {visibilityPanel && <Divider/>}
                {visibilityPanel}
            </div>
        );
    }
});

