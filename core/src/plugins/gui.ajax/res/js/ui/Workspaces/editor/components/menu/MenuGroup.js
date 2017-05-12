import makeMenuTransition from './make-menu-transition';

const MenuGroup = (props) => {
    return (
        <div {...props}>
            {props.children}
        </div>
    );
};

const AnimatedMenuGroup = makeMenuTransition(MenuGroup)
export default AnimatedMenuGroup
