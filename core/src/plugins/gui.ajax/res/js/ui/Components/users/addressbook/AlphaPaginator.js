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
const {muiThemeable} = require('material-ui/styles')
const {PydioContextConsumer} = require('pydio').requireLib('boot')

/**
 * Simple alphabet generator to give a first-letter-based pagination
 */
class AlphaPaginator extends Component{

    render(){

        let letters = 'abcdefghijklmnopqrstuvwxyz0123456789'.split('');
        letters = [-1, ...letters];
        const {item, paginatorCallback, style, muiTheme, getMessage} = this.props;

        const currentPage = (item.currentParams && item.currentParams.alpha_pages && item.currentParams.value) || -1;

        return (
            <div style={{...style, display:'flex', paddingRight: 8}}>
                <div style={{flex:1}}>{getMessage(249, '')}</div>
                <div>
                {letters.map((l) => {

                    const letterStyle = {
                        display         :'inline-block',
                        cursor          :'pointer',
                        margin          :'0 3px',
                        fontWeight      : 400,
                        textDecoration  :(currentPage===l?'underline':'none'),
                        fontSize        : (currentPage===l?'1.3em':'1em')
                    };

                    return (
                        <span
                            key={l}
                            style={letterStyle}
                            onClick={(e) => {paginatorCallback(l)}}
                            title={l === -1 ? 'Limited number of results': ''}
                        >{l === -1 ? getMessage(597, '') : l}
                        </span>
                    )
                })}
                </div>
            </div>
        );
    }

}

AlphaPaginator.propTypes = {
    /**
     * Currently selected Item
     */
    item            : PropTypes.object,
    /**
     * When a letter is clicked, function(letter)
     */
    paginatorCallback: PropTypes.func.isRequired,
    /**
     * Main instance of pydio
     */
    pydio           : PropTypes.instanceOf(Pydio),
    /**
     * Display mode, either large (book) or small picker ('selector', 'popover').
     */
    mode            : PropTypes.oneOf(['book', 'selector', 'popover']).isRequired,
}


AlphaPaginator = PydioContextConsumer(AlphaPaginator);
AlphaPaginator = muiThemeable()(AlphaPaginator);

export {AlphaPaginator as default}