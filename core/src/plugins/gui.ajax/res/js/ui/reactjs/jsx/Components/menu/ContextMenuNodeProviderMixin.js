import ContextMenuModel from './ContextMenuModel'

export default {

    contextMenuNodeResponder: function(event){

        event.preventDefault();
        event.stopPropagation();
        ContextMenuModel.getInstance().openNodeAtPosition(this.props.node, event.clientX, event.clientY);

    },

    contextMenuResponder: function(event){

        event.preventDefault();
        event.stopPropagation();
        ContextMenuModel.getInstance().openAtPosition(event.clientX, event.clientY);

    }

};