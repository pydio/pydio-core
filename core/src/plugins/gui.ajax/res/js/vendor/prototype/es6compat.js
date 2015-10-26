window.ProtoCompat = {};

ProtoCompat.map2hash = function(object){

    if(object.forEach){
        // it's a map!
        var res = $H();
        object.forEach(function(value, key){
            res.set(key, value);
        });
        return res;
    }

    return object;

};

ProtoCompat.map2values = function(object){

    if(object.forEach){
        // it's a map!
        var res = $A();
        object.forEach(function(value){
            res.push(value);
        });
        return res;
    }

    return object;

};

ProtoCompat.hash2map = function(hash){

    try{
        var map = new Map();
        hash.each(function(pair){
            map.set(pair.key, pair.value);
        });
        return map;
    }catch(e){
        return hash;
    }

};