import React from 'react'
import {AppBar} from 'material-ui'

const ModalAppBar = (props) => {

    let {style, titleStyle, iconStyleRight, iconStyleLeft, ...otherProps} = props;
    const styles = {
        style: {
            flexShrink: 0,
            /*borderRadius: '2px 2px 0 0',*/
            ...style
        },
        titleStyle:{
            lineHeight: '56px',
            height: 56,
            marginLeft: -8,
            ...titleStyle
        },
        iconStyleRight:{
            marginTop: 4,
            ...iconStyleRight
        },
        iconStyleLeft: {
            marginTop: 4,
            ...iconStyleLeft
        }
    };

    return <AppBar {...otherProps} {...styles}/>

}

export {ModalAppBar as default}