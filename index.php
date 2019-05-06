<?php
$reload = 1200;
header('Refresh: '.$reload);
header('Pragma: no-cache');
date_default_timezone_set('Asia/Tokyo');
error_reporting(E_ERROR);
$n = PHP_EOL;
$blk = $col = $total = $last_error = $body = $notify = null;
$delete = filter_input(INPUT_POST, 'delete', FILTER_SANITIZE_NUMBER_INT, FILTER_REQUIRE_ARRAY);
function h($str)
{
	return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function s($bytes)
{
	if ($bytes >= 1048576)
		return ceil($bytes / 1048576).'MB';
	elseif ($bytes >= 1024)
		return ceil($bytes / 1024).'KB';
	else
		return $bytes.'B';
}
function d($str, $num)
{
	if ($num === 3)
		return imap_base64($str);
	elseif ($num === 4)
		return imap_qprint($str);
	else
		return $str;
}
function l($str)
{
	return preg_replace_callback('~(https?://[\w./@\?_\-=&%;:]+[[:alnum:]]/?)~ui', 'm', $str);
}
function m($m)
{
	$m1 = trim($m[1], '&lt;&gt;');
	return'<a href="'. $m1. '" target="_blank" rel="noopener noreferrer">'. $m1. '</a>';
}
function p($str)
{
	$str = trim($str);
	if (stripos($str, '<script') !== false) $str = preg_replace('/(<script[^>]*>.*?<\/script>)/is', '', $str);
	if (stripos($str, '<style') !== false) $str = preg_replace('/(<style[^>]*>.*?<\/style>)/is', '', $str);
	if (stripos($str, '<a ') !== false) $str = preg_replace('/<a.*?href="(.*?)"[^>]*>(.*?)<\/a>/i', "$2\n$1", $str);
	if (stripos($str, '<img ') !== false) $str = preg_replace_callback('/<img.*?src="(.*?)"[^>]*>/i',
		function($m){if (stripos($m[1], 'spacer') === false && stripos($m[1], 'blank') === false && stripos($m[1], 'beacon') === false) return $m[1];}, $str);
	$str = strip_tags($str);
	$str = preg_replace("/(\n|\r|\r\n)+/us", PHP_EOL, $str);
	return l($str);
}
ob_implicit_flush(true);
?>
<!doctype html>
<html lang=ja>
	<head>
		<meta charset=utf-8>
		<meta name=viewport content="width=device-width,initial-scale=1">
		<title>AltTray Plus β</title>
		<link href=css/ rel=stylesheet>
		<meta name=description content="オルタナティブなメールチェッカー AltTray Plus">
		<link href=favicon.ico rel="shortcut icon">
	</head>
	<body>
		<form method=post>
			<script>var d=document,div=d.createElement("div"),f=d.getElementsByTagName("form")[0],n=Notification;f.style.display="none";div.setAttribute("id","loading-bg");d.body.appendChild(div);div.innerHTML="<d"+"iv id=loading><i>読<\/i><i>み<\/i><i>込<\/i><i>み<\/i><i>中<\/i><\/d"+"iv>";setTimeout(function(){div.innerHTML="<d"+"iv id=loading><i>タイムアウトしました<\/i><i>リロードして下さい<\/i><\/d"+"iv>"},60000)</script><?php ob_flush();echo $n?>
			<nav>
				<h1><img src=icon.svg alt=alt width=32 height=32> AltTray Plus <small><sup>β</sup></small></h1><?=file_exists($rc = '.poptrayrc') && is_file($rc) && is_readable($rc) ? '
				<button accesskey=d tabindex=1 type=submit id=del>選択したメールを削除する</button>'.$n : $n?>
			</nav>
			<div><?=$n;
				if (is_readable($rc))
				{
					$ini = parse_ini_file($rc, true);
					for ($i = 0, $c = count($ini); $i < $c; ++$i)
					{
						switch ($ini['account'.$i]['protocol'])
						{
							case 'POP3 SSL': $protocol = 'pop3/ssl'; break;
							case 'IMAP': $protocol = 'imap/ssl'; break;
							default: $protocol = 'pop3'; break;
						}
						$imap = imap_open('{'.$ini['account'.$i]['host'].':'.$ini['account'.$i]['port'].'/'.$protocol.'/novalidate-cert}INBOX', $ini['account'.$i]['user'], base64_decode($ini['account'.$i]['passwd']), OP_SILENT);
						if ($imap or $last_error = imap_last_error())
						{
							$d = imap_num_msg($imap);
							if ($d > 0)
							{
								$sort = imap_sort($imap, SORTDATE, 1, SE_UID);
								$notify .= 'new n("'.h($ini['account'.$i]['name']).'",{icon:"./icon.png",body:"新着メールが'.$d.'件あります。"});';
								echo
								'				<section>', $n,
								'					<h2>', h($ini['account'.$i]['name']), ' <small><sup>', $d, '</sup></small></h2>', $n;
								for ($j = 0; $j < $d; ++$j)
								{
									$k = imap_msgno($imap, $sort[$j]);
									$headerinfo = imap_headerinfo($imap, $k);
									if ($delete)
									{
										for ($h = 0, $b = count($delete); $h < $b; ++$h)
										{
											list($blk, $col) = explode('+', $delete[$h]);
											if ($i == $blk && $k == $col)
												imap_delete($imap, $col);
										}
									}
									if (isset($headerinfo->subject))
									{
										if (stripos($headerinfo->subject, '=?') !== false)
											$subject = mb_decode_mimeheader($headerinfo->subject);
										else
											$subject = $headerinfo->subject;
										$subject = str_replace(["\r\n", $n, '&#10;'], '', $subject);
										$subject = h(trim(str_replace(['/', ':', '!', '?', '&'], '-', $subject)));
										if (isset($headerinfo->from[0]->personal))
											$personal = stripos($headerinfo->from[0]->personal, '=?') !== false ? h(mb_decode_mimeheader($headerinfo->from[0]->personal)) : h($headerinfo->from[0]->personal);
										else
											$personal = h($headerinfo->from[0]->mailbox).'@'.h($headerinfo->from[0]->host);
										$header = str_replace("\r\n", '&#10;', h(imap_fetchbody($imap, $k, '0')));
										if (stripos($header, '=?') !== false)
											$header = mb_decode_mimeheader($header);
										$structure = imap_fetchstructure($imap, $k);
										if (isset($structure->parts))
										{
											if (isset($structure->parts[0]->parts[0]->subtype))
												$encoding = $structure->parts[0]->parts[0]->encoding;
											else
												$encoding = $structure->parts[0]->encoding;
										}
										$fetchbody = trim(imap_fetchbody($imap, $k, '1.1'));
										if (!$fetchbody)
											$fetchbody = trim(imap_fetchbody($imap, $k, '1'));
										if ($structure->parameters[0]->attribute === 'CHARSET')
											$charset = trim($structure->parameters[0]->value);
										elseif ($structure->parts[0]->parameters[0]->attribute === 'CHARSET')
											$charset = trim($structure->parts[0]->parameters[0]->value);
										elseif ($structure->parts[0]->parts[0]->parameters[0]->attribute === 'CHARSET')
											$charset = trim($structure->parts[0]->parts[0]->parameters[0]->value);
										else
											$charset = 'auto';
										if (isset($encoding))
											$body = d($fetchbody, $encoding);
										else
											$body = $structure->subtype === 'HTML' ? preg_replace("/(\n|\r|\r\n)+/us", $n, d($fetchbody, $structure->encoding)) : d($fetchbody, $structure->encoding);
										if (!$body)
											$body = $fetchbody;
										$body = isset($charset) && strtoupper($charset) !== 'UTF-8' && strtoupper($charset) !== 'X-UNKNOWN' ? mb_convert_encoding($body, 'UTF-8', $charset) : mb_convert_encoding($body, 'UTF-8', 'auto');
										$body = stripos($body, '<body') !== false ? p($body) : l($body);
										$body = str_replace("\r\n", '&#10;', $body);

										echo
										'					<ul id="t', $i, '-', $k, '" class=header>', $n,
										'						<li class=delete><input type=checkbox id="c', $i, '-', $k, '" value="', $i, '+', $k, '" name=delete[] class=del><label for="c', $i, '-', $k, '">削除</label></li>', $n,
										'						<li class=subject>', $subject, (isset($structure->parts) && count($structure->parts)-1 > 0 ? ' <small><sup>添付x'.(count($structure->parts)-1).'</sup></small>' : ''), '</li>', $n,
										'						<li class=from>', $personal, ' &lt;', h($headerinfo->from[0]->mailbox), '@', h($headerinfo->from[0]->host), '&gt;</li>', $n,
										'						<li class=date>', date('Y年n月j日 H時i分s秒', strtotime($headerinfo->Date)), '</li>', $n,
										'						<li class=size>', s($headerinfo->Size), '</li>', $n,
										'						<li><a onclick="scrl(\'#t', $i, '-', $k, '\',\'#col', $i, '-', $k, '\');this.text=(this.text==\'表示\'?\'閉じる\':\'表示\')" id="a', $i, '-', $k, '">表示</a></li>', $n,
										'						<li><a onclick="$(this).attr(\'download\',\'', $subject, '.txt\').attr(\'href\',\'data:application/octet-stream,\'+encodeURIComponent($(\'#d', $i, '-', $k, '\').text()))">保存</a></li>', $n,
										'					</ul>', $n,
										'					<div id="col', $i, '-', $k, '" class=body>', $n,
										'						<div class=detail>', $n;
										if (isset($structure->parts) && count($structure->parts)-1 > 0)
										{
											echo '							<ol class=attachment>';
											for ($h = 1, $atts = count($structure->parts)-1; $h <= $atts; ++$h)
											{
												$attachment = imap_fetchbody($imap, $k, $h + 1);
												if (isset($structure->parts[$h]))
												{
													if (strtoupper($structure->parts[$h]->subtype) !== 'HTML')
													{
														if ($structure->parts[$h]->encoding === 3)
														{
															$attachname = stripos($structure->parts[$h]->parameters[0]->value, '=?') !== false ?
															mb_decode_mimeheader($structure->parts[$h]->parameters[0]->value):
															$structure->parts[$h]->parameters[0]->value;
															$attach = 'base64,'.str_replace("\r\n", '', $attachment);
														}
														elseif ($structure->parts[$h]->encoding === 4)
														{
															$attachname = quoted_printable_decode($structure->parts[$h]->parameters[0]->value);
															$attach = 'quoted-printable,'.str_replace("\r\n", '', $attachment);
														}
														if (isset($attach, $attachname))
															echo '<li><a onclick="$(this).attr(\'download\',\'',$attachname,'\').attr(\'href\',\'data:application/octet-stream;',$attach,'\')">',$attachname,'</a></li>';
													}
												}
												elseif ($structure->parts[$h]->encoding === 0)
												{
													$body = str_replace(">\r\n<", '><', mb_convert_encoding($attachment, 'UTF-8', $structure->parts[$h]->parameters[0]->value));
													$body = str_replace("\r\n", '&#10;', trim(strip_tags($body, '<a><br>')));
												}
											}
											echo '</ol>', $n;
										}
										echo
										'							<p id="d', $i, '-', $k, '">', $body, '<span id="b', $i, '-', $k, '"></span></p>', $n,
										'							<p id="h', $i, '-', $k, '" class=mimeheader>', $header, !$body ? print_r($structure) : '', '</p>', $n,
										'						</div>', $n,
										'						<div class=footer>', $n,
										'							<a onclick="scrl(\'#b', $i, '-', $k, '\',\'#h', $i, '-', $k, '\');this.text=(this.text==\'ヘッダーを表示する\'?\'ヘッダーを閉じる\':\'ヘッダーを表示する\')" class=view>ヘッダーを表示する</a>', $n,
										'							<a onclick="scrl(\'#t', $i, '-', $k, '\',\'#col', $i, '-', $k, '\');$(\'#a', $i, '-', $k, '\').text(\'表示\')">閉じる</a>', $n,
										'							<a onclick="delc(\'#c', $i, '-', $k, '\');scrl(\'#t', $i, '-', $k, '\',\'#col', $i, '-', $k, '\');$(\'#a', $i, '-', $k, '\').text(\'表示\')">削除にチェックを入れて閉じる</a>', $n,
										'						</div>', $n,
										'					</div>', $n;
									}
								}
								echo
								'				</section>', $n;
								$total += (int)$d;
							}
							else
								echo
								'				<section>', $n,
								'					<div><h2>', h($ini['account'.$i]['name']), ' <small><sup>0</sup></small></h2></div>', $n,
								'					<div>', $last_error, '</div>', $n,
								'				</section>', $n;
						}
						if ($delete)
							imap_close($imap, CL_EXPUNGE);
						else
						{
							imap_errors();
							imap_close($imap);
						}
					}
				}
				else
					echo
					'				<ol class=usage>', $n,
					'					<li>ホーム直下の不可視ファイル <strong>.poptrayrc</strong> を <strong>',__DIR__,'</strong> にコピーして下さい。</li>', $n,
					'					<li>コピーした <strong>.poptrayrc</strong> のパーミッションを、755 など「読み込み可能」に変更して下さい。</li>', $n,
					'					<li>ブラウザのリロードボタンを押して、暫くお待ち下さい。</li>', $n,
					'					<li>メールの削除は、各チェックボックスをクリックしてから、「選択したメールを削除する」ボタンを押して下さい。</li>', $n,
					'					<li>自動チェックの間隔を変更する場合は、<strong>',__FILE__,'</strong> をテキストエディタで開き、『$reload』の値を変えて下さい。<br>現在値は ', $reload, ' = ', floor(($reload/ 60) % 60), '分です。</li>', $n,
					'				</ol>', $n;
				if ($delete)
					exit('<script>location.replace("./")</script><meta http-equiv=refresh content="0;URL=./?d">');
				?>
				<footer><small>&copy; <?=date('Y')?> AltTray Plus</small></footer>
			</div>
		</form><?=$total > 0 ? '
		<script>d.title="'.$total.'件受信 - AltTray Plus β";n.requestPermission(function(p){if(p==="granted"){'.$notify.'}})</script>'.$n : $n?>
		<script src=js/></script>
	</body>
</html>
