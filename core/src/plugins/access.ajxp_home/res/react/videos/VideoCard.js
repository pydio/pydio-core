import VideoPlayer from './VideoPlayer'
import Palette from '../board/Palette'
import ColorPaper from '../board/ColorPaper'

const VideoCard = React.createClass({

    mixins: [PydioComponents.DynamicGridItemMixin],

    statics:{
        gridWidth:2,
        gridHeight:12,
        builderDisplayName:'Video Tutorial',
        builderFields:[]
    },

    propTypes:{
        youtubeId           : React.PropTypes.string,
        contentMessageId    : React.PropTypes.string
    },

    getInitialState: function(){
        this._videos = [
            ['qvsSeLXr-T4', 'user_home.63'],
            ['HViCWPpyZ6k', 'user_home.79'],
            ['jBRNqwannJM', 'user_home.80'],
            ['2jl1EsML5v8', 'user_home.81'],
            ['28-t4dvhE6c', 'user_home.82'],
            ['fP0MVejnVZE', 'user_home.83'],
            ['TXFz4w4trlQ', 'user_home.84'],
            ['OjHtgnL_L7Y', 'user_home.85'],
            ['ot2Nq-RAnYE', 'user_home.66']
        ];
        const k = Math.floor(Math.random() * this._videos.length);
        const value = this._videos[k];
        return {
            videoIndex      : k,
            youtubeId       : value[0],
            contentMessageId: value[1]
        };
    },

    launchVideo: function(){
        const url = "//www.youtube.com/embed/"+this.state.youtubeId+"?list=PLxzQJCqzktEbYm3U_O1EqFru0LsEFBca5&autoplay=1";
        this._videoDiv = document.createElement('div');
        document.body.appendChild(this._videoDiv);
        ReactDOM.render(<VideoPlayer videoSrc={url} closePlayer={this.closePlayer}/>, this._videoDiv);
    },

    closePlayer: function(){
        ReactDOM.unmountComponentAtNode(this._videoDiv);
        document.body.removeChild(this._videoDiv);
    },

    getTitle: function(messId){
        const text = this.props.pydio.MessageHash[messId];
        return text.split('\n').shift().replace('<h2>', '').replace('</h2>', '');
    },

    browse: function(direction = 'next', event){
        let nextIndex;
        const {videoIndex} = this.state;
        if(direction === 'next'){
            nextIndex = videoIndex < this._videos.length -1  ? videoIndex + 1 : 0;
        }else{
            nextIndex = videoIndex > 0  ? videoIndex - 1 : this._videos.length - 1;
        }
        const value = this._videos[nextIndex];
        this.setState({
            videoIndex      : nextIndex,
            youtubeId       : value[0],
            contentMessageId: value[1]
        });
    },

    render: function(){
        const MessageHash = this.props.pydio.MessageHash;
        const htmlMessage = function(id){
            return {__html:MessageHash[id]};
        };
        const menus = this._videos.map(function(item, index){
            return <MaterialUI.MenuItem primaryText={this.getTitle(item[1])} onTouchTap={() => {this.setState({youtubeId:item[0], contentMessageId:item[1], videoIndex: index})} }/>;
        }.bind(this));
        let props = {...this.props};
        const {youtubeId, contentMessageId} = this.state;
        props.className += ' video-card';

        const TMP_VIEW_MORE = (
            <a className="tutorial_more_videos_button" href="https://www.youtube.com/channel/UCNEMnabbk64csjA_qolXvPA" target="_blank" dangerouslySetInnerHTML={htmlMessage('user_home.65')}/>
        );
        const tint = MaterialUI.Color(Palette[3]).alpha(0.8);
        return (
            <ColorPaper {...props} paletteIndex={3} getCloseButton={this.getCloseButton.bind(this)}>
                <div className="tutorial_legend">
                    <div className="tutorial_video_thumb" style={{backgroundImage:'url("https://img.youtube.com/vi/'+youtubeId+'/0.jpg")'}}>
                        <div style={{position:'absolute', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: tint}}/>
                        <div className="tutorial_prev mdi mdi-arrow-left" onClick={this.browse.bind(this, 'previous')}/>
                        <div className="tutorial_play mdi mdi-play" onClick={this.launchVideo}/>
                        <div className="tutorial_next mdi mdi-arrow-right" onClick={this.browse.bind(this, 'next')}/>
                        <div className="tutorial_title">
                            <span dangerouslySetInnerHTML={htmlMessage(contentMessageId)}/>
                            <MaterialUI.IconMenu
                                style={{position: 'absolute', bottom: 0, right: 0, backgroundColor: 'rgba(0,0,0,0.43)', padding: 2, borderRadius: '0 0 2px 0'}}
                                iconStyle={{color:'white'}}
                                iconButtonElement={<MaterialUI.IconButton iconClassName="mdi mdi-dots-vertical"/>}
                                anchorOrigin={{horizontal: 'left', vertical: 'top'}}
                                targetOrigin={{horizontal: 'left', vertical: 'top'}}
                            >{menus}</MaterialUI.IconMenu>
                        </div>
                    </div>
                </div>
            </ColorPaper>
        );
    }
});

export {VideoCard as default}