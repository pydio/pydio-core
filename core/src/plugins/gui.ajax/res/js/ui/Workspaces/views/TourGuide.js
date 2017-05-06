import {Component} from 'react'
import Joyride from 'react-joyride'

class TourGuide extends Component{

    render(){
        const locale = { back: 'Back', close: 'Close', last: 'Finish', next: 'Next', skip: 'Skip' };

        return (
            <Joyride
                {...this.props}
                locale={locale}
                allowClicksThruHole={true}
            />);

    }

}

export {TourGuide as default}