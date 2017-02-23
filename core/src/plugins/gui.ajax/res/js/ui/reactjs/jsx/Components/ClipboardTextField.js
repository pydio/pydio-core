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
            copyMessage = <div className="copy-message" dangerouslySetInnerHTML={setHtml()}/>;
        }
        return (
            <div>
                <div style={{display:'flex', alignItems:'baseline'}}>
                    <MaterialUI.TextField
                        style={{flex:1,width:'100%'}}
                        ref="input"
                        floatingLabelText={this.props.floatingLabelText}
                        defaultValue={this.props.inputValue}
                        className={this.props.inputClassName}
                        readOnly={true}
                        onClick={select}
                    />
                    <span ref="copy-button" style={{padding:'0 5px'}} title={this.props.getMessage('191')} className="copy-button icon-paste"/>
                </div>
                {copyMessage}
            </div>
        );
    }

});
