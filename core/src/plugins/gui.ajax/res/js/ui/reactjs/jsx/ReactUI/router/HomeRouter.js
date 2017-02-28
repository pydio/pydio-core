import { browserHistory } from 'react-router';

class HomeRouter extends React.PureComponent {

    constructor(props) {
        super(props)
    }

    componentDidMount() {
        browserHistory.push("/.")
    }

    render() {
        return (
            <div>
                {this.props.children}
            </div>
        );
    }
};

export default HomeRouter
