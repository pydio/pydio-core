class SelectionModel extends Observable {

    constructor(node, filter = () => true, buildSelection = null{
        super();
        this.currentNode = node;
        this.filter = filter
        this.selection = [];
        this.buildSelection = buildSelection;
    }

    buildSelection() {
        let currentIndex;
        let child;
        let it = this.currentNode.getParent().getChildren().values();
        while (child = it.next()) {
            if (child.done) break;
            let node = child.value;
            if (this.filter(node)) {
                this.selection.push(node);
                if (node === this.currentNode) {
                    this.currentIndex = this.selection.length - 1;
                }
            }
        }
    }

    length(){
        return this.selection.length;
    }

    hasNext(){
        return this.currentIndex < this.selection.length - 1;
    }

    hasPrevious(){
        return this.currentIndex > 0;
    }

    current(){
        return this.selection[this.currentIndex];
    }

    next(){
        if(this.hasNext()){
            this.currentIndex ++;
        }
        return this.current();
    }

    previous(){
        if(this.hasPrevious()){
            this.currentIndex --;
        }
        return this.current();
    }

    first(){
        return this.selection[0];
    }

    last(){
        return this.selection[this.selection.length -1];
    }

    nextOrFirst(){
        if(this.hasNext()) this.currentIndex ++;
        else this.currentIndex = 0;
        return this.current();
    }
}

export default SelectionModel
