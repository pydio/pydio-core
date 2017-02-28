import {Router, Route, browserHistory } from 'react-router';

class HomeRouter extends React.PureComponent {

    constructor(props) {
        super(props)

        //this.onChange = (workspaceId) => props.route.onChange(workspaceId)
        this.state = {}
    }

    componentWillReceiveProps(nextProps) {
        this.setState({
            path: nextProps.params.path
        })
    }

    shouldComponentUpdate(nextProps, nextState, nextContent) {
        if (nextState.path !== this.state.path) {
            return true
        }

        return false
    }

    componentWillUpdate(nextProps, nextState) {
        this.props.route.onChange(nextState.path);
    }

    render() {
        console.log("Path routing")
        return null;
    }
};

export default HomeRouter
