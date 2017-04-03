import browserHistory from 'react-router/lib/browserHistory';


const WorkspaceRouterWrapper = function(pydio) {

    class WorkspaceRouter extends React.PureComponent {

        constructor(props) {
            super(props)

            let targetSlug = props.params.workspaceId.replace("ws-", "")

            this.state = {
                init: false,
                history: true
            }

            this._wsObs = function (event) {
                const {list, active} = event;

                // Upon initialisation, we set a target repository that comes from the url
                // The active repository is coming from the server and might not
                // reflect what we actually want
                let target = null
                if (!this.state.init) {
                    let repository = this.findRepositoryBySlug(list, targetSlug)
                    target = repository ? repository.getId() : null;
                }

                this.setState({
                    init: true,
                    history: true,
                    list: list,
                    active: active,
                    target: target
                })
            }.bind(this);
            // Watching all repository changes
            pydio.observe("repository_list_refreshed", this._wsObs);
        }

        componentWillUnmount() {
            pydio.stopObserving("repository_list_refreshed", this._wsObs);
        }

        // Util function to find a repository by its slug from a list of reposiitories
        findRepositoryBySlug(repositories, repositoryId) {
            let ret = null

            if (!repositories) return null

            repositories.forEach(function (repository) {
                if (repository.slug === repositoryId) {
                    ret = repository
                }
            }, this)

            return ret
        }

        //
        componentWillReceiveProps(nextProps) {

            if (!this.state.init || !this.state.history) return

            // We set a new target only if we're browsing back through the history
            if (nextProps.location.action === 'POP') {
                let target = null

                let repository = this.findRepositoryBySlug(this.state.list, nextProps.params.workspaceId.replace('ws-', ''))
                target = repository ? repository.getId() : null;

                // We have a new target repository
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
            if (nextState.target) {
                if (nextState.target !== nextState.active) {
                    // We don't want history to be touched if we've triggered ourselves the repository change
                    this.setState({
                        history: false
                    })

                    pydio.triggerRepositoryChange(nextState.target);
                }
            } else if (nextState.list && nextState.active) {
                // If we've switched repo and this was triggered elsewhere,
                // navigate to new url and record history
                if (this.state.history && this.state.active !== nextState.active) {
                    browserHistory.push("/ws-" + nextState.list.get(nextState.active).getSlug() + "/")
                }
            } else {
                browserHistory.push("/");
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

    return WorkspaceRouter;

}

export default WorkspaceRouterWrapper
