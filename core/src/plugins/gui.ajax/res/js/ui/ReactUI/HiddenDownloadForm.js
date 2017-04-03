export default React.createClass({

    propTypes:{
        pydio: React.PropTypes.instanceOf(Pydio).isRequired
    },

    componentDidMount: function(){
        pydio.UI.registerHiddenDownloadForm(this);
    },

    componentWillUnmount: function(){
        pydio.UI.unRegisterHiddenDownloadForm(this);
    },

    triggerDownload(userSelection, parameters){
        this.setState({
            userSelection: userSelection,
            parameters: parameters
        }, () => { this.refs.form.submit() });
    },

    render: function(){
        if(!this.state){
            return null;
        }
        let ajxpServerAccess = this.props.pydio.Parameters.get('ajxpServerAccess');
        let inputs = new Map();
        let inputFields = [];
        for(let key in this.state.parameters){
            if(!this.state.parameters.hasOwnProperty[key]){
                const value = this.state.parameters[key];
                inputFields.push(<input type="hidden" name={key} key={key} value={value}/>);
            }
        }
        if(this.state.userSelection){
            this.state.userSelection.getSelectedNodes().map(function(node){
                inputFields.push(<input type="hidden" name="nodes[]" key={node.getPath()} value={node.getPath()}/>);
            });
        }

        return (
            <div style={{visibility:'hidden', position:'absolute', left: -10000}}>
                <form ref="form" action={ajxpServerAccess} target="dl_form_iframe">{inputFields}</form>
                <iframe ref="iframe" name="dl_form_iframe"></iframe>
            </div>
        );
    }

});