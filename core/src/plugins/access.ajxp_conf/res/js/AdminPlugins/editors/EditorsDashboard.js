import PluginsList from '../core/PluginsList'

const EditorsDashboard = React.createClass({

    mixins:[AdminComponents.MessagesConsumerMixin],

    render:function(){
        return(
            <div className="main-layout-nav-to-stack vertical-layout" style={this.props.style}>
                <ReactMUI.Paper className="left-nav vertical-layout" zDepth={0}>
                    <h1>{this.context.getMessage('plugtype.title.editor', '')}</h1>
                    <div style={{padding:'0 20px'}} className="layout-fill-scroll-y">
                        {this.context.getMessage('plugins.4')}
                    </div>
                </ReactMUI.Paper>
                <PluginsList {...this.props}/>
            </div>
        );
    }

});

export {EditorsDashboard as default}