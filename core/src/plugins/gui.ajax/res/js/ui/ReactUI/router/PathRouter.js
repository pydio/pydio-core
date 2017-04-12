import browserHistory from 'react-router/lib/browserHistory';
import _ from 'lodash';

const PathRouterWrapper = (pydio) => {
    class PathRouter extends React.PureComponent {

        _handle({params}) {
            const splat = params.splat || ""
            const path = pydio.getContextNode().getPath()

            if ("/" + splat !== path) {
                pydio.goTo("/" + splat)
            }
        }

        componentWillMount() {
            this._handle(this.props);
        }

        componentWillReceiveProps(nextProps) {
            this._handle(nextProps);
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
