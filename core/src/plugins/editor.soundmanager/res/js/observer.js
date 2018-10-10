export default class SoundObserver extends Observable{

    constructor() {
        super()
        pydio.observe("repository_list_refreshed", () => {
        });
    }

    static getInstance(){
        if(!OpenNodesModel.__INSTANCE){
            OpenNodesModel.__INSTANCE = new OpenNodesModel();
        }
        return OpenNodesModel.__INSTANCE;
    }

    play(soundID) {
        this.notify("soundplay" + soundID)
    }

    pause(soundID) {
        this.notify("soundpause" + soundID)
    }

    stop(soundID) {
        this.notify("soundstop" + soundID)
    }

    resume(soundID) {
        this.notify("soundresume" + soundID)
    }

    finish(soundID) {
        this.notify("soundfinish" + soundID)
    }

    whileloading(soundID) {
        this.notify("soundwhileloading" + soundID)
    }

    whileplaying(soundID) {
        this.notify("soundwhileplaying" + soundID)
    }

    bufferchange(soundID) {
        this.notify("soundbufferchange" + soundID)
    }
}
