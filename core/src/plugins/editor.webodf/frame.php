<?php
define('AJXP_EXEC', true);
require_once('../../core/src/pydio/Core/Utils/Vars/InputFilter.php');
require_once('../../core/src/pydio/Core/Exception/PydioException.php');
require_once('../../core/src/pydio/Core/Exception/ForbiddenCharacterException.php');
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Exception\ForbiddenCharacterException;

try {
    $test = InputFilter::securePath(InputFilter::sanitize($_GET["file"], InputFilter::SANITIZE_DIRNAME, true));
}catch (ForbiddenCharacterException $exception){
    die("Passed file contains forbidden characters!");
}
$parts = explode("/", InputFilter::securePath($_GET["file"]));
foreach($parts as  $i => $part){
    try{
        $parts[$i] = InputFilter::sanitize($part, InputFilter::SANITIZE_FILENAME);
    } catch (ForbiddenCharacterException $e){
        die("Passed file contains forbidden characters");
    }
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
