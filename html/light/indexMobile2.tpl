<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.0//EN" "http://www.wapforum.org/DTD/xhtml-mobile10.dtd">
<html>
<head>
	<title>{system_name}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

    <link rel="stylesheet" href="core2/html/light/css/bootstrap.min.css" type="text/css"/>
    <link rel="stylesheet" href="core2/html/light/css/font-awesome.min.css" type="text/css"/>
    <link rel="stylesheet" href="core2/html/light/css/menu.css" type="text/css"/>
    <link rel="stylesheet" href="core2/html/light/css/styles.css" type="text/css"/>
	<link rel="stylesheet" href="core2/html/light/css/jquery/ui-lightness/jquery-ui-1.10.3.custom.min.css" type="text/css"/>

	<link rel="stylesheet" href="core2/html/light/css/jquery.ui.theme.css" type="text/css"/>
	<link rel="stylesheet" href="core2/html/light/css/jquery.ui.theme.font-awesome.css" type="text/css"/>
    <link rel="stylesheet" href="core2/html/light/css/sweetalert.css" type="text/css"/>
    <link rel="stylesheet" href="core2/html/light/css/jquery.growl.css" type="text/css"/>

	<script type="text/javascript" language="javascript" src="core2/js/md5.js"></script>
    <script type="text/javascript" language="javascript" src="core2/js/jquery/lib/jquery-1.12.4.min.js"></script>
	<script type="text/javascript" language="javascript" src="core2/js/jquery/jquery-ui-1.10.3.custom.min.js"></script>
	<script type="text/javascript" language="javascript" src="core2/js/jquery/i18n/jquery.ui.datepicker-ru.js"></script>
	<script type="text/javascript" language="javascript" src="core2/js/jquery/jquery-ui-timepicker-addon.js"></script>
	<script type="text/javascript" language="javascript" src="core2/ext/QueryString/QueryString.js"></script>
	<script type="text/javascript" language="javascript" src="core2/js/jquery/plugins/jquery.maskedinput.min.js"></script>
	<script type="text/javascript" language="javascript" src="core2/js/jquery/plugins/jquery.maskMoney.js"></script>
    <script type="text/javascript" language="javascript" src="core2/html/light/js/cleave.min.js"></script>
    <script type="text/javascript" language="javascript" src="core2/html/light/js/sweetalert.min.js"></script>
	<script type="text/javascript" language="javascript" src="core2/html/light/js/bootstrap.min.js"></script>
    <script type="text/javascript" language="javascript" src="core2/html/light/js/control_datepicker_range.js"></script>
    <script type="text/javascript" language="javascript" src="core2/html/light/js/jquery.growl.js"></script>

    <!--[if (gte IE 8)&(lt IE 10)]>
    <script src="core2/ext/jQuery/plugins/jQuery-File-Upload-9.8.0/js/cors/jquery.xdr-transport.js"></script>
    <![endif]-->

	<script type="text/javascript" language="javascript" src="core2/html/light/js/class.list.js"></script>
    <script type="text/javascript" language="javascript" src="core2/html/light/js/class.edit.js"></script>
	<script type="text/javascript" language="javascript" src="core2/js/eTip.js"></script>
	<script type="text/javascript" language="javascript" src="core2/html/light/js/main.js"></script>
    <script type="text/javascript">
        jQuery(document).ready(function() {
            if ( ! jQuery.support.leadingWhitespace || (document.all && ! document.querySelector)) {
                $("#mainContainer").prepend(
                    "<h2>" +
                        "<span style=\"color:red\">Внимание!</span> " +
                        "Вы пользуетесь устаревшей версией браузера. " +
                        "Во избежание проблем с работой, рекомендуется обновить текущий или установить другой, более современный браузер." +
                    "</h2>"
                );
            }
			if ($('#module-profile')[0]) {
                $('.dropdown-profile.profile').addClass('show');
                $('.dropdown-profile.divider').addClass('show');
                if ($('#submodule-profile-messages')[0]) {
                    $('.dropdown-profile.messages').addClass('show');
                }
            }
			if ($('#module-settings')[0]) {
                $('.dropdown-settings').addClass('show');
            }
            if ($('#module-billing')[0]) {
                $('.dropdown-billing').addClass('show');
            }
        });
    </script>
    <!--[if IE]>
    <link rel="shortcut icon" href="favicon.ico">
    <![endif]-->
    <link rel="icon" type="image/png" href="favicon.png">
	<!--[if lte IE 7]>
	<style type="text/css">.iefix {display: inline-block;width: 0;overflow: hidden;}</style>
	<![endif]-->
	<!--xajax-->
</head>
<body>
<div id="rootContainer">
<!--index-->
</div>
</body>
</html>