(function(global){

    class ConfigsParser {
        static parseConfigs(){

            let configs = new Map();
            let panelsNodes = XMLUtils.XPathSelectNodes(global.pydio.getXmlRegistry(), 'client_configs/component_config[@className="InfoPanel"]/infoPanelExtension[@reactComponent]|client_configs/component_config[@className="InfoPanel"]/infoPanel[@reactComponent]');
            let panels = new Map();
            panelsNodes.forEach(function(node){
                let mimes = node.getAttribute('mime').split(',');
                let component = node.getAttribute('reactComponent');
                mimes.map(function(mime){
                    if(!panels.has(mime)) panels.set(mime, []);
                    panels.get(mime).push({
                        COMPONENT:component,
                        THEME:node.getAttribute('theme'),
                        ATTRIBUTES:node.getAttribute('attributes')
                    });
                });
            });
            return panels;

        }
    }

    let InfoPanelCard = React.createClass({

        propTypes: {
            title:React.PropTypes.string,
            actions:React.PropTypes.array
        },

        render: function(){
            let title = this.props.title ? <div className="panelHeader">{this.props.title}</div> : null;
            let actions = this.props.actions ? <div className="panelActions">{this.props.actions}</div> : null;

            return (
                <ReactMUI.Paper zDepth={1} className="panelCard">
                    {title}
                    <div className="panelContent">{this.props.children}</div>
                    {actions}
                </ReactMUI.Paper>
            );
        }

    });

    let GenericFile = React.createClass({

        render: function(){
            return (
                <InfoPanelCard>
                    <WSComponents.FilePreview
                        key={this.props.node.getPath()}
                        style={{height:200}}
                        node={this.props.node}
                        loadThumbnail={true}
                        richPreview={true}
                    />
                    <Toolbars.Toolbar className="primaryToolbar" renderingType="button-icon" toolbars={["info_panel", "info_panel_share"]} controller={this.props.pydio.getController()}/>
                </InfoPanelCard>
            );
        }

    });

    let GenericDir = React.createClass({

        render: function(){
            return (
                <InfoPanelCard>
                    <div className="mimefont-container"><div className={"mimefont mdi mdi-" + this.props.node.getMetadata().get('fonticon')}></div></div>
                    <Toolbars.Toolbar className="primaryToolbar" renderingType="button-icon" toolbars={["info_panel", "info_panel_share"]} controller={this.props.pydio.getController()}/>
                </InfoPanelCard>
            );
        }

    });

    let ImagePreview = React.createClass({

        render: function(){
            return (
                <InfoPanelCard title="Image Information">
                    Image Data ?
                </InfoPanelCard>
            );
        }

    });

    let InfoPanel = React.createClass({

        propTypes: {
            dataModel: React.PropTypes.instanceOf(PydioDataModel).isRequired,
            pydio:React.PropTypes.instanceOf(Pydio).isRequired
        },

        getInitialState: function(){
            return ({
                templates:ConfigsParser.parseConfigs(),
                nodes: this.props.dataModel.getSelectedNodes()
            });
        },

        componentDidMount: function(){
            this._updateHandler = function(){
                this.setState({nodes: this.props.dataModel.getSelectedNodes()});
            }.bind(this);
            this._componentConfigHandler = function(event){
                if(event.memo.className == "InfoPanel"){
                    this.setState({templates:ConfigsParser.parseConfigs()});
                }
            }.bind(this);

            this.props.pydio.observe("actions_refreshed", this._updateHandler );
            this.props.pydio.observe("component_config_changed", this._componentConfigHandler );
        },

        componentWillUnmount: function(){
            this.props.pydio.stopObserving("actions_refreshed", this._updateHandler );
            this.props.pydio.stopObserving("component_config_changed", this._componentConfigHandler );
        },

        selectionToTemplates: function(){

            let selection = this.state.nodes;
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
                }
                data.node = uniqueNode;
            }
            if(this.state.templates.has(primaryMime)){
                templates = templates.concat(this.state.templates.get(primaryMime));
            }
            if(uniqueNode){
                this.state.templates.forEach(function(list, mimeName){
                    if(mimeName === primaryMime) return;
                    if(mimeName.indexOf('meta:') === 0 && uniqueNode.getMetadata().has(mimeName.substr(5))){
                        templates = templates.concat(list);
                    }else if(uniqueNode.getAjxpMime() === mimeName){
                        templates = templates.concat(list);
                    }
                });
            }

            return {TEMPLATES:templates, DATA:data};
        },

        render: function(){

            let tplData = this.selectionToTemplates();
            let templates = tplData.TEMPLATES.map(function(tpl){

                let component = tpl.COMPONENT;
                let namespace = component.split(".")[0];
                let name = component.split(".")[1];
                return (
                    <ReactPydio.AsyncComponent
                        {...tplData.DATA}
                        {...this.props}
                        namespace={namespace}
                        componentName={name}
                    />
                );

            }.bind(this));
            return <div id="info_panel">{templates}</div>;

        }
    })

    let ns = global.DetailPanes || {};
    ns.InfoPanel = InfoPanel;
    ns.InfoPanelCard = InfoPanelCard;
    ns.GenericFile = GenericFile;
    ns.GenericDir = GenericDir;
    ns.ImagePreview = ImagePreview;
    global.DetailPanes  = ns;

})(window);