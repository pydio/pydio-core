const {Component} = require('react')
const {findDOMNode} = require('react-dom')

export default function(PydioComponent, onDropFunction){

    let DND, Backend;
    try{
        DND = require('react-dnd');
        Backend = require('react-dnd-html5-backend');
    }catch(e){
        return PydioComponent;
    }


    class NativeFileDropProvider extends Component{

        render(){
            const {connectDropTarget} = this.props;
            return (
                <PydioComponent
                    {...this.props}
                    ref={(instance) => {
                        connectDropTarget(findDOMNode(instance))
                    }}
                />
            );
        }

    }

    const fileTarget = {

        drop: function (props, monitor) {

            let dataTransfer = monitor.getItem().dataTransfer;
            let items;
            if (dataTransfer.items && dataTransfer.items.length && dataTransfer.items[0] && (dataTransfer.items[0].getAsEntry || dataTransfer.items[0].webkitGetAsEntry)) {
                items = dataTransfer.items;
            }
            onDropFunction(items, dataTransfer.files, props);

        }
    };

    NativeFileDropProvider = DND.DropTarget(Backend.NativeTypes.FILE, fileTarget, function (connect, monitor) {
        return {
            connectDropTarget   : connect.dropTarget(),
            isOver              : monitor.isOver(),
            canDrop             : monitor.canDrop()
        };
    })(NativeFileDropProvider);

    return NativeFileDropProvider;

}


