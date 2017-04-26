const {Component, PropTypes} = require('react')

class CSSBlurBackground extends Component{

    constructor(props, context){
        super(props, context);
        this.state = {};
    }

    componentDidMount(){
        this.activateResizeObserver();
    }

    componentWillUnmount(){
        this.deactivateResizeObserver();
    }

    activateResizeObserver(){
        if(this._resizeObserver) return;
        this._resizeObserver = () => {this.computeBackgroundData()};
        DOMUtils.observeWindowResize(this._resizeObserver);
        this.computeBackgroundData();
    }

    deactivateResizeObserver(){
        if(this._resizeObserver){
            DOMUtils.stopObservingWindowResize(this._resizeObserver);
            this._resizeObserver = null;
        }
    }

    computeBackgroundData(){

        const pydioMainElement = document.getElementById(window.pydio.Parameters.get('MAIN_ELEMENT'));
        const reference = pydioMainElement.querySelector('div[data-reactroot]');
        if(!reference){
            return;
        }
        if(this.backgroundImageData){
            this.computeRatio();
            return;
        }

        const url = window.getComputedStyle(reference).getPropertyValue('background-image');
        let backgroundImage = new Image();
        backgroundImage.src = url.replace(/"/g,"").replace(/url\(|\)$/ig, "");

        let oThis = this;
        backgroundImage.onload = function() {
            const width = this.width;
            const height = this.height;

            oThis.backgroundImageData = {
                url: url,
                width: width,
                height: height
            };

            oThis.computeRatio();

        };
    }

    computeRatio(){

        const {width, height, url} = this.backgroundImageData;

        const screenWidth = DOMUtils.getViewportWidth();
        const screenHeight = DOMUtils.getViewportHeight();

        const imageRatio = width/height;
        const coverRatio = screenWidth/screenHeight;

        let coverHeight, scale, coverWidth;
        if (imageRatio >= coverRatio) {
            coverHeight = screenHeight;
            scale = (coverHeight / height);
            coverWidth = width * scale;
        } else {
            coverWidth = screenWidth;
            scale = (coverWidth / width);
            coverHeight = height * scale;
        }
        let cover = coverWidth + 'px ' + coverHeight + 'px';
        this.setState({
            backgroundImage: url,
            backgroundSize: cover
        });

    }


    render(){

        const {backgroundImage, backgroundSize} = this.state;
        if(!backgroundImage) return null;
        return(
            <style dangerouslySetInnerHTML={{
                __html: [
                    '.react-mui-context div[data-reactroot].dialogRootBlur > div > div.dialogRootBlur:before {',
                    '  background-image: '+backgroundImage+';',
                    '  background-size: '+backgroundSize+';',
                    '}'
                ].join('\n')
            }}>
            </style>
        );

    }

}

export {CSSBlurBackground as default}