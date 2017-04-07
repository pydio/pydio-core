const React = require('react');
import ShareContextConsumer from '../ShareContextConsumer'
const {RaisedButton, TextField, Paper, IconButton} = require('material-ui')
const ShareModel = require('pydio').requireLib('ReactModelShare')

class TargetedUsers extends React.Component{

    constructor(props, context){
        super(props, context);
        this.state = {open: false};
    }

    render(){
        const {target_users} = this.props.linkData;
        let items = Object.keys(target_users).map((k) => {
            const userData = target_users[k];
            const title = this.props.linkData.public_link + '?u=' + k;
            return (
                <div style={{display:'flex'}}>
                    <div style={{flex: 1}} >
                        {userData.display}
                        <IconButton
                            iconClassName="mdi mdi-link"
                            tooltip={title}
                            iconStyle={{fontSize: 13, lineHeight:'17px'}} style={{width:34, height: 34, padding:6}}/>
                    </div>
                    <div style={{width: 40, textAlign:'center'}}>{userData.download_count}</div>
                </div>
            );
        });
        if(!items.length) return null;

        const rootStyle = {
            lineHeight: '34px',
            padding: '4px 10px 4px',
            fontSize: 14,
            backgroundColor: '#fafafa',
            borderRadius: 2
        };
        const headerStyle = {
            borderBottom: this.state.open ?  '1px solid #757575' : '',
            color: 'rgba(0, 0, 0, 0.36)'
        }

        return (
            <div style={rootStyle}>
                <div style={{display:'flex', ...headerStyle}}>
                    <div style={{flex: 1}} >{items.length} personal invitation(s) sent <span className={'mdi mdi-chevron-' + (this.state.open ? 'up' : 'down')} style={{cursor:'pointer'}} onClick={() => {this.setState({open:!this.state.open})}}/></div>
                    {this.state.open && <div style={{width: 40, textAlign:'center'}}>#DL</div>}
                </div>
                {this.state.open &&
                    <div>{items}</div>
                }
            </div>
        );

    }

}

TargetedUsers.propTypes = {

    linkData:React.PropTypes.object.isRequired,
    shareModel: React.PropTypes.instanceOf(ShareModel)

}

TargetedUsers = ShareContextConsumer(TargetedUsers);

export {TargetedUsers as default}