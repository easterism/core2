
$(document).ready(function() {
    $("#main_profile_messagesto").autocomplete({
        source: "index.php?module=profile",
        selectFirst: true,
        minLength: 2,
        autoFocus: true
    });
});
