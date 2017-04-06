const {Component} = require('react');
const {Subheader} = require('material-ui')

export default class Title extends Component{

    render(){
        const propStyle = this.props.style||{};
        const style = {
            paddingLeft: 0,
            ...propStyle
        }
        return (<Subheader style={style}>{this.props.children}</Subheader>);

    }

}
