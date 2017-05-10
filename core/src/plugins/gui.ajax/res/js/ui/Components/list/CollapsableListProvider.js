import NodeListCustomProvider from './NodeListCustomProvider'
import DataModelBadge from '../elements/DataModelBadge'

export default React.createClass({

    propTypes:{
        paneData:React.PropTypes.object,
        pydio:React.PropTypes.instanceOf(Pydio),
        nodeClicked:React.PropTypes.func,
        startOpen:React.PropTypes.bool,
        onBadgeIncrease: React.PropTypes.func,
        onBadgeChange: React.PropTypes.func
    },

    getInitialState:function(){

        var dataModel = new PydioDataModel(true);
        var rNodeProvider = new RemoteNodeProvider();
        dataModel.setAjxpNodeProvider(rNodeProvider);
        rNodeProvider.initProvider(this.props.paneData.options['nodeProviderProperties']);
        var rootNode = new AjxpNode("/", false, "loading", "folder.png", rNodeProvider);
        dataModel.setRootNode(rootNode);

        return {
            open:false,
            componentLaunched:!!this.props.paneData.options['startOpen'],
            dataModel:dataModel
        };
    },

    toggleOpen:function(){
        this.setState({open:!this.state.open, componentLaunched:true});
    },

    onBadgeIncrease: function(newValue, prevValue, memoData){
        if(this.props.onBadgeIncrease){
            this.props.onBadgeIncrease(this.props.paneData, newValue, prevValue, memoData);
            if(!this.state.open) this.toggleOpen();
        }
    },

    onBadgeChange(newValue, prevValue, memoData){
        if(this.props.onBadgeChange){
            this.props.onBadgeChange(this.props.paneData, newValue, prevValue, memoData);
            if(!this.state.open) this.toggleOpen();
        }
    },

    render:function(){

        var messages = this.props.pydio.MessageHash;
        var paneData = this.props.paneData;

        const title = messages[paneData.options.title] || paneData.options.title;
        const className = 'simple-provider ' + (paneData.options['className'] ? paneData.options['className'] : '');
        const titleClassName = 'section-title ' + (paneData.options['titleClassName'] ? paneData.options['titleClassName'] : '');

        var badge;
        if(paneData.options.dataModelBadge){
            badge = <DataModelBadge
                dataModel={this.state.dataModel}
                options={paneData.options.dataModelBadge}
                onBadgeIncrease={this.onBadgeIncrease}
                onBadgeChange={this.onBadgeChange}
            />;
        }
        var emptyMessage;
        if(paneData.options.emptyChildrenMessage){
            emptyMessage = <DataModelBadge
                dataModel={this.state.dataModel}
                options={{
                    property:'root_children_empty',
                    className:'emptyMessage',
                    emptyMessage:messages[paneData.options.emptyChildrenMessage]
                }}
            />
        }

        var component;
        if(this.state.componentLaunched){
            var entryRenderFirstLine;
            if(paneData.options['tipAttribute']){
                entryRenderFirstLine = function(node){
                    var meta = node.getMetadata().get(paneData.options['tipAttribute']);
                    if(meta){
                        return <div title={meta.replace(/<\w+(\s+("[^"]*"|'[^']*'|[^>])+)?(\/)?>|<\/\w+>/gi, '')}>{node.getLabel()}</div>;
                    }else{
                        return node.getLabel();
                    }
                };
            }
            component = (
                <NodeListCustomProvider
                    pydio={this.props.pydio}
                    ref={paneData.id}
                    title={title}
                    elementHeight={36}
                    heightAutoWithMax={4000}
                    entryRenderFirstLine={entryRenderFirstLine}
                    nodeClicked={this.props.nodeClicked}
                    presetDataModel={this.state.dataModel}
                    reloadOnServerMessage={paneData.options['reloadOnServerMessage']}
                    actionBarGroups={paneData.options['actionBarGroups']?paneData.options['actionBarGroups']:[]}
                />
            );
        }

        return (
            <div className={className + (this.state.open?" open": " closed")}>
                <div className={titleClassName}>
                    <span className="toggle-button" onClick={this.toggleOpen}>{this.state.open?messages[514]:messages[513]}</span>
                    {title} {badge}
                </div>
                {component}
                {emptyMessage}
            </div>
        );

    }

});

