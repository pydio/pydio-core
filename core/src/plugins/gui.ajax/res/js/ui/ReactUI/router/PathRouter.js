import browserHistory from 'react-router/lib/browserHistory';

const PathRouterWrapper = function(pydio){
    
    class PathRouter extends React.PureComponent {

        constructor(props) {
            super(props)

            let target = props.params.splat

            this.state = {
                init: false
            }

            this._ctxObs = function(event) {
                let active = pydio.getContextNode().getPath();
                if (!this.state.init) {
                    target = props.params.splat;
                } else {
                    target = null;
                }

                this.setState({
                    init:     true,
                    history:  true,
                    active:   active,
                    target:   target
                })
            }.bind(this);
            // Watching all path changes
            pydio.getContextHolder().observe("context_changed", this._ctxObs);
        }

        componentWillUnmount(){
            pydio.getContextHolder().stopObserving("context_changed", this._ctxObs);
        }

        //
        componentWillReceiveProps(nextProps) {
            if (!this.state.init || !this.state.history) return

            // We set a new target only if we're browsing back through the history
            if (nextProps.location.action === 'POP') {
                let target = nextProps.params.splat || ""

                // We have a new target path
                this.setState({
                    target: target
                })
            }
        }

        componentWillUpdate(nextProps, nextState) {

            // We ignore props changes
            if (!nextState.init || !nextState.history || nextState === this.state) return

            // If on load, the url targets a different repository
            // than the one active, we redirect
            if (nextState.target || nextState.target === "") {
                if (nextState.target !== nextState.active) {
                    // We don't want history to be touched if we've triggered ourselves the repository change
                    this.setState({
                        history: false
                    }, () => {
                        pydio.goTo("/" + nextState.target);
                    })

                }
            } else {
                // If we've switched repo and this was triggered elsewhere,
                // navigate to new url and record history
                if (this.state.history && this.state.active !== nextState.active) {
                    let uri = [
                        nextProps.params.workspaceId.replace(/\/$/, "").replace(/^\//, ""),
                        nextState.active.replace(/\/$/, "").replace(/^\//, "")
                    ].join("/");
                    browserHistory.push("/" + uri)
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
