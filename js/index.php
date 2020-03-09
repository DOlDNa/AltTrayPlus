<?php
header('Last-Modified: '. $last_modified = gmdate('D, d M Y H:i:s T', filemtime(__FILE__)));
if (filter_input(INPUT_SERVER, 'HTTP_IF_MODIFIED_SINCE') === $last_modified) header('HTTP/1.1 304 Not Modified');
header('Content-Type: text/javascript');
echo file_get_contents('jquery.js')?>$(function(){if($('.del:checked').length>0){$('#del').slideDown()}else{$('#del').slideUp()}$('.del').change(function(){if(this.checked)$('#del').slideDown();else if($('.del:checked').length===0)$('#del').slideUp()})});function scrl(t,c){$('body,html').animate({scrollTop:$(t).offset().top});$(c).slideToggle()}function delc(id){$(id).prop('checked',true);$('.del').change()}