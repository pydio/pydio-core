(function(global){

    const SendFileTemplate = React.createClass({

        render: function(){
            return (
                <div style={{display:'flex', alignItems:'center', height: '100%'}}>
                    <div style={{flex: 1, textAlign:'center', fontSize: 20}}>Drop files here to share them!</div>
                </div>
            );
        }

    });

    global.SendFile = {
        Template: SendFileTemplate
    };

})(window);