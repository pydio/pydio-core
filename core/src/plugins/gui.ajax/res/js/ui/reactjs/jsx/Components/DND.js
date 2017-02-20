/******************************/
/* REACT DND GENERIC COMPONENTS
 /******************************/
var Types = {
    NODE_PROVIDER: 'node',
    SORTABLE_LIST_ITEM:'sortable-list-item'
};

/**
 * Specifies which props to inject into your component.
 */
function collect(connect, monitor) {
    return {
        connectDragSource: connect.dragSource(),
        isDragging: monitor.isDragging()
    };
}

function collectDrop(connect, monitor){
    return {
        connectDropTarget: connect.dropTarget(),
        canDrop: monitor.canDrop(),
        isOver:monitor.isOver(),
        isOverCurrent:monitor.isOver({shallow:true})
    };
}

/****************************/
/* REACT DND DRAG/DROP NODES
 /***************************/

var nodeDragSource = {
    beginDrag: function (props) {
        // Return the data describing the dragged item
        return { node: props.node };
    },

    endDrag: function (props, monitor, component) {
        if (!monitor.didDrop()) {
            return;
        }
        var item = monitor.getItem();
        var dropResult = monitor.getDropResult();
        var dnd = pydio.Controller.defaultActions.get("dragndrop");
        if(dnd){
            var dndAction = pydio.Controller.getActionByName(dnd);
            // Make sure to enable
            dndAction.enable();
            dndAction.apply([item.node, dropResult.node]);
        }

    }
};

var nodeDropTarget = {

    hover: function(props, monitor){
    },

    canDrop: function(props, monitor){

        var source = monitor.getItem().node;
        var target = props.node;

        var dnd = pydio.Controller.defaultActions.get("dragndrop");
        if(dnd){
            var dndAction = pydio.Controller.getActionByName(dnd);
            // Make sure to enable
            dndAction.enable();
            // Manually apply, do not use action.apply(), as it will
            // catch the exception we are trying to detect.
            window.actionArguments = [source, target, "canDrop"];
            try {
                eval(dndAction.options.callbackCode);
            } catch (e) {
                return false;
            }
            return true;
        }
        return false;
    },

    drop: function(props, monitor){
        var hasDroppedOnChild = monitor.didDrop();
        if (hasDroppedOnChild) {
            return;
        }
        return { node: props.node }
    }

};



export {Types, collect, collectDrop, nodeDragSource, nodeDropTarget}