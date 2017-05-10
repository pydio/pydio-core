import {Component} from 'react'
import Pydio from 'pydio'
import Joyride from 'react-joyride'
const {PydioContextConsumer} = Pydio.requireLib('boot')

class TourGuide extends Component{

    render(){

        const message = (id) => {
            return this.props.getMessage('ajax_gui.tour.locale.' + id);
        }
        const locales = ['back','close','last','next','skip'];
        let locale = {};
        locales.forEach((k) => {
            locale[k] = message(k);
        })
        return (
            <Joyride
                {...this.props}
                locale={locale}
                allowClicksThruHole={true}
            />);

    }

}
TourGuide = PydioContextConsumer(TourGuide);
export {TourGuide as default}