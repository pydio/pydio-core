const WorkspaceRouterWrapper = (pydio) => {

    class WorkspaceRouter extends React.PureComponent {

        _handle({params}) {
            // Making sure we redirect to the right workspace based on initial url
            const slug = params.workspaceId.replace("ws-", "")
            const splat = params.splat || ""
            const repositories = pydio.user ? pydio.user.getRepositoriesList() : new Map()
            const active = pydio.user ? pydio.user.getActiveRepository() : null

            pydio._initLoadRep = "/" + splat
            repositories.forEach((repository) => repository.slug === slug && active !== repository.getId() && pydio.triggerRepositoryChange(repository.getId()))
        }

        componentWillMount() {
            this._handle(this.props)
        }

        componentWillReceiveProps(nextProps) {
            this._handle(nextProps)
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
