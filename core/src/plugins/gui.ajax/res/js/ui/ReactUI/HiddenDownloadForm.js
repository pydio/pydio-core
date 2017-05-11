import React from 'react'

export default class HiddenDownloadForm extends React.Component {

    constructor(props) {
        super(props)

        this.state = {}

        this.configs = pydio.getPluginConfigs('mq');

        this.validateDownload = () => {
            try {
                const iframe = this.iframe.contentDocument || this.iframe.contentWindow.document
            } catch(e) {
                // Setting the BOOSTER DOWNLOAD to off
                this.configs.set("DOWNLOAD_ACTIVE", false)
                this.forceUpdate();
            }
        }
    }

    static get propTypes() {
        return {
            pydio: React.PropTypes.instanceOf(Pydio).isRequired
        }
    }

    componentDidMount() {
        pydio.UI.registerHiddenDownloadForm(this);

        this.iframe.addEventListener("load", this.validateDownload, true)
    }

    componentWillUnmount() {
        pydio.UI.unRegisterHiddenDownloadForm(this);

        this.iframe.removeEventListener("load", this.validateDownload)
    }

    triggerDownload(userSelection, parameters){
        this.setState({
            nodes: userSelection.getSelectedNodes(),
            parameters: parameters
        }, () => {
            this.refs.form.submit();
            this.timeout = setTimeout(() => this.validateDownload(), 1000)
        });
    }

    render() {
        const {nodes, parameters} = this.state

        // Variables to fill
        let url;

        if (this.configs.get("DOWNLOAD_ACTIVE")) {
            let secure = this.configs.get("BOOSTER_MAIN_SECURE");
            if(this.configs.get("BOOSTER_DOWNLOAD_ADVANCED") && this.configs.get("BOOSTER_DOWNLOAD_ADVANCED")['booster_download_advanced'] === 'custom' && this.configs.get("BOOSTER_DOWNLOAD_ADVANCED")['DOWNLOAD_SECURE']){
                secure = this.configs.get("BOOSTER_DOWNLOAD_ADVANCED")['DOWNLOAD_SECURE'];
            }
            let host = this.configs.get("BOOSTER_MAIN_HOST");
            if(this.configs.get("BOOSTER_DOWNLOAD_ADVANCED") && this.configs.get("BOOSTER_DOWNLOAD_ADVANCED")['booster_download_advanced'] === 'custom' && this.configs.get("BOOSTER_DOWNLOAD_ADVANCED")['DOWNLOAD_HOST']){
                host = this.configs.get("BOOSTER_DOWNLOAD_ADVANCED")['DOWNLOAD_HOST'];
            }
            var port = this.configs.get("BOOSTER_MAIN_PORT");
            if(this.configs.get("BOOSTER_DOWNLOAD_ADVANCED") && this.configs.get("BOOSTER_DOWNLOAD_ADVANCED")['booster_download_advanced'] === 'custom' && this.configs.get("BOOSTER_DOWNLOAD_ADVANCED")['DOWNLOAD_PORT']){
                port = this.configs.get("BOOSTER_DOWNLOAD_ADVANCED")['DOWNLOAD_PORT'];
            }

            url = `http${secure?"s":""}://${host}:${port}/${this.configs.get("DOWNLOAD_PATH")}/${pydio.user.activeRepository}/`;
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
