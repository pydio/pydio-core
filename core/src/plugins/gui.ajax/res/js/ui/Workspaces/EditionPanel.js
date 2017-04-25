import OpenNodesModel from './OpenNodesModel'

import { createStore } from 'redux';
import { Provider, connect } from 'react-redux';

import * as actions from './editor/actions';
import {Editor, reducers} from './editor';

const store = createStore(reducers, window.__REDUX_DEVTOOLS_EXTENSION__ && window.__REDUX_DEVTOOLS_EXTENSION__())

class EditionPanel extends React.Component {

    constructor(props) {
        super(props)
    }

    componentDidMount() {
        this._nodesModelObserver = (node) => this._handleNodePushed(node);
        this._nodesRemoveObserver = (index) => this._handleNodeRemoved(index);
        this._titlesObserver = () => this.forceUpdate()

        OpenNodesModel.getInstance().observe("nodePushed", this._nodesModelObserver);
        OpenNodesModel.getInstance().observe("nodeRemovedAtIndex", this._nodesRemoveObserver);
        OpenNodesModel.getInstance().observe("titlesUpdated", this._titlesObserver);
    }

    componentWillUnmount() {
        OpenNodesModel.getInstance().stopObserving("nodePushed", this._nodesModelObserver);
        OpenNodesModel.getInstance().stopObserving("nodeRemovedAtIndex", this._nodesRemoveObserver);
        OpenNodesModel.getInstance().stopObserving("titlesUpdated", this._titlesObserver);
    }

    _handleNodePushed(object) {

        const {pydio, tabCreate, editorModify, editorSetActiveTab} = this.props

        const {node = {}, editorData} = object

        console.log(editorData)

        let tabId = tabCreate({
            id: node.getLabel(),
            title: node.getLabel(),
            url: node.getPath(),
            icon: PydioWorkspaces.FilePreview,
            child: PydioComponents.ReactEditorOpener,
            pydio,
            node,
            editorData,
            registry: pydio.Registry
        }).id

        editorSetActiveTab(tabId)

        editorModify({
            open: true,
            isPanelActive: true
        })
    }

    _handleNodeRemoved(index) {
    }

    render() {
        let style = {
            position: "fixed",
            bottom: "50px",
            right: "100px",
            cursor: "pointer",
            transform: "translate(50%, 50%)",
            zIndex: 1400
        }

        return (
            <div style={{position: "relative", zIndex: 1400}}>
                <Editor/>
            </div>
        )
    }
}

class EditionProvider extends React.Component {
    render () {
        return (
            <Provider store={store}>
                <EditionPanel {...this.props} />
            </Provider>
        )
    }
}

EditionPanel = connect(null, actions)(EditionPanel)

EditionPanel.PropTypes = {
    pydio: React.PropTypes.instanceOf(Pydio)
}

export {EditionProvider as default}
