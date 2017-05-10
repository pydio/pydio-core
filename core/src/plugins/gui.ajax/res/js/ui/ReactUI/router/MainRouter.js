import browserHistory from 'react-router/lib/browserHistory';

const MainRouterWrapper = (pydio) => {

    class MainRouter extends React.PureComponent {

        constructor(props) {
            super(props)

            this.state = this.getState()

            this._ctxObs = (e) => {
                this.setState(this.getState())
            }
        }

        getState() {
            return {
                list: pydio.user ? pydio.user.getRepositoriesList() : new Map(),
                active: pydio.user ? pydio.user.getActiveRepository() : "",
                path: pydio.user ? pydio.getContextNode().getPath() : ""
            }
        }

        getURI({list, active, path}) {
            const repo = list.get(active);
            const slug = repo ? repo.getSlug() : "";
            const prefix = repo && !repo.getAccessType().startsWith("ajxp_") ? "ws-" : ""

            return `/${prefix}${slug}${path}`
        }

        componentDidMount() {
            pydio.getContextHolder().observe("context_changed", this._ctxObs);
        }

        componentWillUnmount() {
            pydio.getContextHolder().stopObserving("context_changed", this._ctxObs);
        }

        componentDidUpdate(prevProps, prevState) {

            if (!pydio.user) {
                return
            }

            if (prevState !== this.state) {
                const uri = this.getURI(this.state)

                if (uri !== "/" + this.props.location.pathname) {
                    browserHistory.push(uri)
                }
            }
        }

        render() {
            return (
                <div>
                    {this.props.children}
                </div>
            );
        }
    };

    return MainRouter;
};

export {MainRouterWrapper as default}
