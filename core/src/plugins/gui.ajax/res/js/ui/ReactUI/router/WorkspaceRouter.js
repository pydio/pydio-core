import browserHistory from 'react-router/lib/browserHistory';


const WorkspaceRouterWrapper = (pydio) => {

    class WorkspaceRouter extends React.PureComponent {
        constructor(props) {
            super(props)

            this.state = {
                list: pydio.user.getRepositoriesList(),
                active: pydio.user.getActiveRepository()
            }

            this._wsObs = ({list, active}) => this.setState({list, active})
        }

        componentWillMount() {
            // Before we mount, we check if we need to trigger a redirection
            // based on the url

            const targetSlug = this.props.params.workspaceId.replace("ws-", "")

            const {list, active} = this.state

            // If on load, the url targets a different repository
            // than the one active, we redirect
            let repository = this.findRepositoryBySlug(list, targetSlug)
            let target = repository ? repository.getId() : null;

            if (target !== active) {
                pydio.triggerRepositoryChange(target)
            }
        }

        componentDidMount() {
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
            // We set a new target only if we're browsing back through the history
            if (nextProps.location.action === 'POP') {
                let target = null

                let repository = this.findRepositoryBySlug(this.state.list, nextProps.params.workspaceId.replace('ws-', ''))
                target = repository ? repository.getId() : null;

                pydio.triggerRepositoryChange(target);
            }
        }

        componentWillUpdate(nextProps, nextState) {
            // Only push history if the state changed
            if (this.state !== nextState) {
                // If we've switched repo and this was triggered elsewhere,
                // navigate to new url and record history
                if (this.state.active !== nextState.active) {
                    if (nextProps.location.action === 'POP') {
                        browserHistory.replace("/ws-" + nextState.list.get(nextState.active).getSlug() + "/")
                    } else {
                        browserHistory.push("/ws-" + nextState.list.get(nextState.active).getSlug() + "/")
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

    return WorkspaceRouter;
}

export default WorkspaceRouterWrapper
