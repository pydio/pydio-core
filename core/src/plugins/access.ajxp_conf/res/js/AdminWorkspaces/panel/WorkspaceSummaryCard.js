export default React.createClass({

    render:function(){
        return <ReactMUI.Paper className="workspace-card" zDepth={1}>
            <div className={this.props.icon + ' icon-card'}></div>
            <div className='card-content'>{this.props.children}</div>
        </ReactMUI.Paper>;
    }

});
