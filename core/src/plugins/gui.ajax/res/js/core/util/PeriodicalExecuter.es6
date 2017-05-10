export default class PeriodicalExecuter{

    constructor(callback, frequency) {
        this.callback = callback;
        this.frequency = frequency;
        this.currentlyExecuting = false;

        this.registerCallback();
    }

    registerCallback() {
        this.timer = setInterval(this.onTimerEvent.bind(this), this.frequency * 1000);
    }

    execute() {
        this.callback(this);
    }

    stop() {
        if (!this.timer) return;
        clearInterval(this.timer);
        this.timer = null;
    }

    onTimerEvent() {
        if (!this.currentlyExecuting) {
            try {
                this.currentlyExecuting = true;
                this.execute();
                this.currentlyExecuting = false;
            } catch(e) {
                this.currentlyExecuting = false;
                throw e;
            }
        }
    }
}