import UserAvatar from '../avatar/UserAvatar'
const {IconButton, Checkbox, FlatButton, RaisedButton, ListItem, FontIcon, Avatar, Divider, Subheader, List} = require('material-ui')
const {muiThemeable} = require('material-ui/styles')
const {Loader} = require('pydio').requireLib('boot')
import EmptyStateView from '../../views/EmptyStateView'
import AlphaPaginator from './AlphaPaginator'
import SearchForm from './SearchForm'

class UsersList extends React.Component{

    constructor(props, context){
        super(props, context);
        this.state = {select: false, selection:[]};
    }

    render(){
        const {item, mode, paginatorType, loading, enableSearch, showSubheaders} = this.props;
        const folders = item.collections || [];
        const leafs = item.leafs || [];
        const foldersSubHeader = folders.length && (leafs.length || showSubheaders) ? [{subheader:'Groups'}] : [];
        let usersSubHeader = [];
        if(showSubheaders || paginatorType){
            usersSubHeader = [{subheader: paginatorType ? <AlphaPaginator {...this.props} style={{lineHeight: '20px',padding: '14px 0'}} /> : 'Users'}];
        }
        const items = [...foldersSubHeader, ...folders, ...usersSubHeader, ...leafs];
        const total = items.length;
        let elements = [];
        const toggleSelect = () => {this.setState({select:!this.state.select, selection:[]})};
        const createAction = () => {this.props.onCreateAction(item)};
        const deleteAction = () => {this.props.onDeleteAction(item, this.state.selection); this.setState({select: false, selection: []})};

        const activeTbarColor = this.props.muiTheme.palette.accent2Color;
        const toolbar = (
            <div style={{padding: 10, height:56, backgroundColor:this.state.select?activeTbarColor : '#fafafa', display:'flex', alignItems:'center', transition:DOMUtils.getBeziersTransition()}}>
                {mode === "selector" && item._parent && <IconButton iconClassName="mdi mdi-chevron-left" onTouchTap={() => {this.props.onFolderClicked(item._parent)}}/>}
                {mode === 'book' && total > 0 && item.actions && item.actions.multiple && <Checkbox style={{width:'initial', marginLeft: this.state.select?7:14}} checked={this.state.select} onCheck={toggleSelect}/>}
                <div style={{flex:1, fontSize:20, color:this.state.select?'white':'rgba(0,0,0,0.87)'}}>{item.label}</div>
                {mode === 'book' && item.actions && item.actions.create && !this.state.select && <FlatButton secondary={true} label={item.actions.create} onTouchTap={createAction}/>}
                {mode === 'book' && item.actions && item.actions.remove && this.state.select && <RaisedButton secondary={true} label={item.actions.remove} disabled={!this.state.selection.length} onTouchTap={deleteAction}/>}
                {enableSearch && <SearchForm searchLabel={this.props.searchLabel} onSearch={this.props.onSearch} style={{flex:1, minWidth: 200}}/>}
            </div>
        );
        // PARENT NODE
        if(item._parent && mode === 'book' && item._parent._parent){
            elements.push(
                <ListItem
                    key={'__parent__'}
                    primaryText={".."}
                    onTouchTap={(e) => {e.stopPropagation(); this.props.onFolderClicked(item._parent)}}
                    leftAvatar={<Avatar icon={<FontIcon className={'mdi mdi-arrow-up'}/>}/>}
                />
            );
            if(total){
                elements.push(<Divider inset={true} key={'parent-divider'}/>);
            }
        }
        // ITEMS
        items.forEach(function(item, index){
            if(item.subheader){
                elements.push(<Subheader>{item.subheader}</Subheader>);
                return;
            }
            const fontIcon = (
                <UserAvatar avatarSize={36} pydio={this.props.pydio || pydio}
                    userId={item.id}
                    userLabel={item.label}
                    avatar={item.avatar}
                    icon={item.icon}
                    avatarOnly={true}
                    useDefaultAvatar={true}
                />
            );
            let rightIconButton;
            let touchTap = (e)=>{e.stopPropagation(); this.props.onItemClicked(item)};
            if(folders.indexOf(item) > -1 && this.props.onFolderClicked){
                touchTap = (e)=>{e.stopPropagation(); this.props.onFolderClicked(item) };
                if(mode === 'selector' && !item._notSelectable){
                    rightIconButton = (
                        <IconButton
                            iconClassName={"mdi mdi-account-multiple-plus"}
                            tooltip={"Select this group"}
                            tooltipPosition="bottom-left"
                            onTouchTap={()=>{this.props.onItemClicked(item)}}
                        />
                    );
                }
            }else if(mode === 'inner' && this.props.onDeleteAction){
                rightIconButton = (
                    <IconButton
                        iconClassName={"mdi mdi-delete"}
                        tooltip={"Remove"}
                        tooltipPosition="bottom-left"
                        iconStyle={{color: 'rgba(0,0,0,0.13)', hoverColor:'rgba(0,0,0,0.53)'}}
                        onTouchTap={()=>{this.props.onDeleteAction(this.props.item, [item])}}
                    />
                );
            }
            const select = (e, checked) => {
                if(checked) {
                    this.setState({selection: [...this.state.selection, item]});
                }else {
                    const stateSel = this.state.selection;
                    const selection = [...stateSel.slice(0, stateSel.indexOf(item)), ...stateSel.slice(stateSel.indexOf(item)+1)];
                    this.setState({selection: selection});
                }
            };
            elements.push(<ListItem
                key={item.id}
                primaryText={item.label}
                onTouchTap={touchTap}
                disabled={mode === 'inner'}
                leftAvatar={!this.state.select && fontIcon}
                rightIconButton={rightIconButton}
                leftCheckbox={this.state.select && <Checkbox checked={this.state.selection.indexOf(item) > -1} onCheck={select}/>}
            />);
            if(mode !== 'inner' && index < total - 1){
                elements.push(<Divider inset={true} key={item.id + '-divider'}/>);
            }
        }.bind(this));

        let emptyState;
        if(!elements.length){
            let emptyStateProps = {
                style               : {backgroundColor: 'rgb(250, 250, 250)'},
                iconClassName       : 'mdi mdi-account-off',
                primaryTextId       : this.props.emptyStatePrimaryText || 'No records yet',
                secondaryTextId     : mode === 'book' ? ( this.props.emptyStateSecondaryText || null ) : null
            };
            if(mode === 'book' && item.actions && item.actions.create){
                emptyStateProps = {
                    ...emptyStateProps,
                    actionLabelId: item.actions.create,
                    actionCallback: createAction
                };
            }
            emptyState = <EmptyStateView {...emptyStateProps}/>;
        }

        return (
            <div style={{flex:1, flexDirection:'column', display:'flex'}} onTouchTap={this.props.onTouchTap}>
                {mode !== 'inner' && (!emptyState || mode !== 'book') && !this.props.noToolbar && toolbar}
                {!emptyState && !loading &&
                    <List style={{flex: 1, overflowY: mode !== 'inner' ? 'auto' : 'initial'}}>
                        {this.props.subHeader && <Subheader>{this.props.subHeader}</Subheader>}
                        {elements}
                    </List>
                }
                {loading && <Loader style={{flex:1}}/>}
                {emptyState}
            </div>
        );
    }

}

UsersList.propTypes ={
    item: React.PropTypes.object,
    onCreateAction:React.PropTypes.func,
    onDeleteAction:React.PropTypes.func,
    onItemClicked:React.PropTypes.func,
    onFolderClicked:React.PropTypes.func,
    mode:React.PropTypes.oneOf(['book', 'selector', 'inner'])
};

UsersList = muiThemeable()(UsersList);

export {UsersList as default}