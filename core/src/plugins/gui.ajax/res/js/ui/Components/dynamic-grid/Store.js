class Store extends Observable{

    /**
     * Init a card store
     * @param prefNamespace Namespace for getting/setting user preferences
     * @param defaultCards Array of cards to be displayed by default
     */
    constructor(prefNamespace, defaultCards, pydioObject){
        super();
        this._namespace = prefNamespace;
        this._pydio = pydioObject;
        this._cards = this.getUserPreference("Cards");
        if(!this._cards){
            this._cards = defaultCards;
        }
    }

    getUserPreference(prefName){
        var prefKey = this._namespace + prefName;
        var guiPrefs = this._pydio.user.getPreference('gui_preferences', true);
        if(guiPrefs && guiPrefs[prefKey]){
            return guiPrefs[prefKey];
        }else{
            return null;
        }
    }

    saveUserPreference(prefName, prefValue){
        var prefKey = this._namespace + prefName;
        var guiPrefs = this._pydio.user.getPreference('gui_preferences', true);
        if(!guiPrefs) guiPrefs = {};
        guiPrefs[prefKey] = prefValue;
        this._pydio.user.setPreference('gui_preferences', guiPrefs, true);
        this._pydio.user.savePreference('gui_preferences');
    }

    saveCards(cards){
        this.saveUserPreference('Cards', cards);
    }

    resetCards(){
        this.saveUserPreference('Cards', null);
    }

    setCards(newCards){
        this._cards = newCards;
        this.notify("cards", this._cards);
        this.saveCards(newCards);
    }

    getCards(){
        return this._cards;
    }

    removeCard(cardId){
        var index = -1;
        var currentCards = this.getCards();
        currentCards.map(function(card, arrayIndex){
            if(card.id == cardId) index = arrayIndex;
        });
        if(index == -1){
            console.warn('Card ID not found, this is strange.', cardId);
            return;
        }
        var newCards;
        if(index == 0) newCards = currentCards.slice(1);
        else if(index == currentCards.length-1) newCards = currentCards.slice(0, -1);
        else newCards = currentCards.slice(0,index).concat(currentCards.slice(index+1));
        this.setCards(newCards);
    }

    createCardId(cardDefinition, randomize=false){
        var id = LangUtils.computeStringSlug(cardDefinition['title']);
        if(randomize){
            id += '-' + Math.round(Math.random() * 100 + 10);
        }
        var alreadyExists = false;
        this._cards.map(function(card){
            if(card.id == id) alreadyExists = true;
        }.bind(this));
        if(alreadyExists){
            id = this.createCardId(cardDefinition, true);
        }
        return id;
    }

    addCard(cardDefinition){
        //console.log(cardDefinition);

        cardDefinition['id'] = this.createCardId(cardDefinition);
        this.setCards(this._cards.concat([cardDefinition]));
    }

}

export {Store as default}