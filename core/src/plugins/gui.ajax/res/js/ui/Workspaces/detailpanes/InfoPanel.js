import React from 'react';
import {compose} from 'redux';
const {Animations, withVerticalScroll} = require('pydio').requireLib('hoc')

const originStyles = {translateX: 600}
const targetStyles = {translateX: 0}

let Template = ({id, style, children}) => {
    return <div id={id} style={style}>{children}</div>
}

Template = compose(
    Animations.makeAsync,
    Animations.makeTransition(originStyles, targetStyles),
)(Template)

class InfoPanel extends React.Component {

    constructor(props) {
        super(props)

        let initTemplates = ConfigsParser.parseConfigs();
        this._updateExpected = true;

        this.state = {
            templates:initTemplates,
            displayData: this.selectionToTemplates(initTemplates)
        };
    }

    shouldComponentUpdate(){
        return this._updateExpected;
    }

    componentDidMount() {
        const scrollerRefresh = () => {
            try{this.context.scrollArea.refresh()}catch(e){}
        };
        this._updateHandler = () => {
            this._updateExpected = true;
            this.setState({displayData: this.selectionToTemplates()}, ()=> {
                this._updateExpected = false;
                if(this.context.scrollArea) setTimeout(scrollerRefresh, 750);
            });
        }
        this._componentConfigHandler = () => {
            this._updateExpected = true;
            this.setState({templates:ConfigsParser.parseConfigs()}, () => {
                this._updateExpected = false;
                if(this.context.scrollArea) setTimeout(scrollerRefresh, 750);
            })
        };

        this.props.pydio.observe("actions_refreshed", this._updateHandler );
        this.props.pydio.observe("registry_loaded", this._componentConfigHandler );

        // Trigger contentChange
        if(this.state.displayData && this.state.displayData.TEMPLATES && this.props.onContentChange){
            this.props.onContentChange(this.state.displayData.TEMPLATES.length);
        }
    }

    componentWillUnmount() {
        this.props.pydio.stopObserving("actions_refreshed", this._updateHandler );
        this.props.pydio.stopObserving("registry_loaded", this._componentConfigHandler );
    }

    selectionToTemplates(initTemplates = null){

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
    }

    render() {

        let templates = this.state.displayData.TEMPLATES.map((tpl, i) => {
            const component = tpl.COMPONENT;
            const [namespace, name] = component.split('.', 2);

            return (
                <PydioReactUI.AsyncComponent
                    {...this.state.displayData.DATA}
                    {...this.props}
                    key={"ip_" + component}
                    namespace={namespace}
                    componentName={name}
                />
            );
        });
        return (
            <Template style={this.props.style}>{templates}</Template>
        );
    }
}

InfoPanel.propTypes = {
    dataModel: React.PropTypes.instanceOf(PydioDataModel).isRequired,
    pydio:React.PropTypes.instanceOf(Pydio).isRequired,
    style: React.PropTypes.object
}

InfoPanel.contextTypes = {
    scrollArea: React.PropTypes.object
};

InfoPanel = withVerticalScroll(InfoPanel, {id: "info_panel"})

class ConfigsParser {

    static parseConfigs(){

        let configs = new Map();
        let panelsNodes = XMLUtils.XPathSelectNodes(pydio.getXmlRegistry(), 'client_configs/component_config[@component="InfoPanel"]/infoPanel');
        let panels = new Map();
        panelsNodes.forEach(function(node){
            if(!node.getAttribute('reactComponent')) {
                return;
            }
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

export {InfoPanel as default};
