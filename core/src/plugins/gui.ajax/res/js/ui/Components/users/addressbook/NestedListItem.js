const {Component, PropTypes} = require('react')

/**
 * Left panel of the address book
 * Display treeview hierarchy of users, teams, groups.
 */
class NestedListItem extends Component{

    /**
     * Triggers this.props.onTouchTap
     */
    onTouchTap(){
        this.props.onTouchTap(this.props.entry);
    }

    /**
     * Recursively build other NestedListItem
     * @param data
     */
    buildNestedItems(data){
        return data.map(function(entry){
            return (
                <NestedListItem
                    nestedLevel={this.props.nestedLevel+1}
                    entry={entry}
                    onTouchTap={this.props.onTouchTap}
                    selected={this.props.selected}
                />);
        }.bind(this));
    }

    render(){
        const {id, label, icon, selected} = this.props.entry;
        const children = this.props.entry.collections || [];
        const nested = this.buildNestedItems(children);
        let fontIcon;
        if(icon){
            fontIcon = <MaterialUI.FontIcon className={icon}/>;
        }
        return (
            <MaterialUI.ListItem
                nestedLevel={this.props.nestedLevel}
                key={id}
                primaryText={label}
                onTouchTap={this.onTouchTap.bind(this)}
                nestedItems={nested}
                initiallyOpen={true}
                leftIcon={false && fontIcon}
                innerDivStyle={{fontWeight:this.props.selected === this.props.entry.id ? 500 : 400}}
            />
        );
    }

}

NestedListItem.propTypes = {
    /**
     * Keeps track of the current depth level
     */
    nestedLevel:PropTypes.number,
    /**
     * Currently selected node id
     */
    selected:PropTypes.string,
    /**
     * Callback triggered when an entry is selected
     */
    onTouchTap:PropTypes.func
}

export {NestedListItem as default}