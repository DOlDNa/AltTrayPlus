<?php
ob_implicit_flush(true);
mb_internal_encoding('UTF-8');
$CONFIG_FILE = __DIR__. '/.poptrayrc';
$BLACKLIST_FILE = __DIR__. '/blacklist.txt';
$REFRESH_MS = 9e5;
$TIMEOUT = 10;
function filter_email_custom(mixed $e): ?string {
	if (!is_string($e)) return null;
	if (filter_var($e, FILTER_VALIDATE_EMAIL)) return $e;
	if (str_contains($e, '@')) {
		[$user, $domain] = explode('@', $e, 2);
		$asciiUser = idn_to_ascii($user) ?: $user;
		$asciiDomain = idn_to_ascii($domain) ?: $domain;
		$sanitized = filter_var($asciiUser . '@' . $asciiDomain, FILTER_SANITIZE_EMAIL);
		return is_string($sanitized) ? $sanitized : null;
	}
	return null;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['delete'])) {
	if ($a = filter_input_array(INPUT_POST, [
		'title' => ['filter' => FILTER_CALLBACK, 'options' => 'strip_tags', 'flags' => FILTER_REQUIRE_ARRAY],
		'name' => ['filter' => FILTER_CALLBACK, 'options' => 'strip_tags', 'flags' => FILTER_REQUIRE_ARRAY],
		'user' => ['filter' => FILTER_CALLBACK, 'options' => 'filter_email_custom', 'flags' => FILTER_REQUIRE_ARRAY | FILTER_FLAG_EMAIL_UNICODE],
		'host' => ['filter' => FILTER_CALLBACK, 'options' => 'strip_tags', 'flags' => FILTER_REQUIRE_ARRAY],
		'port' => ['filter' => FILTER_SANITIZE_NUMBER_INT, 'flags' => FILTER_REQUIRE_ARRAY],
		'password' => ['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY],
		'protocol' => ['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY],
	])) {
		$alt_accounts = [];
		$iniData = file_exists($CONFIG_FILE) ? parse_ini_file($CONFIG_FILE, true) : [];
		for ($l=0, $e=count($a['name'] ?? []); $l < $e; ++$l) {
			if (!empty($a['name'][$l]) && !empty($a['host'][$l]) && !empty($a['port'][$l]) && !empty($a['user'][$l]) && !empty($a['protocol'][$l])) {
				$alt_accounts[] =
				$a['title'][$l]. PHP_EOL.
				'name="'. $a['name'][$l]. '"'. PHP_EOL.
				'host="'. $a['host'][$l]. '"'. PHP_EOL.
				'port="'. $a['port'][$l]. '"'. PHP_EOL.
				'user="'. $a['user'][$l]. '"'. PHP_EOL.
				'passwd="'. (!empty($a['password'][$l]) ? trim(base64_encode($a['password'][$l]), '=') : ($iniData['account'. $l]['passwd'] ?? '')). '"'. PHP_EOL.
				'protocol="'. ($a['protocol'][$l] ?? ''). '"'. PHP_EOL. PHP_EOL;
			}
		}
		if (!empty($alt_accounts)) {
			file_put_contents($CONFIG_FILE, implode('', $alt_accounts), LOCK_EX);
		}
		header('Location: ./');
		exit;
	}
}
readonly class EmailSecurityChecker
{
	public function __construct(
		public array $headers
	) {}
	public function evaluate(): array
	{
		$spamStatus = $this->getHeaderValue('x-spam-status') ?? '';
		$virusStatus = $this->getHeaderValue('x-virus-status') ?? 'unknown';
		$isVirusClean = (strtolower($virusStatus) === 'clean');
		$isSpam = str_starts_with(strtoupper($spamStatus), 'YES');
		$score = $this->extractSpamScore($spamStatus);
		$hasDkimValid = str_contains($spamStatus, 'DKIM_VALID');
		$hasSpfPass = str_contains($spamStatus, 'SPF_PASS') || str_contains($spamStatus, 'SPF_HELO_PASS');
		$arHeader = $this->getHeaderValue('authentication-results') ?? '';
		$dmarcPass = str_contains(strtolower($arHeader), 'dmarc=pass');
		$arcPass = str_contains(strtolower($arHeader), 'arc=pass');
		$level = match(true) {
			!$isVirusClean || $isSpam => 'danger',
			$hasDkimValid && $hasSpfPass && ($score === null || $score < 0) => 'safe',
			default => 'warning',
		};
		return [
			'level' => $level,
			'virus_clean' => $isVirusClean,
			'is_spam' => $isSpam,
			'spam_score' => $score,
			'dkim_valid' => $hasDkimValid,
			'spf_pass' => $hasSpfPass,
			'dmarc_pass' => $dmarcPass,
			'arc_pass' => $arcPass,
			'raw_status' => $spamStatus,
		];
	}
	private function getHeaderValue(string $name): ?string {
		$val = $this->headers[$name] ?? null;
		if (is_array($val)) {
			return $val[0] ?? null;
		}
		return is_string($val) ? $val : null;
	}
	private function extractSpamScore(string $status): ?float
	{
		if (preg_match('/score=([-\d\.]+)/', $status, $matches)) {
			return (float) $matches[1];
		}
		return null;
	}
}
function parse_headers_text(string $headerText): array {
	$headers = [];
	$lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $headerText));
	$currentName = '';
	$currentVal = '';
	foreach ($lines as $line) {
		if (preg_match('/^\s+/', $line) && $currentName !== '') {
			$currentVal .= ' ' . trim($line);
		} else {
			if ($currentName !== '') {
				add_header_entry($headers, $currentName, $currentVal);
			}
			if (strpos($line, ':') !== false) {
				[$name, $val] = explode(':', $line, 2);
				$currentName = strtolower(trim($name));
				$currentVal = trim($val);
			} else {
				$currentName = '';
				$currentVal = '';
			}
		}
	}
	if ($currentName !== '') {
		add_header_entry($headers, $currentName, $currentVal);
	}
	return $headers;
}
function add_header_entry(array &$headers, string $name, string $value): void {
	$decoded = decode_header_value($value);
	if (isset($headers[$name])) {
		if (!is_array($headers[$name])) {
			$headers[$name] = [$headers[$name]];
		}
		$headers[$name][] = $decoded;
	} else {
		$headers[$name] = $decoded;
	}
}
function get_sender_info(array $headers): ?string {
	$received = $headers['received'] ?? null;
	if (!$received) return null;
	$lines = is_array($received) ? $received : [$received];
	$chronologicalLines = array_reverse($lines);
	$ipv4 = '(?<![\d\.\:\-])(?:25[0-5]|2[0-4]\d|[01]?\d\d?)\.(?:25[0-5]|2[0-4]\d|[01]?\d\d?)\.(?:25[0-5]|2[0-4]\d|[01]?\d\d?)\.(?:25[0-5]|2[0-4]\d|[01]?\d\d?)(?![\d\.\:\-])';
	$ipv6 = '(?<![\d\.\:\-])(?:[a-fA-F0-9]{1,4}:){7}[a-fA-F0-9]{1,4}(?![\d\.\:\-])|(?<![\d\.\:\-])(?:[a-fA-F0-9]{1,4}:){1,6}:[a-fA-F0-9]{1,4}(?![\d\.\:\-])';
	$ip_pattern = '/' . $ipv4 . '|' . $ipv6 . '/';
	foreach ($chronologicalLines as $line) {
		if (preg_match_all($ip_pattern, $line, $matches)) {
			foreach ($matches[0] as $ip) {
				if (is_public_ip($ip)) {
					return $ip;
				}
			}
		}
	}
	$oldestLine = $chronologicalLines[0];
	if (preg_match('/from\s+([^\s\(\)]+)/i', $oldestLine, $m)) {
		$host = trim($m[1]);
		if ($host !== 'unknown' && !preg_match('/^[a-zA-Z0-9\+\/=]+$/', $host)) {
			return $host;
		}
	}
	return 'Unknown';
}
function is_public_ip(string $ip): bool {
	return (bool) filter_var(
		$ip,
		FILTER_VALIDATE_IP,
		FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
	);
}
function render_account_html($acc) {
	echo
		'<section class="account">',
			'<h2 class="account-title">', ($hid = h($acc['id'])), '</h2>';
	if (!empty($acc['error'])) {
		echo
			'<div class="error">', h($acc['error']), '</div>';
	}
	elseif (empty($acc['messages'])) {
		echo
			'<div></div>';
	}
	else {
		foreach ($acc['messages'] as $m) {
			$rowId = 'msg_'. $hid. '_'. $m['id'];
			echo
				'<div class="mail-row" data-id="', $rowId, '">',
					'<div class="delete">',
						'<input type="checkbox" name="delete[', $hid, ':', $m['id'], ']" value="1" id="', $hid, '_', $m['id'], '" data-id="', $hid, '_', $m['id'], '">',
						'<label for="', $hid, '_', $m['id'], '" tabindex="1">削除</label>',
					'</div>',
					'<main>',
						'<div class="subject" data-target="', $rowId, '" tabindex="0">', h(preg_replace("/[\r\n]+/", '', $m['subject']));
			if (!empty($m['attachments'])) {
				echo
							'<sup class="badge">添付 ', count($m['attachments']), '</sup>';
			}
			$sec = $m['security'];
			$secClass = match($sec['level']) {
				'safe' => 'pass',
				'warning' => 'none',
				'danger' => 'fail',
			};
			echo
						'</div>',
						'<span class="sender">', h(preg_replace("/[\r\n]+/", '', $m['from'])), '</span>',
						(!filter_var($m['sender'], FILTER_VALIDATE_IP) ? '<span class="sender">'. h($m['sender']). '</span>' : '<wbr><a class="sender" href="../whois.php?ip='. h($m['sender']). '" target="_blank">'. h($m['sender']). '</a>'),
						'<wbr>',
						'<time class="date">', (!empty($m['date']) ? date('Y年n月j日 H時i分s秒', $m['date']) : ''), '</time>',
						'<div class="auth-badges">',
							'<span class="badge ', $secClass, '">判定: ', strtoupper($sec['level']), '</span>',
							'<span class="badge ', ($sec['spf_pass'] ? 'pass' : 'none'), '">SPF</span>',
							'<span class="badge ', ($sec['dkim_valid'] ? 'pass' : 'none'), '">DKIM</span>',
							'<span class="badge ', ($sec['virus_clean'] ? 'pass' : 'fail'), '">Virus: ', ($sec['virus_clean'] ? 'Clean' : 'Alert'), '</span>',
							($sec['spam_score'] !== null ? '<span class="badge">Score: '. $sec['spam_score']. '</span>' : ''),
						'</div>',
					'</main>',
					'<div class="save" tabindex="0">📥<br><small>', h(s_bytes($m['size'])), '</small></div>',
					'<div id="', $rowId, '" class="body', ($m['kind'] === 'html' ? ' html' : ''), '">';
			if (!empty($m['attachments'])) {
				echo
							'<aside class="attach">添付:';
				foreach ($m['attachments'] as $i => $att) {
					$fname = $att['filename'] ?: ('attachment-'. ($i+1));
					$b64 = base64_encode($att['data']);
					echo
								'<a href="data:', h($att['contentType']), ';base64,', $b64, '" download="', ($fname), '">', h($fname), '</a> (', h($att['contentType']), ', ', h(s_bytes(strlen($att['data']))), ')';
				}
				echo
							'</aside>';
			}
			$body = $body_orig = $m['body'];
			$body = preg_replace('/([ \t]+|&nbsp;)</', '<', $body);
			$body = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $body);
			$replace_count = 0;
			$body = preg_replace_callback(
				'/<a[^>]*href\s*=\s*[\'"]([^\'"]+)[\'"][^>]*>(.*?)<\/a>/is',
				function ($match) use (&$replace_count, $hid) {
					$replace_count++;
					return
							'<address>'.
								($match[1] === $match[2] ? '' : '<cite>'. strip_tags($match[2]). '</cite>').
								'<input name="url-'. $hid. '-'. $replace_count. '" type="text" value="'. strip_tags($match[1]). '" readonly class="url" onclick="this.select()" tabindex="0">'.
							'</address>';
				},
				$body
			);
			$body = strip_tags($body, ['address', 'cite', 'input']);
			$body = trim($body);
			$body = preg_replace('/<br\s*\/?>|[\r\n]/i', '&#10;', $body);
			$body = preg_replace('/(&#10;){3,}/', '&#10;&#10;', $body);
			$body = preg_replace('/[ \t]{3,}/', ' ', $body);
			echo $body;
			echo
					'</div>',
					'<div id="', $rowId, '_headers" class="headers">',
						str_replace(PHP_EOL, '&#10;', h($m['headers'])),
					'</div>',
				'</div>',
				'<span id="', $rowId, '_header_body" data-base64="', base64_encode($m['headers']. PHP_EOL. $body_orig), '"></span>';
		}
	}
	echo
		'</section>';
}
function h(?string $s): string {
	return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function s_bytes($bytes) {
	if ($bytes > 1024 * 1024) return sprintf('%.1f MB', $bytes / 1024 / 1024);
	if ($bytes > 1024) return sprintf('%.1f KB', $bytes / 1024);
	return $bytes. ' B';
}
function f(int $i) {
	$ini = (is_file($rc = '.poptrayrc') && is_readable($rc)) ? parse_ini_file($rc, true) : [];
	$inis = $ini['account'.$i]['protocol'] ?? '';
	return
	'<fieldset class="fieldset-account">'.
		'<input class="input-text" name=title[] type=hidden value="[account'. $i. ']">'.
		'<input class="input-text" name=name[] type=text placeholder="タイトル: TEST 1" value="'. h($ini['account'. $i]['name'] ?? ''). '">'.
		'<input class="input-text" name=user[] type=text placeholder="ユーザー: user@example.com" value="'. h($ini['account'. $i]['user'] ?? ''). '">'.
		'<input class="input-text" name=host[] type=text placeholder="ホスト: mail.example.com" value="'. h($ini['account'. $i]['host'] ?? ''). '">'.
		'<input class="input-text" name=port[] type=text placeholder="ポート: 995" value="'. h($ini['account'. $i]['port'] ?? ''). '">'.
		'<input class="input-text" name=password[] type=text placeholder="パスワード: '. (isset($ini['account'. $i]['passwd']) ? '変更時のみ入力"' : 'xxxxxxxx"'). '>'.
		'<select class="select-protocol" name=protocol[]>'.
			'<option value="POP3 SSL"'. ('POP3 SSL' !== $inis ? '' : ' selected'). '>プロトコル: POP3 SSL （デフォルト）</option>'.
			'<option value="TLS"'. ('TLS' !== $inis ? '' : ' selected'). '>TLS</option>'.
		'</select>'.
	'</fieldset>';
}
function load_blacklist($file) {
	if (!file_exists($file)) return [];
	$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	$out = [];
	foreach ($lines as $l) {
		$l = trim($l);
		if ($l === '' || $l[0] === '#') continue;
		$out[] = $l;
	}
	return $out;
}
function is_blacklisted($email, $blacklist) {
	foreach ($blacklist as $pat) {
		if (@preg_match($pat, $email)) {
			if (preg_match($pat, $email)) return true;
		} else {
			if (stripos($email, $pat) !== false) return true;
		}
	}
	return false;
}
class Pop3Client {
	public $host, $port, $user, $pass, $ssl, $timeout, $fp, $id;
	public function __construct($id, $host, $port, $user, $pass, $ssl = true, $timeout = 5) {
		$this->id = $id;
		$this->host = $host;
		$this->port = $port;
		$this->user = $user;
		$this->pass = $pass;
		$this->ssl = $ssl;
		$this->timeout = $timeout;
	}
	public function connect() {
		$proto = $this->ssl ? 'ssl://' : 'tls://';
		$this->fp = @stream_socket_client($proto. $this->host. ':'. $this->port, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT);
		if (!$this->fp) throw new Exception("Connection failed: $errstr ($errno)");
		stream_set_timeout($this->fp, $this->timeout);
		if (strpos($this->readLine(), '+OK') !== 0) throw new Exception("Server rejected connection");
	}
	public function cmd($cmd, $expectOk = true) {
		$this->writeLine($cmd);
		$resp = $this->readLine();
		if ($expectOk && strpos($resp, '+OK') !== 0) throw new Exception("POP3 error on '$cmd': $resp");
		return $resp;
	}
	public function writeLine($line) { fwrite($this->fp, $line. "\r\n"); }
	public function readLine() {
		$line = fgets($this->fp);
		if ($line === false) throw new Exception("Connection closed");
		return rtrim($line, "\r\n");
	}
	public function login() {
		$this->cmd("USER ". $this->user);
		$this->cmd("PASS ". $this->pass);
	}
	public function stat() {
		$parts = explode(' ', $this->cmd("STAT"));
		return [(int)($parts[1] ?? 0), (int)($parts[2] ?? 0)];
	}
	public function listMessages() {
		$this->cmd("LIST");
		$list = [];
		while (($line = $this->readLine()) !== '.') {
			$parts = explode(' ', $line);
			if (count($parts) >= 2) $list[(int)$parts[0]] = (int)$parts[1];
		}
		return $list;
	}
	public function retr($id) {
		$this->cmd("RETR $id");
		$data = '';
		while (($line = fgets($this->fp)) !== false) {
			$trim = rtrim($line, "\r\n");
			if ($trim === '.') break;
			if (isset($trim[0]) && $trim[0] === '.' && strpos($trim, '..') === 0) $trim = substr($trim, 1);
			$data .= $trim. "\r\n";
		}
		return $data;
	}
	public function dele($id) { $this->cmd("DELE $id"); }
	public function quit() {
		if ($this->fp) {
			$this->writeLine("QUIT");
			fclose($this->fp);
			$this->fp = null;
		}
	}
}
class MimePart {
	public $headers = [], $body = '', $parts = [], $contentType = 'text/plain', $charset = 'UTF-8', $encoding = '7bit', $filename = null, $isAttachment = false;
	public function __construct($headers = [], $body = '') {
		$this->headers = $headers;
		$this->body = $body;
		$this->parseHeaders();
	}
	protected function parseHeaders() {
		if ($ct = $this->getHeader('content-type')) $this->parseContentType($ct);
		if ($ce = $this->getHeader('content-transfer-encoding')) $this->encoding = strtolower(trim($ce));
		if ($cd = $this->getHeader('content-disposition')) $this->parseContentDisposition($cd);
	}
	public function getHeader($name) {
		$lname = strtolower($name);
		foreach ($this->headers as $k => $v) {
			if (strtolower($k) === $lname) return $v;
		}
		return null;
	}
	protected function parseContentType($ct) {
		$parts = explode(';', $ct);
		$this->contentType = strtolower(trim(array_shift($parts)));
		foreach ($parts as $p) {
			if (strpos($p, '=') !== false) {
				[$k, $v] = explode('=', $p, 2);
				$k = strtolower(trim($k));
				$v = trim($v, " \t\"'");
				if ($k === 'charset') $this->charset = strtoupper($v);
				elseif ($k === 'name') $this->filename = mb_decode_mimeheader($v);
			}
		}
	}
	protected function parseContentDisposition($cd) {
		$parts = explode(';', $cd);
		$disp = strtolower(trim(array_shift($parts)));
		if ($disp === 'attachment' || $disp === 'inline') $this->isAttachment = true;
		foreach ($parts as $p) {
			if (strpos($p, '=') !== false) {
				[$k, $v] = explode('=', $p, 2);
				$k = strtolower(trim($k));
				$v = trim($v, " \t\"'");
				if ($k === 'filename') $this->filename = mb_decode_mimeheader($v);
			}
		}
	}
	public function decodeBody() {
		$b = $this->body;
		if ($this->encoding === 'base64') $b = base64_decode($b);
		elseif ($this->encoding === 'quoted-printable') $b = quoted_printable_decode($b);
		if ($this->charset && strtoupper($this->charset) !== 'UTF-8') {
			$b = @mb_convert_encoding($b, 'UTF-8', $this->charset);
		}
		return $b;
	}
}
function parse_raw_message($raw) {
	$raw = str_replace(["\r\n", "\r"], "\n", $raw);
	$parts = explode("\n\n", $raw, 2);
	$headers = parse_headers_text($parts[0] ?? '');
	$root = new MimePart($headers, $parts[1] ?? '');
	parse_multipart_recursive($root);
	return $root;
}
function parse_multipart_recursive(MimePart $part) {
	if (strpos($part->contentType, 'multipart/') !== 0) return;
	$ct = $part->getHeader('content-type');
	if (!$ct) return;
	$boundary = null;
	foreach (explode(';', $ct) as $p) {
		if (strpos($p, '=') !== false) {
			[$k, $v] = explode('=', $p, 2);
			if (strtolower(trim($k)) === 'boundary') {
				$boundary = trim($v, " \t\"'");
				break;
			}
		}
	}
	if (!$boundary) return;
	$segments = preg_split('/\n--'. preg_quote($boundary, '/'). '(--)?\s*/', "\n". $part->body);
	foreach ($segments as $seg) {
		$seg = ltrim($seg, "\n");
		if ($seg === '' || $seg === "--") continue;
		$subParts = explode("\n\n", $seg, 2);
		$childHeaders = parse_headers_text($subParts[0] ?? '');
		$child = new MimePart($childHeaders, $subParts[1] ?? '');
		parse_multipart_recursive($child);
		$part->parts[] = $child;
	}
}
function extract_best_text(MimePart $part) {
	$texts = ['text/html' => null, 'text/plain' => null];
	$stack = [$part];
	while ($stack) {
		$p = array_pop($stack);
		if ($p->parts) {
			foreach ($p->parts as $c) $stack[] = $c;
		} else {
			if (strpos($p->contentType, 'text/plain') === 0 && !$p->isAttachment) {
				$texts['text/plain'] .= $p->decodeBody(). "\n";
			} elseif (strpos($p->contentType, 'text/html') === 0 && !$p->isAttachment) {
				$texts['text/html'] .= $p->decodeBody(). "\n";
			}
		}
	}
	if ($texts['text/html']) return ['html', $texts['text/html']];
	if ($texts['text/plain']) return ['plain', $texts['text/plain']];
	return ['plain', ''];
}
function extract_attachments(MimePart $part) {
	$attachments = [];
	$stack = [$part];
	while ($stack) {
		$p = array_pop($stack);
		if ($p->parts) {
			foreach ($p->parts as $c) $stack[] = $c;
		} else {
			if ($p->isAttachment || ($p->filename && strpos($p->contentType, 'text/') !== 0)) {
				$attachments[] = [
					'filename' => $p->filename ?: 'attachment',
					'contentType' => $p->contentType,
					'data' => $p->decodeBody(),
				];
			}
		}
	}
	return $attachments;
}
function decode_header_value($val) {
	$val = preg_replace("/\r?\n\s+/", "", $val);
	if (preg_match('/=\?.*\?=/', $val)) {
		$decoded = @iconv_mime_decode($val, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, "UTF-8");
		if ($decoded !== false) return $decoded;
		return mb_decode_mimeheader($val);
	}
	return $val;
}
function load_accounts($file) {
	if (!file_exists($file)) return [];
	$accounts = [];
	foreach (parse_ini_file($file, true) as $name => $cfg) {
		if (empty($cfg['host']) || empty($cfg['user']) || empty($cfg['passwd'])) continue;
		$accounts[] = [
			'id' => $cfg['name'],
			'server' => $cfg['host'],
			'port' => isset($cfg['port']) ? (int)$cfg['port'] : 995,
			'ssl' => $cfg['protocol'],
			'user' => $cfg['user'],
			'pass' => base64_decode($cfg['passwd']),
		];
	}
	return $accounts;
}
$deleteRequests = [];
$delete = filter_input(INPUT_POST, 'delete', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
if (!empty($delete)) {
	ob_start();
	foreach ($delete as $key => $val) {
		if ($val && strpos($key, ':') !== false) {
			[$aid, $mid] = explode(':', $key, 2);
			$deleteRequests[$aid][] = (int)$mid;
		}
	}
	header('Location: ./');
}
$accounts = load_accounts($CONFIG_FILE);
$blacklist = load_blacklist($BLACKLIST_FILE);
echo
'<!DOCTYPE html>',
'<html lang="ja">',
	'<head>',
		'<meta charset="UTF-8">',
		'<title>AltTrayPlus 3.1S</title>',
		'<link href="alt3.css" rel="stylesheet">',
		'<link href="icon.svg" rel="icon" sizes="any" type="image/svg+xml">',
	'</head>',
	'<body>',
		'<div id="app">',
			'<header id="header">',
				'<h1 id="logo"><img src="icon.svg"> AltTrayPlus 3.1S<a href="./?config" id="config" tabindex="-1" accesskey="c">⚙</a></h1>',
				'<input id="searchBox" class="search" type="text" placeholder="検索（件名・送信者・メール）" tabindex="-1" accesskey="s">',
			'</header>',
			'<form method="post">';
ob_flush();
if (filter_has_var(INPUT_GET, 'config')) {
	echo
				'<div id="account">',
					'<div id="modal" class="modal show">',
						'<div class="modal-content" autofocus>';
	ob_flush();
	for ($i = 0, $c = count($accounts); $i < $c; ++$i) {
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
} else {
	$count = $total = 0;
	$blacklisted = false;
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
			$client = new Pop3Client($id, $acc['server'], $acc['port'], $acc['user'], $acc['pass'], $acc['ssl'], $TIMEOUT);
			$client->connect();
			$client->login();
			[$count, $size] = $client->stat();
			$list = $client->listMessages();
			foreach ($list as $msgId => $msgSize) {
				if (isset($deleteRequests[$id]) && in_array($msgId, $deleteRequests[$id], true)) {
					$client->dele($msgId);
					continue;
				}
				$raw = $client->retr($msgId);
				$root = parse_raw_message($raw);
				$headers = $root->headers;
				$securityChecker = new EmailSecurityChecker($headers);
				$securityResult = $securityChecker->evaluate();
				$sender = get_sender_info($headers);
				$from = $root->getHeader('from') ?: '';
				$subject = $root->getHeader('subject') ?: '';
				$dateVal = $root->getHeader('date');
				$date = $dateVal ? (strtotime($dateVal) ?: '') : '';
				if ($from && is_blacklisted($from, $blacklist)) {
					$client->dele($msgId);
					$blacklisted = true;
					continue;
				}
				[$kind, $text] = extract_best_text($root);
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
					'header' => $headers,
					'headers' => json_encode($headers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
					'sender' => $sender,
					'security' => $securityResult,
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
				'<button id="remove" type="submit">チェックしたメールを削除</button>',
			'</form>',
			'<button id="notify-btn">受信通知を許可する</button>',
		'</div>',
		'<div id="modal" class="modal" role="dialog" aria-modal="true">',
			'<div class="modal-content">',
				($total >= 2 ?
				'<button class="modal-arrow left nav-prev" tabindex="0">◀</button>'.
				'<button class="modal-arrow right nav-next" tabindex="0">▶</button>' : ''),
				'<div id="tab-body" class="tab-content" autofocus></div>',
				'<div id="tab-header" class="tab-content"></div>',
				'<div class="modal-header">',
					'<div class="delete">',
						'<label for="modal-delete" tabindex="0">📤 Check Delete</label>',
						'<input id="modal-delete" type="checkbox" data-id="">',
					'</div>',
					'<button class="tab-btn" data-tab="tab-body" tabindex="0">Body</button>',
					'<button class="tab-btn" data-tab="tab-header" tabindex="0">Header</button>',
					'<button class="tab-btn" data-tab="tab-body" data-action="download" tabindex="0">📥 Body+Headers</button>',
					'<button class="close" tabindex="0">&times;</button>',
				'</div>',
			'</div>',
		'</div>',
		'<script id="altjs" src="alt3.js?r=', (int)$REFRESH_MS, '&t=', (int)$total, '"></script>',
	'</body>',
'</html>';
	if ($blacklisted) echo '<script>window.addEventListener("DOMContentLoaded", () => { location.reload(); });</script>';
}