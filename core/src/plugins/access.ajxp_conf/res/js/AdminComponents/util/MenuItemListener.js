class MenuItemListener extends Observable{

    static getInstance(){
        if(!MenuItemListener.INSTANCE){
            MenuItemListener.INSTANCE = new MenuItemListener();
        }
        return MenuItemListener.INSTANCE;
    }

}

export {MenuItemListener as default}