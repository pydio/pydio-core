const React = require('react')
import TeamCard from './TeamCard'
import UserCard from './UserCard'
const {Paper} = require('material-ui')

/**
 * Container for UserCard or TeamCard
 */
class RightPanelCard extends React.Component{

    render(){

        let content;
        if(this.props.item.type === 'user'){
            content = <UserCard {...this.props}/>
        }else if(this.props.item.type === 'group' && this.props.item.id.indexOf('/AJXP_TEAM/') === 0){
            content = <TeamCard {...this.props}/>
        }

        return (
            <Paper zDepth={2} style={{position:'relative', ...this.props.style}}>{content}</Paper>
        );
    }

}

RightPanelCard.propTypes = {
    /**
     * Pydio instance
     */
    pydio: React.PropTypes.instanceOf(Pydio),
    /**
     * Selected item
     */
    item: React.PropTypes.object,
    /**
     * Applies to root container
     */
    style: React.PropTypes.object,
    /**
     * Forwarded to child
     */
    onRequestClose: React.PropTypes.func,
    /**
     * Forwarded to child
     */
    onDeleteAction: React.PropTypes.func,
    /**
     * Forwarded to child
     */
    onUpdateAction: React.PropTypes.func
};

export {RightPanelCard as default}