import {Component} from 'react'
import Pydio from 'pydio'
import {Paper, FontIcon, TextField, CircularProgress} from 'material-ui'
import {muiThemeable} from 'material-ui/styles'
import PydioDataModel from 'pydio/model/data-model'
import LangUtils from 'pydio/util/lang'
import _ from 'lodash';

const {NodeListCustomProvider, SimpleList} = Pydio.requireLib('components')
const {PydioContextConsumer} = Pydio.requireLib('boot')
const {FilePreview} = Pydio.requireLib('workspaces')

class HomeSearchForm extends Component{

    constructor(props) {
        super(props)

        // Create Fake DM
        this.basicDataModel = new PydioDataModel(true);
        let rNodeProvider = new EmptyNodeProvider();
        this.basicDataModel.setAjxpNodeProvider(rNodeProvider);
        const rootNode = new AjxpNode("/", false, '', '', rNodeProvider);
        this.basicDataModel.setRootNode(rootNode);

        this.state = {
            queryString: '',
            dataModel: this.basicDataModel,
            empty: true,
            loading: false
        };

        this.submit = _.debounce(this.submit, 500)
    }

    update(queryString) {
        this.setState({queryString}, ()=>{this.submit()});
    }

    submit(forceValue = null) {
        let {queryString} = this.state
        if(forceValue) queryString = forceValue;
        if (!queryString) {
            this.setState({empty: true, dataModel: this.basicDataModel});
            return;
        }
        // Refresh data model
        let dmParams = {
            get_action          : 'multisearch',
            query               : queryString,
            limit               : this.props.limit || 5,
            connexion_discrete  : true,
        };
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

    render(){

        const {loading, dataModel, empty, queryString} = this.state;
        const {style, zDepth, pydio, muiTheme} = this.props;
        const hintText = pydio.MessageHash[607];
        const accent2Color = muiTheme.palette.accent2Color;
        const whiteTransp = 'rgba(255,255,255,.63)';
        const white = 'rgb(255,255,255)'

        const styles = {
            textFieldContainer: {
                display:'flex',
                backgroundColor: accent2Color,
                height: 55,
                padding: '4px 8px'
            },
            textField: {flex: 1},
            textInput: {color: white},
            textHint : {color: whiteTransp},
            magnifier: {color: whiteTransp, fontSize: 20, padding:'14px 8px'},
            close: {color: whiteTransp, fontSize: 20, padding:'14px 8px', cursor: 'pointer'}
        }


        const renderIcon = (node, entryProps = {}) => {
            return <FilePreview loadThumbnail={!entryProps['parentIsScrolling']} node={node}/>;
        };
        const renderSecondLine = (node) => {
            let path = node.getPath();
            return <div>{path}</div>
        };
        const renderGroupHeader = (repoId, repoLabel) =>{
            return (
                <div style={{fontSize: 13,color: '#93a8b2',fontWeight: 500, cursor: 'pointer'}} onClick={() => pydio.triggerRepositoryChange(repoId)}>
                    {repoLabel}
                </div>
            );
        };

        return (
            <Paper style={style} zDepth={zDepth} className="vertical-layout">
                <div style={styles.textFieldContainer} className="home-search-form">
                    <FontIcon className="mdi mdi-magnify" style={styles.magnifier}/>
                    <TextField
                        ref={(input) => this.input = input}
                        style={styles.textField}
                        inputStyle={styles.textInput}
                        hintStyle={styles.textHint}
                        fullWidth={true}
                        underlineShow={false}
                        hintText={hintText}
                        value={queryString}
                        onChange={(e,v) => this.update(v)}
                        onKeyPress={(e) => (e.key === 'Enter' ? this.update(e.target.value) : null)}
                    />
                    {loading &&
                        <div style={{marginTop:14, marginRight: 8}} ><CircularProgress size={20} thickness={3}/></div>
                    }
                    {queryString && !loading &&
                        <FontIcon className="mdi mdi-close" style={styles.close} onTouchTap={()=>this.update('')}/>
                    }
                </div>
                {!empty &&
                    <PydioComponents.NodeListCustomProvider
                        ref="results"
                        className={'files-list vertical_fit'}
                        elementHeight={SimpleList.HEIGHT_TWO_LINES}
                        entryRenderIcon={renderIcon}
                        entryRenderActions={function() {return null}}
                        entryRenderSecondLine={renderSecondLine}
                        entryRenderGroupHeader={renderGroupHeader}
                        presetDataModel={dataModel}
                        openCollection={(node) => {pydio.goTo(node)}}
                        nodeClicked={(node) => {pydio.goTo(node)}}
                        defaultGroupBy="repository_id"
                        groupByLabel="repository_display"
                        emptyStateProps={{
                            iconClassName:"",
                            primaryTextId:478,
                            style:{backgroundColor: 'transparent'}
                        }}
                    />
                }
                {empty && this.props.children}
            </Paper>
        );

    }


}

HomeSearchForm = PydioContextConsumer(HomeSearchForm);
HomeSearchForm = muiThemeable()(HomeSearchForm);
export {HomeSearchForm as default}