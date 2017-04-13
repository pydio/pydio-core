const {Component} = require('react')
const {IconButton, FlatButton, Paper} = require('material-ui')

const UP_ARROW = 'mdi mdi-menu-up';
const DOWN_ARROW = 'mdi mdi-menu-down';
const REMOVE = 'mdi mdi-delete-circle';

import FormPanel from './FormPanel'

class ReplicatedGroup extends Component{

    constructor(props, context){
        super(props, context);
        const {subValues, parameters} = props;
        const firstParam = parameters[0];
        const instanceValue = subValues[firstParam['name']] || '';
        this.state = {toggled: instanceValue ? false : true};
    }

    render(){

        const {depth, onSwapUp, onSwapDown, onRemove, parameters, subValues} = this.props;
        const {toggled} = this.state;
        const firstParam = parameters[0];
        const instanceValue = subValues[firstParam['name']] || <span style={{color: 'rgba(0,0,0,0.33)'}}>Empty Value</span>;

        return (
            <Paper style={{marginBottom: 10}}>
                <div style={{display:'flex', alignItems: 'center'}}>
                    <div>{<IconButton iconClassName={'mdi mdi-chevron-' + (this.state.toggled ? 'up' : 'down')} onTouchTap={()=>{this.setState({toggled:!this.state.toggled})}}/>}</div>
                    <div style={{flex: 1, fontSize:16}}>{instanceValue}</div>
                    <div>
                        <IconButton iconClassName={UP_ARROW} onTouchTap={onSwapUp} disabled={!!!onSwapUp}/>
                        <IconButton iconClassName={DOWN_ARROW} onTouchTap={onSwapDown} disabled={!!!onSwapDown}/>
                    </div>
                </div>
                {toggled &&
                    <FormPanel
                        {...this.props}
                        tabs={null}
                        values={subValues}
                        onChange={null}
                        className="replicable-group"
                        depth={depth}
                    />
                }
                {toggled &&
                    <div style={{padding: 4, textAlign: 'right'}}>
                        <FlatButton label="Remove" primary={true} onTouchTap={onRemove} disabled={!!!onRemove}/>
                    </div>
                }
            </Paper>
        );


    }

}

export {ReplicatedGroup as default}