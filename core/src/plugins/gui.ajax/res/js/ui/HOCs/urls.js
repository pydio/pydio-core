import React, {Component} from 'react';

import {toTitleCase} from './utils'

export const URLProvider = (urls = []) => {
    return class extends React.Component {
        static get displayName() {
            return `URLProvider`
        }

        static get propTypes() {
            return urls.reduce((current, type) => ({
                    ...current,
                    [`on${toTitleCase(type)}`]: React.PropTypes.func.isRequired
                }), {
                    urlType: React.PropTypes.oneOf(urls).isRequired,
                })
        }

        constructor(props) {
            super(props)

            this.state = {
                url: ""
            }
        }

        componentWillReceiveProps(nextProps) {
            const fn =  nextProps[`on${toTitleCase(nextProps.urlType)}`]

            this.setState({
                url: fn()
            })
        }

        render() {
            return this.props.children(this.state.url)
        }
    }
}
