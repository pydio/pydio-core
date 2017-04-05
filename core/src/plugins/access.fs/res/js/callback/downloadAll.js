export default function (pydio) {

    return function(){
        let dm = pydio.getContextHolder();
        dm.setSelectedNodes([dm.getRootNode()]);
        require('./download')(pydio)();
    }

}