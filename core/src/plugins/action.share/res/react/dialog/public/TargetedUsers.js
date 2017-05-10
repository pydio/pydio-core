const React = require('react');
const ReactDOM = require('react-dom');
import ShareContextConsumer from '../ShareContextConsumer'
const {RaisedButton, TextField, Paper, IconButton} = require('material-ui')
const ShareModel = require('pydio').requireLib('ReactModelShare')
const Clipboard = require('clipboard');

class TargetedUserLink extends React.Component{


    constructor(props){
        super(props);
        this.state = {copyMessage : ''};
    }


    componentDidMount(){
        if(this._clip){
            this._clip.destroy();
        }
        if(this._button){
            this._clip = new Clipboard(this._button, {
                text: function(trigger) {
                    return this.props.link;
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
                    copyMessage = this.props.getMessage('share_center.143');
                }
                this.setState({copyMessage:copyMessage}, this.clearCopyMessage);
            }.bind(this));
        }
    }

    componentWillUnmount(){
        if(this._clip){
            this._clip.destroy();
        }
    }

    clearCopyMessage(){
        setTimeout(function(){
            this.setState({copyMessage:''});
        }.bind(this), 5000);
    }

    render(){
        const {display, link, download_count} = this.props;

        return (
            <div style={{display:'flex'}}>
                <div style={{flex: 1}} >
                    {display}
                    <IconButton
                        pydio={this.props.pydio}
                        ref={(ref) => {this._button = ReactDOM.findDOMNode(ref)}}
                        iconClassName="mdi mdi-link"
                        tooltip={this.state.copyMessage || link}
                        iconStyle={{fontSize: 13, lineHeight:'17px'}} style={{width:34, height: 34, padding:6}}
                    />
                </div>
                <div style={{width: 40, textAlign:'center'}}>{download_count}</div>
            </div>
        );

    }
}

class TargetedUsers extends React.Component{

    constructor(props, context){
        super(props, context);
        this.state = {open: false};
    }

    render(){
        const {target_users} = this.props.linkData;
        let items = Object.keys(target_users).map((k) => {
            const userData = target_users[k];
            const title = this.props.linkData.public_link + '?u=' + k;
            return <TargetedUserLink {...userData} link={title}/>;
        });
        if(!items.length) return null;

        const rootStyle = {
            lineHeight: '34px',
            padding: '4px 10px 4px',
            fontSize: 14,
            backgroundColor: '#fafafa',
            borderRadius: 2
        };
        const headerStyle = {
            borderBottom: this.state.open ?  '1px solid #757575' : '',
            color: 'rgba(0, 0, 0, 0.36)'
        }

        return (
            <div style={rootStyle}>
                <div style={{display:'flex', ...headerStyle}}>
                    <div style={{flex: 1}} >{this.props.getMessage('245').replace('%s', items.length)} <span className={'mdi mdi-chevron-' + (this.state.open ? 'up' : 'down')} style={{cursor:'pointer'}} onClick={() => {this.setState({open:!this.state.open})}}/></div>
                    {this.state.open && <div style={{width: 40, textAlign:'center'}}>#DL</div>}
                </div>
                {this.state.open &&
                    <div>{items}</div>
                }
            </div>
        );

    }

}

TargetedUsers.propTypes = {

    linkData:React.PropTypes.object.isRequired,
    shareModel: React.PropTypes.instanceOf(ShareModel)

}

TargetedUsers = ShareContextConsumer(TargetedUsers);
TargetedUserLink = ShareContextConsumer(TargetedUserLink);

export {TargetedUsers as default}