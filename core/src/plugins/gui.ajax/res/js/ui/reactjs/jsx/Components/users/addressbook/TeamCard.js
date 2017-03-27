import UsersList from './UsersList'
import Loaders from './Loaders'

class TeamCard extends React.Component{

    constructor(props, context){
        super(props, context);
        this.state = {label: this.props.item.label};
    }

    loadMembers(item){
        this.setState({loading: true});
        Loaders.childrenAsPromise(item, false).then((children) => {
            Loaders.childrenAsPromise(item, true).then((children) => {
                this.setState({members:item.leafs, loading: false});
            });
        });
    }
    componentWillMount(){
        this.loadMembers(this.props.item);
    }
    componentWillReceiveProps(nextProps){
        this.loadMembers(nextProps.item);
        this.setState({label: nextProps.item.label});
    }
    onLabelChange(e, value){
        this.setState({label: value});
    }
    updateLabel(){
        PydioUsers.Client.updateTeamLabel(this.props.item.id.replace('/AJXP_TEAM/', ''), this.state.label, () => {
            this.props.onUpdateAction(this.props.item);
        });
    }
    deleteTeam(){
        const {item, onDeleteAction} = this.props;
        onDeleteAction(item._parent, [item]);
    }
    render(){
        const {item, onDeleteAction, onCreateAction} = this.props;
        const membersSummary = <div style={{margin:10}}>{item.leafs? "Currently " + (item.leafs.length) + " members. Open the team in the main panel to add or remove users." : ""}</div>;
        const membersList = <UsersList onItemClicked={()=>{}} item={item} mode="inner" onDeleteAction={onDeleteAction}/>
        const createButton = <MaterialUI.IconButton iconClassName="mdi mdi-plus" onTouchTap={() => onCreateAction(item)} style={{position:'absolute', right:6, top:-14}}/>
        return (
            <div>
                <MaterialUI.TextField style={{margin:'0 10px'}} fullWidth={true} disabled={false} underlineShow={false} floatingLabelText="Label" value={this.state.label} onChange={this.onLabelChange.bind(this)}/>
                <MaterialUI.Divider/>
                <MaterialUI.TextField style={{margin:'0 10px'}} fullWidth={true} disabled={true} underlineShow={false} floatingLabelText="Id" value={item.id.replace('/AJXP_TEAM/', '')}/>
                <MaterialUI.Divider/>
                <div style={{position:'relative'}}>
                    {createButton}
                    <div style={{margin:'16px 10px 0', transform: 'scale(0.75)', transformOrigin: 'left', color: 'rgba(0,0,0,0.33)'}}>Team Members</div>
                </div>
                {membersList}
                <MaterialUI.Divider/>
                {this.props.onDeleteAction &&
                <div style={{margin:10, textAlign:'right'}}>
                    <MaterialUI.FlatButton secondary={false} label="Remove Team" onTouchTap={() => {onDeleteAction(item._parent, [item])}}/>
                    {
                        this.props.item.label !== this.state.label &&
                        <MaterialUI.FlatButton secondary={true} label="Update" onTouchTap={() => {this.updateLabel()}}/>
                    }
                </div>
                }
            </div>
        )
    }

}

TeamCard.propTypes = {
    pydio: React.PropTypes.instanceOf(Pydio),
    item: React.PropTypes.object,
    style: React.PropTypes.object,
    onRequestClose: React.PropTypes.func,
    onDeleteAction: React.PropTypes.func,
    onUpdateAction: React.PropTypes.func
};


export {TeamCard as default}