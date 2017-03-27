import TeamCard from './TeamCard'
import UserCard from './UserCard'

class RightPanelCard extends React.Component{

    render(){

        let content;
        if(this.props.item.type === 'user'){
            content = <UserCard {...this.props}/>
        }else if(this.props.item.type === 'group' && this.props.item.id.indexOf('/AJXP_TEAM/') === 0){
            content = <TeamCard {...this.props}/>
        }

        return (
            <MaterialUI.Paper zDepth={2} style={{position:'relative', ...this.props.style}}>{content}</MaterialUI.Paper>
        );
    }

}

RightPanelCard.propTypes = {
    pydio: React.PropTypes.instanceOf(Pydio),
    item: React.PropTypes.object,
    style: React.PropTypes.object,
    onRequestClose: React.PropTypes.func,
    onDeleteAction: React.PropTypes.func,
    onUpdateAction: React.PropTypes.func
};

export {RightPanelCard as default}