const React = require('react')
const ReactDOM = require('react-dom')
const Clipboard = require('clipboard')
const {Snackbar, Divider, FlatButton, TextField} = require('material-ui')

import PydioContextConsumer from '../PydioContextConsumer'

class ErrorStack extends React.Component{

    constructor(props){
        super(props);
        this.state = {
            copyMessage : false,
            copyError : false
        };
    }

    componentDidMount(){
        this._attachClipboard();
    }

    componentDidUpdate(){
        this._attachClipboard();
    }

    componentWillUnmount(){
        this._detachClipboard();
    }

    _attachClipboard(){
        this._detachClipboard();
        if(this._button){
            this._clip = new Clipboard(this._button, {
                text: (trigger) => {
                    return this.props.fullMessage;
                }
            });
            this._clip.on('success', ()=>{
                this.setState({copyMessage:true}, this.clearCopyMessage);
            });
            this._clip.on('error', ()=>{
                this.setState({copyError: true});
            });
        }
    }

    _detachClipboard(){
        if(this._clip){
            this._clip.destroy();
        }
    }

    clearCopyMessage(){
        setTimeout(function(){
            this.setState({copyMessage:false});
        }.bind(this), 5000);
    }

    render(){
        const {copyMessage, copyError} = this.state;
        const {errorStack, fullMessage, pydio} = this.props;
        return (
            <div>
                <Divider style={{marginTop:10, marginBottom:10}}/>
                <div style={{display:'flex', alignItems:'center'}}>
                    <div style={{flex:1, fontSize:16, fontWeight:500}}>{pydio.MessageHash['622']}</div>
                    <FlatButton secondary={true} ref={(e)=>{this._button = ReactDOM.findDOMNode(e)}} label={copyMessage ? pydio.MessageHash['624'] : pydio.MessageHash['623']}/>
                </div>
                {copyError &&
                    <TextField fullWidth={true} multiLine={true} value={fullMessage} textareaStyle={{fontSize: 13, color:'white'}} ref="fullMessageCopy"/>
                }
                {!copyError &&
                    errorStack.map((m)=>{
                        return <div style={{whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis'}}>{m}</div>
                    })
                }
            </div>
        );

    }
}

class MessageBar extends React.Component{

    constructor(props){
        super(props);
        this.state = {open: false};
    }

    componentDidMount(){
        this.props.getPydio().UI.registerMessageBar(this);
    }

    componentWillUnmount(){
        this.props.getPydio().UI.unregisterMessageBar();
    }

    error(message, actionLabel, actionCallback) {
        this.setState({
            open: true,
            errorStatus: true,
            message: message,
            actionLabel: actionLabel,
            actionCallback: actionCallback
        });
    }

    info(message, actionLabel, actionCallback) {
        this.setState({
            open: true,
            errorStatus: false,
            message: message,
            actionLabel: actionLabel,
            actionCallback: actionCallback
        });
    }

    handleClose() {
        this.setState({open: false});
    }

    render(){
        let message = this.state.message || '';
        const {errorStatus, actionLabel, actionCallback} = this.state;
        let mainStack = [];
        let errorStack = [];
        if(message.split('\n').length){
            message.split('\n').forEach((m) => {
                if(errorStatus && m.length && ( m[0] === '#' || errorStack.length )){
                    errorStack.push(m);
                }else{
                    mainStack.push(m);
                }
            });
            if(errorStack.length && ! mainStack.length) mainStack = errorStack[0];
        }else{
            mainStack.push(message);
        }
        message = (
            <span>
                {errorStatus && <span><span className="mdi mdi-alert"/>&nbsp;</span>}
                {mainStack}
                {errorStack.length > 0 &&
                    <ErrorStack fullMessage={message} errorStack={errorStack} pydio={this.props.pydio}/>
                }
            </span>
        );
        return (
            <Snackbar
                open={this.state.open}
                message={message}
                onRequestClose={this.handleClose.bind(this)}
                autoHideDuration={errorStatus ? 9000 : 4000}
                action={actionLabel}
                onActionTouchTap={actionCallback}
                bodyStyle={{padding:'16px 24px', height:'auto', maxHeight:200, overflowY:'auto', lineHeight:'inherit'}}
            />
        );
    }
}

MessageBar = PydioContextConsumer(MessageBar);

export {MessageBar as default}
