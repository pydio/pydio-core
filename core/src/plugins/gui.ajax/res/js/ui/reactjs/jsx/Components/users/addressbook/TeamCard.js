import UsersList from './UsersList'
import Loaders from './Loaders'
import ActionsPanel from '../avatar/ActionsPanel'

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
        if(this.state.label !== this.props.item.label){
            PydioUsers.Client.updateTeamLabel(this.props.item.id.replace('/AJXP_TEAM/', ''), this.state.label, () => {
                this.props.onUpdateAction(this.props.item);
            });
        }
        this.setState({editMode: false});
    }
    render(){
        const {item, onDeleteAction, onCreateAction} = this.props;

        const editProps = {
            team: item,
            userEditable: true,
            onDeleteAction: () => {this.props.onDeleteAction(item._parent, [item])},
            onEditAction: () => {this.setState({editMode: !this.state.editMode})},
            reloadAction: () => {this.props.onUpdateAction(item)}
        };

        let title;
        if(this.state.editMode){
            title = (
                <div style={{display:'flex', alignItems:'center', margin: 16}}>
                    <MaterialUI.TextField style={{flex: 1, fontSize: 24}} fullWidth={true} disabled={false} underlineShow={false} value={this.state.label} onChange={this.onLabelChange.bind(this)}/>
                    <MaterialUI.FlatButton secondary={true} label="OK" onTouchTap={() => {this.updateLabel()}}/>
                </div>
            );
        }else{
            title = <MaterialUI.CardTitle title={this.state.label} subtitle={(item.leafs && item.leafs.length ? item.leafs.length + ' team members' : 'No team members')}/>;
        }
        return (
            <div>
                {title}
                <ActionsPanel {...this.props} {...editProps} />
                <MaterialUI.Divider/>
                <UsersList subHeader={"Team Members"} onItemClicked={()=>{}} item={item} mode="inner" onDeleteAction={onDeleteAction}/>
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