import DownloadApp from './DownloadApp'

class DlAppsPanel extends React.Component{

    render(){
        let configs = this.props.pydio.getPluginConfigs('access.ajxp_home');
        let mobileBlocks = [], syncBlocks = [];
        if(configs.get('URL_APP_IOSAPPSTORE')){
            mobileBlocks.push(
                <DownloadApp
                    {...this.props}
                    id="dl_pydio_ios"
                    key="dl_pydio_ios"
                    configs={configs}
                    configHref="URL_APP_IOSAPPSTORE"
                    containerClassName="icon-tablet"
                    iconClassName="icon-apple"
                    messageId="user_home.59"
                    tooltipId="user_home.70"
                />

            );
        }
        if(configs.get('URL_APP_ANDROID')){
            mobileBlocks.push(
                <DownloadApp
                    {...this.props}
                    id="dl_pydio_android"
                    key="dl_pydio_android"
                    configs={configs}
                    configHref="URL_APP_ANDROID"
                    containerClassName="icon-mobile-phone"
                    iconClassName="icon-android"
                    messageId="user_home.58"
                    tooltipId="user_home.71"
                />
            );
        }
        if(configs.get('URL_APP_SYNC_WIN')){
            syncBlocks.push(
                <DownloadApp
                    {...this.props}
                    id="dl_pydio_win"
                    key="dl_pydio_win"
                    configs={configs}
                    configHref="URL_APP_SYNC_WIN"
                    containerClassName="icon-laptop"
                    iconClassName="icon-windows"
                    messageId="user_home.61"
                    tooltipId="user_home.68"
                />
            );
        }
        if(configs.get('URL_APP_SYNC_MAC')){
            syncBlocks.push(
                <DownloadApp
                    {...this.props}
                    id="dl_pydio_mac"
                    key="dl_pydio_mac"
                    configs={configs}
                    configHref="URL_APP_SYNC_MAC"
                    containerClassName="icon-desktop"
                    iconClassName="icon-apple"
                    messageId="user_home.60"
                    tooltipId="user_home.69"
                />
            );
        }

        return (
            <div style={{textAlign: 'center', paddingTop: 5}}>{this.props.type === 'sync' ? syncBlocks : mobileBlocks}</div>
        );
    }

}


const DlAppsCard = React.createClass({
    mixins: [PydioComponents.DynamicGridItemMixin],

    statics:{
        gridWidth:2,
        gridHeight:10,
        builderDisplayName:'Download Applications',
        builderFields:[]
    },

    render: function(){
        let props = {...this.props};
        const style = {
            ...props.style,
            overflow:'visible',
            backgroundColor: this.props.tint,
            color: 'white'
        };
        return (
            <MaterialUI.Paper zDepth={1} {...props}  transitionEnabled={false} style={style}>
                {this.getCloseButton()}
                <DlAppsPanel pydio={this.props.pydio} type="sync" iconColor={style.color}/>
                <div style={{fontSize: 16, padding: 16, paddingTop: 0, textAlign:'center'}}>Keep your files offline with Pydio Desktop Client</div>
            </MaterialUI.Paper>
        );
    }
});

export {DlAppsCard as default}