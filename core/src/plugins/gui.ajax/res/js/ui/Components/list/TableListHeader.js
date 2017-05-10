import MessagesConsumerMixin from '../util/MessagesConsumerMixin'
import SortColumns from './SortColumns'
import ListPaginator from './ListPaginator'

/**
 * Specific header for Table layout, reading metadata from node and using keys
 */
export default React.createClass({

    mixins:[MessagesConsumerMixin],

    propTypes:{
        tableKeys:React.PropTypes.object.isRequired,
        loading:React.PropTypes.bool,
        reload:React.PropTypes.func,
        dm:React.PropTypes.instanceOf(PydioDataModel),
        node:React.PropTypes.instanceOf(AjxpNode),
        onHeaderClick:React.PropTypes.func,
        sortingInfo:React.PropTypes.object
    },

    render: function(){
        let headers, paginator;
        if(this.props.node.getMetadata().get("paginationData") && this.props.node.getMetadata().get("paginationData").get('total') > 1){
            paginator = <ListPaginator dataModel={this.props.dm} node={this.props.node}/>;
        }
        return (
            <ReactMUI.Toolbar className="toolbarTableHeader">
                <SortColumns displayMode="tableHeader" {...this.props} columnClicked={this.props.onHeaderClick}/>
                <ReactMUI.ToolbarGroup float="right">
                    {paginator}
                    <ReactMUI.FontIcon
                        key={1}
                        tooltip={this.context.getMessage('149', '')}
                        className={"icon-refresh" + (this.props.loading?" rotating":"")}
                        onClick={this.props.reload}
                    />
                    {this.props.additionalActions}
                </ReactMUI.ToolbarGroup>
            </ReactMUI.Toolbar>
        );

    }
});

