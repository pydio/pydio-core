import _ from 'lodash';
import { Motion, spring, presets } from 'react-motion';

const ANIMATION={stiffness: 300, damping: 40}
const ORIGIN=0
const TARGET=100

const makeEditorMinimise = (Target) => {
    return class extends React.Component {
        constructor(props) {
            super(props);
            this.state = {};
        }

        componentWillReceiveProps(nextProps) {
            this.setState({
                minimised: nextProps.minimised
            })
        }

        render() {
            const {minimised} = this.state

            const motionStyle = {
                scale: minimised ? spring(ORIGIN, ANIMATION) : TARGET
            };

            const transform = this.props.style.transform || ""

            return (
                <Motion style={motionStyle} onRest={this.props.onMinimise} >
                    {({scale}) => {
                        let float = scale / 100

                        return (
                            <Target
                                {...this.props}
                                scale={scale}
                                style={{
                                    ...this.props.style,
                                    transition: "none",
                                    transform: `${transform} scale(${float})`
                                }}
                            />
                        )
                    }}
                </Motion>
            );
        }
    }
};

export default makeEditorMinimise;
