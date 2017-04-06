const React = require('react');
import ShareContextConsumer from '../ShareContextConsumer'
const {Checkbox} = require('material-ui')
const ShareModel = require('pydio').requireLib('ReactModelShare');
import Title from '../main/title'

let PublicLinkPermissions = React.createClass({

    propTypes: {
        linkData: React.PropTypes.object.isRequired,
        shareModel: React.PropTypes.instanceOf(ShareModel),
        style: React.PropTypes.object
    },

    changePermission: function(event){
        var name = event.target.name;
        var checked = event.target.checked;
        this.props.shareModel.setPublicLinkPermission(this.props.linkData.hash, name, checked);
    },

    render: function(){
        var linkId = this.props.linkData.hash;
        var perms = [], previewWarning;
        var currentIsFolder = !this.props.shareModel.getNode().isLeaf();
        perms.push({
            NAME:'read',
            LABEL:this.props.getMessage('72'),
            DISABLED:currentIsFolder && !this.props.shareModel.getPublicLinkPermission(linkId, 'write')
        });
        perms.push({
            NAME:'download',
            LABEL:this.props.getMessage('73')
        });
        if(currentIsFolder){
            perms.push({
                NAME:'write',
                LABEL:this.props.getMessage('74')
            });
        }else if(this.props.shareModel.fileHasWriteableEditors()){
            perms.push({
                NAME:'write',
                LABEL:this.props.getMessage('74b')
            });
        }
        if(this.props.shareModel.isPublicLinkPreviewDisabled() && this.props.shareModel.getPublicLinkPermission(linkId, 'read')){
            previewWarning = <div>{this.props.getMessage('195')}</div>;
        }
        return (
            <div style={this.props.style}>
                <Title>{this.props.getMessage('71')}</Title>
                <div className="section-legend">{this.props.getMessage('70r')}</div>
                <div style={{margin:'10px 0 20px'}} className="ie_material_checkbox_fix">
                    {perms.map(function(p){
                        return (
                            <div style={{display:'inline-block',width:'33%'}}>
                                <Checkbox
                                    disabled={p.DISABLED || this.props.isReadonly()}
                                    type="checkbox"
                                    name={p.NAME}
                                    label={p.LABEL}
                                    onCheck={this.changePermission}
                                    checked={this.props.shareModel.getPublicLinkPermission(linkId, p.NAME)}
                                />
                            </div>
                        );
                    }.bind(this))}
                    {previewWarning}
                </div>
            </div>
        );
    }
});

PublicLinkPermissions = ShareContextConsumer(PublicLinkPermissions)
export {PublicLinkPermissions as default}