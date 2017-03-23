const DeleteDialog = React.createClass({

    propTypes:{
        userSelection:React.PropTypes.instanceOf(PydioDataModel)
    },

    getInitialState:function(){

        let selection = this.props.userSelection;
        let firstNode = selection.getUniqueNode();
        let meta = firstNode.getMetadata();
        let deleteMessageId, fieldName, fieldValues = [], metaAttribute = 'basename';

        if(meta.get("ajxp_mime") == "user_editable"){
            deleteMessageId = 'ajxp_conf.34';
            fieldName = "user_id";
        }else if(meta.get("ajxp_mime") == "role"){
            deleteMessageId = 'ajxp_conf.126';
            fieldName = "role_id";
        }else if(meta.get("ajxp_mime") == "group"){
            deleteMessageId = 'ajxp_conf.126';
            fieldName = "group";
            metaAttribute = "filename"
        }else{
            deleteMessageId = 'ajxp_conf.35';
            fieldName = "repository_id";
            metaAttribute = "repository_id";
        }
        fieldValues = selection.getSelectedNodes().map(function(node){
            if(metaAttribute === 'basename'){
                return PathUtils.getBasename(node.getMetadata().get('filename'));
            }else{
                return node.getMetadata().get(metaAttribute);
            }
        })
        return {
            node:firstNode,
            mime:firstNode.getMetadata().get('ajxp_mime'),
            deleteMessage:global.MessageHash[deleteMessageId],
            fieldName:fieldName,
            fieldValues:fieldValues
        };
    },

    getTitle:function(){
        return this.state.deleteMessage;
    },

    getDialogClassName:function(){
        return "dialog-max-480";
    },

    getButtons:function(){
        return [
            { text: 'Cancel' },
            { text: 'Delete', onClick: this.submit, ref: 'submit' }
        ];
    },

    submit: function(dialog) {
        if(!this.state.fieldValues.length){
            return;
        }
        var parameters = {
            get_action:'delete'
        };
        if(this.state.fieldValues.length === 1){
            parameters[this.state.fieldName] = this.state.fieldValues[0];
        }else{
            parameters[this.state.fieldName + '[]'] = this.state.fieldValues;
        }
        PydioApi.getClient().request(parameters, function(transport){
            this.props.dismiss();
            if(this.state.node.getParent()) {
                this.state.node.getParent().reload();
            }
        }.bind(this));
    },

    render: function(){
        return <div></div>;
    }

});

export {DeleteDialog as default}