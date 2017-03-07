import className from 'classnames';
import debounce from 'lodash.debounce';
import SystemJS from 'systemjs';

let CodeMirror = null

window.define = SystemJS.amdDefine;
window.require = window.requirejs = SystemJS.amdRequire;

SystemJS.config({
    baseURL: 'plugins/editor.codemirror/node_modules',
    packages: {
        'codemirror': {},
        '.': {}
    }
});

function normalizeLineEndings (str) {
	if (!str) return str;
	return str.replace(/\r\n|\r/g, '\n');
}

class Editor extends React.Component {

    constructor(props) {
        super(props)

        let loaded = new Promise((resolve, reject) => {
            SystemJS.import('codemirror/lib/codemirror').then((m) => {
                CodeMirror = m
                SystemJS.import('codemirror/addon/search/search')
                SystemJS.import('codemirror/addon/mode/loadmode').then(() => {
                    SystemJS.import('codemirror/mode/meta').then(() => {
                        CodeMirror.modeURL = 'codemirror/mode/%N/%N.js'
                        resolve()
                    })
                })
            })
        });

        this.state = {
            isFocused: false,
            loaded: loaded
        }
    }

	componentWillMount() {
		this.componentWillReceiveProps = debounce(this.componentWillReceiveProps, 0);
	}

	componentDidMount() {
        const {loaded} = this.state;
        const textareaNode = ReactDOM.findDOMNode(this.refs.textarea);

        loaded.then(() => {
            const info = CodeMirror.findModeByExtension(this.props.name.split('.').pop());
    		const {mode, spec} = info;

    		this.codeMirror = CodeMirror.fromTextArea(textareaNode);

    		this.codeMirror.setOption('mode', mode);
    		this.codeMirror.setOption('readOnly', this.props.options.readOnly);
    		this.codeMirror.setOption('lineNumbers', this.props.options.lineNumbers)
    		this.codeMirror.setOption('lineWrapping', this.props.options.lineWrapping)

            CodeMirror.autoLoadMode(this.codeMirror, mode);

    		this.codeMirror.on('change', this.codemirrorValueChanged.bind(this));
    		this.codeMirror.on('focus', this.focusChanged.bind(this, true));
    		this.codeMirror.on('blur', this.focusChanged.bind(this, false));
    		this.codeMirror.on('scroll', this.scrollChanged.bind(this));
    		this.codeMirror.setValue(this.props.defaultValue || this.props.value || '');
        })
	}

	componentWillUnmount() {
		// is there a lighter-weight way to remove the cm instance?
		if (this.codeMirror) {
			this.codeMirror.toTextArea();
		}
	}

	componentWillReceiveProps(nextProps) {
		if (this.codeMirror && nextProps.value !== undefined && normalizeLineEndings(this.codeMirror.getValue()) !== normalizeLineEndings(nextProps.value)) {
			if (this.props.preserveScrollPosition) {
				var prevScrollPosition = this.codeMirror.getScrollInfo();
				this.codeMirror.setValue(nextProps.value);
				this.codeMirror.scrollTo(prevScrollPosition.left, prevScrollPosition.top);
			} else {
				this.codeMirror.setValue(nextProps.value);
			}
		}

		if (typeof nextProps.options === 'object') {
			for (let optionName in nextProps.options) {
				if (nextProps.options.hasOwnProperty(optionName)) {
                    let optionVal = nextProps.options[optionName]
                    this.codeMirror.setOption(optionName, optionVal);
				}
            }
		}

        if (typeof nextProps.jumpToLine !== 'undefined') {
            let cur = this.codeMirror.getCursor();
            this.codeMirror.setCursor(nextProps.jumpToLine - 1, cur.ch);
            this.codeMirror.focus();
        }

        if (typeof nextProps.actions === 'object') {
            for (let actionName in nextProps.actions) {
                if (nextProps.actions.hasOwnProperty(actionName) && nextProps.actions[actionName]) {
                    if (typeof this.codeMirror[actionName] === "function") {
                        this.codeMirror[actionName]()
                        this.props.onDone()
                    } else {
                        let actionArgs = nextProps.actions[actionName]

                        if (!actionArgs) continue

                        switch (actionName) {
                            case "search": {
                                const {query, pos} = actionArgs

                                let cursor = this.codeMirror.getSearchCursor(query, pos);

                                if (!cursor.find()) {
                                    cursor = this.codeMirror.getSearchCursor(query, CodeMirror.Pos(this.codeMirror.firstLine(), 0));
                                    if (!cursor.find()) return;
                                }

                                this.codeMirror.setSelection(cursor.from(), cursor.to());
                                this.codeMirror.scrollIntoView({from: cursor.from(), to: cursor.to()}, 20);

                                this.props.onFound(cursor.to());

                                break;
                            }
                            case "jump": {

                                const {line} = actionArgs

                                let cur = this.codeMirror.getCursor();
                                this.codeMirror.focus();
                                this.codeMirror.setCursor(line - 1, cur.ch);
                                this.codeMirror.scrollIntoView({line: line - 1, ch: cur.ch}, 20);

                                this.props.onJumped();

                                break;
                            }
                        }
                    }
                }
            }
        }
	}

	focusChanged(focused) {
		this.setState({
			isFocused: focused,
		});
		this.props.onFocusChange && this.props.onFocusChange(focused);
	}

	scrollChanged(cm) {
		this.props.onScroll && this.props.onScroll(cm.getScrollInfo());
	}

	codemirrorValueChanged(doc, change) {
		if (this.props.onChange && change.origin !== 'setValue') {
			this.props.onChange(doc.getValue(), change);
		}
	}

	render() {
		const editorClassName = className(
			'ReactCodeMirror',
			this.state.isFocused ? 'ReactCodeMirror--focused' : null,
			this.props.className
		);

		return (
			<div className={editorClassName} style={{height:"100%", zIndex: 0}}>
				<textarea ref="textarea" defaultValue={this.props.value} autoComplete="off" />
			</div>
		);
	}
}

Editor.propTypes = {
    className: React.PropTypes.any,
    codeMirrorInstance: React.PropTypes.func,
    defaultValue: React.PropTypes.string,
    onChange: React.PropTypes.func,
    onFocusChange: React.PropTypes.func,
    onScroll: React.PropTypes.func,
    options: React.PropTypes.object,
    path: React.PropTypes.string,
    value: React.PropTypes.string,
    preserveScrollPosition: React.PropTypes.bool,
}

Editor.defaultProps = {
	mode: '',
	lineWrapping: false,
	lineNumbers: false,
	readOnly: true,
    preserveScrollPosition: false
}

export default Editor
