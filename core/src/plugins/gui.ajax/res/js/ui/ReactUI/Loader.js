const React = require('react')

export default class Loader extends React.Component{

    svgSupport(){
        return !!document.createElementNS && !!document.createElementNS('http://www.w3.org/2000/svg', 'svg').createSVGRect;
    }

    render(){
        const ext = !this.svgSupport() ? 'gif' : 'svg';
        let style = Object.assign({background:'transparent',display:'flex',alignItems:'center',width:'100%',height:'100%'}, this.props.style || {});
        let src = window.pydio.Parameters.get('ajxpResourcesFolder') + '/themes/common/images/loader/hourglass.' + ext;
        return (
            <div style={style}>
                <div style={{background:'transparent',flex:1,textAlign:'center'}}><img src={src}/></div>
            </div>
        );
    }

}