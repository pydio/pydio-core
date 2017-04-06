const React = require('react');
import LabelDescriptionPanel from './LabelDescriptionPanel'
import NotificationPanel from './NotificationPanel'
import PublicLinkTemplate from './PublicLinkTemplate'
import VisibilityPanel from './VisibilityPanel'
const ShareModel = require('pydio').requireLib('ReactModelShare');

export default React.createClass({

    propTypes:{
        pydio:React.PropTypes.instanceOf(Pydio),
        shareModel:React.PropTypes.instanceOf(ShareModel)
    },

    render: function(){

        const layoutData = ShareModel.compileLayoutData(this.props.pydio, this.props.shareModel.getNode());
        let layoutPane, visibilityPanel;
        if(!this.props.shareModel.getNode().isLeaf() && layoutData.length > 1 && this.props.shareModel.hasPublicLink()){
            layoutPane = <PublicLinkTemplate {...this.props} linkData={this.props.shareModel.getPublicLinks()[0]} layoutData={layoutData}/>;
        }
        if(!this.props.shareModel.currentRepoIsUserScope()){
            visibilityPanel = <VisibilityPanel  {...this.props}/>;
        }
        return (
            <div style={{padding:16}}>
                <LabelDescriptionPanel {...this.props}/>
                <NotificationPanel {...this.props}/>
                {layoutPane}
                {visibilityPanel}
            </div>
        );
    }
});

