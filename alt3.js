const mailRows = Array.from(document.querySelectorAll('.mail-row'));
let currentIndex = 0;

const app = document.getElementById('app');
const modal = document.getElementById('modal');
const closeBtn = document.querySelector('.close');
const tabButtons = document.querySelectorAll('.tab-btn');
const tabContents = document.querySelectorAll('.tab-content');
const navPrev = document.querySelector('.nav-prev');
const navNext = document.querySelector('.nav-next');

const checkboxes = document.querySelectorAll('.delete input[type="checkbox"]');
const deleteBtn = document.querySelector('#remove');
const searchBox = document.getElementById('searchBox');
const notifyBtn = document.getElementById('notify-btn');
const t = Number(new URL(document.getElementById('altjs').src).searchParams.get('t'));

const showMail = index => {
	const row = mailRows[index];
	if (!row) return;

	const source = row.dataset.id;
	const body = document.getElementById(source);
	const header = document.getElementById(source + '_headers');

	if (!body || !header) return;

	document.getElementById('tab-body').innerHTML = body.innerHTML;
	document.getElementById('tab-header').textContent = header.textContent;
	modal.dataset.source = source;

	// モーダル内の削除チェックボックスと一覧側を同期
	const listCheckbox = row.querySelector('.delete input[type="checkbox"][data-id]');
	const modalCheckbox = modal.querySelector('.delete input[type="checkbox"][data-id]');

	if (listCheckbox && modalCheckbox) {
		modalCheckbox.dataset.id = listCheckbox.dataset.id;
		modalCheckbox.checked = listCheckbox.checked;
	}

	updateNavVisibility();
};

const updateNavVisibility = () => {
	if (!navPrev || !navNext) return;
	navPrev.style.visibility = currentIndex <= 0 ? 'hidden' : 'visible';
	navNext.style.visibility = currentIndex >= mailRows.length - 1 ? 'hidden' : 'visible';
};

const base64DecodeUnicode = str => {
	return decodeURIComponent(
		atob(str)
			.split('')
			.map(c => '%' + c.charCodeAt(0).toString(16).padStart(2, '0'))
			.join('')
	);
};

const saveTextFile = (text = 'Error', filename = Date.now()) => {
	const blob = new Blob([text], { type: 'text/plain' });
	const url = URL.createObjectURL(blob);
	const a = document.createElement('a');
	a.href = url;
	a.download = filename + '.txt';
	a.click();
	URL.revokeObjectURL(url);
};

const updateButtonVisibility = () => {
	const checkedCount = [...checkboxes].filter(cb => cb.checked).length;
	if (checkedCount > 0) {
		deleteBtn.classList.add('show');
		deleteBtn.setAttribute('accesskey', 'd');
	} else {
		deleteBtn.classList.remove('show');
		deleteBtn.removeAttribute('accesskey');
	}
};

// チェックボックスの同期（一覧・モーダル共通）
document.addEventListener('change', e => {
	if (!e.target.matches('input[type="checkbox"][data-id]')) return;
	const id = e.target.dataset.id;
	const checked = e.target.checked;
	document
		.querySelectorAll(`input[type="checkbox"][data-id="${id}"]`)
		.forEach(cb => {
			cb.checked = checked;
		});
	updateButtonVisibility();
});

// タブ切り替え
tabButtons.forEach(btn => {
	btn.classList.remove('active');

	btn.addEventListener('keydown', e => {
		if (e.key === 'Enter' || e.key === ' ') e.currentTarget.click();
	});

	btn.addEventListener('click', e => {
		const targetBtn = e.currentTarget;
		const tabId = targetBtn.dataset.tab;

		tabButtons.forEach(b => b.classList.remove('active'));
		tabContents.forEach(c => {
			c.classList.remove('active');
			c.removeAttribute('autofocus');
			c.removeAttribute('tabindex');
		});

		targetBtn.classList.add('active');
		const tab = document.getElementById(tabId);
		if (tab) {
			tab.classList.add('active');
			tab.setAttribute('tabindex', 0);
			tab.setAttribute('autofocus', '');
			tab.scrollTop = 0;
			tab.focus();
		}

		if (targetBtn.dataset.action === 'download') {
			const source = modal.dataset.source;
			const dataEl = document.getElementById(source + '_header_body');
			if (!dataEl) return;

			const data = dataEl.dataset.base64;
			const header = document.getElementById('tab-header').textContent;

			const subject = header.replace(/\r?\n[ \t]+/g, ' ').match(/^Subject:\s*(.*)$/im)?.[1] || '';
			saveTextFile(base64DecodeUnicode(data), subject);
		}
	});
});

const closeModal = () => {
	modal.classList.remove('show');
	app.style.display = 'block';
	updateNavVisibility();
};

closeBtn.addEventListener('click', closeModal);

window.addEventListener('click', e => {
	if (e.target === modal) {
		closeModal();
		e.target.focus();
	}
});

// 保存ボタン（一覧）
document.querySelectorAll('.save').forEach(el => {
	el.addEventListener('keydown', e => {
		if (e.key === 'Enter' || e.key === ' ') e.currentTarget.click();
	});
	el.addEventListener('click', () => {
		const item = el.closest('.mail-row');
		if (!item) return;
		const body = item.querySelector('.body');
		const subject = item.querySelector('.subject');
		if (!body || !subject) return;

		const text = body.innerText.trim();
		const title = subject.innerText.trim();
		saveTextFile(text, title);
	});
});

// 件名クリックでモーダル表示
document.querySelectorAll('.subject').forEach(el => {
	el.setAttribute('tabindex', 0);

	el.addEventListener('keydown', e => {
		if (e.key === 'Enter' || e.key === ' ') e.currentTarget.click();
	});

	el.addEventListener('click', e => {
		const row = e.currentTarget.closest('.mail-row');
		if (!row) return;

		modal.classList.add('show');
		currentIndex = mailRows.indexOf(row);
		showMail(currentIndex);

		tabButtons.forEach(c => c.classList.remove('active'));
		tabContents.forEach(c => c.classList.remove('active'));

		const bodyTab = document.getElementById('tab-body');
		if (bodyTab) {
			bodyTab.classList.add('active');
			bodyTab.setAttribute('tabindex', 0);
		}
		closeBtn.setAttribute('tabindex', 0);

		setTimeout(() => {
			bodyTab?.focus();
			if (bodyTab) bodyTab.scrollTop = 0;
			app.style.display = 'none';
		}, 50);
	});
});

// Enter / Space で label 経由のチェックボックスをトグル
document.addEventListener('keydown', e => {
	if (e.key === 'Enter' || e.key === ' ') {
		const forId = e.target.htmlFor;
		if (forId) {
			const f = document.getElementById(forId);
			if (f && f.type === 'checkbox') {
				f.checked = !f.checked;
				// data-id が付いていれば同期される
				if (f.dataset.id) {
					document
						.querySelectorAll(`input[type="checkbox"][data-id="${f.dataset.id}"]`)
						.forEach(cb => (cb.checked = f.checked));
				}
				updateButtonVisibility();
			}
		}
	}

	if (!modal.classList.contains('show')) return;

	if (e.key === 'ArrowRight') {
		if (e.shiftKey) return;
		if (currentIndex < mailRows.length - 1) {
			currentIndex++;
			showMail(currentIndex);
		}
	}
	if (e.key === 'ArrowLeft') {
		if (e.shiftKey) return;
		if (currentIndex > 0) {
			currentIndex--;
			showMail(currentIndex);
		}
	}
	if (e.key === 'Escape') {
		closeModal();
	}
});

// 検索
searchBox.addEventListener('input', () => {
	const keyword = searchBox.value.toLowerCase();
	const rows = document.querySelectorAll('.mail-row');
	rows.forEach(row => {
		const text = row.innerText.toLowerCase();
		row.style.display = text.includes(keyword) ? 'flex' : 'none';
	});
});

// 通知関連
if (Notification.permission === 'denied') {
	notifyBtn.style.display = 'none';
}

if (navPrev) {
	navPrev.addEventListener('click', () => {
		if (currentIndex > 0) {
			currentIndex--;
			showMail(currentIndex);
		}
	});
}

if (navNext) {
	navNext.addEventListener('click', () => {
		if (currentIndex < mailRows.length - 1) {
			currentIndex++;
			showMail(currentIndex);
		}
	});
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

// 自動リロード
(loop = () => {
	const r = Number(new URL(document.getElementById('altjs').src).searchParams.get('r'));
	setTimeout(() => {
		if (!modal.classList.contains('show')) location.reload();
		loop();
	}, r);
})();

updateButtonVisibility();
updateNavVisibility();
