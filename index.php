<?php
ob_implicit_flush(1);
include 'tray.php';
echo
'<!DOCTYPE html>',
'<html lang="ja">',
	'<head>',
		'<meta charset="UTF-8">',
		'<title>AltTrayPlus 3S</title>',
		'<link href="alt3.css" rel="stylesheet">',
		'<link href="icon.svg" rel="icon" sizes="any" type="image/svg+xml">',
	'</head>',
	'<body>',
		'<div id="app">',
			'<header id="header">',
				'<h1 id="logo"><img src="icon.svg"> AltTrayPlus 3S<a href="./?config" id="config" tabindex="-1" accesskey="c">⚙</a></h1>',
				'<input id="searchBox" class="search" type="text" placeholder="検索（件名・送信者・メール）" tabindex="-1" accesskey="s">',
			'</header>',
			'<form method="post">';
ob_flush();
if (filter_has_var(INPUT_GET, 'config'))
{
	echo
				'<div id="account">',
					'<div id="modal" class="modal show">',
						'<div class="modal-content" autofocus>';
	for ($i = 0, $c = count($accounts); $i < $c; ++$i)
	{
		echo
							'<section class="section-account"><h2 class="account-title">[account', $i, ']</h2>', f($i), '</section>';
	}
	ob_flush();
	echo
							'<section class="section-account"><h2 class="account-title">[account', $i, ']</h2>', f($i), '</section>',
							'<section class="section-buttons">',
								'<button class="btn btn-cancel" onclick="location.href=\'./\'" accesskey="c">キャンセル</button>',
								'<button class="btn btn-submit" type=submit accesskey="s">送信</button>',
							'</section>',
						'</div>',
					'</div>',
				'</div>',
			'</form>',
		'</div>',
	'</body>',
'</html>';
}
else
{
	$results = [];
	$count = $total = 0;
	foreach ($accounts as $acc) {
		$id = $acc['id'];
		$res = [
			'id' => $id,
			'server' => $acc['server'],
			'user' => $acc['user'],
			'error' => null,
			'messages' => [],
		];
		try {
			$client = new Pop3Client(
				$id,
				$acc['server'],
				$acc['port'],
				$acc['user'],
				$acc['pass'],
				$acc['ssl'],
				$TIMEOUT
			);
			$client->connect();
			$client->login();
			list($count, $size) = $client->stat();
			$list = $client->listMessages();
			foreach ($list as $msgId => $msgSize) {
				$header = [];
				if (isset($deleteRequests[$id]) && in_array($msgId, $deleteRequests[$id], true)) {
					$client->dele($msgId);
					continue;
				}
				$raw = $client->retr($msgId);
				$root = parse_raw_message($raw);
				foreach ($root->headers as $name => $value) {
					$header[$name] = decode_header_value($value);
				}
				$from = decode_header_value($root->getHeader('from') ?: '');
				$subject = decode_header_value($root->getHeader('subject') ?: '');
				$date = strtotime($root->getHeader('date')) ?: '';
				if ($from && is_blacklisted($from, $blacklist)) {
					$client->dele($msgId);
					continue;
				}
				list($kind, $text) = extract_best_text($root);
				$attachments = extract_attachments($root);
				$res['messages'][] = [
					'id' => $msgId,
					'size' => $msgSize,
					'from' => $from,
					'subject' => $subject,
					'date' => $date,
					'kind' => $kind,
					'body' => $text,
					'attachments' => $attachments,
					'header' => $header,
				];
			}
			usort($res['messages'], function($a, $b) { return $b['date'] <=> $a['date']; });
			$client->quit();
		} catch (Exception $e) {
			$res['error'] = $e->getMessage();
		}
		$total += $count;
		$results[] = $res;
	}
	foreach ($results as $acc)
	{
		echo
				'<section class="account">',
					'<h2 class="account-title">', ($hid = h($acc['id'])), '</h2>';
		if ($acc['error'])
		{
			echo
					'<div class="error">', h($acc['error']), '</div>';
		}
		elseif (empty($acc['messages']))
		{
			echo
					'<div></div>';
		}
		else
		{
			foreach ($acc['messages'] as $m)
			{
				$rowId = 'msg_'. $hid. '_'. $m['id'];
				echo
					'<div class="mail-row">',
						'<div class="delete">',
							'<input type="checkbox" name="delete[', $hid, ':', $m['id'], ']" value="1" id="', $hid, '-', $m['id'], '">',
							'<label for="', $hid, '-', $m['id'], '" tabindex="1">削除</label>',
						'</div>',
						'<main>',
							'<div class="subject" data-target="', $rowId, '" tabindex="0">', h($m['subject']);
				if (!empty($m['attachments']))
				{
					echo
								'<sup class="badge">添付 ', count($m['attachments']), '</sup>';
				}
				echo
							'</div>',
							'<span class="sender">', h($m['from']), '</span>',
							'<wbr>',
							'<time class="date">', date('Y年n月j日 H時i分s秒', h($m['date'])), '</time>',
						'</main>',
						'<div class="save" tabindex="0">📥<br><small>', h(s_bytes($m['size'])), '</small></div>',
						'<div id="', $rowId, '" class="body', ($m['kind'] === 'html' ? ' html' : ''), '">';
				if (!empty($m['attachments']))
				{
					echo
							'<aside class="attach">添付:';
					foreach ($m['attachments'] as $i => $a)
					{
						$fname = $a['filename'] ?: ('attachment-'. ($i+1));
						$b64 = base64_encode($a['data']);
						echo
								'<a href="data:', h($a['contentType']), ';base64,', $b64, '" download="', ($fname), '">', h($fname), '</a> (', h($a['contentType']), ', ', h(s_bytes(strlen($a['data']))), ')';
					}
					echo
							'</aside>';
				}
				$body = $body_orig = $m['body'];
				$body = preg_replace('/(<style[^>]*>.*?<\/style>)/is', '', $body);
				$body = strip_tags($body, ['a', 'br']);
				$replace_count = 0;
				$body = preg_replace_callback(
					'/<a[^>]*href\s*=\s*[\'"]([^\'"]+)[\'"][^>]*>(.*?)<\/a>/is',
					function ($m) use (&$replace_count, $hid) {
						$replace_count++;
						return '<address><cite>'. strip_tags($m[2]). '</cite><input name="url-'. $hid. '-'. $replace_count. '" type="text" value="'. strip_tags($m[1]). '" readonly class="url" onclick="this.select()"></address>';
					},
					$body
				);
				$body = str_replace(["\r\n", PHP_EOL], '&#10;', trim($body));
				$body = preg_replace("/([\s\t]*&#10;){3,}/", '&#10;', $body);
				echo $body;
				echo
						'</div>',
						'<div id="', $rowId, '_headers" class="headers">';
				foreach ($m['header'] as $k => $v)
				{
					echo
							h($k. ': '. $v), '&#10;';
				}
				$json_header = json_encode($m['header'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
				echo
						'</div>',
					'</div>',
					'<span id="', $rowId, '_header_body" data-base64="', base64_encode($json_header. PHP_EOL. $body_orig), '"></span>';
			}
		}
		echo
				'</section>';
		ob_flush();
	}
	echo
				'<button id="remove" type="submit" accesskey="d">チェックしたメールを削除</button>',
			'</form>',
			'<button id="notify-btn">受信通知を許可する</button>',
		'</div>',
		'<div id="modal" class="modal" role="dialog" aria-modal="true">',
			'<div class="modal-content">',
				'<div class="modal-header">',
					'<button class="tab-btn" data-tab="tab-body" tabindex="0">Body</button>',
					'<button class="tab-btn" data-tab="tab-header" tabindex="0">Header</button>',
					'<button class="tab-btn" data-tab="tab-body" data-action="download" tabindex="0">📥 Body+Header</button>',
					'<button class="close" tabindex="0">&times;</button>',
				'</div>',
				'<div id="tab-body" class="tab-content" autofocus></div>',
				'<div id="tab-header" class="tab-content"></div>',
			'</div>',
		'</div>',
		'<script id="altjs" src="alt3.js?r=', (int)$REFRESH_MS, '&t=', (int)$total, '"></script>',
	'</body>',
'</html>';
}
