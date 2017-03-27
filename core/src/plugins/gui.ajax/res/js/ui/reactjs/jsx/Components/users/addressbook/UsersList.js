import UserAvatar from '../avatar/UserAvatar'

class UsersList extends React.Component{

    constructor(props, context){
        super(props, context);
        this.state = {select: false, selection:[]};
    }

    render(){
        if(this.props.loading){
            return <PydioReactUI.Loader style={{flex:1}}/>;
        }
        const {item, mode} = this.props;
        const folders = item.collections || [];
        const leafs = item.leafs || [];
        const items = [...folders, ...leafs];
        const total = items.length;
        let elements = [];
        const toggleSelect = () => {this.setState({select:!this.state.select, selection:[]})};
        const createAction = () => {this.props.onCreateAction(item)};
        const deleteAction = () => {this.props.onDeleteAction(item, this.state.selection); this.setState({select: false, selection: []})};

        const activeTbarColor = this.props.muiTheme.palette.accent2Color;
        const toolbar = (
            <div style={{padding: 10, height:56, backgroundColor:this.state.select?activeTbarColor : '#fafafa', display:'flex', alignItems:'center', transition:DOMUtils.getBeziersTransition()}}>
                {item.actions && item.actions.multiple && <MaterialUI.Checkbox style={{width:'initial', marginLeft: this.state.select?7:14}} checked={this.state.select} onCheck={toggleSelect}/>}
                <div style={{flex:1, fontSize:20, color:this.state.select?'white':'rgba(0,0,0,0.87)'}}>{item.label}</div>
                {item.actions && item.actions.create && !this.state.select && <MaterialUI.FlatButton secondary={true} label={item.actions.create} onTouchTap={createAction}/>}
                {item.actions && item.actions.remove && this.state.select && <MaterialUI.RaisedButton secondary={true} label={item.actions.remove} disabled={!this.state.selection.length} onTouchTap={deleteAction}/>}
            </div>
        );
        // PARENT NODE
        if(item._parent && mode !== 'inner' && !(mode==='book' && !item._parent._parent)){
            elements.push(
                <MaterialUI.ListItem
                    key={'__parent__'}
                    primaryText={".."}
                    onTouchTap={(e) => {e.stopPropagation(); this.props.onFolderClicked(item._parent)}}
                    leftAvatar={<MaterialUI.Avatar icon={<MaterialUI.FontIcon className={'mdi mdi-arrow-up'}/>}/>}
                />
            );
            if(total){
                elements.push(<MaterialUI.Divider inset={true} key={'parent-divider'}/>);
            }
        }
        // ITEMS
        items.forEach(function(item, index){
            const fontIcon = (
                <UserAvatar cardSize={40} pydio={this.props.pydio || pydio}
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
                        <MaterialUI.IconButton
                            iconClassName={"mdi mdi-account-multiple-plus"}
                            tooltip={"Select this group"}
                            tooltipPosition="bottom-left"
                            onTouchTap={()=>{this.props.onItemClicked(item)}}
                        />
                    );
                }
            }else if(mode === 'inner' && this.props.onDeleteAction){
                rightIconButton = (
                    <MaterialUI.IconButton
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
            elements.push(<MaterialUI.ListItem
                key={item.id}
                primaryText={item.label}
                onTouchTap={touchTap}
                disabled={mode === 'inner'}
                leftAvatar={!this.state.select && fontIcon}
                rightIconButton={rightIconButton}
                leftCheckbox={this.state.select && <MaterialUI.Checkbox checked={this.state.selection.indexOf(item) > -1} onCheck={select}/>}
            />);
            if(mode !== 'inner' && index < total - 1){
                elements.push(<MaterialUI.Divider inset={true} key={item.id + '-divider'}/>);
            }
        }.bind(this));
        return (
            <div style={{flex:1, flexDirection:'column', display:'flex'}} onTouchTap={this.props.onTouchTap}>
                {mode === 'book' && toolbar}
                <MaterialUI.List style={{flex:1, overflowY:mode !== 'inner' ? 'auto' : 'initial'}}>
                    {this.props.subHeader && <MaterialUI.Subheader>{this.props.subHeader}</MaterialUI.Subheader>}
                    {elements}
                </MaterialUI.List>
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

UsersList = MaterialUI.Style.muiThemeable()(UsersList);

export {UsersList as default}