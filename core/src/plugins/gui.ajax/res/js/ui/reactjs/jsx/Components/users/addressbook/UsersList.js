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
        const toolbar = (
            <div style={{padding: 10, height:56, backgroundColor:'#ECEFF1', display:'flex', alignItems:'center'}}>
                {item.actions && item.actions.multiple && <MaterialUI.Checkbox style={{width:'initial', marginLeft: 7}} onCheck={toggleSelect}/>}
                <div style={{flex:1, fontSize:20}}>{item.label}</div>
                {item.actions && item.actions.create && !this.state.select && <MaterialUI.FlatButton secondary={true} label={item.actions.create} onTouchTap={createAction}/>}
                {item.actions && item.actions.remove && this.state.select && <MaterialUI.FlatButton secondary={true} label={item.actions.remove} disabled={!this.state.selection.length} onTouchTap={deleteAction}/>}
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
            let fontIcon = <MaterialUI.Avatar icon={<MaterialUI.FontIcon className={item.icon || 'mdi mdi-account'}/>}/>
            let addGroupButton;
            let touchTap = (e)=>{e.stopPropagation(); this.props.onItemClicked(item)};
            if(folders.indexOf(item) > -1 && this.props.onFolderClicked){
                touchTap = (e)=>{e.stopPropagation(); this.props.onFolderClicked(item) };
                if(!item._notSelectable){
                    addGroupButton = (
                        <MaterialUI.IconButton
                            iconClassName={"mdi " + (mode === 'book' ? "mdi-dots-vertical":"mdi-account-multiple-plus")}
                            tooltip={mode === 'book' ? "Open group / team" : "Add this group / team"}
                            tooltipPosition="bottom-left"
                            onTouchTap={()=>{this.props.onItemClicked(item)}}
                        />
                    );
                }
            }else if(mode === 'inner' && this.props.onDeleteAction){
                addGroupButton = (
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
                rightIconButton={addGroupButton}
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

export {UsersList as default}