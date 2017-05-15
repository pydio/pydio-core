<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
"http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <?php if(isSet($serverBaseUrl)) echo "<base href='$serverBaseUrl'/>"; ?>
</head>
<body bgcolor="ffffff" style="overflow:hidden; padding: 0px; padding-left: 0px; margin: 0px;">

<link rel="stylesheet" href="plugins/uploader.plupload/plupload/jquery.plupload.queue/css/jquery.plupload.queue.css?v=2" type="text/css" media="screen" />
<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1/jquery.js"></script>

<script type="text/javascript" src="plugins/uploader.plupload/plupload/plupload.full.min.js"></script>
<script type="text/javascript" src="plugins/uploader.plupload/plupload/jquery.plupload.queue/jquery.plupload.queue.min.js"></script>

<script language="javascript" type="text/javascript">
// Fix height of modal box
//$(window.parent.document).find("#generic_dialog_box").height(355);


var clientId = '<?php echo session_id(); ?>';

var partitionLength = <?php echo ($partitionLength?$partitionLength:-1); ?>;
var maxFileLength = <?php echo ($maxFileLength?$maxFileLength:-1); ?>;

var ftpUrl = '<?php echo ($ftpURL?$ftpURL:""); ?>';

//var uploadUrl = '<?php print($_SERVER["SCRIPT_NAME"]); ?>?get_action=upload_chunks_unify_plupload&secure_token=<?php echo $secureToken;?>&ajxp_sessid=<?php echo session_id().$minisite_session; ?>';
var uploadUrl = '<?php print($uploadUrlBase); ?>?get_action=upload_chunks_unify_plupload&secure_token=<?php echo $secureToken;?>&ajxp_sessid=<?php echo session_id().$minisite_session; ?>';

var maxFileSize = '<?PHP echo ($UploadMaxSize/1048576) . "mb"; ?>';
var maxHTML4 = '<?PHP echo $UploadMaxSize; ?>';
var config_runtimes = '<?php echo $pluginConfigs["RUNTIMES"]; ?>';
var config_max_file_size = '<?php echo $pluginConfigs["MAX_FILE_SIZE"]; ?>';
var config_chunk_size = '<?php echo (!empty($pluginConfigs["CHUNK_SIZE"]) ? $pluginConfigs["CHUNK_SIZE"] : (($UploadMaxSize/1048576) - 1)."mb"); ?>';
var currentDir = parent.pydio.getContextNode().getPath();
var AjxpNode = parent.AjxpNode;

$(function() {
	$("#plupload_uploader").pluploadQueue({
		// General settings
		runtimes : config_runtimes,
		url : uploadUrl,
		max_file_size : config_max_file_size,
		chunk_size : config_chunk_size,

		unique_names : false,
        multiple_queues : true,
		multipart : true,
		multipart_params : { dir: currentDir },

		// Specify what files to browse for
		filters : [],

		// Flash settings
        flash_swf_url : 'plugins/uploader.plupload/plupload/Moxie.swf',
        silverlight_xap_url : 'plugins/uploader.plupload/plupload/Moxie.xap',

		// attach callbacks before queue
		preinit: attachCallbacks
	});

	// attach callbacks for FileUploaded
	function attachCallbacks(Uploader) {
        Uploader.bind('FilesAdded', function(up, files){
            plupload.each(files, function(file){
                var node = new AjxpNode(currentDir + '/' + file.name);
                if(file.size) node.getMetadata().set('filesize', file.size);
                try{
                    parent.pydio.getContextHolder().applyCheckHook(node);
                }catch(e){
                    Uploader.removeFile(file);
                }
            });
        });
		Uploader.bind('UploadFile', function(up, file, res) {
            parent.pydio.notify("longtask_starting");
		});
		Uploader.bind('FileUploaded', function(up, file, res) {
			if(this.total.queued <= 1) {
				parent.pydio.fireContextRefresh();
                parent.pydio.notify("longtask_finished");
			}
		});
	}

});
</script>

<div id="plupload_uploader">Cannot load PLUploader plugin.</div>
</body>
</html>
