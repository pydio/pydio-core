(function(global){

    var Uploader = React.createClass({

        getInitialState: function () {
            return {
                dir: ajaxplorer.getContextNode().getPath(),
                submitting: false,
                currentURL: "",
                urls: []
            }
        },

        render: function(){
            return (
                <div id="plupload_form" box_width="550" box_padding="0">
    			    <div id="pluploadscreen">
    			        <iframe id="pluploadframe" style={{width: '100%', height:330, border:'0px none'}} frameborder="0" src={window.ajxpServerAccessPath + '&get_action=plupload_tpl&encode=false'}></iframe>
    			    </div>
    			</div>
            );
        }
    });

    var ns = global.PLUploaderView || {};
    ns.Uploader = Uploader;
    global.PLUploaderView = ns;

})(window);
