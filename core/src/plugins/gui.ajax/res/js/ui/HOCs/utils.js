import * as Actions from '../Workspaces/editor/actions';

export { Actions }

import * as contentActions from './content/actions';
import * as resolutionActions from './resolution/actions';
import * as selectionActions from './selection/actions';
import * as sizeActions from './size/actions';

const defaultActions = {
    ...contentActions,
    ...resolutionActions,
    ...selectionActions,
    ...sizeActions
}

// Helper functions
const getActions = ({editorData}) => (editorData.editorActions && {...defaultActions, ...FuncUtils.getFunctionByName(editorData.editorActions, window)} || {...defaultActions})
export const handler = (func, props) => getActions(props)[func](props)

export const toTitleCase = str => str.replace(/\w\S*/g, (txt) => `${txt.charAt(0).toUpperCase()}${txt.substr(1)}`)

export const getDisplayName = (Component) => {
    return Component.displayName || Component.name || 'Component';
}

export const getRatio = {
    cover: ({widthRatio, heightRatio}) => Math.max(widthRatio, heightRatio),
    contain: ({widthRatio, heightRatio}) => Math.min(widthRatio, heightRatio),
    auto: ({scale}) => scale
}

export const getBoundingRect = (element) => {

    const style = window.getComputedStyle(element);
    const keys = ["left", "right", "top", "bottom"];

    const margin = keys.reduce((current, key) => ({...current, [key]: parseInt(style[`margin-${key}`])}), {})
    const padding = keys.reduce((current, key) => ({...current, [key]: parseInt(style[`padding-${key}`])}), {})
    const border = keys.reduce((current, key) => ({...current, [key]: parseInt(style[`border-${key}`])}), {})

    const rect = element.getBoundingClientRect();

    const res = {
        left: rect.left - margin.left,
        right: rect.right - margin.right - padding.left - padding.right,
        top: rect.top - margin.top,
        bottom: rect.bottom - margin.bottom - padding.top - padding.bottom - border.bottom
    }

    return {
        ...res,
        width: res.right - res.left,
        height: res.bottom - res.top
    }
}
