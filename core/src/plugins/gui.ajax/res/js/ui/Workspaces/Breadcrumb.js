const React = require('react')
const PydioNode = require('pydio/model/node')
const {muiThemeable} = require('material-ui/styles')
const LangUtils = require('pydio/util/lang')
const {Textfit} = require('react-textfit')


let Breadcrumb = React.createClass({

    getInitialState: function(){
        return {node: null};
    },

    componentDidMount: function(){
        let n = this.props.pydio.getContextHolder().getContextNode();
        if(n){
            this.setState({node: n});
        }
        this._observer = function(event){
            this.setState({node: event.memo});
        }.bind(this);
        this.props.pydio.getContextHolder().observe("context_changed", this._observer);
    },

    componentWillUnmount: function(){
        this.props.pydio.getContextHolder().stopObserving("context_changed", this._observer);
    },

    goTo: function(target, event){
        const targetNode = new PydioNode(target);
        this.props.pydio.getContextHolder().requireContextChange(targetNode);
    },

    render: function(){
        const pydio = this.props.pydio;
        const styles = {
            main: {
                fontSize: 22,
                lineHeight:'24px',
                padding: 20,
                color: this.props.muiTheme.appBar.textColor,
                maxWidth: '70%',
                flex:1
            }
        };
        if(!pydio.user){
            return <span className="react_breadcrumb"></span>;
        }
        let repoLabel = pydio.user.repositories.get(pydio.user.activeRepository).getLabel();
        let segments = [];
        let path = this.state.node ? LangUtils.trimLeft(this.state.node.getPath(), '/') : '';
        let rebuilt = '';
        let i = 0;
        let mainStyle = this.props.rootStyle || {};
        mainStyle = {...styles.main, ...mainStyle};
        path.split('/').map(function(seg){
            if(!seg) return;
            rebuilt += '/' + seg;
            segments.push(<span key={'bread_sep_' + i} className="separator"> / </span>);
            segments.push(<span key={'bread_' + i} className="segment" onClick={this.goTo.bind(this, rebuilt)}>{seg}</span>);
            i++;
        }.bind(this));
        return (
            <Textfit mode="single" perfectFit={false} min={12} max={22} className="react_breadcrumb" style={mainStyle}>
                 {this.props.startWithSeparator && <span className="separator"> / </span>}
                <span className="segment first" onClick={this.goTo.bind(this, '/')}>{repoLabel}</span>
                {segments}
            </Textfit>
        );
    }

});

Breadcrumb = muiThemeable()(Breadcrumb);

export {Breadcrumb as default}
