const {Component} = require('react');
const {Subheader} = require('material-ui')

export default class Title extends Component{

    render(){
        const propStyle = this.props.style||{};
        const style = {
            paddingLeft: 0,
            fontSize: 16,
//            color: 'rgba(0,0,0,0.43)',
            fontWeight: 400,
            ...propStyle
        }
        return (<h3 style={style}>{this.props.children}</h3>);

    }

}
