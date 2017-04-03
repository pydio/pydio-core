(function(global){

    class ConfigsParser {
        static parseConfigs(){

            let configs = new Map();
            let panelsNodes = XMLUtils.XPathSelectNodes(global.pydio.getXmlRegistry(), 'client_configs/component_config[@className="InfoPanel"]/infoPanel[@reactComponent]');
            let panels = new Map();
            panelsNodes.forEach(function(node){
                let mimes = node.getAttribute('mime').split(',');
                let component = node.getAttribute('reactComponent');
                mimes.map(function(mime){
                    if(!panels.has(mime)) panels.set(mime, []);
                    panels.get(mime).push({
                        COMPONENT:component,
                        THEME:node.getAttribute('theme'),
                        ATTRIBUTES:node.getAttribute('attributes'),
                        WEIGHT:node.getAttribute('weight') ? parseInt(node.getAttribute('weight')) : 0
                    });
                });
            });
            return panels;

        }
    }

    const InfoPanel = React.createClass({

        propTypes: {
            dataModel: React.PropTypes.instanceOf(PydioDataModel).isRequired,
            pydio:React.PropTypes.instanceOf(Pydio).isRequired,
            style: React.PropTypes.object
        },

        getInitialState: function(){
            let initTemplates = ConfigsParser.parseConfigs();
            return ({
                templates:initTemplates,
                displayData: this.selectionToTemplates(initTemplates)
            });
        },

        componentDidMount: function(){
            this._updateHandler = function(){
                this.setState({displayData: this.selectionToTemplates()});
            }.bind(this);
            this._componentConfigHandler = function(event){
                this.setState({templates:ConfigsParser.parseConfigs()});
            }.bind(this);
            this.props.pydio.observe("actions_refreshed", this._updateHandler );
            this.props.pydio.observe("registry_loaded", this._componentConfigHandler );
            // Trigger contentChange
            if(this.state.displayData && this.state.displayData.TEMPLATES && this.props.onContentChange){
                this.props.onContentChange(this.state.displayData.TEMPLATES.length);
            }
        },

        componentWillUnmount: function(){
            this.props.pydio.stopObserving("actions_refreshed", this._updateHandler );
            this.props.pydio.stopObserving("registry_loaded", this._componentConfigHandler );
        },

        selectionToTemplates: function(initTemplates = null){

            let refTemplates = initTemplates || this.state.templates;
            const {dataModel} = this.props;
            let selection = dataModel.getSelectedNodes();
            if((!selection || !selection.length) && dataModel.getContextNode() === dataModel.getRootNode()){
                selection = [dataModel.getContextNode()];
            }
            let primaryMime, templates = [], uniqueNode;
            let data = {};
            if(!selection || selection.length < 1){
                primaryMime = 'no_selection';
            }else if(selection.length > 1){
                primaryMime = 'generic_multiple';
                data.nodes = selection;
            }else {
                uniqueNode = selection[0];
                if(uniqueNode.isLeaf()){
                    primaryMime = 'generic_file';
                }else{
                    primaryMime = 'generic_dir';
                    if(this.props.dataModel.getRootNode() === uniqueNode){
                        primaryMime = 'ajxp_root_node';
                    }
                }
                data.node = uniqueNode;
            }
            if(refTemplates.has(primaryMime)){
                templates = templates.concat(refTemplates.get(primaryMime));
            }
            if(uniqueNode){
                refTemplates.forEach(function(list, mimeName){
                    if(mimeName === primaryMime) return;
                    if(mimeName.indexOf('meta:') === 0 && uniqueNode.getMetadata().has(mimeName.substr(5))){
                        templates = templates.concat(list);
                    }else if(uniqueNode.getAjxpMime() === mimeName){
                        templates = templates.concat(list);
                    }
                });
            }

            if(this.props.onContentChange && !initTemplates){
                this.props.onContentChange(templates.length);
            }
            templates.sort(function(a, b){
                return (a.WEIGHT === b.WEIGHT ? 0 : ( a.WEIGHT > b.WEIGHT ? 1 : -1));
            });
            return {TEMPLATES:templates, DATA:data};
        },

        render: function(){

            let templates = this.state.displayData.TEMPLATES.map(function(tpl){
                let component = tpl.COMPONENT;
                const parts = component.split('.', 2);
                let namespace = parts[0];
                let name = parts[1];
                return (
                    <PydioReactUI.AsyncComponent
                        {...this.state.displayData.DATA}
                        {...this.props}
                        key={"ip_" + component}
                        namespace={namespace}
                        componentName={name}
                    />
                );

            }.bind(this));
            return <div id="info_panel" style={this.props.style}>{templates}</div>;

        }
    });

    export {InfoPanel as default};

})(window);