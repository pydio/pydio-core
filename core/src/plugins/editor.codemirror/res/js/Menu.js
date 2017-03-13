/*
  * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
  * This file is part of Pydio.
  *
  * Pydio is free software: you can redistribute it and/or modify
  * it under the terms of the GNU Affero General Public License as published by
  * the Free Software Foundation, either version 3 of the License, or
  * (at your option) any later version.
  *
  * Pydio is distributed in the hope that it will be useful,
  * but WITHOUT ANY WARRANTY; without even the implied warranty of
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  * GNU Affero General Public License for more details.
  *
  * You should have received a copy of the GNU Affero General Public License
  * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
  *
  * The latest code can be found at <https://pydio.com>.
  */

class MenuOptions extends React.Component {

     constructor(props) {
         super(props)

         const {modified} = props

         this.state = {
             undoable: false,
             redoable: false
         }

         this.props.codemirror.clearHistory()
     }

     componentWillReceiveProps(nextProps) {
         const {undo, redo} = nextProps.codemirror.historySize()

         this.setState({
             undoable: undo,
             redoable: redo
         })
     }

     toggleLineNumbers() {
         this.props.codemirror.setOption("lineNumbers", !this.props.codemirror.getOption("lineNumbers"))
     }

     toggleLineWrapping() {
         this.props.codemirror.setOption("lineWrapping", !this.props.codemirror.getOption("lineWrapping"))
     }

     render() {
         const {MessageHash} = this.props.pydio

         if (!this.props.codemirror) return null

         return (
             <MaterialUI.ToolbarGroup {...this.props}>
                <MaterialUI.IconButton disabled={!this.state.undoable} iconClassName="mdi mdi-content-save" tooltipPosition="bottom-right" tooltip={MessageHash[53]} onClick={this.props.onSave} />
                <MaterialUI.IconButton disabled={!this.state.undoable} iconClassName="mdi mdi-undo" tooltipPosition="bottom-right" tooltip={MessageHash["code_mirror.7"]} onClick={this.props.codemirror.undo.bind(this.props.codemirror)}/>
                <MaterialUI.IconButton disabled={!this.state.redoable} iconClassName="mdi mdi-redo" tooltipPosition="bottom-right" tooltip={MessageHash["code_mirror.8"]} onClick={this.props.codemirror.redo.bind(this.props.codemirror)}/>

                <MaterialUI.IconButton iconClassName="mdi mdi-format-list-numbers" tooltipPosition="bottom-right" tooltip={MessageHash["code_mirror.5"]} onClick={this.toggleLineNumbers.bind(this)} />
                <MaterialUI.IconButton iconClassName="mdi mdi-wrap" tooltipPosition="bottom-right" tooltip={MessageHash["code_mirror.3b"]} onClick={this.toggleLineWrapping.bind(this)} />
            </MaterialUI.ToolbarGroup>
        )
     }
}

MenuOptions.propTypes = {
    onSave: React.PropTypes.func.isRequired,
    onUndo: React.PropTypes.func.isRequired,
    onRedo: React.PropTypes.func.isRequired,
    onToggleLineNumbers: React.PropTypes.func.isRequired,
    onToggleTextWrap: React.PropTypes.func.isRequired
}

class MenuActions extends React.Component {
    constructor(props) {
        super(props)

        // Handling actions
        this.onJumpTo = this.onJumpTo.bind(this)
        this.onFind = this.onFind.bind(this)
    }

    onJumpTo(e) {
        switch (e.key) {
            case 'Enter':
                this.jumpTo(parseInt(e.target.value))
                break
        }
    }

    onFind(e) {
        switch (e.key) {
            case 'Enter':
                this.find(parseQuery(e.target.value))
                break
        }
    }

    find(query) {
        let cursor = this.props.codemirror.getSearchCursor(query, this.props.cursor.to);

        if (!cursor.find()) {
            cursor = this.props.codemirror.getSearchCursor(query, 0);
            if (!cursor.find()) return;
        }

        this.props.codemirror.setSelection(cursor.from(), cursor.to());
        this.props.codemirror.scrollIntoView({from: cursor.from(), to: cursor.to()}, 20);
    }

    jumpTo(line) {
        let cur = this.props.codemirror.getCursor();

        this.props.codemirror.focus();
        this.props.codemirror.setCursor(line - 1, cur.ch);
        this.props.codemirror.scrollIntoView({line: line - 1, ch: cur.ch}, 20);
    }

    render() {
        const {MessageHash} = this.props.pydio

        return (
            <MaterialUI.ToolbarGroup key="right" lastChild={true}>
                <MaterialUI.TextField hintText={MessageHash["code_mirror.6"]} onKeyUp={this.onJumpTo} />
                <MaterialUI.TextField hintText={MessageHash["code_mirror.9"]} onKeyUp={this.onFind}/>
            </MaterialUI.ToolbarGroup>
        )
    }
}

MenuActions.propTypes = {
    pydio: React.PropTypes.instanceOf(Pydio).isRequired,

    onJumpTo: React.PropTypes.func.isRequired,
    onFind: React.PropTypes.func.isRequired,
}

function parseString(string) {
    return string.replace(/\\(.)/g, function(_, ch) {
      if (ch == "n") return "\n"
      if (ch == "r") return "\r"
      return ch
    })
  }

function parseQuery(query) {
    var isRE = query.match(/^\/(.*)\/([a-z]*)$/);
    if (isRE) {
        try {
            query = new RegExp(isRE[1], isRE[2].indexOf("i") == -1 ? "" : "i");
        } catch(e) {

        } // Not a regular expression after all, do a string search
    } else {
        query = parseString(query)
    }
    if (typeof query == "string" ? query == "" : query.test("")) query = /x^/;

    return query;
}

export {
    MenuOptions,
    MenuActions
}
