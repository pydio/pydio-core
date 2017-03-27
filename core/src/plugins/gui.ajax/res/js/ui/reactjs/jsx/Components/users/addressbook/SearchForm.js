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

        return (
            <div style={{flex: 1, display:'flex', flexDirection:'column'}}>
                <div>
                    <MaterialUI.Paper zDepth={1} style={{padding: 10, margin: 10, paddingTop: 0}}>
                        <MaterialUI.TextField
                            fullWidth={true}
                            value={this.state.value}
                            onChange={this.onChange.bind(this)}
                            floatingLabelText={this.props.searchLabel}
                        />
                    </MaterialUI.Paper>
                </div>
                <UsersList onItemClicked={this.props.onItemClicked} item={{leafs: this.state.items}}/>
            </div>
        );

    }

}

SearchForm.propTypes = {
    params: React.PropTypes.object,
    searchLabel: React.PropTypes.string,
    onItemClicked:React.PropTypes.func
};

export {SearchForm as default}