import React from 'react'

export default class HiddenDownloadForm extends React.Component {

    constructor(props) {
        super(props)

        this.state = {}
    }

    static get propTypes() {
        return {
            pydio: React.PropTypes.instanceOf(Pydio).isRequired
        }
    }

    componentDidMount() {
        pydio.UI.registerHiddenDownloadForm(this);

        // console.log("Added event listener", this.iframe)
        this.iframe.addEventListener("abort", (args) => console.log("Abort", args, this.iframe), true )
        this.iframe.addEventListener("load", (args) => console.log("Load", args, this.iframe), true )
        this.iframe.addEventListener("error", (args) => console.log("Error", args, this.iframe), true )
    }

    componentWillUnmount() {
        pydio.UI.unRegisterHiddenDownloadForm(this);
    }

    triggerDownload(userSelection, parameters){
        this.setState({
            nodes: userSelection.getSelectedNodes(),
            parameters: parameters
        }, () => {
            this.refs.form.submit();
        });
    }

    render() {
        const {nodes, parameters} = this.state

        // Variables to fill
        let url;

        const configs = pydio.getPluginConfigs('mq');
        
        if (configs.get("DOWNLOAD_ACTIVE")) {
            let secure = configs.get("BOOSTER_MAIN_SECURE");
            if(configs.get("BOOSTER_DOWNLOAD_ADVANCED") && configs.get("BOOSTER_DOWNLOAD_ADVANCED")['booster_download_advanced'] === 'custom' && configs.get("BOOSTER_DOWNLOAD_ADVANCED")['DOWNLOAD_SECURE']){
                secure = configs.get("BOOSTER_DOWNLOAD_ADVANCED")['DOWNLOAD_SECURE'];
            }
            let host = configs.get("BOOSTER_MAIN_HOST");
            if(configs.get("BOOSTER_DOWNLOAD_ADVANCED") && configs.get("BOOSTER_DOWNLOAD_ADVANCED")['booster_download_advanced'] === 'custom' && configs.get("BOOSTER_DOWNLOAD_ADVANCED")['DOWNLOAD_HOST']){
                host = configs.get("BOOSTER_DOWNLOAD_ADVANCED")['DOWNLOAD_HOST'];
            }
            var port = configs.get("BOOSTER_MAIN_PORT");
            if(configs.get("BOOSTER_DOWNLOAD_ADVANCED") && configs.get("BOOSTER_DOWNLOAD_ADVANCED")['booster_download_advanced'] === 'custom' && configs.get("BOOSTER_DOWNLOAD_ADVANCED")['DOWNLOAD_PORT']){
                port = configs.get("BOOSTER_DOWNLOAD_ADVANCED")['DOWNLOAD_PORT'];
            }

            url = `http${secure?"s":""}://${host}:${port}/${configs.get("DOWNLOAD_PATH")}/${pydio.user.activeRepository}/`;
        } else {
            url = pydio.Parameters.get('ajxpServerAccess')
        }

        return (
            <div style={{visibility:'hidden', position:'absolute', left: -10000}}>
                <form ref="form" action={url} target="dl_form_iframe">

                    {parameters && Object.keys(parameters).map(key =>
                        <input type="hidden" name={key} key={key} value={parameters[key]}/>
                    )}

                    {nodes && nodes.map(node =>
                        <input type="hidden" name="nodes[]" key={node.getPath()} value={node.getPath()}/>
                    )}
                </form>
                <iframe ref={(iframe) => this.iframe = iframe} name="dl_form_iframe"></iframe>
            </div>
        );
    }
}
