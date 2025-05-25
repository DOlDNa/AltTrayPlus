<?php
$time_start = hrtime(true);
$base_mem = memory_get_usage();
$refresh = 9e5; # 9×10^5 = 15分

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
	'title' => ['filter' => FILTER_CALLBACK, 'options' => 'strip_tags', 'flags' => FILTER_REQUIRE_ARRAY],
	'name' => ['filter' => FILTER_CALLBACK, 'options' => 'strip_tags', 'flags' => FILTER_REQUIRE_ARRAY],
	'user' => ['filter' => FILTER_CALLBACK, 'options' => 'e', 'flags' => FILTER_REQUIRE_ARRAY+FILTER_FLAG_EMAIL_UNICODE],
	'host' => ['filter' => FILTER_CALLBACK, 'options' => 'strip_tags', 'flags' => FILTER_REQUIRE_ARRAY],
	'port' => ['filter' => FILTER_SANITIZE_NUMBER_INT, 'flags' => FILTER_REQUIRE_ARRAY],
	'password' => ['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY],
	'protocol' => ['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY],
]))
{
	for ($l=0, $e=count($a['name']); $l < $e; ++$l)
	{
		if ($a['name'][$l] && $a['host'][$l] && $a['port'][$l] && $a['user'][$l] && $a['protocol'][$l])
		$accounts[] =
		$a['title'][$l]. $n.
		'name="'. $a['name'][$l]. '"'.$n.
		'host="'. $a['host'][$l]. '"'.$n.
		'port="'. $a['port'][$l]. '"'.$n.
		'user="'. $a['user'][$l]. '"'.$n.
		'passwd="'. ($a['password'][$l] ? trim(base64_encode($a['password'][$l]), '=') : $ini['account'. $l]['passwd'] ?? ''). '"'. $n.
		'protocol="'. ($a['protocol'][$l] ?? ''). '"'. $n. $n;
	}
	if (isset($accounts)) file_put_contents('.poptrayrc', implode($accounts), LOCK_EX);
	exit (header('Location: ./'));
}

function f(int $i)
{
	global $ini, $accounts;
	if (isset($accounts[$i])) $ini_str = parse_ini_string($accounts[$i], true);
	$inis = $ini['account'.$i]['protocol'] ?? $ini_str['account'. $i]['protocol'] ?? '';
	return
	'<fieldset>'.
		'<input name=title[] type=hidden value="[account'. $i. ']">'.
		'<input name=name[] type=text placeholder="タイトル: TEST 1" value="'. ($ini['account'. $i]['name'] ?? $ini_str['account'. $i]['name'] ?? ''). '">'.
		'<input name=user[] type=text placeholder="ユーザー: user@example.com" value="'. ($ini['account'. $i]['user'] ?? $ini_str['account'. $i]['user'] ?? ''). '">'.
		'<input name=host[] type=text placeholder="ホスト: mail.example.com" value="'. ($ini['account'. $i]['host'] ?? $ini_str['account'. $i]['host'] ?? ''). '">'.
		'<input name=port[] type=text placeholder="ポート: 995" value="'. ($ini['account'. $i]['port'] ?? $ini_str['account'. $i]['port'] ?? ''). '">'.
		'<input name=password[] type=text placeholder="パスワード: '. (isset($ini['account'. $i]['passwd']) ? '変更時のみ入力"' : 'xxxxxxxx"'). '>'.
		'<select name=protocol[] tabindex=-1>'.
			'<option'. ($inis ? '' : ' selected'). ' disabled value="">プロトコル: 選択して下さい</option>'.
			'<option value="POP3 SSL"'. ('POP3 SSL' !== $inis ? '' : ' selected'). '>POP3 SSL</option>'.
			'<option value="POP3"'. ('POP3' !== $inis ? '' : ' selected'). '>POP3</option>'.
			'<option value="IMAP"'. ('IMAP' !== $inis ? '' : ' selected'). '>IMAP</option>'.
		'</select>'.
	'</fieldset>';
}
function b($email)
{
	global $blacklist;
	if (filter_var($email, FILTER_VALIDATE_EMAIL))
	{
		$e = [];
		$list = file($blacklist, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		foreach ($list as $l) if (false !== stripos($email, $l)) $e[] = $l;
		if (!$e && false === stripos($email, 'MAILER-DAEMON')) return $email;
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
		$str = preg_replace('/<a.*?href="(.*?)"[^>]*>(.*?)<\/a>/isu', '<a href="$1" target="_blank" rel="noopener noreferrer">$2</a>&#10;', $str);
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
function e($e)
{
	if (filter_var($e, FILTER_VALIDATE_EMAIL)) return $e;
	elseif (false !== strpos($e, '@'))
	{
		$ex = explode('@', $e);
		return filter_var(idn_to_ascii($ex[0]). '@'. idn_to_ascii($ex[1]), FILTER_SANITIZE_EMAIL);
	}
}
echo
'<!doctype html>',
'<html lang=ja>',
	'<head>',
		'<meta charset=utf-8>',
		'<meta name=viewport content="width=device-width,initial-scale=1">',
		'<title>AltTray Plus 2.1</title>',
		'<link href=alt.css rel=stylesheet>',
		'<link href=icon.svg rel=icon sizes=any type="image/svg+xml">',
	'</head>',
	'<body>',
		'<header>',
			'<h1><a accesskey=a tabindex="-1" href="./"><img decoding=async src=icon.svg alt=alt width=64 height=50> AltTray Plus 2.1 <sup>β</sup></a></h1>', (filter_has_var(INPUT_GET, 'config') ? '' :
			'<a href="./?config" tabindex=-1>⚙️</a>'),
		'</header>',
		'<main>',
			'<form method=post>';
			ob_flush();
			if (isset($ini))
			{
				$c = count($ini);
				if (filter_has_var(INPUT_GET, 'config'))
				{
					for ($i=0; $i < $c; ++$i) echo
				'<section><h1>[account', $i, ']</h1>', f($i), '</section>';echo
				'<section><h1>[account', $i, ']</h1>', f($i), '</section>',
				'<input type=image src=icon.svg alt=alt width=52 height=40>';exit (
			'</form>'.
		'</main>'.
		'<footer>&copy; '. date('Y'). ' AltTray Plus 2.1, '. round((hrtime(true) - $time_start)/1e9, 4). '秒, '. s(memory_get_usage() - $base_mem). '.</footer>'.
	'</body>'.
'</html>'
					);
				}
				for ($i=0; $i < $c; ++$i)
				{
					if (!isset($ini['account'. $i]['name'], $ini['account'. $i]['host'], $ini['account'. $i]['port'], $ini['account'. $i]['user'], $ini['account'.$i]['protocol'], $ini['account'. $i]['passwd'])) continue;
					match ($ini['account'.$i]['protocol'])
					{
						'POP3 SSL' => $protocol = 'pop3/ssl',
						'IMAP' => $protocol = 'imap/ssl',
						default => $protocol = 'pop3',
					};
					if ($imap = imap_open('{'. $ini['account'. $i]['host']. ':'. $ini['account'. $i]['port']. '/'. $protocol. '/novalidate-cert}INBOX', $ini['account'. $i]['user'], base64_decode($ini['account'. $i]['passwd']), OP_SILENT, 0))
					{
						$d = imap_num_msg($imap);
						if (0 < $d)
						{
							$sort = imap_sort($imap, SORTDATE, 1, SE_UID);
							$notify .= 'new Notification("'. h($ini['account'. $i]['name']). '",{icon:"./icon.png",body:"新着メールが'. $d. '件あります。"});';
							echo
							'<section id="s', $i, '">',
							'<h2>', h($ini['account'. $i]['name']), ' <sup>', $d, '</sup></h2>';
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
								$from = isset($headerinfo->sender[1]) ?
									h($headerinfo->sender[1]->mailbox. '@'. $headerinfo->sender[1]->host) : h($headerinfo->from[0]->mailbox. '@'. $headerinfo->from[0]->host);

								if (isset($headerinfo->subject))
								{
									if (false !== stripos($headerinfo->subject, '=?'))
										$subject = mb_decode_mimeheader(str_replace('= =', '==', $headerinfo->subject));
									else
										$subject = $headerinfo->subject;

									$subject = str_replace(["\r\n", $n, '&#10;'], '', $subject);
									$subject = h(trim(str_replace(['/', ':', '!', '?', '&'], '-', $subject)));

									if (isset($headerinfo->from[0]->personal))
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

									if (isset($structure->parts[0]->parts[0]->encoding))
										$encoding = $structure->parts[0]->parts[0]->encoding;
									elseif (isset($structure->parts[0]->encoding))
										$encoding = $structure->parts[0]->encoding;
									elseif (isset($structure->encoding))
										$encoding = $structure->encoding;

									if (3 === $encoding)
										$body = imap_base64($body);
									elseif (4 === $encoding)
										$body = imap_qprint($body);

									$body = isset($charset) && 'UTF-8' !== strtoupper($charset) && 'X-UNKNOWN' !== strtoupper($charset) ?
										mb_convert_encoding($body, 'UTF-8', $charset) : mb_convert_encoding($body, 'UTF-8', 'auto');

									$body = l($body);

									if (!filter_var($from, FILTER_CALLBACK, ['options' => 'b']))
									{
										echo '<p class=blocked>', $from, ' からのメールは次の読み込み時に削除されます。 <a class=blocked download="'. $from, '.txt" href="data:application/octet-stream;base64,', base64_encode(html_entity_decode($header. $body)), '">保存</a></p>';
										imap_delete($imap, $k);
										imap_expunge($imap);
									}
									else
									{
										echo
										'<ul id="t', $i, '-', $k, '" class=header>',
											'<li class=delete><input type=checkbox id="c', $i, '-', $k, '" value="', $i, '+', $k, '" name=delete[] class=del><label for="c', $i, '-', $k, '" tabindex=1 onkeyup="if(\'Enter\'===event.key){fo=document.getElementById(this.htmlFor);if(true!==fo.checked)fo.checked=true;else fo.checked=false;a()}">削除</label></li>',
											'<li class=subject>', $subject, (isset($structure->parts) && 0 < count($structure->parts)-1 ? ' <sup>添付x'. (count($structure->parts)-1). '</sup>' : ''), '</li>',
											'<li class=from>', $personal, ' &lt;', $from, '&gt;</li>',
											'<li class=date>', date('Y年n月j日 H時i分s秒', strtotime($headerinfo->Date)), '</li>',
											'<li class=size>', s($headerinfo->Size), '</li>',
											'<li><a tabindex=0 id="a', $i, '-', $k, '" href="#t', $i, '-', $k, '" onclick="document.getElementById(\'col', $i, '-', $k, '\').classList.toggle(\'hide\');this.text=(\'表示\'===this.text?\'閉じる\':\'表示\')">表示</a></li>',
											'<li><a tabindex=0 id="i', $i, '-', $k, '" download="', $subject, '.txt" href="data:application/octet-stream;base64,', base64_encode(html_entity_decode($body)), '">保存</a></li>',
											'<li><a tabindex=0 id="j', $i, '-', $k, '" download="', $subject, '.txt" href="data:application/octet-stream;base64,', base64_encode(html_entity_decode($header. $body)), '">頭付保存</a></li>',
										'</ul>',
										'<div id="col', $i, '-', $k, '" class="body hide">',
											'<div class=detail>';
										if (isset($structure->parts) && is_array($structure->parts) && 0 < count($structure->parts)-1)
										{
											echo
												'<ol class=attachment>';
											for ($h=1, $atts=count($structure->parts)-1; $h <= $atts; ++$h)
											{
												$attachment = imap_fetchbody($imap, $k, $h+1);

												if (isset($structure->parts[$h]) && 'DELIVERY-STATUS' !== strtoupper($structure->parts[$h]->subtype))
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
															if (str_contains($attachment, '</'))
															{
																$attachname = quoted_printable_decode($structure->parts[$h]->parameters[0]->value);
																$attachment = base64_encode(quoted_printable_decode($attachment));
																$attach = 'base64,'. str_replace("\r\n", '', $attachment);
															}
															else
															{
																$attachname = quoted_printable_decode($structure->parts[$h]->parameters[0]->value);
																$attach = 'quoted-printable,'. str_replace("\r\n", '', $attachment);
															}
														}
														else
														{
															$attachname = htmlentities($structure->parts[$h]->parameters[0]->value, ENT_QUOTES);
															$attach = 'charset=UTF-8,'. rawurlencode($attachment);
														}
														if (isset($attach, $attachname) && 'null' !== $attachname)
															echo '<li><a download="', $attachname, '" href="data:application/octet-stream;', $attach, '">', $attachname, '</a></li>';
													}
												}
												elseif (0 === $structure->parts[$h]->encoding && is_array($structure->parts[$h]->parameters))
												{
													$body = str_replace(">\r\n<", '><', mb_convert_encoding($attachment, 'UTF-8', $structure->parts[$h]->parameters[0]->value));
													$body = str_replace("\r\n", '&#10;', trim(strip_tags($body, ['a', 'br'])));
												}
											}
											echo '</ol>';
										}
										echo
										'<p id="d', $i, '-', $k, '">', $body, '<span id="b', $i, '-', $k, '"></span></p>',
										'<p id="h', $i, '-', $k, '" class=mimeheader>', $header, !$body ? print_r($structure) : '', '</p>',
										'</div>',
										'<div class=footer>',
										'<a tabindex=0 id="x', $i, '-', $k, '" href="#b', $i, '-', $k, '" onclick="document.getElementById(\'h', $i, '-', $k, '\').classList.toggle(\'mimeheader\');this.text=(\'ヘッダを表示する\'===this.text?\'ヘッダを閉じる\':\'ヘッダを表示する\')" class=view>ヘッダを表示する</a>',
										'<a tabindex=0 id="y', $i, '-', $k, '" href="#t', $i, '-', $k, '" onclick="document.getElementById(\'col', $i, '-', $k, '\').classList.add(\'hide\');document.getElementById(\'a', $i, '-', $k, '\').text=\'表示\'">閉じる</a>',
										'<a tabindex=0 id="z', $i, '-', $k, '" class=del href="#t', $i, '-', $k, '" onmouseup="document.getElementById(\'c', $i, '-', $k, '\').checked=true;document.getElementById(\'col', $i, '-', $k, '\').classList.add(\'hide\');document.getElementById(\'a', $i, '-', $k, '\').text=\'表示\'" onkeydown="if(\'Enter\'===event.key){document.getElementById(\'c', $i, '-', $k, '\').checked=true;document.getElementById(\'col', $i, '-', $k, '\').classList.add(\'hide\');document.getElementById(\'a', $i, '-', $k, '\').text=\'表示\';a()}">削除にチェックを入れて閉じる</a>',
										'</div>',
										'</div>';
									}
								}
							}
							echo
							'</section>';
							$total += $d;
						}
						else
							echo
							'<section>',
								'<div><h2>', h($ini['account'. $i]['name']), ' <sup>0</sup></h2></div>',
								'<div>', imap_last_error(), '</div>',
							'</section>';
						ob_flush();

						if ($delete)
							imap_close($imap, CL_EXPUNGE);
						else
						{
							imap_errors();
							imap_close($imap);
						}
					}
				}
				if ($delete) exit ('<script>location.replace("./")</script><meta http-equiv=refresh content="0;URL=./?d">');
				echo !is_readable($rc) ? '' :
				'<button type=submit tabindex=0 id=del class=hide accesskey=d>選択したメールを削除する</button>';
			}
			else
			{
				echo
				'<section>',
					'<h1>使用方法</h1>',
					'<ol class=usage>',
						'<li><strong>~/.poptrayrc</strong> を <strong>', __DIR__, '</strong> にコピーするか、下のフォームから作成します。</li>',
						'<li>アカウントの追加と編集は歯車アイコンからも行えます。</li>',
						'<li>メール削除は、各メールの「削除」ボタンをクリックしてから、「選択したメールを削除する」ボタンを押します。</li>',
						'<li>自動チェックはオンライン接続時のみ行います。間隔を変更する場合は、<strong>', __FILE__, '</strong> をテキストエディタで開き、『<code>$refresh</code>』の値を変更して下さい。</li>',
						'<li>スパムメールを自動削除する場合は、<strong>AltTrayPlus/blacklist.txt</strong> にメールアドレスもしくはドメイン名を一行ずつ入力します。</li>',
					'</ol>',
				'</section>',
				'<section>',
					'<h1> .poptrayrc 作成</h1>', f(0),
					'<input type=image src=icon.svg alt=alt width=52 height=40>',
				'</section>';
			} echo
			'</form>',
		'</main>',
		'<script>const del=document.getElementById("del"),a=()=>{if(0===document.querySelectorAll(".del:checked").length)del.classList.add("hide");else del.classList.remove("hide")};if(0<document.querySelectorAll(".del:checked").length)del.classList.remove("hide"),[].slice.call(document.querySelectorAll(".del")||[]).map(d=>d.onclick=()=>a());(l=()=>setTimeout(()=>{if(navigator.onLine)location.reload();l()},', $refresh, '))()', (!$total ? '' : ';document.title="'. $total. '件受信 - AltTray Plus 2";Notification.requestPermission(p=>{if("granted"===p){'. $notify. '}})'),
		'</script>',
		'<footer>&copy; ', date('Y'), ' AltTray Plus, ', round((hrtime(true) - $time_start)/1e9, 4), '秒, ', s(memory_get_usage() - $base_mem), '.</footer>',
	'</body>',
'</html>';
