var MetaSourceForm = React.createClass({

    mixins:[
        AdminComponents.MessagesConsumerMixin,
        PydioReactUI.ActionDialogMixin,
        PydioReactUI.CancelButtonProviderMixin,
        PydioReactUI.SubmitButtonProviderMixin
    ],

    propTypes:{
        model: React.PropTypes.object,
        editor: React.PropTypes.object
    },

    getDefaultProps: function(){
        return {
            dialogTitleId: 'ajxp_admin.ws.46',
            dialogSize:'sm'
        };
    },

    propTypes:{
        modalData:React.PropTypes.object
    },

    getInitialState:function(){
        return {step:'chooser'};
    },

    setModal:function(pydioModal){
        this.setState({modal:pydioModal});
    },

    submit:function(){
        if(this.state.pluginId && this.state.pluginId !== -1){
            this.dismiss();
            this.props.editor.addMetaSource(this.state.pluginId);
        }
    },

    render:function(){
        var model = this.props.model;
        var currentMetas = model.getOption("META_SOURCES", true);
        var allMetas = model.getAllMetaSources();

        // Step is Chooser: build a DropDownMenu
        var menuItems = [{payload:-1, text:this.context.getMessage('ws.47', 'ajxp_admin')}];
        allMetas.map(function(metaSource){
            var id = metaSource['id'];
            var type = id.split('.').shift();
            if(type == 'metastore' || type == 'index'){
                var already = false;
                Object.keys(currentMetas).map(function(metaKey){
                    if(metaKey.indexOf(type) === 0) already = true;
                });
                if(already) return;
            }else{
                if(currentMetas[metaSource['id']]) return;
            }
            menuItems.push({payload:metaSource['id'], text:metaSource['label']});
        });
        var change = function(event, index, item){
            this.setState({pluginId:item.payload});
        }.bind(this);
        return (
            <div style={{height: 350}}>
                <ReactMUI.DropDownMenu
                    menuItems={menuItems} onChange={change}
                />
            </div>
        );
    }

});

export {MetaSourceForm as default}