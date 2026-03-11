// --- Base64 を Unicode 文字列にデコードする関数 ---
function base64DecodeUnicode(str) {
	return decodeURIComponent(
		atob(str)
			.split('')
			.map(c => '%' + c.charCodeAt(0).toString(16).padStart(2, '0'))
			.join('')
	);
}

// --- テキストファイルを保存するユーティリティ ---
saveTextFile = (text = 'Error', filename = Date.now()) => {
	const blob = new Blob([text], { type: 'text/plain' });
	const url = URL.createObjectURL(blob);
	const a = document.createElement('a');
	a.href = url;
	a.download = filename + '.txt';
	a.click();
	URL.revokeObjectURL(url);
};

// --- 削除チェックボックスと削除ボタンの制御 ---
const checkboxes = document.querySelectorAll('.delete input[type="checkbox"]');
const deleteBtn = document.querySelector('#remove');

// チェック数に応じて削除ボタンの表示/非表示を切り替える
updateButtonVisibility = () => {
	const checkedCount = [...checkboxes].filter(cb => cb.checked).length;
	if (checkedCount > 0) {
		deleteBtn.classList.add('show');
	} else {
		deleteBtn.classList.remove('show');
	}
};

// チェックボックスにイベント付与
checkboxes.forEach(cb =>
	cb.addEventListener('change', updateButtonVisibility)
);

// --- モーダル関連の要素 ---
const app = document.getElementById('app');
const modal = document.getElementById('modal');
const closeBtn = document.querySelector('.close');
const tabButtons = document.querySelectorAll('.tab-btn');
const tabContents = document.querySelectorAll('.tab-content');

// --- モーダル内タブ切り替え処理 ---
tabButtons.forEach(btn => {
	btn.classList.remove('active');
	btn.addEventListener('keydown', e => {
		if (e.key === 'Enter') e.target.click();
	});
	btn.addEventListener('click', e => {
		tabButtons.forEach(b => b.classList.remove('active'));
		tabContents.forEach(c => {c.classList.remove('active'),c.removeAttribute('autofocus'),c.removeAttribute('tabindex')});
		e.target.classList.add('active');
		document.getElementById(e.target.getAttribute('data-tab')).classList.add('active');
		document.getElementById(btn.dataset.tab).setAttribute('tabindex', 1);
		document.getElementById(btn.dataset.tab).focus();
		document.getElementById(btn.dataset.tab).setAttribute('autofocus', '');
		document.getElementById(btn.dataset.tab).scrollTop = 0;
		if (e.target.dataset.action === 'download') {
			const item = e.target.closest('.modal-content');
			const source = document.getElementById('modal').dataset.source;
			const data = document.getElementById(source + '_header_body').dataset.base64;
			const header = item.querySelector('#tab-header').innerText;
			const subject = header.replace(/\r?\n[ \t]+/g, ' ').match(/^Subject:\s*(.*)$/im)?.[1] || '';
			saveTextFile(base64DecodeUnicode(data), subject);
		}
	});
});

// --- モーダルを閉じる処理 ---
closeBtn.addEventListener('click', () => {
	modal.classList.remove('show');
	app.setAttribute('style', 'display:block');
});

// モーダル外クリックで閉じる
window.addEventListener('click', e => {
	if (e.target === modal) {
		modal.classList.remove('show');
		app.setAttribute('style', 'display:block');
		e.target.focus();
	}
});

// --- メール一覧の「保存」ボタン処理 ---
document.querySelectorAll('.save').forEach(el => {
	el.addEventListener('keydown', e => {
		if (e.key === 'Enter') e.target.click();
	});
	el.addEventListener('click', () => {
		const item = el.closest('.mail-row');
		const body = item.querySelector('.body');
		const subject = item.querySelector('.subject');
		const text = body.innerText.trim();
		const title = subject.innerText.trim();
		saveTextFile(text, title);
	});
});

// --- メール件名クリックでモーダル表示 ---
document.querySelectorAll('.subject').forEach(el => {
	el.setAttribute('tabindex', 0);
	el.addEventListener('keydown', e => {
		if (e.key === 'Enter') e.target.click();
	});
	el.addEventListener('click', em => {
		modal.classList.add('show');
		const target = em.target.getAttribute('data-target');
		const body = document.getElementById(target);
		const header = document.getElementById(target + '_headers');
		const bodyTab = document.getElementById('tab-body');
		document.getElementById('tab-header').innerHTML = header.innerHTML;
		bodyTab.innerHTML = body.innerHTML;
		tabButtons.forEach(c => c.classList.remove('active'));
		tabContents.forEach(c => c.classList.remove('active'));
		modal.dataset.source = target;
		document.querySelector('[data-tab="tab-body"]').classList.add('active');
		bodyTab.classList.add('active');
		bodyTab.setAttribute('tabindex', 0);
		closeBtn.setAttribute('tabindex', 0);
		setTimeout(() => {
			bodyTab.focus();
			bodyTab.scrollTop = 0;
			app.setAttribute('style', 'display:none');
		}, 50);
	});
});

// --- Enter キーでチェックボックス ON/OFF、Esc でモーダル閉じる ---
document.addEventListener('keydown', e => {
	if (e.key === 'Enter') {
		if ((f = document.getElementById(e.target.htmlFor))) {
			f.checked = !f.checked;
			updateButtonVisibility();
		}
	}

	if (!modal.classList.contains('show')) return;

	if (e.key === 'Escape') {
		modal.classList.remove('show');
		app.setAttribute('style', 'display:block');
	}
});

// --- メール検索機能 ---
const searchBox = document.getElementById('searchBox');
searchBox.addEventListener('input', () => {
	const keyword = searchBox.value.toLowerCase();
	const rows = document.querySelectorAll('.mail-row');
	rows.forEach(row => {
		const text = row.innerText.toLowerCase();
		row.style.display = text.includes(keyword) ? 'flex' : 'none';
	});
});

// --- 新着通知処理 ---
const t = Number(new URL(document.getElementById('altjs').src).searchParams.get('t'));
const notifyBtn = document.getElementById('notify-btn');

if (Notification.permission === 'denied') {
	notifyBtn.style.display = 'none';
}

if (t > 0) {
	document.title = t + '件受信 - ' + document.title;
	if (Notification.permission === 'granted') {
		notifyBtn.style.display = 'none';
		Notification.requestPermission().then(() => notice());
	}
	notifyBtn.addEventListener('click', () => {
		Notification.requestPermission().then(result => {
			if (result === 'granted') notice();
		});
		notifyBtn.classList.add('fade-out');
		setTimeout(() => (notifyBtn.style.display = 'none'), 400);
	});
	notice = () =>
		new Notification(document.title, {
			body: '新着メールが' + t + '件あります。',
			icon: './icon.svg'
		});
}

// --- 自動リロード（モーダル表示中は停止） ---
(l = () =>
	setTimeout(() => {
		if (!modal.classList.contains('show')) location.reload();
		l();
	}, Number(new URL(document.getElementById('altjs').src).searchParams.get('r'))))();
