(function () {
	const cfg = window.atsMoknahAnalytics;
	if (!cfg || !cfg.postId || !cfg.restUrl) return;

	const HEARTBEAT_SECONDS = 10;
	const bound = new WeakSet();
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
				listen_seconds: Math.max(0, Math.round(listenSeconds))
			})
		}).catch(() => {});
	};

	const bindPlayer = (player) => {
		if (!player || bound.has(player)) return;
		bound.add(player);

		if (!impressionSent) {
			impressionSent = true;
			send('impression');
		}

		let lastTime = player.currentTime || 0;
		let accumulated = 0;
		let completedForThisPlay = false;
		let heartbeatTimer = null;

		const flush = () => {
			if (accumulated > 0) {
				send('heartbeat', accumulated);
				accumulated = 0;
			}
		};

		const startHeartbeat = () => {
			if (heartbeatTimer) return;
			heartbeatTimer = setInterval(flush, HEARTBEAT_SECONDS * 1000);
		};

		const stopHeartbeat = () => {
			if (heartbeatTimer) {
				clearInterval(heartbeatTimer);
				heartbeatTimer = null;
			}
		};

		player.addEventListener('play', () => {
			completedForThisPlay = false;
			lastTime = player.currentTime || 0;
			send('play');
			startHeartbeat();
		}, { passive: true });

		player.addEventListener('timeupdate', () => {
			const current = player.currentTime || 0;
			if (current > lastTime) {
				accumulated += current - lastTime;
			}
			lastTime = current;
		}, { passive: true });

		player.addEventListener('ended', () => {
			if (!completedForThisPlay) {
				completedForThisPlay = true;
				flush();
				send('complete');
			}
			stopHeartbeat();
			accumulated = 0;
		}, { passive: true });

		player.addEventListener('pause', () => {
			flush();
			stopHeartbeat();
		}, { passive: true });

		window.addEventListener('beforeunload', flush);
		document.addEventListener('visibilitychange', () => {
			if (document.visibilityState === 'hidden') {
				flush();
				stopHeartbeat();
			} else if (!player.paused) {
				startHeartbeat();
			}
		});
	};

	const selector = cfg.audioSelector || 'audio';
	document.querySelectorAll(selector).forEach(bindPlayer);

	document.addEventListener('play', (e) => {
		if (e.target && e.target.tagName === 'AUDIO') bindPlayer(e.target);
	}, true);

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

	if (cfg.playButtonSelector) {
		document.addEventListener('click', (e) => {
			const btn = e.target.closest(cfg.playButtonSelector);
			if (!btn) return;
			const player = document.querySelector(selector);
			if (player) {
				bindPlayer(player);
				player.play().catch(() => {});
			}
		}, true);
	}
})();