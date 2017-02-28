import {Router, Route, IndexRoute, browserHistory } from 'react-router';
import WorkspaceRouter from './WorkspaceRouter';
import PathRouter from './PathRouter';
import HomeRouter from './HomeRouter';

class PydioRouter extends React.PureComponent {

    constructor(props) {
        super(props)

        const {pydio} = props
    }

    render() {
        return (
            // Routes are defined as a constant to avoid warning about hot reloading
            <Router history={browserHistory} routes={routes} />
        );
    }
}

const routes = (
    <Route path="/">
        <Route path=":workspaceId" component={WorkspaceRouter}>
            <IndexRoute component={PathRouter} />
            <Route path="*" component={PathRouter} />
        </Route>
    </Route>
)

export default PydioRouter;
