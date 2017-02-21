import ListEntryNodeListenerMixin from './ListEntryNodeListenerMixin'
import {ListEntry} from './ListEntry'
import InlineEditor from './InlineEditor'


/**
 * Specific list entry rendered as a table row. Not a real table, CSS used.
 */
export default React.createClass({

    mixins:[ListEntryNodeListenerMixin],

    propTypes:{
        node:React.PropTypes.instanceOf(AjxpNode),
        tableKeys:React.PropTypes.object.isRequired,
        renderActions:React.PropTypes.func
        // See also ListEntry nodes
    },

    render: function(){

        let actions = this.props.actions;
        if(this.props.renderActions) {
            actions = this.props.renderActions(this.props.node);
        }

        let cells = [];
        let firstKey = true;
        for(var key in this.props.tableKeys){
            if(!this.props.tableKeys.hasOwnProperty(key)) continue;

            let data = this.props.tableKeys[key];
            let style = data['width']?{width:data['width']}:null;
            let value, rawValue;
            if(data.renderCell){
                data['name'] = key;
                value = data.renderCell(this.props.node, data);
            }else{
                value = this.props.node.getMetadata().get(key);
            }
            rawValue = this.props.node.getMetadata().get(key);
            let inlineEditor;
            if(this.state && this.state.inlineEdition && firstKey){
                inlineEditor = <InlineEditor
                    node={this.props.node}
                    onClose={()=>{this.setState({inlineEdition:false})}}
                    callback={this.state.inlineEditionCallback}
                />
                let style = this.props.style || {};
                style.position = 'relative';
                this.props.style = style;
            }
            cells.push(<span key={key} className={'cell cell-' + key} title={rawValue} style={style} data-label={data['label']}>{inlineEditor}{value}</span>);
            firstKey = false;
        }

        return (
            <ListEntry
                {...this.props}
                iconCell={null}
                firstLine={cells}
                actions={actions}
            />
        );


    }

});

