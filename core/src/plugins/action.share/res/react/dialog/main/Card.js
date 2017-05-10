const {Component} = require('react')
const {Paper} = require('material-ui')

class Card extends Component{

    render(){

        const style = {
            panel: {
                padding: 16,
                margin: 10,
                ...this.props.style
            },
            title: {
                paddingTop: 0,
                fontSize: 18,
                ...this.props.titleStyle
            }
        }

        return (
            <Paper zDepth={1} rounded={false} style={style.panel}>
                {this.props.title &&
                    <h3  style={style.title}>{this.props.title}</h3>
                }
                {this.props.children}
                {this.props.actions &&
                    <div style={{textAlign: 'center', clear: 'both', position: 'relative', padding: '10px 0'}}>
                        {this.props.actions}
                    </div>
                }
            </Paper>
        )

    }

}

export {Card as default}