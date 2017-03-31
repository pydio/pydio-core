const styles = {
    textArea: {
        width: '100%',
        height: '100%',
        backgroundColor: '#fff',
        fontSize: 13,
    }
}

let Viewer =  ({value, onChange}) => {
    return <textarea value={value} style={styles.textArea} onChange={onChange} />
}

class TextEditor extends React.Component {

    constructor(props) {
        super(props);

        this.handleChange = this.handleChange.bind(this);
        this.hasBeenModified = this.hasBeenModified.bind(this);
        this.state = {}
    }

    componentWillMount() {
        let {pydio, node} = this.props

        pydio.ApiClient.request({
            get_action: 'get_content',
            file: node.getPath(),
        }, function (transport) {
            this.setState({originalText: transport.responseText});
            this.setState({textContent: transport.responseText});
        }.bind(this));
    }

    saveContent() {
        let {pydio, node} = this.props
        pydio.ApiClient.postPlainTextContent(node.getPath(), this.state.textContent, (success) => {
            this.setState({originalText: this.state.textContent});
        }.bind(this));
    }

    hasBeenModified() {
        return (this.state.originalText != this.state.textContent)
    }

    handleChange(event) {
      this.setState({textContent: event.target.value});
    }

    buildActions() {
        let actions = [];
        let mess = this.props.pydio.MessageHash;
        actions.push(
            <MaterialUI.ToolbarGroup
                firstChild={true}
                key="left"
            >
                <MaterialUI.FlatButton label={'Save'} disabled={!this.hasBeenModified()} onClick={()=>{this.saveContent()}}/>
            </MaterialUI.ToolbarGroup>
        );
        return actions;
    }

    render() {
        return (
            <Viewer actions={this.buildActions()} value={this.state.textContent} onChange={() => this.handleChange()} />
        );
    }
}

// Define HOCs
if (typeof PydioHOCs !== "undefined") {
    Viewer = PydioHOCs.withActions(Viewer);
    Viewer = PydioHOCs.withLoader(Viewer)
    Viewer = PydioHOCs.withErrors(Viewer)
}

window.TextEditor = TextEditor;
