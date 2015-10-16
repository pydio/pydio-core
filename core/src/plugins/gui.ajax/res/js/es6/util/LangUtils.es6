class LangUtils{

    static arrayWithout(array, key){
        if(!array.length) return array;
        if(key >= array.length) return array;
        var newArray;
        if(key == 0) newArray = array.slice(1);
        else if(key == array.length-1) newArray = array.slice(0, -1);
        else newArray = array.slice(0,key).concat(array.slice(key+1));
        return newArray;
    }

    static objectMerge(obj1, obj2){
        for(var k in obj2){
            if(obj2.hasOwnProperty(k)){
                obj1[k] = obj2[k];
            }
        }
        return obj1;
    }

    static parseUrl(data) {
        var matches = $A();
        //var e=/((http|ftp):\/)?\/?([^:\/\s]+)((\/\w+)*\/)([\w\-\.]+\.[^#?\s]+)(#[\w\-]+)?/;
        var detect = /(pydio:\/)?\/?([^:\/\s]+)((\/\w+)*\/)(.*)(#[\w\-]+)?/g;
        var results = data.match(detect);
        if(results && results.length){
            var e = /^((pydio):\/)?\/?([^:\/\s]+)((\/\w+)*\/)(.*)(#[\w\-]+)?$/;
            for(var i=0;i<results.length;i++){
                if(results[i].match(e)){
                    matches.push({url: RegExp['$&'],
                        protocol: RegExp.$2,
                        host:RegExp.$3,
                        path:RegExp.$4,
                        file:RegExp.$6,
                        hash:RegExp.$7});
                }
            }
        }
        return  matches;
    }

    static computeStringSlug(value){
        for(var i=0, len=LangUtils.slugTable.length; i<len; i++)
            value=value.replace(LangUtils.slugTable[i].re, LangUtils.slugTable[i].ch);

        // 1) met en bas de casse
        // 2) remplace les espace par des tirets
        // 3) enleve tout les caratères non alphanumeriques
        // 4) enlève les doubles tirets
        return value.toLowerCase()
            .replace(/\s+/g, '-')
            .replace(/[^a-z0-9-]/g, '')
            .replace(/\-{2,}/g,'-');
    }

    static forceJSONArrayToObject(container, value){
        if(container[value] instanceof Array){
            // Clone
            var copy = container[value].slice(0);
            container[value] = {};
            for(var i = 0; i<copy.length; i++){
                container[value][i] = copy[i];
            }
        }
    }

    static deepCopy(source){
        return LangUtils.mergeObjectsRecursive({}, source);
    }

    static simpleCopy(source){
        var copy = {};
        for (var property in source) {
            if (source.hasOwnProperty(property)) {
                copy[property] = source[property];
            }
        }
        return copy;
    }

    static mergeObjectsRecursive(source, destination) {
        var newObject = {}, property;
        for (property in source) {
            if (source.hasOwnProperty(property)) {
                //if (source[property] === null) continue;
                if (destination.hasOwnProperty(property)) {
                    if (source[property] instanceof Object && destination instanceof Object) {
                        newObject[property] = LangUtils.mergeObjectsRecursive(source[property], destination[property]);
                    } else {
                        newObject[property] = destination[property];
                    }
                } else {
                    if (source[property] instanceof Object) {
                        newObject[property] = LangUtils.mergeObjectsRecursive(source[property], {});
                    } else {
                        newObject[property] = source[property];
                    }
                }
            }
        }
        for (property in destination) {
            if (destination.hasOwnProperty(property) && !newObject.hasOwnProperty(property) /*&& destination[property] !== null*/) {
                if (destination[property] instanceof Object) {
                    newObject[property] = LangUtils.mergeObjectsRecursive(destination[property], {});
                } else {
                    newObject[property] = destination[property];
                }
            }
        }
        return newObject;
    }

    static objectValues(object) {
        var results = [];
        for (var property in object)
            if(object.hasOwnProperty(property)){
                results.push(object[property]);
            }
        return results;
    }

}

LangUtils.slugTable = [
    {re:/[\xC0-\xC6]/g, ch:'A'},
    {re:/[\xE0-\xE6]/g, ch:'a'},
    {re:/[\xC8-\xCB]/g, ch:'E'},
    {re:/[\xE8-\xEB]/g, ch:'e'},
    {re:/[\xCC-\xCF]/g, ch:'I'},
    {re:/[\xEC-\xEF]/g, ch:'i'},
    {re:/[\xD2-\xD6]/g, ch:'O'},
    {re:/[\xF2-\xF6]/g, ch:'o'},
    {re:/[\xD9-\xDC]/g, ch:'U'},
    {re:/[\xF9-\xFC]/g, ch:'u'},
    {re:/[\xC7-\xE7]/g, ch:'c'},
    {re:/[\xD1]/g, ch:'N'},
    {re:/[\xF1]/g, ch:'n'} ];
