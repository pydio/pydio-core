import { Motion, spring, presets } from 'react-motion';

const ANIMATION={stifness: 500, damping: 20}
const ORIGIN = -720
const TARGET = 0

const makeRotate = (Target) => {
    return class extends React.Component {
        constructor(props) {
            super(props);
            this.state = {
                rotate: false
            };
        }

        componentWillReceiveProps(nextProps) {
            this.setState({
                rotate: nextProps.open
            })
        }

        render() {
            const style = {
                rotate: this.state.rotate ? ORIGIN : TARGET
            };
            return (
                <Motion style={style}>
                    {({rotate}) => {
                        let rotated = rotate === ORIGIN

                        return (
                            <Target
                                {...this.props}
                                rotated={rotated}
                                style={{
                                    ...this.props.style,
                                    transform: `${this.props.style.transform} rotate(${rotate}deg)`
                                }}
                            />
                        )
                    }}
                </Motion>
            );
        }
    }
};

export default makeRotate;
