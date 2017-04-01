import UsersList from '../addressbook/UsersList'
const {Divider} = require('material-ui')
const {UsersApi} = require('pydio/http/users-api')

class GraphPanel extends React.Component{

    render(){

        const {graph, userLabel, pydio} = this.props;
        let elements = [];
        if(graph.teams && graph.teams.length){
            const onDeleteAction = function(parentItem, team){
                UsersApi.removeUserFromTeam(team[0].id, this.props.userId, (response) => {
                    if(response.message) pydio.UI.displayMessage('SUCCESS', response.message);
                    this.props.reloadAction();
                });
            }.bind(this);
            elements.push(
                <div key="teams">
                    <Divider/>
                    <UsersList subHeader={"User belongs to " + graph.teams.length +" team(s)."} onItemClicked={()=>{}} item={{leafs: graph.teams}} mode="inner" onDeleteAction={onDeleteAction}/>
                </div>
            )
        }
        if(graph.source && Object.keys(graph.source).length){
            elements.push(
                <div key="source">
                    {elements.length ? <Divider/> : null}
                    <div style={{padding: 16}}>{userLabel} has shared  {Object.keys(graph.source).length} item(s) with you.</div>
                </div>
            )
        }
        if(graph.target && Object.keys(graph.target).length){
            elements.push(
                <div key="target">
                    {elements.length ? <Divider/> : null}
                    <div style={{padding: 16}}>You have shared  {Object.keys(graph.target).length} item(s) with {userLabel}.</div>
                </div>
            )
        }
        return <div>{elements}</div>;
    }

}

export {GraphPanel as default}