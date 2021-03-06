<?php
$time_start = hrtime(true);
$base_mem = memory_get_usage();
$refresh = 9e5; # 9×10^5 = 15分
$save_header = false;
header('Pragma: no-cache');
date_default_timezone_set('Asia/Tokyo');
error_reporting(0);
ob_implicit_flush(1);
imap_timeout(IMAP_READTIMEOUT, 3);
$n = PHP_EOL;
$blk = $col = $total = 0;
$body = $notify = '';
$delete = !filter_has_var(INPUT_POST, 'delete') ? '' : filter_input(INPUT_POST, 'delete', FILTER_SANITIZE_NUMBER_INT, FILTER_REQUIRE_ARRAY);
if (!is_file($blacklist = './blacklist.txt')) file_put_contents($blacklist, '');
if (is_file($rc = '.poptrayrc') && is_readable($rc)) $ini = parse_ini_file($rc, true);
if (!$delete && $a = filter_input_array(INPUT_POST, [
	'title' => ['filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_REQUIRE_ARRAY],
	'name' => ['filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_REQUIRE_ARRAY],
	'user' => ['filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_REQUIRE_ARRAY],
	'host' => ['filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_REQUIRE_ARRAY],
	'port' => ['filter' => FILTER_SANITIZE_NUMBER_INT, 'flags' => FILTER_REQUIRE_ARRAY],
	'password' => ['filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_REQUIRE_ARRAY],
	'protocol' => ['filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_REQUIRE_ARRAY],
]))
{
	for ($l=0, $e=count($a['name']); $l < $e; ++$l)
	{
		if ($a['name'][$l] && $a['host'][$l] && $a['port'][$l] && $a['user'][$l] && $a['protocol'][$l])
			$accounts[] =
			$a['title'][$l]. $n.
			'name='. $a['name'][$l]. $n.
			'host='. $a['host'][$l]. $n.
			'port='. $a['port'][$l]. $n.
			'user='. $a['user'][$l]. $n.
			'passwd="'. ($a['password'][$l] ? trim(base64_encode($a['password'][$l]), '=') : $ini['account'. $l]['passwd'] ?? ''). '"'. $n.
			'protocol='. trim($a['protocol'][$l]). $n. $n;
	}
	if (isset ($accounts)) file_put_contents('.poptrayrc', implode($accounts), LOCK_EX);
	exit (header('Location: ./'));
}
function f(int $i)
{
	global $ini;
	return '
					<fieldset>
						<input name=title[] type=hidden value="[account'. $i. ']">
						<input name=name[] type=text placeholder="タイトル: TEST 1" value="'. ($ini['account'. $i]['name'] ?? ''). '">
						<input name=user[] type=text placeholder="ユーザー: user@example.com" value="'. ($ini['account'. $i]['user'] ?? ''). '">
						<input name=host[] type=text placeholder="ホスト: mail.example.com" value="'. ($ini['account'. $i]['host'] ?? ''). '">
						<input name=port[] type=text placeholder="ポート: 995" value="'. ($ini['account'. $i]['port'] ?? ''). '">
						<input name=password[] type=text placeholder="パスワード: '. (isset ($ini['account'. $i]['passwd']) ? '変更時のみ入力' : 'xxxxxxxx'). '">
						<select name=protocol[] tabindex=-1>'. (isset ($ini['account'. $i]['protocol']) ? '' : '<option selected disabled>プロトコル: 選択して下さい</option>'). '
							<option value="POP3 SSL"'. (isset ($ini['account'. $i]['protocol']) && 'POP3 SSL' === $ini['account'. $i]['protocol'] ? ' selected' : ''). '>POP3 SSL</option>
							<option value="POP3"'. (isset ($ini['account'. $i]['protocol']) && 'POP3' === $ini['account'. $i]['protocol'] ? ' selected' : ''). '>POP3</option>
							<option value="IMAP"'. (isset ($ini['account'. $i]['protocol']) && 'IMAP' === $ini['account'. $i]['protocol'] ? ' selected' : ''). '>IMAP</option>
						</select>
					</fieldset>';
}
function b($email)
{
	global $blacklist;
	if (filter_var($email, FILTER_VALIDATE_EMAIL))
	{
		$e = [];
		$list = file($blacklist, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		foreach ($list as $l) if (false !== stripos($email, $l)) $e[] = $l;
		if (!$e) return $email;
	}
}
function h($str)
{
	return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
}
function s(int $bytes)
{
	if (1048576 <= $bytes)
		return ceil($bytes/1048576). 'MB';
	elseif (1024 <= $bytes)
		return ceil($bytes/1024). 'kB';
	else
		return $bytes. 'B';
}
function l($str)
{
	if (false !== stripos($str, '<script')) $str = preg_replace('/(<script[^>]*>.*?<\/script>)/is', '', $str);
	if (false !== stripos($str, '<style')) $str = preg_replace('/(<style[^>]*>.*?<\/style>)/is', '', $str);
	if (false !== stripos($str, '</tr>') || false !== stripos($str, '</p>') || false !== stripos($str, '<br>')) $str = str_replace(['</tr>', '</p>', '<br>'], '&#10;', $str);
	if (false !== stripos($str, '<a'))
	{
		$str = h(strip_tags($str, '<a>'));
		$str = preg_replace_callback('|(&lt;a.*?/a&gt;)|isu', function ($m) {return htmlspecialchars_decode($m[1]);}, $str);
		$str = preg_replace('/(&nbsp;|&zwnj;|\s\s)+/', '', $str);
		$str = preg_replace('/<a.*?href="(.*?)"[^>]*>(.*?)<\/a>/iu', '<a href="$1" target="_blank" rel="noopener noreferrer">$2</a>&#10;', $str);
	}
	else
	{
		$pat = 'https?://[\w#$%&()+-./:;’=?~@\]\[]+[[:alnum:]]/?';
		$str = preg_replace_callback('!\(('. $pat. ')\)|('. $pat. ')!iu', 'm', $str);
		$str = h($str);
		$str = preg_replace_callback('|(&lt;a.*?/a&gt;)|isu', function ($m) {return htmlspecialchars_decode($m[1]);}, $str);
		$str = str_replace(["\r\n", "\r", "\n",], '&#10;', $str);
	}
	return $str;
}
function m($m)
{
	$l = $m[1] ?: $m[0];
	return '<a href="'. $l. '" target="_blank" rel="noopener noreferrer">'. $l. '</a>';
}
?>
<!doctype html>
<html lang=ja>
	<head>
		<meta charset=utf-8>
		<meta name=viewport content="width=device-width,initial-scale=1">
		<title>AltTray Plus 2</title>
		<link href=alt.css rel=stylesheet>
		<link href=icon.svg rel=icon sizes=any type="image/svg+xml">
		<script src=jquery-3.5.1.min.js></script>
	</head>
	<body>
		<header>
			<h1><a tabindex="-1" href="./"><img decoding=async src=icon.svg alt=alt width=64 height=50> AltTray Plus 2 <sup>β</sup></a></h1>
			<?=filter_has_var(INPUT_GET, 'config') ? '' : '<a href="./?config" tabindex=-1><img decoding=async src="Cog_font_awesome.svg" alt=conf data-license="Dave Gandy CC BY-SA 3.0" width=32 height=32></a>', $n?>
		</header>
		<main>
			<form method=post><?php ob_flush();
			if (isset ($ini))
			{
				$c = count($ini);
				if (filter_has_var(INPUT_GET, 'config'))
				{
					for ($i=0; $i < $c; ++$i) echo $n,
					'				<section>', $n,
					'					<h1>[account', $i, ']</h1>', f($i), $n,
					'				</section>';
					echo
					'				<section>', $n,
					'					<h1>[account', $i, ']</h1>', f($i), $n,
					'				</section>', $n,
					'				<input type=image src=icon.svg alt=alt width=52 height=40>', $n;
					exit
					(
					'			</form>'. $n.
					'		</main>'. $n.
					'		<footer>&copy; '. date('Y'). ' AltTray Plus 2, '. round((hrtime(true) - $time_start)/1e9, 4). '秒, '. s(memory_get_usage() - $base_mem). '.</footer>'. $n.
					'	</body>'. $n.
					'</html>'
					);
				}
				for ($i=0; $i < $c; ++$i)
				{
					if (!isset ($ini['account'. $i]['name'], $ini['account'. $i]['host'], $ini['account'. $i]['port'], $ini['account'. $i]['user'], $ini['account'.$i]['protocol'], $ini['account'. $i]['passwd'])) continue;
					switch ($ini['account'.$i]['protocol'])
					{
						case 'POP3 SSL': $protocol = 'pop3/ssl'; break;
						case 'IMAP': $protocol = 'imap/ssl'; break;
						default: $protocol = 'pop3'; break;
					}
					if ($imap = imap_open('{'. $ini['account'. $i]['host']. ':'. $ini['account'. $i]['port']. '/'. $protocol. '/novalidate-cert}INBOX', $ini['account'. $i]['user'], base64_decode($ini['account'. $i]['passwd']), OP_SILENT, 0) or $last_error = imap_last_error())
					{
						$d = imap_num_msg($imap);
						if (0 < $d)
						{
							$sort = imap_sort($imap, SORTDATE, 1, SE_UID);
							$notify .= 'new Notification("'. h($ini['account'. $i]['name']). '",{icon:"./icon.png",body:"新着メールが'. $d. '件あります。"});';
							echo $n,
							'				<section id="s', $i, '">', $n,
							'					<h2>', h($ini['account'. $i]['name']), ' <sup>', $d, '</sup></h2>', $n;
							for ($j=0; $j < $d; ++$j)
							{
								$headerinfo = imap_headerinfo($imap, $k = imap_msgno($imap, $sort[$j]));
								if ($delete)
								{
									for ($h=0, $b=count($delete); $h < $b; ++$h)
									{
										list($blk, $col) = explode('+', $delete[$h]);
										if ($i === (int)$blk && $k === (int)$col)
										{
											imap_delete($imap, $col);
											echo '<style>ul{display:none}</style>';
										}
									}
								}
								$from = isset ($headerinfo->sender[1]) ?
									h($headerinfo->sender[1]->mailbox. '@'. $headerinfo->sender[1]->host) : h($headerinfo->from[0]->mailbox. '@'. $headerinfo->from[0]->host);

								if (isset ($headerinfo->subject))
								{
									if (false !== stripos($headerinfo->subject, '=?'))
										$subject = mb_decode_mimeheader($headerinfo->subject);
									else
										$subject = $headerinfo->subject;

									$subject = str_replace(["\r\n", $n, '&#10;'], '', $subject);
									$subject = h(trim(str_replace(['/', ':', '!', '?', '&'], '-', $subject)));

									if (isset ($headerinfo->from[0]->personal))
										$personal = false !== stripos($headerinfo->from[0]->personal, '=?') ? h(mb_decode_mimeheader($headerinfo->from[0]->personal)) : h($headerinfo->from[0]->personal);
									else
										$personal = h($headerinfo->from[0]->mailbox);

									$header = str_replace("\r\n", '&#10;', h(imap_fetchbody($imap, $k, '0')));

									if (false !== stripos($header, '=?')) $header = mb_decode_mimeheader($header);

									$structure = imap_fetchstructure($imap, $k);

									if (!$body = trim(imap_fetchbody($imap, $k, '1.1'))) $body = trim(imap_fetchbody($imap, $k, '1'));

									if ('CHARSET' === $structure->parameters[0]->attribute)
										$charset = trim($structure->parameters[0]->value);
									elseif ('CHARSET' === $structure->parts[0]->parameters[0]->attribute)
										$charset = trim($structure->parts[0]->parameters[0]->value);
									elseif ('CHARSET' === $structure->parts[0]->parts[0]->parameters[0]->attribute)
										$charset = trim($structure->parts[0]->parts[0]->parameters[0]->value);
									else
										$charset = 'auto';

									if (isset ($structure->parts[0]->parts[0]->encoding))
										$encoding = $structure->parts[0]->parts[0]->encoding;
									elseif (isset ($structure->parts[0]->encoding))
										$encoding = $structure->parts[0]->encoding;
									elseif (isset ($structure->encoding))
										$encoding = $structure->encoding;

									if (3 === $encoding)
										$body = imap_base64($body);
									elseif (4 === $encoding)
										$body = imap_qprint($body);

									$body = isset ($charset) && 'UTF-8' !== strtoupper($charset) && 'X-UNKNOWN' !== strtoupper($charset) ?
										mb_convert_encoding($body, 'UTF-8', $charset) : mb_convert_encoding($body, 'UTF-8', 'auto');

									$body = l($body);

									if (!filter_var($from, FILTER_CALLBACK, ['options' => 'b']))
									{
										echo '<p class=blocked>', $from, ' からのメールは次の読み込み時に削除されます。 <a class=blocked onclick="$(this).attr(\'download\',\'', $from, '.txt\').attr(\'href\',\'data:application/octet-stream;base64,', base64_encode(html_entity_decode($header. $body)), '\')">保存</a></p>';
										imap_delete($imap, $k);
										imap_expunge($imap);
									}
									else
									{
										echo
										'					<ul id="t', $i, '-', $k, '" class=header>', $n,
										'						<li class=delete><input type=checkbox id="c', $i, '-', $k, '" value="', $i, '+', $k, '" name=delete[] class=del><label for="c', $i, '-', $k, '">削除</label></li>', $n,
										'						<li class=subject>', $subject, (isset ($structure->parts) && 0 < count($structure->parts)-1 ? ' <sup>添付x'. (count($structure->parts)-1). '</sup>' : ''), '</li>', $n,
										'						<li class=from>', $personal, ' &lt;', $from, '&gt;</li>', $n,
										'						<li class=date>', date('Y年n月j日 H時i分s秒', strtotime($headerinfo->Date)), '</li>', $n,
										'						<li class=size>', s($headerinfo->Size), '</li>', $n,
										'						<li><a tabindex=0 id="a', $i, '-', $k, '">表示</a></li>', $n,
										'						<li><a tabindex=0 id="i', $i, '-', $k, '">保存</a></li>', $n,
										'					</ul>', $n,
										'					<script>$("#i', $i, '-', $k, '").on("click keypress",function(e){if(13===e.keyCode||"click"===e.type){$(this).attr("download","', $subject, '.txt").attr("href","data:application/octet-stream,"+encodeURIComponent(', (!$save_header ? '' : '$("#h'. $i. '-'. $k. '").text()+'), '$("#d', $i, '-', $k, '").text()))}});$("#a', $i, '-', $k, '").on("click keypress",function(e){if(13===e.keyCode||"click"===e.type){scrl("#t', $i, '-', $k, '","#col', $i, '-', $k, '");this.text=("表示"===this.text?"閉じる":"表示")}})</script>', $n,

										'					<div id="col', $i, '-', $k, '" class=body>', $n,
										'						<div class=detail>', $n;
										if (isset($structure->parts) && 0 < count($structure->parts)-1 && false === stripos('multipart/report', $header) && false === stripos('delivery-status', $header))
										{
											echo
											'							<ol class=attachment>';
											for ($h=1, $atts=count($structure->parts)-1; $h <= $atts; ++$h)
											{
												$attachment = imap_fetchbody($imap, $k, $h+1);

												if (isset ($structure->parts[$h]) && 'DELIVERY-STATUS' !== strtoupper($structure->parts[$h]->subtype))
												{
													if ('HTML' !== strtoupper($structure->parts[$h]->subtype) && 'PGP-SIGNATURE' !== strtoupper($structure->parts[$h]->subtype))
													{
														if (3 === $structure->parts[$h]->encoding)
														{
															$attachname = false !== stripos($structure->parts[$h]->parameters[0]->value, '=?') ?
																mb_decode_mimeheader($structure->parts[$h]->parameters[0]->value)
															:
																$structure->parts[$h]->parameters[0]->value;
															$attach = 'base64,'. str_replace("\r\n", '', $attachment);
														}
														elseif (4 === $structure->parts[$h]->encoding)
														{
															$attachname = quoted_printable_decode($structure->parts[$h]->parameters[0]->value);
															$attach = 'quoted-printable,'. str_replace("\r\n", '', $attachment);
														}
														else
														{
															$attachname = htmlentities($structure->parts[$h]->parameters[0]->value, ENT_QUOTES);
															$attach = 'charset=UTF-8,'. rawurlencode($attachment);
														}
														if (isset ($attach, $attachname) && 'null' !== $attachname)
															echo '<li><a onclick="$(this).attr(\'download\',\'', $attachname, '\').attr(\'href\',\'data:application/octet-stream;', $attach, '\')">', $attachname, '</a></li>';
													}
												}
												elseif (0 === $structure->parts[$h]->encoding)
												{
													$body = str_replace(">\r\n<", '><', mb_convert_encoding($attachment, 'UTF-8', $structure->parts[$h]->parameters[0]->value));
													$body = str_replace("\r\n", '&#10;', trim(strip_tags($body, ['a', 'br'])));
												}
											}
											echo '</ol>', $n;
										}
										echo
										'							<p id="d', $i, '-', $k, '">', $body, '<span id="b', $i, '-', $k, '"></span></p>', $n,
										'							<p id="h', $i, '-', $k, '" class=mimeheader>', $header, !$body ? print_r($structure) : '', '</p>', $n,
										'						</div>', $n,
										'						<div class=footer>', $n,
										'							<a tabindex=0 id="x', $i, '-', $k, '" class=view>ヘッダーを表示する</a>', $n,
										'							<a tabindex=0 id="y', $i, '-', $k, '">閉じる</a>', $n,
										'							<a tabindex=0 id="z', $i, '-', $k, '">削除にチェックを入れて閉じる</a>', $n,
										'						</div>', $n,
										'					</div>', $n,
										'					<script>$("#x', $i, '-', $k, '").on("click keypress",function(e){if(13===e.keyCode||"click"===e.type){scrl("#b', $i, '-', $k, '","#h', $i, '-', $k, '");this.text=("ヘッダーを表示する"===this.text?"ヘッダーを閉じる":"ヘッダーを表示する")}});$("#y', $i, '-', $k, '").on("click keypress",function(e){if(13===e.keyCode||"click"===e.type){scrl("#t', $i, '-', $k, '","#col', $i, '-', $k, '");$("#a', $i, '-', $k, '").text("表示")}});$("#z', $i, '-', $k, '").on("click keypress",function(e){if(13===e.keyCode||"click"===e.type){delc("#c', $i, '-', $k, '");scrl("#t', $i, '-', $k, '","#col', $i, '-', $k, '");$("#a', $i, '-', $k, '").text("表示")}})
										</script>', $n;
									}
								}
							}
							echo
							'				</section>';
							$total += $d;
						}
						else
							echo $n,
							'				<section>', $n,
							'					<div><h2>', h($ini['account'. $i]['name']), ' <sup>0</sup></h2></div>', $n,
							'					<div>', imap_last_error(), '</div>', $n,
							'				</section>';
						ob_flush();
					}
					if ($delete)
						imap_close($imap, CL_EXPUNGE);
					else
					{
						imap_errors();
						imap_close($imap);
					}
				}
				if ($delete) exit ('<script>location.replace("./")</script><meta http-equiv=refresh content="0;URL=./?d">');
				echo $n, !is_readable($rc) ? '' :
				'				<button type=submit tabindex=0 id=del>選択したメールを削除する</button>', $n;
			}
			else
			{
				echo $n,
				'				<section>', $n,
				'					<h1>使用方法</h1>', $n,
				'					<ol class=usage>', $n,
				'						<li><strong>~/.poptrayrc</strong> を <strong>', __DIR__, '</strong> にコピーするか、下のフォームから作成します。</li>', $n,
				'						<li>アカウントの追加と編集は歯車アイコンからも行えます。</li>', $n,
				'						<li>メール削除は、各メールの「削除」ボタンをクリックしてから、「選択したメールを削除する」ボタンを押します。</li>', $n,
				'						<li>自動チェックはオンライン接続時のみ行います。間隔を変更する場合は、<strong>', __FILE__, '</strong> をテキストエディタで開き、『<code>$refresh</code>』の値を変更して下さい。</li>', $n,
				'						<li>スパムメールを自動削除する場合は、<strong>AltTrayPlus/blacklist.txt</strong> にメールアドレスもしくはドメイン名を一行ずつ入力します。</li>', $n,
				'						<li>メールの保存にヘッダを含める場合は、『<code>$save_header</code>』を <strong>true</strong> とします。</li>', $n,
				'					</ol>', $n,
				'				</section>', $n,
				'				<section>', $n,
				'					<h1> .poptrayrc 作成</h1>', f(0),
				'					<input type=image src=icon.svg alt=alt width=52 height=40>', $n,
				'				</section>', $n;
			}?>
			</form>
		</main>
		<script>$(function(){if(0<$('.del:checked').length){$('#del').slideDown()}else{$('#del').slideUp()}$('.del').change(function(){if(this.checked)$('#del').slideDown();else if(0===$('.del:checked').length)$('#del').slideUp()})});function scrl(t,c){$('body,html').animate({scrollTop:$(t).offset().top});$(c).slideToggle()}function delc(id){$(id).prop('checked',true);$('.del').change()}let a='ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split(''),i=0;$('a[tabindex=0],button[tabindex=0]').each(function(i){$(this).attr('accesskey',a[i])});(function l(){setTimeout(function(){if(navigator.onLine){location.reload()}l()},<?=$refresh?>)})();<?=0 < $total ? 'document.title="'. $total. '件受信 - AltTray Plus 2";Notification.requestPermission(function(p){if("granted"===p){'. $notify. '}})' : ''?></script>
		<footer>&copy; <?=date('Y')?> AltTray Plus, <?=round((hrtime(true) - $time_start)/1e9, 4), '秒, ', s(memory_get_usage() - $base_mem)?>.</footer>
	</body>
</html>
