/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <https://pydio.com>.
 */


const {Component, PropTypes} = require('react')

/**
 * Ready to use Form + Result List for search users
 */
class SearchForm extends Component{

    constructor(props, context){
        super(props.context);
        this.state = {value: ''};
    }

    search(){
        this.props.onSearch(this.state.value);
    }

    onChange(event, value){
        this.setState({value: value});
        FuncUtils.bufferCallback('search_users_list', 300, this.search.bind(this) );
    }

    render(){

        return (
            <div style={{minWidth:320, ...this.props.style}}>
                <MaterialUI.TextField
                    fullWidth={true}
                    value={this.state.value}
                    onChange={this.onChange.bind(this)}
                    hintText={this.props.searchLabel}
                />
            </div>
        );

    }

}

SearchForm.propTypes = {
    /**
     * Label displayed in the search field
     */
    searchLabel     : PropTypes.string.isRequired,
    /**
     * Callback triggered to search
     */
    onSearch        : PropTypes.func.isRequired,
    /**
     * Will be appended to the root element
     */
    style           : PropTypes.object
};

export {SearchForm as default}