class DownloadApp extends React.Component{

    render(){

        const styles = {
            smallIcon: {
                fontSize: 40,
                width: 40,
                height: 40,
            },
            small: {
                width: 80,
                height: 80,
                padding: 20,
            }
        };

        const {pydio, iconClassName, tooltipId, configs, configHref} = this.props;

        return (
            <MaterialUI.IconButton
                iconClassName={iconClassName}
                tooltip={pydio.MessageHash[tooltipId]}
                tooltipStyles={{marginTop: 40}}
                style={styles.small}
                iconStyle={{...styles.smallIcon, color: this.props.iconColor}}
                onTouchTap={() => { window.open(configs.get(configHref)) }}
            />);

    }

}

DownloadApp.propTypes = {
    pydio: React.PropTypes.instanceOf(Pydio),
    id:React.PropTypes.string,
    configs:React.PropTypes.object,
    configHref:React.PropTypes.string,
    iconClassName:React.PropTypes.string,
    iconColor:React.PropTypes.string,
    messageId:React.PropTypes.string,
    tooltipId:React.PropTypes.string
};

export {DownloadApp as default}