import React from 'react'
import {RaisedButton} from 'material-ui'

const Dashboard = React.createClass({

    mixins:[AdminComponents.MessagesConsumerMixin],

    keys: {
        'date':{label:'Date', message:'17'},
        'ip':{label:'IP', message:'18'},
        'level':{label:'Level', message:'19'},
        'user':{label:'User', message:'20'},
        'action':{label:'Action', message:'21'},
        'source':{label:'Source', message:'22'},
        'params':{label:'More Info', message:'22a'}
    },

    componentDidMount: function(){
        Object.keys(this.keys).map(function(k){
            this.keys[k]['label'] = this.context.getMessage(this.keys[k]['message'], 'ajxp_conf');
        }.bind(this));
    },

    getInitialState:function() {
        return {
            currentDate:new Date(),
            currentNode:this.dateToLogNode(new Date())
        };
    },

    openLogDate:function(event, jsDate){
        this.setState({
            currentDate:jsDate,
            currentNode:this.dateToLogNode(jsDate)
        });
    },

    nodeSelected:function(node){
        this.setState({selectedLog:node},function(){this.refs.dialog.show();}.bind(this));
        return false;
    },

    clearNodeSelected: function(){
        this.setState({selectedLog:null}, function(){this.refs.dialog.dismiss();}.bind(this));
    },

    renderActions:function(node){
        return null;
    },

    dateToLogNode: function(date){
        var dateY = date.getFullYear();
        var dateM = date.getMonth() + 1;
        var dateD = date.getDate();
        var path = "/admin/logs/"+dateY+"/"+dateM+"/"+dateY+"-"+dateM+"-"+dateD;
        return new AjxpNode(path);
    },

    currentIsToday: function(){
        var d = new Date();
        var c = this.state.currentDate;
        return (d.getFullYear() == c.getFullYear() && d.getMonth() == c.getMonth() && d.getDate() == c.getDate());
    },

    changeFilter: function(event){
        var keys = this.keys;
        var filter = event.target.value.toLowerCase();
        if(!filter){
            this.setState({filterNodes:function(node){return true;}});
        }else{
            this.setState({filterNodes:function(node){
                var res = false;
                for(var k in keys){
                    if(keys.hasOwnProperty(k)){
                        var val = node.getMetadata().get(k);
                        if(val && val.toLowerCase().indexOf(filter) !== -1) res = true;
                    }
                }
                return res;
            }});
        }
    },

    openExporter: function(){
        this.props.pydio.UI.openComponentInModal('EnterpriseComponents', 'LogsExporter');
    },

    render:function(){
        var maxDate = new Date();
        var dialogButtons =  [
            {text:this.context.getMessage('48', ''), onClick:this.clearNodeSelected}
        ];
        var dialogContent;
        if(this.state.selectedLog){
            var items = Object.keys(this.keys).map(function(k){
                var value = this.state.selectedLog.getMetadata().get(k);
                var label = this.context.getMessage(this.keys[k].message, 'ajxp_conf');
                return (
                    <div className="log-detail">
                        <div className="log-detail-label">{label}</div>
                        <div className="log-detail-value">{value}</div>
                    </div>
                );
            }.bind(this));
            dialogContent = <div>{items}</div>;
        }
        let exportButton = (<RaisedButton label={this.context.getMessage("logs.11")} onTouchTap={this.openExporter}/>);
        if(!ResourcesManager.moduleIsAvailable('EnterpriseComponents')){
            exportButton = (<RaisedButton label={this.context.getMessage("logs.11")} disabled={true}/>);
        }

        return (
            <div className="vertical-layout logs-dashboard" style={{height:'100%'}}>
                <ReactMUI.Dialog
                    ref="dialog"
                    title={this.context.getMessage('logs.5')}
                    actions={dialogButtons}
                    contentClassName="dialog-max-480"
                >
                    {dialogContent}
                </ReactMUI.Dialog>
                <div>
                    <div style={{float:'right', padding:'28px 10px 0'}}>
                        {exportButton}
                    </div>
                    <div className="logger-filterInput">
                        <ReactMUI.TextField
                            onChange={this.changeFilter}
                            floatingLabelText={this.context.getMessage('logs.3')}
                        />
                    </div>
                    <div className="logger-dateInput">
                        <div className="datepicker-legend">{this.context.getMessage('logs.2')}</div>
                        <ReactMUI.DatePicker
                            ref="logDate"
                            onChange={this.openLogDate}
                            key="start"
                            autoOk={true}
                            maxDate={maxDate}
                            defaultDate={this.state.currentDate}
                            showYearSelector={true} />
                    </div>
                    <h1 className="admin-panel-title">{this.context.getMessage('logs.1')} <span style={{fontSize: 13, letterSpacing: 0, fontStyle:'italic', lineHeight: 'initial'}}>{this.context.getMessage('logs.4')}</span></h1>

                </div>
                <PydioComponents.SimpleList
                    node={this.state.currentNode}
                    dataModel={this.props.dataModel}
                    className="logs-list layout-fill"
                    actionBarGroups={[]}
                    infineSliceCount={1000}
                    tableKeys={this.keys}
                    entryRenderActions={this.renderActions}
                    filterNodes={this.state.filterNodes}
                    autoRefresh={this.currentIsToday() ? 10000 : null}
                    reloadAtCursor={true}
                    openEditor={this.nodeSelected}
                    elementHeight={{
                        "max-width:480px":201,
                        "(min-width:480px) and (max-width:760px)":80,
                        "min-width:760px":PydioComponents.SimpleList.HEIGHT_ONE_LINE
                    }}
                />
            </div>
        );
    }

});

export {Dashboard as default}