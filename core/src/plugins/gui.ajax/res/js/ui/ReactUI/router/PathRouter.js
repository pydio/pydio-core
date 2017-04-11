import browserHistory from 'react-router/lib/browserHistory';
import _ from 'lodash';

const PathRouterWrapper = (pydio) => {

    class PathRouter extends React.PureComponent {

        constructor(props) {
            super(props)

            this.state = {
                active: pydio.getContextNode().getPath()
            }

            this._ctxObs = (e) => this.setState({active: pydio.getContextNode().getPath()})
        }

        componentWillMount() {
            const target = this.props.params.splat

            const active = this.state;

            if (target && target !== active) {
                pydio.goTo("/" + target);
            }
        }

        componentDidMount() {
            pydio.getContextHolder().observe("context_changed", this._ctxObs);
        }

        componentWillUnmount(){
            pydio.getContextHolder().stopObserving("context_changed", this._ctxObs);
        }

        //
        componentWillReceiveProps(nextProps) {
            // We set a new target only if we're browsing back through the history
            if (nextProps.location.action === 'POP') {
                const target = nextProps.params.splat

                if (target) {
                    pydio.goTo("/" + target);
                }
            }
        }

        componentWillUpdate(nextProps, nextState) {

            // If we've switched repo and this was triggered elsewhere,
            // navigate to new url and record history
            if (this.state != nextState) {
                let uri = [
                    nextProps.params.workspaceId.replace(/\/$/, "").replace(/^\//, ""),
                    nextState.active.replace(/\/$/, "").replace(/^\//, "")
                ].join("/");

                if (this.state.active !== nextState.active) {
                    if (nextProps.location.action === 'POP') {
                        browserHistory.replace("/" + uri)
                    } else {
                        browserHistory.push("/" + uri)
                    }
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

    return PathRouter;
};

export {PathRouterWrapper as default}
