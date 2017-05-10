class VideoPlayer extends React.Component{

    render(){
        return (
            <div className="video-player" style={{position:'absolute', top:0, left:0, right:0, bottom:0, zIndex:200000}}>
                <div className="overlay" style={{position:'absolute', top:0, left:0, right:0, bottom:0, backgroundColor:'black', opacity:0.4}} onClick={this.props.closePlayer}></div>
                <div style={{position:'absolute', top:'10%', left:'10%', width:'80%', height:'80%', minWidth:420, minHeight: 600, boxShadow:'rgba(0, 0, 0, 0.156863) 0px 3px 10px, rgba(0, 0, 0, 0.227451) 0px 3px 10px'}}>
                    <iframe src={this.props.videoSrc} style={{width:'100%', height:'100%', border:0}}/>
                </div>
                <a className="mdi mdi-close" style={{position:'absolute', right:'8%', top:'7%', color:'white', textDecoration:'none', fontSize:24}} onClick={this.props.closePlayer}/>
            </div>
        );
    }
}

VideoPlayer.propTypes = {
    videoSrc:React.PropTypes.string,
    closePlayer:React.PropTypes.func
};

export {VideoPlayer as default}