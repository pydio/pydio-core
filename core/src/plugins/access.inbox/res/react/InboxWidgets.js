(function(global){

    var ns = global.InboxWidgets ||{};

    var LeftPanel = React.createClass({

        propTypes:{
            pydio:React.PropTypes.instanceOf(Pydio)
        },

        getInitialState:function(){
            return {meta_filter:null};
        },

        handleChange: function(event) {
            var value = event.target.value;
            if(value) value += '*';
            document.getElementById('content_pane').ajxpPaneObject.addMetadataFilter('text', value);
        },

        focus:function(){
            this.props.pydio.UI.disableAllKeyBindings();
        },
        blur:function(){
            this.props.pydio.UI.enableAllKeyBindings();
        },
        filterByShareMetaType(type, event){
            if(type == '-1'){
                type = '';
            }
            this.setState({meta_filter:type});
            document.getElementById('content_pane').ajxpPaneObject.addMetadataFilter('share_meta_type', type);
        },

        render: function(){
            return (
                <div className="inbox-left-panel">
                    <h3 className="colorcode-folder">Files shared with me</h3>
                    <div>These are the standalone files that people have shared with you. Folders are accessible directly via the left panel.</div>
                    <h4>Quick Filtering</h4>
                    <div>
                        <h5>By file name</h5>
                        <input type="text" placeholder="Filter..." onChange={this.handleChange} onFocus={this.focus} onBlur={this.blur}/>
                    </div>
                    <div style={{paddingTop:20}}>
                        <h5><span className="clear" onClick={this.filterByShareMetaType.bind(this, '-1')}>Clear</span>
                            By type
                        </h5>
                        <span className={(this.state.meta_filter === '0'?'active':'') + " share_meta_filter"} onClick={this.filterByShareMetaType.bind(this, '0')}>Invitations</span>
                        <span className={(this.state.meta_filter === '1'?'active':'') + " share_meta_filter"} onClick={this.filterByShareMetaType.bind(this, '1')}>Shares</span>
                        <span className={(this.state.meta_filter === '2'?'active':'') + " share_meta_filter"} onClick={this.filterByShareMetaType.bind(this, '2')}>Errors</span>
                    </div>
                </div>
            );
        }

    });


    function filesListCellModifier(element, ajxpNode, type, metadataDef, ajxpNodeObject){

        if(element != null){
            var nodeMetaValue = ajxpNode.getMetadata().get('share_meta_type');
            var nodeMetaLabel;
            if(nodeMetaValue == "0") nodeMetaLabel = "Invitation";
            else if(nodeMetaValue == "1") nodeMetaLabel = "Share";
            else if(nodeMetaValue == "2") nodeMetaLabel = "Error";
            if(element.down('.text_label')){
                element.down('.text_label').update(nodeMetaLabel);
            }
            var mainElement;
            if(element.up('.ajxpNodeProvider')){
                mainElement = element.up('.ajxpNodeProvider');
            }else if(ajxpNodeObject){
                mainElement = ajxpNodeObject;
            }else{
                console.log(element, ajxpNodeObject);
            }
            if(mainElement){
                mainElement.addClassName('share_meta_type_' + nodeMetaValue);
            }

            if(type == 'row'){
                element.writeAttribute("data-sorter_value", nodeMetaValue);
            }else{
                element.writeAttribute("data-"+metadataDef.attributeName+"-sorter_value", nodeMetaValue);
            }

            var obj = document.getElementById('content_pane').ajxpPaneObject;
            var colIndex;
            obj.columnsDef.map(function(c, index){
                if (c.attributeName == "share_meta_type") {
                    colIndex = index;
                }
            }.bind(this));
            if(colIndex !== undefined){
                obj._sortableTable.sort(colIndex, false);
                obj._sortableTable.updateHeaderArrows();
            }
        }


    }

    ns.filesListCellModifier = filesListCellModifier;
    ns.LeftPanel = LeftPanel;
    global.InboxWidgets = ns;

})(window);