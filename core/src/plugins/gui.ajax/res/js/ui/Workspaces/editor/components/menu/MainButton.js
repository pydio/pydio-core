import Pydio from 'pydio'
import { connect } from 'react-redux';
import { FloatingActionButton } from 'material-ui';
import makeRotate from './make-rotate';

const { EditorActions } = Pydio.requireLib('hoc');

class Button extends React.Component {

    render() {
        const {rotated} = this.props

        let iconClassName = 'mdi mdi-close'
        if (!rotated) {
            iconClassName = 'mdi mdi-animation'
        }

        return (
            <FloatingActionButton {...this.props} iconClassName={iconClassName}/>
        );
    }
};

const AnimatedButton = makeRotate(Button)

function mapStateToProps(state, ownProps) {
    const { editor } = state

    return  {
        ...editor.menu
    }
}

const ConnectedButton = connect(mapStateToProps, EditorActions)(AnimatedButton)

export default ConnectedButton
