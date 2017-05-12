import Pydio from 'pydio';
import { connect } from 'react-redux';
import MainButton from './MainButton';
import MenuGroup from './MenuGroup';
import MenuItem from './MenuItem';

const { EditorActions } = Pydio.requireLib('hoc');

// Components
class Menu extends React.Component {
    constructor(props) {
        super(props);

        this.state = {
            ready: false
        }

        const {editorModify} = props

        this.toggle = () => editorModify({isMenuActive: !this.props.isActive})
        this.recalculate = this.recalculate.bind(this)
    }

    componentDidMount() {
        window.addEventListener('resize', this.recalculate)
    }

    componentWillUnmount() {
        window.removeEventListener('resize', this.recalculate)
    }


    componentWillReceiveProps(nextProps) {

        if (this.state.ready) return

        const {translated} = nextProps

        if (!translated) return

        this.recalculate()

        this.setState({ready: true})
    }

    recalculate() {
        const {editorModify} = this.props

        const element = ReactDOM.findDOMNode(this.refs.button)

        if (!element) return

        editorModify({
            menu: {
                rect: element.getBoundingClientRect()
            }
        })
    }

    renderChild() {

        const {isActive, tabs} = this.props

        if (!isActive) return null

        return tabs.map((tab) => {
            const style = {
                position: "absolute",
                top: 0,
                left: 0,
                right: 0,
                bottom: 0,
                transition: "transform 0.3s ease-in"
            }

            return <MenuItem key={tab.id} id={tab.id} style={{...style}} />
        })
    }

    render() {
        const {style, isActive} = this.props

        return (
            <div>
                <MenuGroup style={style}>
                    {this.renderChild()}
                </MenuGroup>
                <MainButton ref="button" open={isActive} style={style} onClick={this.toggle} />
            </div>
        );
    }
};

// REDUX - Then connect the redux store
function mapStateToProps(state, ownProps) {
    const { editor, tabs } = state

    const activeTab = tabs.filter(tab => tab.id === editor.activeTabId)[0]

    return  {
        ...editor,
        activeTab: activeTab,
        tabs,
        isActive: editor.isMenuActive
    }
}
const ConnectedMenu = connect(mapStateToProps, EditorActions)(Menu)

// EXPORT
export default ConnectedMenu;
