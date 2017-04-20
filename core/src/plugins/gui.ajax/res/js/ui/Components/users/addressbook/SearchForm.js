import UsersList from './UsersList'
import Loaders from './Loaders'

class SearchForm extends React.Component{

    constructor(props, context){
        super(props.context);
        this.state = {value: '', items: []};
    }

    search(){
        if(!this.state.value){
            this.setState({items: []});
            return;
        }
        let params = {value: this.state.value, existing_only:'true'};
        if(this.props.params){
            params = {...params, ...this.props.params};
        }
        Loaders.listUsers(params, (children) => {this.setState({items:children})});
    }

    onChange(event, value){
        this.setState({value: value});
        FuncUtils.bufferCallback('search_users_list', 300, this.search.bind(this) );
    }

    render(){

        const {mode, item} = this.props;

        return (
            <div style={{flex: 1, display:'flex', flexDirection:'column'}}>
                <div style={{padding: 10, height:56, backgroundColor:this.state.select?activeTbarColor : '#fafafa', display:'flex', alignItems:'center', transition:DOMUtils.getBeziersTransition()}}>
                    {mode === "selector" && item._parent && <MaterialUI.IconButton iconClassName="mdi mdi-chevron-left" onTouchTap={() => {this.props.onFolderClicked(item._parent)}}/>}
                    {mode === 'book' && <div style={{fontSize:20, color:'rgba(0,0,0,0.87)', flex:1}}>{this.props.title}</div>}
                    <div style={mode === 'book'?{minWidth:320}:{flex:1}}>
                        <MaterialUI.TextField
                            fullWidth={true}
                            value={this.state.value}
                            onChange={this.onChange.bind(this)}
                            hintText={this.props.searchLabel}
                        />
                    </div>
                </div>
                <UsersList
                    mode={this.props.mode}
                    onItemClicked={this.props.onItemClicked}
                    item={{leafs: this.state.items}}
                    noToolbar={true}
                    emptyStatePrimaryText="No results"
                    emptyStateSecondaryText="Start typing in the search form to find users in the local directory"
                />
            </div>
        );

    }

}

SearchForm.propTypes = {
    params: React.PropTypes.object,
    searchLabel: React.PropTypes.string,
    onItemClicked:React.PropTypes.func,
    // Required for navigation
    item: React.PropTypes.object,
    onFolderClicked:React.PropTypes.func
};

export {SearchForm as default}