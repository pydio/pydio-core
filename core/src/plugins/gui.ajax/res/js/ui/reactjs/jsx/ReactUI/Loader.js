export default React.createClass({

    render(){
        const ext = (Modernizr && !Modernizr.svg) ? 'gif' : 'svg';
        let style = Object.assign({background:'transparent',display:'flex',alignItems:'center',width:'100%',height:'100%'}, this.props.style || {});
        let src = pydio.Parameters.get('ajxpResourcesFolder') + '/themes/common/images/loader/hourglass.' + ext;
        return (
            <div style={style}>
                <div style={{background:'transparent',flex:1,textAlign:'center'}}><img src={src}/></div>
            </div>
        );
    }

});