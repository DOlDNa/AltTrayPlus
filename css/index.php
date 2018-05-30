<?php
header( 'Content-Type:text/css; charset=UTF-8' );
$last_modified = gmdate( 'D, d M Y H:i:s T', filemtime( __FILE__ ) );
header( 'Last-Modified:' . $last_modified );
if ( filter_input( INPUT_SERVER, 'HTTP_IF_MODIFIED_SINCE' ) === $last_modified )
	header( 'HTTP/1.1 304 Not Modified' )?>
#loading-bg{background:rgba(0,0,0,.6);display:block;height:100%;left:0px;position:fixed;top:0px;width:100%;z-index:1}
#loading{color:#fff;display:block;height:200px;left:50%;margin-left:-100px;margin-top:-100px;position:fixed;text-align:center;top:50%;width:200px;z-index:2}
#loading i{animation:i 1s infinite;display:inline-block}@keyframes i{0%,100%{transform:translate(0)}50%{transform:translateY(-1em)}}#loading i:nth-child(2){animation-delay:.1s}
#loading i:nth-child(3){animation-delay:.2s}#loading i:nth-child(4){animation-delay:.3s}#loading i:nth-child(5){animation-delay:.4s}
*{box-sizing:border-box}
body{color:dimgrey;font-family:Roboto,"Droid Sans","Yu Gothic",YuGothic,"Hiragino Sans",sans-serif;font-kerning:auto}
h1,h2,section{margin-bottom:1em}h1 img{vertical-align:bottom}
a{color:dodgerblue;text-decoration:none}
a:hover{opacity:.5}
a,#del{cursor:pointer}
#del{position:fixed;bottom:2em;right:.4em;border-radius:6px;font-size:1em;padding:1em;border:1px solid transparent}
#del:hover{background-image:linear-gradient(to bottom right,deepskyblue,dodgerblue)}
#del,.attachment li:before,small sup{background-color:dodgerblue;color:white}
.header{padding-left:0}
.header li,.attachment li{display:inline-block}
.header li+li,.footer a+a{border-left:1px solid dimgrey}
.subject{width:30%}
.subject,.from,.date,.detail p,.attachment li{word-wrap:break-word;white-space:pre-wrap}
.header li,.footer a,.usage li{padding:.5em}
.footer{text-align:center;padding-left:1.5em;padding-bottom:1em;clear:both}
.footer a,small sup{white-space:nowrap}
.detail p{margin:0;padding:2em;float:left;font-family:monospace;line-height:1.6;width:50%}
.attachment{margin-bottom:0;padding-left:2em;counter-reset:num}
.attachment a{margin-left:.2em}
.attachment li{margin:.3em 0;padding-right:1em}
.attachment li:before{padding:0 .1em;border-radius:2px;content:"\006dfb\004ed8" counter(num);counter-increment:num}
.view{display:none}
small sup{padding:.1em .4em;border-radius:5em;display:inline-block;line-height:1;text-align:center}
footer{text-align:right}
@media(max-width:1300px){
.detail p,.subject,.from,.date{width:100%}
.delete{border-left:1px solid dimgrey}
.footer{text-align:left}
.mimeheader{clear:left;display:none}
.view{display:inline-block}
}
