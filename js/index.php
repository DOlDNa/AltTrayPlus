<?php
header( 'Content-Type: application/javascript; charset=UTF-8' );
$last_modified = gmdate( 'D, d M Y H:i:s T', filemtime( 'jquery.js' ) );
header( 'Last-Modified: ' . $last_modified );
if ( filter_input( INPUT_SERVER, 'HTTP_IF_MODIFIED_SINCE' ) === $last_modified )
	header( 'HTTP/1.1 304 Not Modified' );
echo
file_get_contents( 'jquery.js' ),
file_get_contents( 'tether.min.js' ),
file_get_contents( 'bootstrap.min.js' ),
'$("#loading-bg").delay(600).fadeOut(500);$("#loading").delay(500).fadeOut(300);$("form").delay(500).fadeIn(400);if($(".form-check-input:checked").length>0){$("#del").prop("disabled",false)}else{$("#del").prop("disabled",true)}$(".form-check-input").change(function(){if(this.checked){$("#del").prop("disabled",false)}else if($(".form-check-input:checked").length===0)$("#del").prop("disabled",true)});function scrl(id){$("body,html").animate({scrollTop:$(id).offset().top-60})}function delc(id){$(id).prop("checked",true);$("#del").prop("disabled",false)}';
