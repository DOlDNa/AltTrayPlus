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
	ob_flush();
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
								'<button class="btn btn-submit" type=submit accesskey="s">保存</button>',
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
	$count = $total = 0;
	foreach ($accounts as $acc) {
		$id = $acc['id'];
		echo "<samp class=\"typewriter dots check$id\">Checking $id</samp>";
		ob_flush();
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
			usort($res['messages'], fn($a, $b) => $b['date'] <=> $a['date']);
			$client->quit();
		} catch (Exception $e) {
			$res['error'] = $e->getMessage();
		}
		$total += $count;
		echo "<style>.check$id{display:none}</style>";
		render_account_html($res);
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
