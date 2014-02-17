<?php
$AJXP_SECURE_TOKEN = $_GET["token"];
$AJXP_FILE_URL = $_GET["file"];
?>
<html>
<head>
    <script src="webodf/webodf.js" type="text/javascript" charset="utf-8"></script>
    <script type="text/javascript" charset="utf-8">
        function init()
        {
            var odfelement = document.getElementById("odf");
            window.odfcanvas = new odf.OdfCanvas(odfelement);
            window.odfcanvas.load("../../index.php?secure_token=<?php echo $AJXP_SECURE_TOKEN; ?>&get_action=download&file=<?php echo $AJXP_FILE_URL; ?>");
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
