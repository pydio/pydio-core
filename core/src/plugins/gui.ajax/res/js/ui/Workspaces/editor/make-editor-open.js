import { TransitionMotion, spring, presets } from 'react-motion';

const ANIMATION={stiffness: 200, damping: 22, precision: 1}
const TRANSLATEY_ORIGIN=800
const TRANSLATEY_TARGET=0

const makeEditorOpen = (Target) => {
    return class extends React.Component {
        getStyles() {
            if (!this.props.children) return []

            let counter = 0;

            return React.Children
                .toArray(this.props.children)
                .filter(child => child) // Removing null values
                .map(child => {
                    return {
                        key: `t${counter++}`,
                        data: {element: child},
                        style: {
                            y: spring(TRANSLATEY_TARGET * counter, ANIMATION)
                        }
                    }
                });
        }

        willEnter() {
            return {
                y: TRANSLATEY_ORIGIN
            };
        }

        willLeave() {
            return {
                y: TRANSLATEY_ORIGIN
            }
        }

        render() {
            return (
                <TransitionMotion
                    styles={this.getStyles()}
                    willLeave={this.willLeave}
                    willEnter={this.willEnter}
                    >
                    {styles =>
                        <Target {...this.props}>
                        {styles.map(({key, style, data}) => {
                            // During the transition, we handle the style
                            if (style.y !== TRANSLATEY_TARGET) {

                                // Retrieve previous transform
                                const transform = data.element.props.style.transform || ""

                                return React.cloneElement(data.element, {
                                    key: key,
                                    translated: false,
                                    style: {
                                        ...data.element.props.style,
                                        transition: "none",
                                        transformOrigin: "none",
                                        transform: `${transform} translateY(${style.y}px)`
                                    }
                                })
                            }

                            return React.cloneElement(data.element, {
                                key: key,
                                translated: true
                            })
                        })}
                        </Target>
                    }
                </TransitionMotion>
            );
        }
    }
};

export default makeEditorOpen;
