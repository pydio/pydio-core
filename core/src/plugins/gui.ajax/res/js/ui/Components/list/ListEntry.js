import ReactDOM from 'react-dom';
import { Types, collect, collectDrop, nodeDragSource, nodeDropTarget } from '../util/DND';
import { DragSource, DropTarget, flow } from 'react-dnd';
import { Checkbox, FontIcon } from 'material-ui';

/**
 * Material List Entry
 */
//mixins:[ContextMenuNodeProviderMixin],
class ListEntry extends React.Component {

    onClick(event) {
        if(this.props.showSelector) {
            if(this.props.selectorDisabled) return;
            this.props.onSelect(this.props.node, event);
            event.stopPropagation();
            event.preventDefault();
        }else if(this.props.onClick){
            this.props.onClick(this.props.node, event);
        }
    }

    onDoubleClick(event) {
        if(this.props.onDoubleClick){
            this.props.onDoubleClick(this.props.node, event);
        }
    }

    render() {

        let selector, icon, additionalClassName;

        const {showSelector, selected, selectorDisabled} = this.props
        if(showSelector){
            selector = (
                <div className="material-list-selector">
                    <Checkbox checked={selected} ref="selector" disabled={selectorDisabled}/>
                </div>
            );
        }

        const {iconCell, mainIcon} = this.props
        if(iconCell){
            icon = this.props.iconCell;
        }else if(this.props.mainIcon){
            icon = <FontIcon className={"mui-font-icon " + this.props.mainIcon} style={{fontSize: 18/*, color: "#FFFFFF"*/}} />;
        }

        const {className, canDrop, isOver} = this.props
        additionalClassName = className ? className + ' ' : '';
        if(canDrop && isOver){
            additionalClassName += ' droppable-active ';
        }

        const {node} = this.props
        if(node){
            additionalClassName += ' listentry' + node.getPath().replace(/\//g, '_') + ' ' + ' ajxp_node_' + (node.isLeaf()?'leaf':'collection') + ' ';
            if(node.getAjxpMime()){
                additionalClassName += ' ajxp_mime_' + node.getAjxpMime() + ' ';
            }
        }

        const {connectDragSource, connectDropTarget, firstLine, secondLine, thirdLine, style, actions} = this.props

        return (
            <ContextMenuWrapper
                {...this.props}
                ref={instance => {
                    const node = ReactDOM.findDOMNode(instance)
                    if (typeof connectDropTarget === 'function') connectDropTarget(node)
                    if (typeof connectDragSource === 'function') connectDragSource(node)
                }}
                onClick={this.onClick.bind(this)}
                onDoubleClick={showSelector? null : this.onDoubleClick.bind(this)}
                className={additionalClassName + "material-list-entry material-list-entry-" + (thirdLine?3:secondLine?2:1) + "-lines"+ (selected? " selected":"")}
                style={style}>
                {selector}
                <div className={"material-list-icon" + ((mainIcon || iconCell)?"":" material-list-icon-none")}>
                    {icon}
                </div>
                <div className="material-list-text">
                    <div key="line-1" className="material-list-line-1">{firstLine}</div>
                    <div key="line-2" className="material-list-line-2">{secondLine}</div>
                    <div key="line-3" className="material-list-line-3">{thirdLine}</div>
                </div>
                <div className="material-list-actions">
                    {actions}
                </div>
            </ContextMenuWrapper>
        );
    }
}

let ContextMenuWrapper = (props) => {
    return (
        <div {...props} />
    )
}
ContextMenuWrapper = PydioHOCs.withContextMenu(ContextMenuWrapper)

ListEntry.propTypes = {
    showSelector:React.PropTypes.bool,
    selected:React.PropTypes.bool,
    selectorDisabled:React.PropTypes.bool,
    onSelect:React.PropTypes.func,
    onClick:React.PropTypes.func,
    iconCell:React.PropTypes.element,
    mainIcon:React.PropTypes.string,
    firstLine:React.PropTypes.node,
    secondLine:React.PropTypes.node,
    thirdLine:React.PropTypes.node,
    actions:React.PropTypes.element,
    activeDroppable:React.PropTypes.bool,
    className:React.PropTypes.string,
    style: React.PropTypes.object
}

let DragDropListEntry = flow(
    DragSource(Types.NODE_PROVIDER, nodeDragSource, collect),
    DropTarget(Types.NODE_PROVIDER, nodeDropTarget, collectDrop)
)(ListEntry);

export {DragDropListEntry as DragDropListEntry, ListEntry as ListEntry}
