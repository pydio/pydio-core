<html>
	<head>	
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<link rel='stylesheet' type='text/css' href='allz.css'/>
	</head>
	<body style="font-family: Helvetica, Verdana, Arial;">
    <?php if($AJXP_LINK_HAS_PASSWORD){ ?>
		<form action='' method='post' name="submit_password">
            <div style="width:560px;height:400px;margin: 8% auto;text-align: center;position: relative;">
                <h1><?php echo sprintf($messages[1], ConfService::getCoreConf("APPLICATION_TITLE")) ?></h1>
                <?php if($AJXP_LINK_WRONG_PASSWORD): ?>
                    <div style="color: hsl(0, 82%, 51%);width: 261px;position: absolute;top: 80px;left: 11px;"><?php echo $messages[3] ?></div>
                <?php endif; ?>
                <input type="password" name="password" style="width: 301px;height: 50px;font-size: 31px;border-radius: 10px;position: absolute;top: 106px;left: 68px;" placeholder="<?php echo $messages[5] ?>">
                <a href="#" onclick="document.forms['submit_password'].submit();" style="position: absolute;display: block;height: 200px;width: 128px;right: 34px;">
                    <img src="drive_harddisk.png" style="position: absolute;top: 43px;left: 0px;height: 64px;width: 64px;border: 0px;">
                    <img src="down.png" style="position: absolute;left: 0px;height: 64px;width: 64px;top: 11px;border: 0px;">
                </a>
                <h2 style="font-weight: normal;position: absolute;top: 198px;left: 44px;font-size: 1.3em;"><?php echo sprintf($messages[4], $AJXP_LINK_BASENAME) ?></h2>
            </div>
		</form>
    <?php } else { ?>
        <div style="width:560px;height:400px;margin: 8% auto;text-align: center;">
            <h1><?php echo sprintf($messages[1], ConfService::getCoreConf("APPLICATION_TITLE")) ?></h1>
            <a href="?dl=true" style="position: relative;display: block;height: 200px;width: 203px;margin: 0 auto;-webkit-border-radius: 19px;-moz-border-radius: 19px;border-radius: 19px;background-color: #eee;box-shadow: 1px 1px 10px rgba(0,0,0,0.4);">
                <img src="drive_harddisk.png" style="position: absolute;top: 67px;left: 41px;border: 0px;">
                <img src="down.png" style="position: absolute;left: 41px;border: 0px;">
            </a>
            <h2 style="font-weight: normal;font-size: 1.4em;width: 400px;margin: 28px auto;"><?php echo sprintf($messages[2], $AJXP_LINK_BASENAME) ?></h2>
        </div>
    <?php  } ?>
	</body>
</html>