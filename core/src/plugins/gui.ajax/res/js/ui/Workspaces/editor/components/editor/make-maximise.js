import _ from 'lodash';
import { Motion, spring, presets } from 'react-motion';

import {getDisplayName} from '../../../../HOCs/utils';

const ANIMATION={stiffness: 400, damping: 30}
const TARGET=100

const makeMaximise = (Target) => {
    return class extends React.Component {
        constructor(props) {
            super(props);
            this.state = {maximised: props.maximised};
        }

        componentWillReceiveProps(nextProps) {
            this.setState({
                maximised: nextProps.maximised
            })
        }

        static get displayName() {
            return `MakeMaximise(${getDisplayName(Target)})`
        }

        render() {
            const {maximised} = this.state
            const motionStyle = {
                width: maximised ? spring(TARGET, ANIMATION) : spring(parseInt(this.props.style.width.replace(/%$/, '')), ANIMATION),
                height: maximised ? spring(TARGET, ANIMATION) : spring(parseInt(this.props.style.height.replace(/%$/, '')), ANIMATION)
            };

            let {style} = this.props || {style: {}}

            return (
                <Motion style={motionStyle}>
                    {({width, height}) => {
                        return (
                            <Target
                                {...this.props}
                                style={{
                                    ...this.props.style,
                                    width: `${width}%`,
                                    height: `${height}%`,
                                    transition: "none"
                                }}
                            />
                        )
                    }}
                </Motion>
            );
        }
    }
};

export default makeMaximise;
