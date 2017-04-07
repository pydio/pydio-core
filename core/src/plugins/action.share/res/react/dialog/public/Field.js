const React = require('react');
import ShareContextConsumer from '../ShareContextConsumer'
import TargetedUsers from './TargetedUsers'
const {RaisedButton, FloatingActionButton, TextField, Paper} = require('material-ui')
const ShareModel = require('pydio').requireLib('ReactModelShare')
const QRCode = require('qrcode.react');
const Clipboard = require('clipboard');
import ActionButton from '../main/ActionButton'
const PathUtils = require('pydio/util/path')

let PublicLinkField = React.createClass({

    propTypes: {
        linkData:React.PropTypes.object.isRequired,
        shareModel: React.PropTypes.instanceOf(ShareModel),
        editAllowed: React.PropTypes.bool,
        onChange: React.PropTypes.func,
        showMailer:React.PropTypes.func
    },
    getInitialState: function(){
        return {editLink: false, copyMessage:'', showQRCode: false};
    },
    toggleEditMode: function(){
        if(this.state.editLink && this.state.customLink){
            this.props.shareModel.updateCustomLink(this.props.linkData.hash, this.state.customLink);
        }
        this.setState({editLink: !this.state.editLink});
    },
    changeLink:function(event){
        this.setState({customLink: event.target.value});
    },
    clearCopyMessage:function(){
        global.setTimeout(function(){
            this.setState({copyMessage:''});
        }.bind(this), 5000);
    },

    attachClipboard: function(){
        this.detachClipboard();
        if(this.refs['copy-button']){
            this._clip = new Clipboard(this.refs['copy-button'], {
                text: function(trigger) {
                    return this.props.linkData['public_link'];
                }.bind(this)
            });
            this._clip.on('success', function(){
                this.setState({copyMessage:this.props.getMessage('192')}, this.clearCopyMessage);
            }.bind(this));
            this._clip.on('error', function(){
                let copyMessage;
                if( global.navigator.platform.indexOf("Mac") === 0 ){
                    copyMessage = this.props.getMessage('144');
                }else{
                    copyMessage = this.props.getMessage('143');
                }
                this.refs['public-link-field'].focus();
                this.setState({copyMessage:copyMessage}, this.clearCopyMessage);
            }.bind(this));
        }
    },
    detachClipboard: function(){
        if(this._clip){
            this._clip.destroy();
        }
    },

    componentDidUpdate: function(prevProps, prevState){
        this.attachClipboard();
    },

    componentDidMount: function(){
        this.attachClipboard();
    },

    componentWillUnmount: function(){
        this.detachClipboard();
    },

    openMailer: function(){
        const mailData = this.props.shareModel.prepareEmail("link", this.props.linkData.hash);
        this.props.showMailer(mailData.subject, mailData.message, [], this.props.linkData.hash);
    },

    toggleQRCode: function(){
        this.setState({showQRCode:!this.state.showQRCode});
    },

    render: function(){
        const publicLink = this.props.linkData['public_link'];
        const editAllowed = this.props.editAllowed && !this.props.linkData['hash_is_shorten'] && !this.props.isReadonly() && this.props.shareModel.currentIsOwner();
        if(this.state.editLink && editAllowed){
            return (
                <Paper zDepth={0} rounded={false} className={"public-link-container edit-link"}>
                    <div style={{display:'flex', alignItems:'center'}}>
                        <span style={{fontSize:16, color:'rgba(0,0,0,0.4)'}}>{PathUtils.getDirname(publicLink) + '/ '}</span>
                        <TextField style={{flex:1, marginRight: 16}} onChange={this.changeLink} value={this.state.customLink !== undefined ? this.state.customLink : this.props.linkData['hash']}/>
                        <FloatingActionButton mini={true} iconClassName="mdi mdi-check" onTouchTap={this.toggleEditMode} />
                    </div>
                    <div className="section-legend">{this.props.getMessage('194')}</div>
                </Paper>
            );
        }else{
            const copyButton = <span ref="copy-button" className="copy-link-button mdi mdi-content-copy" title={this.props.getMessage('191')}/>;
            const setHtml = function(){
                return {__html:this.state.copyMessage};
            }.bind(this);
            const focus = function(e){
                e.target.select();
            };
            let actionLinks = [], qrCode;
            if(this.props.showMailer){
                actionLinks.push(<ActionButton key="outline" callback={this.openMailer} mdiIcon="email-outline" messageId="45"/>);
            }
            if(editAllowed){
                actionLinks.push(<ActionButton key="pencil" callback={this.toggleEditMode} mdiIcon="pencil" messageId={"193"}/>);
            }
            if(ShareModel.qrcodeEnabled()){
                actionLinks.push(<ActionButton key="qrcode" callback={this.toggleQRCode} mdiIcon="qrcode" messageId={'94'}/>);
            }
            if(actionLinks.length){
                actionLinks = (
                    <div className="additional-actions-links">{actionLinks}</div>
                ) ;
            }else{
                actionLinks = null;
            }
            if(this.state.showQRCode){
                qrCode = <div className="qrCode"><QRCode size={128} value={publicLink} level="Q"/></div>;
            }
            return (
                <Paper zDepth={0} rounded={false} className="public-link-container">
                    <div style={{position:'relative'}}>
                        <TextField
                            className={"public-link" + (this.props.linkData['is_expired'] ? ' link-expired':'')}
                            type="text"
                            name="Link"
                            ref="public-link-field"
                            value={publicLink}
                            onFocus={focus}
                            fullWidth={true}
                        /> {copyButton}
                    </div>
                    <div style={{textAlign:'center'}} className="section-legend" dangerouslySetInnerHTML={setHtml()}/>
                    {this.props.linkData.target_users && <TargetedUsers {...this.props}/>}
                    {actionLinks}
                    {qrCode}
                </Paper>
            );
        }
    }
});

PublicLinkField = ShareContextConsumer(PublicLinkField)
export {PublicLinkField as default};