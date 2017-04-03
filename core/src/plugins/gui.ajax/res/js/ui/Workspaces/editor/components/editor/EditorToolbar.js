/**
 * Copyright (c) 2013-present, Facebook, Inc. All rights reserved.
 *
 * This file provided by Facebook is for non-commercial testing and evaluation
 * purposes only. Facebook reserves all rights not expressly granted.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * FACEBOOK BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
 * ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
const {AppBar, IconButton} = require('material-ui');

// Display components
const EditorToolbar = ({title, className, style, onMinimise, onClose}) => {

    const innerStyle = {color: "#000000", fill: "#000000"}
    const outerStyle = {background: "none"}

    return (
        <AppBar
            className={className}
            style={{...style, ...outerStyle}}
            title={<span>{title}</span>}
            titleStyle={innerStyle}
            iconElementLeft={<IconButton iconClassName="mdi mdi-close" iconStyle={innerStyle} disabled={typeof onClose !== "function"} touch={true} onTouchTap={onClose}/>}
            iconElementRight={<IconButton iconClassName="mdi mdi-window-minimize" iconStyle={innerStyle} disabled={typeof onMinimise !== "function"} touch={true} onTouchTap={onMinimise}/>}
        />
    )
}

export default EditorToolbar
