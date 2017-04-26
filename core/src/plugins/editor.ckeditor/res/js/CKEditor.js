import React, {Component} from 'react'

class Editor extends Component {

    static get styles() {
        return {
            textarea: {
                width: "100%"
            }
        }
    }

    componentDidMount() {
        const {pydio, url, content, config, onChange} = this.props
        const {id} = this.textarea

        this.textarea.value = content

        const editor = CKEDITOR.replace(this.textarea, config);

        editor.on('change', ({editor}) => onChange(editor.getData()))
    }

    componentWillUnmount() {
        const {id} = this.textarea

        if(CKEDITOR.instances[id]){
            this.textarea.value = CKEDITOR.instances[id].getData();
            CKEDITOR.instances[id].destroy();
        }
    }

	render() {
        const {url} = this.props
        const {textarea} = Editor.styles

        const id = LangUtils.computeStringSlug(url);

        return (
            <textarea ref={(textarea) => this.textarea = textarea} key={id} style={textarea} />
        )
	}
}

Editor.propTypes = {
    url: React.PropTypes.string.isRequired
}

export default Editor
