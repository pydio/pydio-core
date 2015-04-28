class LangUtils{

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
        var detect=/(((ajxp\.)(\w+)):\/)?\/?([^:\/\s]+)((\/\w+)*\/)(.*)(#[\w\-]+)?/g;
        var results = data.match(detect);
        if(results && results.length){
            var e=/^((ajxp\.(\w+)):\/)?\/?([^:\/\s]+)((\/\w+)*\/)(.*)(#[\w\-]+)?$/;
            for(var i=0;i<results.length;i++){
                if(results[i].match(e)){
                    matches.push({url: RegExp['$&'],
                        protocol: RegExp.$2,
                        host:RegExp.$4,
                        path:RegExp.$5,
                        file:RegExp.$7,
                        hash:RegExp.$8});
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
