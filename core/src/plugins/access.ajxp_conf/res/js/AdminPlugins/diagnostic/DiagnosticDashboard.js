const React = require('react')
const {List, ListItem, FlatButton, Paper, Divider} = require('material-ui')
const PydioApi = require('pydio/http/api')
const {Loader, PydioContextConsumer} = require('pydio').requireLib('boot')
const {ClipboardTextField} = require('pydio').requireLib('components')

class DiagnosticDashboard extends React.Component{

    constructor(props, context){
        super(props,context);
        this.state = {loaded: false, entries: {}, copy: false};
    }

    componentDidMount(){
        if(this.state.loaded) return;
        this.setState({loading: true});
        PydioApi.getClient().request({
            get_action:'ls',
            dir: this.props.access || '/admin/diagnostic',
            format: 'json'
        }, (transport) => {
            const resp = transport.responseJSON;
            if(!resp || !resp.children) return;
            this.setState({loaded: true, loading: false, entries: resp.children});
        });
    }

    render(){

        const {entries, loading, copy} = this.state;
        let content, copyPanel, copyContent = '';
        if(loading){
            content = <Loader/>;
        }else{
            let listItems = [];
            Object.keys(entries).forEach((k) => {
                const entry = entries[k];
                let data = entry.data;
                if(typeof data === 'boolean'){
                    data = data ? 'Yes' : 'No';
                }
                listItems.push(<Divider/>);
                listItems.push(
                    <ListItem
                        key={k}
                        primaryText={entry.label}
                        secondaryText={data}
                        disabled={true}

                    />
                );
                copyContent += entry.label + ' : ' + data + '\n';
            });
            content = <List style={{flex: 1, overflowY: 'auto'}}>{listItems}</List>;
        }

        if(copy){
            copyPanel = (
                <Paper zDepth={2} style={{position:'absolute', top: '15%', left: '20%', width: '60%', padding:'20px 20px 0', height:370, overflowY: 'auto', zIndex:2}}>
                    <div style={{fontSize: 20}}>Copy Diagnostic</div>
                    <ClipboardTextField rows={5} rowsMax={10} multiLine={true} inputValue={copyContent} floatingLabelText={this.props.getMessage('5', 'ajxp_conf')} getMessage={this.props.getMessage}/>
                    <div style={{textAlign:'right'}}>
                        <FlatButton label="Close" onTouchTap={() => {this.setState({copy:false})}} secondary={true}/>
                    </div>
                </Paper>
            )
        }

        return (
            <div style={{height: '100%', display:'flex', flexDirection:'column', position:'relative'}}>
                {copyPanel}
                <div style={{display:'flex', alignItems:'center'}}>
                    {this.props.displayMode === 'card' &&
                        <h3 style={{margin: '0 20px 20px', flex:1}}>{this.props.getMessage('5', 'ajxp_conf')}</h3>
                    }
                    {!this.props.displayMode &&
                        <h1 style={{margin: 12, flex:1}}>{this.props.getMessage('5', 'ajxp_conf')}</h1>
                    }
                    <FlatButton label="Copy" onTouchTap={() => {this.setState({copy:true})}} secondary={true} style={{marginRight: 16}}/>
                </div>
                {content}
            </div>
        );

    }

}

DiagnosticDashboard = PydioContextConsumer(DiagnosticDashboard)
export {DiagnosticDashboard as default}