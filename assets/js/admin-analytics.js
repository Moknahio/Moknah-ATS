(function () {
	const table = document.querySelector('.ats-analytics-table');
	if (!table) return;

	const getCellValue = (tr, idx, type) => {
		const text = tr.children[idx].innerText.trim();
		if (type === 'int') return parseInt(text.replace(/,/g, ''), 10) || 0;
		if (type === 'float') return parseFloat(text.replace(/,/g, '')) || 0;
		return text.toLowerCase();
	};

	table.querySelectorAll('th[data-sort]').forEach((th, idx) => {
		th.dataset.order = 'none';
		th.addEventListener('click', () => {
			const type = th.dataset.sort;
			const tbody = table.querySelector('tbody');
			const rows = Array.from(tbody.querySelectorAll('tr'));

			table.querySelectorAll('th[data-sort]').forEach(h => {
				if (h !== th) { h.dataset.order = 'none'; h.classList.remove('ats-sorted-asc','ats-sorted-desc'); }
			});

			th.dataset.order = (th.dataset.order === 'asc') ? 'desc' : 'asc';
			const order = th.dataset.order;

			rows.sort((a, b) => {
				const va = getCellValue(a, idx, type);
				const vb = getCellValue(b, idx, type);
				if (va < vb) return order === 'asc' ? -1 : 1;
				if (va > vb) return order === 'asc' ? 1 : -1;
				return 0;
			});

			th.classList.remove('ats-sorted-asc','ats-sorted-desc');
			th.classList.add(order === 'asc' ? 'ats-sorted-asc' : 'ats-sorted-desc');
			th.setAttribute('aria-sort', order === 'asc' ? 'ascending' : 'descending');

			tbody.innerHTML = '';
			rows.forEach(r => tbody.appendChild(r));
		});
	});
	const range = document.getElementById('ats-range');
	const customWrap = document.getElementById('ats-custom-dates');
	if (range && customWrap) {
		const toggle = () => {
			const show = range.value === 'custom';
			customWrap.style.display = show ? 'inline-flex' : 'none';
		};
		toggle();
		range.addEventListener('change', toggle);
	}
})();