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
                url: this.getUrl(props)
            }
        }

        componentWillReceiveProps(nextProps) {
            this.setState({
                url: this.getUrl(nextProps)
            })
        }

        getUrl(props) {
            const fn =  props[`on${toTitleCase(props.urlType)}`]

            return fn()
        }

        render() {
            return this.props.children(this.state.url)
        }
    }
}
