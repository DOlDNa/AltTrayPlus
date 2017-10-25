<?php
header( 'Content-Type: application/javascript; charset=UTF-8' );
$last_modified = gmdate( 'D, d M Y H:i:s T', filemtime( __FILE__ ) );
header( 'Last-Modified: ' . $last_modified );
if ( filter_input( INPUT_SERVER, 'HTTP_IF_MODIFIED_SINCE' ) === $last_modified )
	header( 'HTTP/1.1 304 Not Modified' );
echo file_get_contents( 'jquery.js' )?>
$(".body").hide();
$("#loading-bg").delay(600).fadeOut(500);$("#loading").delay(500).fadeOut(300);$("form").delay(500).fadeIn(400);
if($(".del:checked").length>0){$("#del").slideDown()}else{$("#del").slideUp()}
$(".del").change(function(){if(this.checked)$("#del").slideDown();else if($(".del:checked").length===0)$("#del").slideUp()});
function scrl(t,c){$("body,html").animate({scrollTop:$(t).offset().top});$(c).slideToggle()}
function delc(id){$(id).prop("checked",true);$(".del").change()}