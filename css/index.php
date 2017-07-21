<?php
header( 'Content-Type:text/css; charset=UTF-8' );
$last_modified = gmdate( 'D, d M Y H:i:s T', filemtime( 'bootstrap.min.css' ) );
header( 'Last-Modified:' . $last_modified );
if ( filter_input( INPUT_SERVER, 'HTTP_IF_MODIFIED_SINCE' ) === $last_modified )
	header( 'HTTP/1.1 304 Not Modified' );
echo file_get_contents( 'bootstrap.min.css' ),
'#loading-bg{background:rgba(0,0,0,.6);display:block;height:100%;left:0px;position:fixed;top:0px;width:100%;z-index:1}#loading{color:#fff;display:block;height:200px;left:50%;margin-left:-100px;margin-top:-100px;position:fixed;text-align:center;top:50%;width:200px;z-index:2}#loading i{animation:i 1s infinite;display:inline-block}@keyframes i{0%,100%{transform:translate(0)}50%{transform:translateY(-1em)}}#loading i:nth-child(2){animation-delay:.1s}#loading i:nth-child(3){animation-delay:.2s}#loading i:nth-child(4){animation-delay:.3s}#loading i:nth-child(5){animation-delay:.4s}body{font-family:Roboto,"Droid Sans","Yu Gothic",YuGothic,"Hiragino Sans",sans-serif}.container-fluid{margin-top:80px}.table th,.table td{padding:.4em .5em}th{white-space:nowrap}.table,nav{box-shadow:0px 2px 4px 2px rgba(0,0,0,0.3)}.table:hover{box-shadow:0px 3px 4px 2px rgba(0,0,0,0.5)}td:last-child,.detail{width:50%}.detail{word-wrap:break-word;white-space:pre-wrap;font-family:monospace}td>table{width:100%;table-layout:fixed}td:nth-child(3),td:nth-child(4){width:15%}.collapsed{padding:0!important}li{padding:.7em}';
