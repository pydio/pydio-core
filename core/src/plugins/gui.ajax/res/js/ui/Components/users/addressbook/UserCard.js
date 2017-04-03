import UserCreationForm from '../UserCreationForm'
import UserAvatar from '../avatar/UserAvatar'

class UserCard extends React.Component{

    constructor(props, context){
        super(props, context);
        this.state = {editForm: false};
    }


    render(){

        const {item} = this.props;
        let editableProps = {}, editForm;
        if(item._parent.id === 'ext'){
            editableProps = {
                userEditable: true,
                onDeleteAction: () => {this.props.onDeleteAction(item._parent, [item])},
                onEditAction: () => {this.setState({editForm: true})},
                reloadAction: () => {this.props.onUpdateAction(item)}
            };
        }

        if(this.state.editForm){
            editForm = (
                <UserCreationForm
                    pydio={this.props.pydio}
                    zDepth={0}
                    style={{height:500}}
                    newUserName={item.id}
                    editMode={true}
                    userData={item}
                    onUserCreated={() => {this.props.onUpdateAction(item); this.setState({editForm:false}) }}
                    onCancel={() => {this.setState({editForm:false})}}
                />
            );
        }

        return (
            <div>
                <UserAvatar
                    userId={this.props.item.id}
                    richCard={true}
                    pydio={this.props.pydio}
                    cardSize={this.props.style.width}
                    {...editableProps}
                >{editForm}</UserAvatar>
            </div>
        );
    }

}

UserCard.propTypes = {
    pydio: React.PropTypes.instanceOf(Pydio),
    item: React.PropTypes.object,
    style: React.PropTypes.object,
    onRequestClose: React.PropTypes.func,
    onDeleteAction: React.PropTypes.func,
    onUpdateAction: React.PropTypes.func
};


export {UserCard as default}