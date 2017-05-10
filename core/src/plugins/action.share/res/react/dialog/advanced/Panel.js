const React = require('react');
import LabelDescriptionPanel from './LabelDescriptionPanel'
import NotificationPanel from './NotificationPanel'
import PublicLinkTemplate from './PublicLinkTemplate'
import VisibilityPanel from './VisibilityPanel'
import ShareContextConsumer from '../ShareContextConsumer'
const ShareModel = require('pydio').requireLib('ReactModelShare');
const {Divider} = require('material-ui')
import Card from '../main/Card'

let Panel =  React.createClass({

    propTypes:{
        pydio:React.PropTypes.instanceOf(Pydio),
        shareModel:React.PropTypes.instanceOf(ShareModel)
    },

    render: function(){

        const layoutData = ShareModel.compileLayoutData(this.props.pydio, this.props.shareModel.getNode());
        let layoutPane, visibilityPanel;
        let {style, ...props} = this.props;
        if(!this.props.shareModel.getNode().isLeaf() && layoutData.length > 1 && this.props.shareModel.hasPublicLink()){
            layoutPane = <PublicLinkTemplate {...props} linkData={this.props.shareModel.getPublicLinks()[0]} layoutData={layoutData}/>;
        }
        if(!this.props.shareModel.currentRepoIsUserScope()){
            visibilityPanel = <VisibilityPanel  {...props}  style={{paddingBottom: 16}}/>;
        }
        return (
            <div>
                <Card style={this.props.style} title={this.props.getMessage('486', '')}>
                    <LabelDescriptionPanel {...props} style={{marginTop: -10}}/>
                    <NotificationPanel {...props}/>
                    {layoutPane}
                </Card>
                {visibilityPanel}
            </div>
        );
    }
});

Panel = ShareContextConsumer(Panel);
export {Panel as default}