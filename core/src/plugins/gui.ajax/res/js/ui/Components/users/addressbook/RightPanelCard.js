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
        const item = this.props.item || {};
        if(item.type === 'user'){
            content = <UserCard {...this.props}/>
        }else if(item.type === 'group' && item.id.indexOf('/AJXP_TEAM/') === 0){
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