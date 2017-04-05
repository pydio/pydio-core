export default function(pydio){

    return function(){
        pydio.goTo(pydio.getUserSelection().getUniqueNode());
    }

}