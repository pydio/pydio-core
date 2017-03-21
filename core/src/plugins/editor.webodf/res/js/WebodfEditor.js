const Viewer = ({url, style}) => {
    return (
        <iframe src={url} style={{...style, border: 0, flex: 1}} className="vertical_fit"></iframe>
    );
};

class WebodfEditor extends React.Component {
    constructor(props) {
        super(props);
        this.state = {};
    }

    componentWillMount() {
        let {pydio, node} = this.props;

        this.setState({url: `plugins/editor.webodf/frame.php?token=${pydio.Parameters.get('SECURE_TOKEN')}&file=${node.getPath()}`});
    }

    render() {
        return (
            <PydioComponents.AbstractEditor {...this.props}>
                <Viewer {...this.props} url={this.state.url} />
            </PydioComponents.AbstractEditor>
        );
    }
}

window.WebodfEditor = WebodfEditor;
