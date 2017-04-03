class NestedListItem extends React.Component{

    onTouchTap(){
        this.props.onTouchTap(this.props.entry);
    }

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
        const {id, label, icon} = this.props.entry;
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
                leftIcon={fontIcon}
                innerDivStyle={{fontWeight:this.props.selected === this.props.entry.id ? 500 : 400}}
            />
        );
    }

}

NestedListItem.propTypes = {
    nestedLevel:React.PropTypes.number,
    selected:React.PropTypes.string,
    onTouchTap: React.PropTypes.func
}

export {NestedListItem as default}