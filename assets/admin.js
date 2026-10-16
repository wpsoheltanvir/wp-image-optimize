(() => {
	'use strict';
	const start = document.querySelector('.wpio-bulk-start');
	const panel = document.querySelector('.wpio-bulk-progress');
	if (!start || !panel || !window.wpioBulk) return;

	const status = panel.querySelector('.wpio-progress-copy span');
	const bar = panel.querySelector('.wpio-progress-track i');
	const cancel = panel.querySelector('.wpio-bulk-cancel');
	let stopped = false;
	let count = 0;
	let lastId = 0;

	const finish = (message) => {
		status.textContent = message;
		bar.style.width = '100%';
		start.disabled = false;
		start.textContent = 'Optimize Media Library';
		cancel.hidden = true;
		setTimeout(() => { panel.hidden = true; }, 4500);
	};

	const next = () => {
		if (stopped) {
			finish(`Stopped after ${count} image${count === 1 ? '' : 's'}.`);
			return;
		}
		const body = new URLSearchParams({ action: 'wpio_bulk_optimize', nonce: wpioBulk.nonce, last_id: lastId });
		fetch(wpioBulk.url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body })
			.then(response => response.json())
			.then(response => {
				if (!response.success) throw new Error(response.data?.message || 'Optimization request failed.');
				if (response.data.done) {
					finish(`Finished. Optimized ${count} image${count === 1 ? '' : 's'}.`);
					return;
				}
				count += 1;
				lastId = response.data.last_id;
				status.textContent = response.data.error
					? `Skipped ${response.data.name || 'an image'} — continuing…`
					: `Optimized ${count} image${count === 1 ? '' : 's'}${response.data.name ? ': ' + response.data.name : ''}`;
				bar.style.width = `${Math.min(94, 8 + Math.log(count + 1) * 15)}%`;
				window.setTimeout(next, 80);
			})
			.catch(error => {
				status.textContent = error.message || 'Optimization stopped because of an error.';
				start.disabled = false;
				cancel.hidden = true;
			});
	};

	start.addEventListener('click', () => {
		if (!window.confirm('Optimize every JPEG and PNG in the Media Library? This overwrites the original files.')) return;
		stopped = false;
		count = 0;
		lastId = 0;
		panel.hidden = false;
		cancel.hidden = false;
		status.textContent = 'Starting…';
		bar.style.width = '3%';
		start.disabled = true;
		start.textContent = 'Optimization running…';
		next();
	});
	cancel.addEventListener('click', () => { stopped = true; });
})();