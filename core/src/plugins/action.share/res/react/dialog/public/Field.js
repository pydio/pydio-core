const React = require('react');
import ShareContextConsumer from '../ShareContextConsumer'
const {RaisedButton, TextField, Paper} = require('material-ui')
const ShareModel = require('pydio').requireLib('ReactModelShare')
const QRCode = require('qrcode.react');
const Clipboard = require('clipboard');

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
                var copyMessage;
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
        var mailData = this.props.shareModel.prepareEmail("link", this.props.linkData.hash);
        this.props.showMailer(mailData.subject, mailData.message, [], this.props.linkData.hash);
    },

    toggleQRCode: function(){
        this.setState({showQRCode:!this.state.showQRCode});
    },

    render: function(){
        var publicLink = this.props.linkData['public_link'];
        var editAllowed = this.props.editAllowed && !this.props.linkData['hash_is_shorten'] && !this.props.isReadonly() && this.props.shareModel.currentIsOwner();
        if(this.state.editLink && editAllowed){
            return (
                <div className={"public-link-container edit-link"}>
                    <span>{publicLink.split('://')[0]}://[..]/{PathUtils.getBasename(PathUtils.getDirname(publicLink)) + '/'}</span>
                    <TextField onChange={this.changeLink} value={this.state.customLink !== undefined ? this.state.customLink : this.props.linkData['hash']}/>
                    <RaisedButton label="Ok" onClick={this.toggleEditMode}/>
                    <div className="section-legend">{this.props.getMessage('194')}</div>
                </div>
            );
        }else{
            var copyButton = <span ref="copy-button" className="copy-link-button icon-paste" title={this.props.getMessage('191')}/>;
            var setHtml = function(){
                return {__html:this.state.copyMessage};
            }.bind(this);
            var focus = function(e){
                e.target.select();
            };
            var actionLinks = [];
            if(this.props.showMailer){
                actionLinks.push(<a key="invitation" onClick={this.openMailer}>{this.props.getMessage('45')}</a>);
            }
            if(editAllowed){
                actionLinks.push(<a key="customize" onClick={this.toggleEditMode}>{this.props.getMessage('193')}</a>);
            }
            if(ShareModel.qrcodeEnabled()){
                actionLinks.push(<a className={this.state.showQRCode?'qrcode-active':''} key="qrcode" onClick={this.toggleQRCode}>{this.props.getMessage('94')}</a>)
            }
            if(actionLinks.length){
                actionLinks = (
                    <div className="additional-actions-links">{actionLinks}</div>
                ) ;
            }else{
                actionLinks = null;
            }
            if(this.state.showQRCode){
                var qrCode = <div className="qrCode"><QRCode size={128} value={publicLink} level="Q"/></div>;
            }
            return (
                <Paper zDepth={1} rounded={false} className="public-link-container">
                    <div style={{display:'flex', alignItems:'center'}}>
                        <TextField
                            className={"public-link" + (this.props.linkData['is_expired'] ? ' link-expired':'')}
                            type="text"
                            name="Link"
                            ref="public-link-field"
                            value={publicLink}
                            onFocus={focus}
                            style={{flex: 1}}
                        /> {copyButton}
                    </div>
                    <div style={{textAlign:'center'}} className="section-legend" dangerouslySetInnerHTML={setHtml()}/>
                    {actionLinks}
                    {qrCode}
                </Paper>
            );
        }
    }
});

PublicLinkField = ShareContextConsumer(PublicLinkField)
export {PublicLinkField as default};