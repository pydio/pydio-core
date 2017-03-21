export default React.createClass({

    propTypes: {
        floatingLabelText: React.PropTypes.string,

        inputValue: React.PropTypes.string,
        inputClassName: React.PropTypes.string,
        getMessage: React.PropTypes.func,
        inputCopyMessage: React.PropTypes.string
    },

    getInitialState: function(){
        return {copyMessage: null};
    },

    componentDidMount:function(){
        this.attachClipboard();
    },
    componentDidUpdate:function(){
        this.attachClipboard();
    },

    attachClipboard:function(){
        if(this._clip){
            this._clip.destroy();
        }
        if(!this.refs['copy-button']) {
            return;
        }
        this._clip = new Clipboard(this.refs['copy-button'], {
            text: function(trigger) {
                return this.props.inputValue;
            }.bind(this)
        });
        this._clip.on('success', function(){
            this.setState({copyMessage:this.props.getMessage(this.props.inputCopyMessage)}, this.clearCopyMessage);
        }.bind(this));
        this._clip.on('error', function(){
            var copyMessage;
            if( global.navigator.platform.indexOf("Mac") === 0 ){
                copyMessage = this.props.getMessage('144');
            }else{
                copyMessage = this.props.getMessage('143');
            }
            this.refs['input'].focus();
            this.setState({copyMessage:copyMessage}, this.clearCopyMessage);
        }.bind(this));
    },

    clearCopyMessage:function(){
        global.setTimeout(function(){
            this.setState({copyMessage:''});
        }.bind(this), 3000);
    },

    render: function(){

        let select = function(e){
            e.currentTarget.select();
        };

        let copyMessage = null;
        if(this.state.copyMessage){
            var setHtml = function(){
                return {__html:this.state.copyMessage};
            }.bind(this);
            copyMessage = <div style={{color:'rgba(0,0,0,0.53)'}} className="copy-message" dangerouslySetInnerHTML={setHtml()}/>;
        }

        const buttonStyle = {
            position    :'absolute',
            right: 0,
            bottom: 8,
            fontSize: 16,
            backgroundColor: 'rgba(242, 242, 242, 0.93)',
            height: 30,
            width: 28,
            lineHeight: '32px',
            textAlign: 'center',
            cursor: 'pointer'
        };


        return (
            <div>
                <div style={{position:'relative'}}>
                    <MaterialUI.TextField
                        fullWidth={true}
                        ref="input"
                        floatingLabelText={this.props.floatingLabelText}
                        defaultValue={this.props.inputValue}
                        className={this.props.inputClassName}
                        readOnly={true}
                        onClick={select}
                        floatingLabelStyle={{whiteSpace:'nowrap'}}
                        style={{marginTop:-10}}
                    />
                    <span ref="copy-button" style={buttonStyle} title={this.props.getMessage('191')} className="copy-button mdi mdi-content-copy"/>
                </div>
                {copyMessage}
            </div>
        );
    }

});
