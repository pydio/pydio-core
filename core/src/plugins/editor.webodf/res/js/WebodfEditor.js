let Viewer = ({url, style, onLoad}) => {
    return (
        <iframe src={url} style={{...style, height: "100%", border: 0, flex: 1}} onLoad={onLoad} className="vertical_fit"></iframe>
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
            <Viewer ref="iframe" {...this.props} url={this.state.url} error={this.state.error} />
        );
    }
}

// Define HOCs
if (typeof PydioHOCs !== "undefined") {
    Viewer = PydioHOCs.withLoader(Viewer)
    Viewer = PydioHOCs.withErrors(Viewer)
}

window.WebodfEditor = WebodfEditor;
