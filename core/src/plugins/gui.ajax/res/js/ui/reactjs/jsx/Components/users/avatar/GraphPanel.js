import UsersList from '../addressbook/UsersList'

class GraphPanel extends React.Component{

    render(){

        const {graph} = this.props;
        let elements = [];
        if(graph.teams && graph.teams.length){
            const onDeleteAction = function(parentItem, team){
                PydioUsers.Client.removeUserFromTeam(team[0].id, this.props.userId, () => {
                    this.props.reloadAction();
                });
            }.bind(this);
            elements.push(
                <div key="teams">
                    <MaterialUI.Divider/>
                    <UsersList subHeader={"User belongs to " + graph.teams.length +" team(s)."} onItemClicked={()=>{}} item={{leafs: graph.teams}} mode="inner" onDeleteAction={onDeleteAction}/>
                </div>
            )
        }
        if(graph.source && Object.keys(graph.source).length){
            elements.push(
                <div key="source">
                    {elements.length ? <MaterialUI.Divider/> : null}
                    <div style={{padding: 16}}>You have shared  {Object.keys(graph.source).length} item(s) with this user.</div>
                </div>
            )
        }
        if(graph.target && Object.keys(graph.target).length){
            elements.push(
                <div key="target">
                    {elements.length ? <MaterialUI.Divider/> : null}
                    <div style={{padding: 16}}>User has shared  {Object.keys(graph.target).length} item(s) with you.</div>
                </div>
            )
        }
        return <div>{elements}</div>;
    }

}

export {GraphPanel as default}