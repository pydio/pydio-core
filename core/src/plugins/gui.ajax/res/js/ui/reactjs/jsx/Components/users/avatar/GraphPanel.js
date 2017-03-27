class GraphPanel extends React.Component{

    render(){

        const {graph} = this.props;
        let elements = [];
        if(graph.teams && graph.teams.length){
            elements.push(
                <div key="teams">
                    {elements.length ? <MaterialUI.Divider/> : null}
                    <div style={{padding: 16}}>User belongs to {graph.teams.length} team(s).</div>
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