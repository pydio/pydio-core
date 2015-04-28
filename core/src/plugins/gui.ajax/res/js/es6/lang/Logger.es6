class Logger{

    static log(message){
        if(console) console.log(message);
    }

    static error(message){
        if(console) console.error(message);
    }

    static debug(message){
        if(console) console.debug(message);
    }

}