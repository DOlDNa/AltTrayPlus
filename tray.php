<?php
mb_internal_encoding('UTF-8');
error_reporting(0);

// =========================
// Config
// =========================

$CONFIG_FILE = __DIR__. '/.poptrayrc';
$BLACKLIST_FILE = __DIR__. '/blacklist.txt';
$REFRESH_MS = 9e5; // 15min
$TIMEOUT = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST['delete']) {
	if ($a = filter_input_array(INPUT_POST, [
		'title' => ['filter' => FILTER_CALLBACK, 'options' => 'strip_tags', 'flags' => FILTER_REQUIRE_ARRAY],
		'name' => ['filter' => FILTER_CALLBACK, 'options' => 'strip_tags', 'flags' => FILTER_REQUIRE_ARRAY],
		'user' => ['filter' => FILTER_CALLBACK, 'options' => 'e', 'flags' => FILTER_REQUIRE_ARRAY+FILTER_FLAG_EMAIL_UNICODE],
		'host' => ['filter' => FILTER_CALLBACK, 'options' => 'strip_tags', 'flags' => FILTER_REQUIRE_ARRAY],
		'port' => ['filter' => FILTER_SANITIZE_NUMBER_INT, 'flags' => FILTER_REQUIRE_ARRAY],
		'password' => ['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY],
		'protocol' => ['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY],
	])) {
		for ($l=0, $e=count($a['name']); $l < $e; ++$l) {
			if ($a['name'][$l] && $a['host'][$l] && $a['port'][$l] && $a['user'][$l] && $a['protocol'][$l])
			$alt_accounts[] =
			$a['title'][$l]. PHP_EOL.
			'name="'. $a['name'][$l]. '"'. PHP_EOL.
			'host="'. $a['host'][$l]. '"'. PHP_EOL.
			'port="'. $a['port'][$l]. '"'. PHP_EOL.
			'user="'. $a['user'][$l]. '"'. PHP_EOL.
			'passwd="'. ($a['password'][$l] ? trim(base64_encode($a['password'][$l]), '=') : parse_ini_file($CONFIG_FILE, true)['account'. $l]['passwd'] ?? ''). '"'. PHP_EOL.
			'protocol="'. ($a['protocol'][$l] ?? ''). '"'. PHP_EOL. PHP_EOL;
		}
		if (isset($alt_accounts)) file_put_contents($CONFIG_FILE, implode($alt_accounts), LOCK_EX);
		header('Location: ./');
		exit;
	}
}
// =========================
// Helpers
// =========================
function render_account_html($acc) {
	echo
				'<section class="account">',
					'<h2 class="account-title">', ($hid = h($acc['id'])), '</h2>';
	if ($acc['error']) {
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
					'<div class="mail-row">',
						'<div class="delete">',
							'<input type="checkbox" name="delete[', $hid, ':', $m['id'], ']" value="1" id="', $hid, '-', $m['id'], '">',
							'<label for="', $hid, '-', $m['id'], '" tabindex="1">削除</label>',
						'</div>',
						'<main>',
							'<div class="subject" data-target="', $rowId, '" tabindex="0">', h($m['subject']);
			if (!empty($m['attachments'])) {
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
			if (!empty($m['attachments'])) {
				echo
							'<aside class="attach">添付:';
				foreach ($m['attachments'] as $i => $a) {
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
					return
							'<address>'.
								'<cite>'. strip_tags($m[2]). '</cite>'.
								'<input name="url-'. $hid. '-'. $replace_count. '" type="text" value="'. strip_tags($m[1]). '" readonly class="url" onclick="this.select()">'.
							'</address>';
				},
				$body
			);
			$body = preg_replace('/<br\s*\/?>/i', PHP_EOL, $body);
			$body = str_replace(["\r\n", PHP_EOL], '&#10;', trim($body));
			$body = preg_replace("/([\s\t]*&#10;){3,}/", '&#10;', $body);
			echo $body;
			echo
						'</div>',
						'<div id="', $rowId, '_headers" class="headers">';
			foreach ($m['header'] as $k => $v) {
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
}

function h($s) {
	return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function e($e) {
	if (filter_var($e, FILTER_VALIDATE_EMAIL)) return $e;
	elseif (false !== strpos($e, '@')) {
		$ex = explode('@', $e);
		return filter_var(idn_to_ascii($ex[0]). '@'. idn_to_ascii($ex[1]), FILTER_SANITIZE_EMAIL);
	}
}
function s_bytes($bytes) {
	if ($bytes > 1024 * 1024) return sprintf('%.1f MB', $bytes / 1024 / 1024);
	if ($bytes > 1024) return sprintf('%.1f KB', $bytes / 1024);
	return $bytes. ' B';
}
function f(int $i) {
	if (is_file($rc = '.poptrayrc') && is_readable($rc)) $ini = parse_ini_file($rc, true);
	if (isset($accounts[$i])) $ini_str = parse_ini_string($accounts[$i], true);
	$inis = $ini['account'.$i]['protocol'] ?? $ini_str['account'. $i]['protocol'] ?? '';
	return
	'<fieldset class="fieldset-account">'.
		'<input class="input-text" name=title[] type=hidden value="[account'. $i. ']">'.
		'<input class="input-text" name=name[] type=text placeholder="タイトル: TEST 1" value="'. ($ini['account'. $i]['name'] ?? $ini_str['account'. $i]['name'] ?? ''). '">'.
		'<input class="input-text" name=user[] type=text placeholder="ユーザー: user@example.com" value="'. ($ini['account'. $i]['user'] ?? $ini_str['account'. $i]['user'] ?? ''). '">'.
		'<input class="input-text" name=host[] type=text placeholder="ホスト: mail.example.com" value="'. ($ini['account'. $i]['host'] ?? $ini_str['account'. $i]['host'] ?? ''). '">'.
		'<input class="input-text" name=port[] type=text placeholder="ポート: 995" value="'. ($ini['account'. $i]['port'] ?? $ini_str['account'. $i]['port'] ?? ''). '">'.
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

// =========================
// POP3 client (stream_socket_client)
// =========================

class Pop3Client {
	public $host;
	public $port;
	public $user;
	public $pass;
	public $ssl;
	public $timeout;
	public $fp;
	public $id;
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
		$errno = 0;
		$errstr = '';
		$this->fp = @stream_socket_client(
			$proto. $this->host. ':'. $this->port,
			$errno,
			$errstr,
			$this->timeout,
			STREAM_CLIENT_CONNECT
		);
		if (!$this->fp) {
			throw new Exception("Connection failed: $errstr ($errno)");
		}
		stream_set_timeout($this->fp, $this->timeout);
		$greet = $this->readLine();
		if (strpos($greet, '+OK') !== 0) {
			throw new Exception("Server rejected: $greet");
		}
	}
	public function cmd($cmd, $expectOk = true) {
		$this->writeLine($cmd);
		$resp = $this->readLine();
		if ($expectOk && strpos($resp, '+OK') !== 0) {
			throw new Exception("POP3 error on '$cmd': $resp");
		}
		return $resp;
	}
	public function writeLine($line) {
		fwrite($this->fp, $line. "\r\n");
	}
	public function readLine() {
		$line = fgets($this->fp);
		if ($line === false) {
			throw new Exception("Connection closed");
		}
		return rtrim($line, "\r\n");
	}
	public function login() {
		$this->cmd("USER ". $this->user);
		$this->cmd("PASS ". $this->pass);
	}
	public function stat() {
		$resp = $this->cmd("STAT");
		$parts = explode(' ', $resp);
		$count = isset($parts[1]) ? (int)$parts[1] : 0;
		$size = isset($parts[2]) ? (int)$parts[2] : 0;
		return [$count, $size];
	}
	public function listMessages() {
		$this->cmd("LIST");
		$list = [];
		while (true) {
			$line = $this->readLine();
			if ($line === '.') break;
			$parts = explode(' ', $line);
			if (count($parts) >= 2) {
				$list[(int)$parts[0]] = (int)$parts[1];
			}
		}
		return $list;
	}
	public function retr($id) {
		$this->cmd("RETR $id");
		$data = '';
		while (true) {
			$line = fgets($this->fp);
			if ($line === false) break;
			$trim = rtrim($line, "\r\n");
			if ($trim === '.') break;
			if (isset($trim[0]) && $trim[0] === '.' && strpos($trim, '..') === 0) {
				$trim = substr($trim, 1);
			}
			$data .= $trim. "\r\n";
		}
		return $data;
	}
	public function dele($id) {
		$this->cmd("DELE $id");
	}
	public function quit() {
		if ($this->fp) {
			$this->writeLine("QUIT");
			fclose($this->fp);
			$this->fp = null;
		}
	}
}

// =========================
// MIME parsing
// =========================

class MimePart {
	public $headers = [];
	public $body = '';
	public $parts = [];
	public $contentType = 'text/plain';
	public $charset = 'UTF-8';
	public $encoding = '7bit';
	public $filename = null;
	public $isAttachment = false;
	public function __construct($headers = [], $body = '') {
		$this->headers = $headers;
		$this->body = $body;
		$this->parseHeaders();
	}
	protected function parseHeaders() {
		$ct = $this->getHeader('content-type');
		if ($ct) {
			$this->parseContentType($ct);
		}
		$ce = $this->getHeader('content-transfer-encoding');
		if ($ce) {
			$this->encoding = strtolower(trim($ce));
		}
		$cd = $this->getHeader('content-disposition');
		if ($cd) {
			$this->parseContentDisposition($cd);
		}
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
				list($k, $v) = explode('=', $p, 2);
				$k = strtolower(trim($k));
				$v = trim($v, " \t\"'");
				if ($k === 'charset') {
					$this->charset = strtoupper($v);
				} elseif ($k === 'name') {
					$this->filename = $this->decodeHeader($v);
				} elseif ($k === 'boundary') {
				}
			}
		}
	}
	protected function parseContentDisposition($cd) {
		$parts = explode(';', $cd);
		$disp = strtolower(trim(array_shift($parts)));
		if ($disp === 'attachment' || $disp === 'inline') {
			$this->isAttachment = true;
		}
		foreach ($parts as $p) {
			if (strpos($p, '=') !== false) {
				list($k, $v) = explode('=', $p, 2);
				$k = strtolower(trim($k));
				$v = trim($v, " \t\"'");
				if ($k === 'filename') {
					$this->filename = $this->decodeHeader($v);
				}
			}
		}
	}
	public function decodeBody() {
		$b = $this->body;
		switch ($this->encoding) {
			case 'base64':
				$b = base64_decode($b);
				break;
			case 'quoted-printable':
				$b = quoted_printable_decode($b);
				break;
			default:
				break;
		}
		if ($this->charset && strtoupper($this->charset) !== 'UTF-8') {
			$b = @mb_convert_encoding($b, 'UTF-8', $this->charset);
		}
		return $b;
	}
	public function decodeHeader($str) {
		$decoded = mb_decode_mimeheader($str);
		return $decoded;
	}
}
function parse_raw_message($raw) {
	$raw = str_replace("\r\n", "\n", $raw);
	$raw = str_replace("\r", "\n", $raw);
	$parts = explode("\n\n", $raw, 2);
	$headerText = isset($parts[0]) ? $parts[0] : '';
	$bodyText = isset($parts[1]) ? $parts[1] : '';

	$headers = [];
	$lines = explode("\n", $headerText);
	$current = '';
	$currentName = '';
	foreach ($lines as $line) {
		if (preg_match('/^\s+/', $line) && $currentName !== '') {
			$current.= ' '. trim($line);
		} else {
			if ($currentName !== '') {
				$headers[$currentName] = $current;
			}
			if (strpos($line, ':') !== false) {
				list($name, $val) = explode(':', $line, 2);
				$currentName = strtolower(trim($name));
				$current = trim($val);
			} else {
				$currentName = '';
				$current = '';
			}
		}
	}
	if ($currentName !== '') {
		$headers[$currentName] = $current;
	}
	$root = new MimePart($headers, $bodyText);
	parse_multipart_recursive($root);
	return $root;
}
function parse_multipart_recursive(MimePart $part) {
	if (strpos($part->contentType, 'multipart/') !== 0) {
		return;
	}
	$ct = $part->getHeader('content-type');
	if (!$ct) return;
	$boundary = null;
	$pieces = explode(';', $ct);
	array_shift($pieces);
	foreach ($pieces as $p) {
		if (strpos($p, '=') !== false) {
			list($k, $v) = explode('=', $p, 2);
			$k = strtolower(trim($k));
			$v = trim($v, " \t\"'");
			if ($k === 'boundary') {
				$boundary = $v;
				break;
			}
		}
	}
	if (!$boundary) return;
	$body = $part->body;
	$body = str_replace("\r\n", "\n", $body);
	$segments = preg_split('/\n--'. preg_quote($boundary, '/'). '(--)?\s*/', "\n". $body);
	$children = [];
	foreach ($segments as $seg) {
		$seg = ltrim($seg, "\n");
		if ($seg === '' || $seg === "--") continue;
		$subParts = explode("\n\n", $seg, 2);
		$hText = isset($subParts[0]) ? $subParts[0] : '';
		$bText = isset($subParts[1]) ? $subParts[1] : '';
		$headers = [];
		$lines = explode("\n", $hText);
		$current = '';
		$currentName = '';
		foreach ($lines as $line) {
			if (preg_match('/^\s+/', $line) && $currentName !== '') {
				$current .= ' '. trim($line);
			} else {
				if ($currentName !== '') {
					$headers[$currentName] = $current;
				}
				if (strpos($line, ':') !== false) {
					list($name, $val) = explode(':', $line, 2);
					$currentName = strtolower(trim($name));
					$current = trim($val);
				} else {
					$currentName = '';
					$current = '';
				}
			}
		}
		if ($currentName !== '') {
			$headers[$currentName] = $current;
		}
		$child = new MimePart($headers, $bText);
		parse_multipart_recursive($child);
		$children[] = $child;
	}
	$part->parts = $children;
}

function extract_best_text(MimePart $part) {
	$texts = [
		'text/html' => null,
		'text/plain' => null,
	];
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
		if ($decoded !== false) {
			return $decoded;
		}
		return mb_decode_mimeheader($val);
	}
	return $val;
}


// =========================
// Load accounts from .poptrayrc (simple INI-like)
// =========================

function load_accounts($file) {
	if (!file_exists($file)) return [];
	$accounts = [];
	$ini = parse_ini_file($file, true);
	foreach ($ini as $name => $cfg) {
		if (empty($cfg['host']) || empty($cfg['user']) || empty($cfg['passwd'])) continue;
		$acc = [
			'id' => $cfg['name'],
			'server' => $cfg['host'],
			'port' => isset($cfg['port']) ? (int)$cfg['port'] : 995,
			'ssl' => $cfg['protocol'],
			'user' => $cfg['user'],
			'pass' => base64_decode($cfg['passwd']),
		];
		$accounts[] = $acc;
	}
	return $accounts;
}

// =========================
// Handle delete requests
// =========================

$deleteRequests = [];
$delete = filter_input(INPUT_POST, 'delete', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
if (!empty($delete)) {
	ob_start();
	foreach ($delete as $key => $val) {
		if (!$val) continue;
		if (strpos($key, ':') !== false) {
			list($aid, $mid) = explode(':', $key, 2);
			$deleteRequests[$aid][] = (int)$mid;
		}
	}
	header('Location: ./');
}

// =========================
// Main: check accounts
// =========================

$accounts = load_accounts($CONFIG_FILE);
$blacklist = load_blacklist($BLACKLIST_FILE);
