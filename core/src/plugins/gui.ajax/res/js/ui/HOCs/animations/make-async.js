import React from 'react';

let counter=0

const makeAsync = (WrappedComponent) => {
    return class AsyncGroup extends React.PureComponent {
        constructor(props) {
            super(props)

            this.state = {
                ...this.buildPromises(props)
            }
        }

        componentDidMount() {
            this.waitAndSee()
        }

        waitAndSee() {
            Promise.all(this.state.promises).then((values) => this.setState({ready: true}))
        }

        buildPromises(props) {
            let onloads = []
            let promises = React.Children
                .toArray(props.children)
                .filter(child => child)
                .map(child => new Promise((resolve, reject) => {
                    if (typeof child.props.onLoad !== "function") return resolve()

                    let timeout = setTimeout(resolve, 3000);

                    onloads.push(() => {
                        window.clearTimeout(timeout)

                        child.props.onLoad()

                        setTimeout(resolve, 1000)
                    })
                }));

            return {
                promises,
                onloads,
                ready: false
            }
        }

        render() {
            const {...props} = this.props

            //console.log("Make Async", this.state.ready)
             //, {onLoad: this.state.onloads[i]}))}

            return (
                <WrappedComponent {...props} ready={this.state.ready}>
                    {React.Children.toArray(props.children).filter(child => child).map((Child, i) => React.cloneElement(Child, {onLoad: () => {}}))}
                </WrappedComponent>
            );
        }
    }
};

export default makeAsync;
