<?php
define('AJXP_EXEC', true);
require_once('../../core/classes/class.AJXP_Utils.php');
$AJXP_FILE_URL = AJXP_Utils::securePath(AJXP_Utils::sanitize($_GET["file"], 5));
$parts = explode("/", AJXP_Utils::securePath($_GET["file"]));
foreach($parts as  $i => $part){
    $parts[$i] = AJXP_Utils::sanitize($part, AJXP_SANITIZE_FILENAME);
}
$AJXP_FILE_URL = implode("/", $parts);
?>
<html>
<head>
    <script src="webodf/webodf.js" type="text/javascript" charset="utf-8"></script>
    <script type="text/javascript" charset="utf-8">
        function init()
        {
            var odfelement = document.getElementById("odf");
            window.odfcanvas = new odf.OdfCanvas(odfelement);
            window.odfcanvas.load("../../" + window.parent.ajxpServerAccessPath + "&get_action=download&file=<?php echo $AJXP_FILE_URL; ?>");
            //window.odfcanvas.setEditable(true);
            /*
            odfcanvas.odfContainer().save(function(err){
                console.log(err);
            });
            */
        }
        window.setTimeout(init, 0);
    </script>
    <style type="text/css">
        .shadow_class{
            box-shadow: 1px 1px 6px black;
        }
    </style>
</head>
<body style="background-color: rgb(85, 85, 85);">
    <div id="odf" class="shadow_class"></div>
</body>
</html>
