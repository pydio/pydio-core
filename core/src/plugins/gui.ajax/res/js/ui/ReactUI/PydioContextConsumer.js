const React = require('react')
const Pydio = require('pydio')

export default function(PydioComponent){

    class Wrapped extends React.Component{

        render(){
            return <PydioComponent  {...this.context} {...this.props}/>
        }
    }


    Wrapped.displayName = 'PydioContextConsumer'
    Wrapped.contextTypes = {
        pydio:React.PropTypes.instanceOf(Pydio),
        getPydio:React.PropTypes.func,
        messages:React.PropTypes.object,
        getMessage:React.PropTypes.func
    }

    return Wrapped;

}