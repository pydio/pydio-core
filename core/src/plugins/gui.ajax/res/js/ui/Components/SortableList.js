import {Types, collect, collectDrop} from './DND'

/***************************/
/* REACT DND SORTABLE LIST
 /**************************/
/**
 * Specifies the drag source contract.
 * Only `beginDrag` function is required.
 */
var sortableItemSource = {
    beginDrag: function (props) {
        // Return the data describing the dragged item
        return { id: props.id };
    },
    endDrag: function(props){
        props.endSwitching();
    }
};

var sortableItemTarget = {

    hover: function(props, monitor){
        const draggedId = monitor.getItem().id;
        if (draggedId !== props.id) {
            props.switchItems(draggedId, props.id);
        }
    }

};

var sortableItem = React.createClass({

    propTypes:{
        connectDragSource: React.PropTypes.func.isRequired,
        connectDropTarget: React.PropTypes.func.isRequired,
        isDragging: React.PropTypes.bool.isRequired,
        id: React.PropTypes.any.isRequired,
        label: React.PropTypes.string.isRequired,
        switchItems: React.PropTypes.func.isRequired,
        removable: React.PropTypes.bool,
        onRemove:React.PropTypes.func
    },

    removeClicked:function(){
        this.props.onRemove(this.props.id);
    },

    render: function () {
        // Your component receives its own props as usual
        var id = this.props.id;

        // These two props are injected by React DnD,
        // as defined by your `collect` function above:
        var isDragging = this.props.isDragging;
        var connectDragSource = this.props.connectDragSource;
        var connectDropTarget = this.props.connectDropTarget;

        var remove;
        if(this.props.removable){
            remove = <span className="button mdi mdi-close" onClick={this.removeClicked}></span>
        }
        return (
            <ReactMUI.Paper
                ref={instance => {
                        connectDropTarget(ReactDOM.findDOMNode(instance));
                        connectDragSource(ReactDOM.findDOMNode(instance));
                    }}
                zDepth={1}
                style={{opacity:isDragging?0:1}}
            >
                <div className={this.props.className}>
                    {this.props.label}
                    {remove}
                </div>
            </ReactMUI.Paper>
        );
    }
});

var NonDraggableListItem = React.createClass({
    render: function(){
        var remove;
        if(this.props.removable){
            remove = <span className="button mdi mdi-close" onClick={this.removeClicked}></span>
        }
        return (
            <ReactMUI.Paper zDepth={1}>
                <div className={this.props.className}>
                    {this.props.label}
                    {remove}
                </div>
            </ReactMUI.Paper>
        );
    }
});

var DraggableListItem;
if(window.ReactDND){
    DraggableListItem = ReactDND.flow(
        ReactDND.DragSource(Types.SORTABLE_LIST_ITEM, sortableItemSource, collect),
        ReactDND.DropTarget(Types.SORTABLE_LIST_ITEM, sortableItemTarget, collectDrop)
    )(sortableItem);
}else{
    DraggableListItem = NonDraggableListItem;
}


var SortableList = React.createClass({

    propTypes: {
        values: React.PropTypes.array.isRequired,
        onOrderUpdated: React.PropTypes.func,
        removable: React.PropTypes.bool,
        onRemove:React.PropTypes.func,
        className:React.PropTypes.string,
        itemClassName:React.PropTypes.string
    },

    getInitialState: function(){
        return {values: this.props.values};
    },
    componentWillReceiveProps: function(props){
        this.setState({values: props.values, switchData:null});
    },

    findItemIndex: function(itemId, data){
        for(var i=0; i<data.length; i++){
            if(data[i]['payload'] == itemId){
                return i;
            }
        }
    },

    switchItems:function(oldId, newId){
        var oldIndex = this.findItemIndex(oldId, this.state.values);
        var oldItem = this.state.values[oldIndex];
        var newIndex = this.findItemIndex(newId, this.state.values);
        var newItem = this.state.values[newIndex];

        var currentValues = this.state.values.slice();
        currentValues[oldIndex] = newItem;
        currentValues[newIndex] = oldItem;

        // Check that it did not come back to original state
        var oldPrevious = this.findItemIndex(oldId, this.props.values);
        var newPrevious = this.findItemIndex(newId, this.props.values);
        if(oldPrevious == newIndex && newPrevious == oldIndex){
            this.setState({values:currentValues, switchData:null})
            //console.log("no more moves");
        }else{
            this.setState({values:currentValues, switchData:{oldId:oldId, newId:newId}});
            //console.log({oldId:oldIndex, newId:newIndex});
        }

    },

    endSwitching:function(){
        if(this.state.switchData){
            // Check that it did not come back to original state
            if(this.props.onOrderUpdated){
                this.props.onOrderUpdated(this.state.switchData.oldId, this.state.switchData.newId, this.state.values);
            }
        }
        this.setState({switchData:null});
    },

    render: function(){
        var switchItems = this.switchItems;
        return (
            <div className={this.props.className}>
                {this.state.values.map(function(item){
                    return <DraggableListItem
                        id={item.payload}
                        key={item.payload}
                        label={item.text}
                        switchItems={switchItems}
                        endSwitching={this.endSwitching}
                        removable={this.props.removable}
                        onRemove={this.props.onRemove}
                        className={this.props.itemClassName}
                    />
                }.bind(this))}
            </div>
        )
    }
});

export {SortableList as default}