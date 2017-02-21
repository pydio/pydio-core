import {Types, collect, collectDrop, nodeDragSource, nodeDropTarget} from '../DND'
import ContextMenuNodeProviderMixin from '../menu/ContextMenuNodeProviderMixin'


/**
 * Material List Entry
 */
var ListEntry = React.createClass({

    mixins:[ContextMenuNodeProviderMixin],

    propTypes:{
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
    },

    onClick: function(event){
        if(this.props.showSelector) {
            if(this.props.selectorDisabled) return;
            this.props.onSelect(this.props.node, event);
            event.stopPropagation();
            event.preventDefault();
        }else if(this.props.onClick){
            this.props.onClick(this.props.node, event);
        }
    },

    onDoubleClick: function(event){
        if(this.props.onDoubleClick){
            this.props.onDoubleClick(this.props.node, event);
        }
    },

    render: function(){
        var selector;
        if(this.props.showSelector){
            selector = (
                <div className="material-list-selector">
                    <ReactMUI.Checkbox checked={this.props.selected} ref="selector" disabled={this.props.selectorDisabled}/>
                </div>
            );
        }
        var iconCell;
        if(this.props.iconCell){
            iconCell = this.props.iconCell;
        }else if(this.props.mainIcon){
            iconCell = <ReactMUI.FontIcon className={this.props.mainIcon}/>;
        }
        var additionalClassName = this.props.className ? this.props.className + ' ' : '';
        if(this.props.canDrop && this.props.isOver){
            additionalClassName += ' droppable-active ';
        }
        if(this.props.node){
            additionalClassName += ' listentry' + this.props.node.getPath().replace(/\//g, '_') + ' ' + ' ajxp_node_' + (this.props.node.isLeaf()?'leaf':'collection') + ' ';
            if(this.props.node.getAjxpMime()){
                additionalClassName += ' ajxp_mime_' + this.props.node.getAjxpMime() + ' ';
            }
        }
        let connector = (instance) => instance;
        if(window.ReactDND && this.props.connectDragSource && this.props.connectDropTarget){
            let connectDragSource = this.props.connectDragSource;
            let connectDropTarget = this.props.connectDropTarget;
            connector = (instance) => {
                connectDragSource(ReactDOM.findDOMNode(instance));
                connectDropTarget(ReactDOM.findDOMNode(instance));
            };
        }
        return (
            <div
                ref={connector}
                onClick={this.onClick}
                onDoubleClick={this.props.showSelector?null:this.onDoubleClick}
                onContextMenu={this.contextMenuNodeResponder}
                className={additionalClassName + "material-list-entry material-list-entry-" + (this.props.thirdLine?3:this.props.secondLine?2:1) + "-lines"+ (this.props.selected? " selected":"")}
                style={this.props.style}>
                {selector}
                <div className={"material-list-icon" + ((this.props.mainIcon || iconCell)?"":" material-list-icon-none")}>
                    {iconCell}
                </div>
                <div className="material-list-text">
                    <div key="line-1" className="material-list-line-1">{this.props.firstLine}</div>
                    <div key="line-2" className="material-list-line-2">{this.props.secondLine}</div>
                    <div key="line-3" className="material-list-line-3">{this.props.thirdLine}</div>
                </div>
                <div className="material-list-actions">
                    {this.props.actions}
                </div>
            </div>
        );

    }
});

var DragDropListEntry;
if(window.ReactDND){
    var DragDropListEntry = ReactDND.flow(
        ReactDND.DragSource(Types.NODE_PROVIDER, nodeDragSource, collect),
        ReactDND.DropTarget(Types.NODE_PROVIDER, nodeDropTarget, collectDrop)
    )(ListEntry);
}else{
    DragDropListEntry = ListEntry;
}

export {DragDropListEntry as DragDropListEntry, ListEntry as ListEntry}