const {ModalAppBar} = PydioComponents
const {ToolbarGroup, IconButton} = require('material-ui');

// Display components
const EditorToolbar = ({title, className, style, onFullScreen, onMinimise, onClose}) => {

    const innerStyle = {color: "#FFFFFF", fill: "#FFFFFF"}

    return (
        <ModalAppBar
            className={className}
            style={style}
            title={<span>{title}</span>}
            titleStyle={innerStyle}
            iconElementLeft={<IconButton iconClassName="mdi mdi-close" iconStyle={innerStyle} disabled={typeof onClose !== "function"} touch={true} onTouchTap={onClose}/>}
            iconElementRight={
                <ToolbarGroup>
                    <IconButton iconClassName="mdi mdi-window-minimize" iconStyle={innerStyle} disabled={typeof onMinimise !== "function"} touch={true} onTouchTap={onMinimise}/>
                    <IconButton iconClassName="mdi mdi-window-maximize" iconStyle={innerStyle} disabled={typeof onFullScreen !== "function"} touch={true} onTouchTap={onFullScreen}/>
                </ToolbarGroup>
            }
        />
    )
}

export default EditorToolbar
