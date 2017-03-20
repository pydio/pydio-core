import OpenNodesModel from './OpenNodesModel'

import { createStore } from 'redux';
import { Provider } from 'react-redux';

import {Editor, reducers} from './editor';

import EditorModeEdit from 'material-ui/svg-icons/editor/mode-edit';
import AVPlayArrow from 'material-ui/svg-icons/av/play-arrow';

const store = createStore(reducers, {})

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

        const {pydio} = this.props
        const {node, editorData} = object

        store.dispatch({
            type: "TAB_CREATE",
            data: {
                id: node.getLabel(),
                title: node.getLabel(),
                url: node.getPath(),
                icon: PydioComponents.ReactEditorOpener,
                child: PydioComponents.ReactEditorOpener,
                pydio,
                node,
                editorData,
                registry: pydio.Registry,
                closeEditorContainer : () => {},
                onRequestTabClose : () => {},
                onRequestTabTitleUpdate : () => {},
            }
        })

        store.dispatch({
            type: 'EDITOR_MODIFY_PANEL',
            open: true
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
            <Provider store={store}>
                <div style={{position: "relative", zIndex: 1400}}>
                    <Editor/>
                </div>
            </Provider>
        )
    }
}

EditionPanel.PropTypes = {
    pydio: React.PropTypes.instanceOf(Pydio)
}

export {EditionPanel as default}
