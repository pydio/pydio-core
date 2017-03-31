class Logger{

    static log(message){
        if(window.console) console.log(message);
    }

    static error(message){
        if(window.console) console.error(message);
    }

    static debug(message){
        if(window.console) console.debug(message);
    }

}

export {Logger as default}