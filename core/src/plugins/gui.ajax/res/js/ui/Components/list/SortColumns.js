import IconButtonMenu from '../menu/IconButtonMenu'

export default React.createClass({

    mixins:[PydioReactUI.PydioContextConsumerMixin],

    propTypes:{
        tableKeys:React.PropTypes.object.isRequired,
        columnClicked:React.PropTypes.func,
        sortingInfo:React.PropTypes.object,
        displayMode:React.PropTypes.string
    },

    onMenuClicked: function(object){
        this.props.columnClicked(object.payload);
    },

    onHeaderClick: function(key, ev){
        let data = this.props.tableKeys[key];
        if(data && data['sortType'] && this.props.columnClicked){
            data['name'] = key;
            this.props.columnClicked(data);
        }
    },

    getColumnsItems: function(displayMode){

        let items = [];
        for(var key in this.props.tableKeys){
            if(!this.props.tableKeys.hasOwnProperty(key)) continue;
            let data = this.props.tableKeys[key];
            let style = data['width']?{width:data['width']}:null;
            let icon;
            let className = 'cell header_cell cell-' + key;
            if(data['sortType']){
                className += ' sortable';
                if(this.props.sortingInfo && (
                    this.props.sortingInfo.attribute === key
                    || this.props.sortingInfo.attribute === data['sortAttribute']
                    || this.props.sortingInfo.attribute === data['remoteSortAttribute'])){
                    icon = this.props.sortingInfo.direction === 'asc' ? 'mdi mdi-arrow-up' : 'mdi mdi-arrow-down';
                    className += ' active-sort-' + this.props.sortingInfo.direction;
                }
            }
            if(displayMode === 'menu') {
                data['name'] = key;
                items.push({
                    payload: data,
                    text: data['label'],
                    iconClassName: icon
                });
            }else if(displayMode === 'menu_data'){
                items.push({
                    name: data['label'],
                    callback:this.onHeaderClick.bind(this, key),
                    icon_class:icon
                });
            }else{
                items.push(<span
                    key={key}
                    className={className}
                    style={style}
                    onClick={this.onHeaderClick.bind(this, key)}
                >{data['label']}</span>);

            }
        }
        return items;

    },

    buildSortingMenuItems: function(){
        return this.getColumnsItems('menu_data');
    },

    componentDidMount: function(){

        var sortAction = new Action({
            name:'sort_action',
            icon_class:'mdi mdi-sort-descending',
            text_id:450,
            title_id:450,
            text:this.context.getMessage(450),
            title:this.context.getMessage(450),
            hasAccessKey:false,
            subMenu:true,
            subMenuUpdateImage:true
        }, {
            selection:false,
            dir:true,
            actionBar:true,
            actionBarGroup:'display_toolbar',
            contextMenu:false,
            infoPanel:false
        }, {}, {}, {
            dynamicBuilder:this.buildSortingMenuItems
        });
        let buttons = new Map();
        buttons.set('sort_action', sortAction);
        this.context.pydio.getController().updateGuiActions(buttons);

    },

    componentWillUnmount: function(){
        this.context.pydio.getController().deleteFromGuiActions('sort_action');
    },

    render: function(){
        if(this.props.displayMode === 'menu'){
            return (
                <IconButtonMenu buttonTitle="Sort by..." buttonClassName="mdi mdi-sort-descending" menuItems={this.getColumnsItems('menu')} onMenuClicked={this.onMenuClicked}/>
            );
        }else{
            return (
                <ReactMUI.ToolbarGroup float="left">{this.getColumnsItems('header')}</ReactMUI.ToolbarGroup>
            );
        }

    }
});

