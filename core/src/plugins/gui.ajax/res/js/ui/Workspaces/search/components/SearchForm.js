import React, {Component} from 'react'

import FilePreview from '../../FilePreview'
import AdvancedSearch from './AdvancedSearch'
import Textfit from 'react-textfit'
import Pydio from 'pydio'
import LangUtils from 'pydio/util/lang'
const {EmptyStateView} = Pydio.requireLib('components')
const {PydioContextConsumer} = require('pydio').requireLib('boot')

import SearchScopeSelector from './SearchScopeSelector'
import MainSearch from './MainSearch'

import {Paper, FlatButton} from 'material-ui';

import _ from 'lodash';

/**
 * Multi-state search component
 */
class SearchForm extends Component {

    constructor(props) {
        super(props)

        // Create Fake DM
        let basicDataModel = new PydioDataModel(true);
        let rNodeProvider = new EmptyNodeProvider();
        basicDataModel.setAjxpNodeProvider(rNodeProvider);
        const rootNode = new AjxpNode("/", false, '', '', rNodeProvider);
        basicDataModel.setRootNode(rootNode);

        this.state = {
            values: {},
            display: 'closed',
            dataModel: basicDataModel,
            empty: true,
            loading: false,
            searchScope: 'folder'
        }

        this.setMode = _.debounce(this.setMode, 250);
        this.update = _.debounce(this.update, 500)
        this.submit = _.debounce(this.submit, 500)
    }

    componentDidUpdate(prevProps, prevState) {
        if(this.refs.results && this.refs.results.refs.list){
            this.refs.results.refs.list.updateInfiniteContainerHeight();
            FuncUtils.bufferCallback('search_results_resize_list', 550, ()=>{
                try{
                    this.refs.results.refs.list.updateInfiniteContainerHeight();
                }catch(e){}
            });
        }
    }

    setMode(mode) {
        if (mode === 'small' && this.state.display !== 'closed') return // we can only set to small when the previous state was closed

        this.setState({
            display: mode
        })
    }

    update(newValues) {
        let values = {
            ...this.state.values,
            ...newValues
        }

        // Removing null values
        Object.keys(values).forEach((key) => (values[key] == null) && delete values[key]);

        this.setState({
            values
        });
    }

    submit() {
        const {display, values, searchScope} = this.state
        const {crossWorkspace} = this.props

        let queryString = ''
        const keys = Object.keys(values);
        if (keys.length === 1 && keys[0] === 'basename') {
            queryString = values['basename'];
        } else {
            queryString = keys.map((k) => k + ':' + values[k]).join(' AND ')
        }

        if (queryString === '') {
            return
        }

        // Refresh data model
        let dmParams = {
            get_action : crossWorkspace || searchScope === 'all' ? 'multisearch' : 'search',
            query: queryString,
            limit: (crossWorkspace  || searchScope === 'all') ? 5 : (display === 'small' ? 9 : 100),
            connexion_discrete: true,
        };
        if(searchScope === 'folder'){
            dmParams.current_dir = this.props.pydio.getContextHolder().getContextNode().getPath();
        }
        const newDM = PydioDataModel.RemoteDataModelFactory(dmParams);
        newDM.getRootNode().observeOnce("loaded", () => {
            this.setState({loading: false});
        });
        this.setState({
            loading     : true,
            dataModel   : newDM,
            empty       : false
        }, () => {
            this.refs.results.reload();
        });
    }


    render() {

        const {crossWorkspace, pydio, getMessage} = this.props;
        const {searchScope, display, loading, dataModel, empty, values} = this.state;

        let renderSecondLine = null, renderIcon = null, elementHeight = 49;
        if (display !== 'small') {
            elementHeight = PydioComponents.SimpleList.HEIGHT_TWO_LINES + 10;
            renderSecondLine = (node) => {
                let path = node.getPath();
                if(searchScope === 'folder'){
                    const crtFolder = pydio.getContextHolder().getContextNode().getPath();
                    if(path.indexOf(crtFolder) === 0){
                        path = './' + LangUtils.trimLeft(path.substr(crtFolder.length), '/');
                    }
                }
                return <div>{path}</div>
            };
            renderIcon = (node, entryProps = {}) => {
                return <FilePreview loadThumbnail={!entryProps['parentIsScrolling']} node={node}/>;
            };
        }

        const nodeClicked = (node)=>{
            pydio.goTo(node);
            this.setMode('closed');
        };

        const searchScopeChanged = (value) =>{
            if(display === 'small') {
                setTimeout(()=>this.setMode('small'), 250);
            }
            this.setState({searchScope:value});
            this.submit();
        };

        let style = this.props.style;
        let zDepth = 2;
        if(display === 'closed'){
            zDepth = 0;
            style = {...style, backgroundColor: 'transparent'};
        }

        return (
            <Paper ref="root" zDepth={zDepth} className={"top_search_form " + display} style={style}>
                <MainSearch
                    mode={display}
                    title={display === 'advanced' ? 'Advanced Search' : null}
                    onOpen={() => this.setMode("small")}
                    showAdvanced={!this.props.crossWorkspace}
                    onAdvanced={() => this.setMode("advanced")}
                    onClose={() => this.setMode("closed")}
                    onMore={() => this.setMode("more")}
                    onChange={(values) => this.update(values)}
                    onSubmit={() => this.submit()}
                    hintText={ getMessage(this.props.crossWorkspace || searchScope === 'all' ? 607 : 87 ) + "..."}
                    loading={loading}
                    scopeSelectorProps={this.props.crossWorkspace ? null : {
                        value:searchScope,
                        onChange:searchScopeChanged
                    }}
                />
                {display === 'advanced' &&
                    <AdvancedSearch
                        {...this.props}
                        value={values.basename}
                        onChange={(values) => this.update(values)}
                        onSubmit={() => this.submit()}
                    />
                }

                <div className="search-results">
                    {empty &&
                        <EmptyStateView
                            iconClassName=""
                            primaryTextId={611}
                            style={{minHeight: 180, backgroundColor: 'transparent'}}
                        />
                    }
                    <PydioComponents.NodeListCustomProvider
                        ref="results"
                        className={display !== 'small' ? 'files-list' : null}
                        elementHeight={elementHeight}
                        entryRenderIcon={renderIcon}
                        entryRenderActions={function() {return null}}
                        entryRenderSecondLine={renderSecondLine}
                        presetDataModel={dataModel}
                        heightAutoWithMax={display === 'small' ? 500  : 412}
                        openCollection={nodeClicked}
                        nodeClicked={nodeClicked}
                        defaultGroupBy={(crossWorkspace || searchScope ==='all') ? 'repository_id' : null }
                        groupByLabel={(crossWorkspace || searchScope ==='all') ? 'repository_display' : null }
                        emptyStateProps={{
                            iconClassName:"",
                            primaryTextId:478,
                            style:{minHeight: (display === 'small' ? 180  : 412), backgroundColor: 'transparent'}
                        }}
                    />

                    {display === 'small' &&
                        <div style={{display:'flex', alignItems:'center', padding:5, paddingLeft: 0, backgroundColor:'#f5f5f5'}}>
                            {!this.props.crossWorkspace &&  <SearchScopeSelector style={{flex: 1, maxWidth:170}} labelStyle={{paddingLeft: 8}} value={searchScope} onChange={searchScopeChanged} onTouchTap={() => this.setMode('small')}/>}
                            <FlatButton style={{marginTop:4}} primary={true} label={getMessage(456)} onFocus={() => this.setMode("small")} onTouchTap={() => this.setMode("more")} onClick={() => this.setMode("more")} />
                        </div>
                    }
                </div>

            </Paper>
        );
    }
}


SearchForm = PydioContextConsumer(SearchForm)
export default SearchForm
