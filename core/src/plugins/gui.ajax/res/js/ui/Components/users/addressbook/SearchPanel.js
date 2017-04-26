const {Component, PropTypes} = require('react')

import SearchForm from './SearchForm'
import UsersList from './UsersList'
import Loaders from './Loaders'

/**
 * Ready to use Form + Result List for search users
 */
class SearchPanel extends Component{

    constructor(props, context){
        super(props.context);
        this.state = {items: []};
    }

    onSearch(value){
        if(!value){
            this.setState({items: []});
            return;
        }
        let params = {value: value, existing_only:'true'};
        if(this.props.params){
            params = {...params, ...this.props.params};
        }
        Loaders.listUsers(params, (children) => {this.setState({items:children})});
    }

    render(){

        const {mode, item} = this.props;

        return (
            <div style={{flex: 1, display:'flex', flexDirection:'column'}}>
                <div style={{padding: 10, height:56, backgroundColor:this.state.select?activeTbarColor : '#fafafa', display:'flex', alignItems:'center', transition:DOMUtils.getBeziersTransition()}}>
                    {mode === "selector" && item._parent && <MaterialUI.IconButton iconClassName="mdi mdi-chevron-left" onTouchTap={() => {this.props.onFolderClicked(item._parent)}}/>}
                    {mode === 'book' && <div style={{fontSize:20, color:'rgba(0,0,0,0.87)', flex:1}}>{this.props.title}</div>}
                    <SearchForm style={mode === 'book'?{minWidth:320}:{flex:1}} searchLabel={this.props.searchLabel} onSearch={this.onSearch.bind(this)}/>
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

SearchPanel.propTypes = {
    /**
     * Optional parameters added to listUsers() request
     */
    params          : PropTypes.object,
    /**
     * Label displayed in the toolbar
     */
    searchLabel     : PropTypes.string,
    /**
     * Callback triggered when a search result is clicked
     */
    onItemClicked   : PropTypes.func,
    /**
     * Currently selected item, required for navigation
     */
    item            : PropTypes.object,
    /**
     * Callback triggered if the result is a collection
     */
    onFolderClicked : PropTypes.func
};

export {SearchPanel as default}