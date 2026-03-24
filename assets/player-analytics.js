(function () {
	const cfg = window.atsMoknahAnalytics;
	if (!cfg || !cfg.postId || !cfg.restUrl) return;

	let impressionSent = false;

	const send = (event, listenSeconds = 0) => {
		fetch(cfg.restUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce
			},
			body: JSON.stringify({
				post_id: cfg.postId,
				event,
				listen_seconds: listenSeconds
			})
		}).catch(() => {});
	};

	const bindPlayer = (player) => {
		if (!player || player.dataset.atsBound) return;
		player.dataset.atsBound = '1';

		if (!impressionSent) {
			impressionSent = true;
			send('impression');
		}

		let lastTime = 0;
		let accumulated = 0;
		let completed = false;

		player.addEventListener('play', () => send('play'), { passive: true });

		player.addEventListener('timeupdate', () => {
			const current = player.currentTime || 0;
			if (current > lastTime) accumulated += current - lastTime;
			lastTime = current;
		}, { passive: true });

		player.addEventListener('ended', () => {
			if (!completed) {
				completed = true;
				send('complete', Math.round(accumulated));
				accumulated = 0;
			}
		}, { passive: true });

		const flush = () => {
			if (accumulated > 0) {
				send('heartbeat', Math.round(accumulated));
				accumulated = 0;
			}
		};
		player.addEventListener('pause', flush, { passive: true });
		window.addEventListener('beforeunload', flush);
		document.addEventListener('visibilitychange', () => {
			if (document.visibilityState === 'hidden') flush();
		});
	};

	// 1) Bind the audio element from the template (if present now)
	if (cfg.audioSelector) {
		document.querySelectorAll(cfg.audioSelector).forEach(bindPlayer);
	} else {
		document.querySelectorAll('audio').forEach(bindPlayer);
	}

	// 2) Catch any audio that starts playing later (e.g., injected nodes)
	document.addEventListener('play', (e) => {
		if (e.target && e.target.tagName === 'AUDIO') bindPlayer(e.target);
	}, true);

	// 3) Observe DOM mutations to bind future audio elements
	const observer = new MutationObserver((mutations) => {
		mutations.forEach((m) => {
			m.addedNodes.forEach((node) => {
				if (node.nodeType !== 1) return;
				if (node.tagName === 'AUDIO') {
					bindPlayer(node);
				} else if (node.querySelectorAll) {
					node.querySelectorAll('audio').forEach(bindPlayer);
				}
			});
		});
	});
	observer.observe(document.documentElement, { childList: true, subtree: true });

	// 4) Also listen to your custom play button to fire play immediately on click
	if (cfg.playButtonSelector) {
		document.addEventListener('click', (e) => {
			const btn = e.target.closest(cfg.playButtonSelector);
			if (!btn) return;
			// If audio is not yet bound, bind and record play promptly
			const player = cfg.audioSelector
				? document.querySelector(cfg.audioSelector)
				: document.querySelector('audio');
			if (player) {
				bindPlayer(player);
				send('play');
			}
		}, true);
	}
})();