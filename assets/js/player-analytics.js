(function () {
	const cfg = window.atsmoknahAnalytics;
	if (!cfg || !cfg.postId || !cfg.restUrl) return;

	// GLOBAL LOCK: Prevents the script from running twice
	if (window['ats_tracking_init_' + cfg.postId]) return;
	window['ats_tracking_init_' + cfg.postId] = true;

	// --- 1. NETWORK SENDER ---
	const postJSON = (body, useBeacon = false) => {
		if (useBeacon && navigator.sendBeacon) {
			try {
				const blob = new Blob([JSON.stringify(body)], { type: 'application/json' });
				if (navigator.sendBeacon(cfg.restUrl, blob)) return;
			} catch (_) {}
		}

		fetch(cfg.restUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce
			},
			body: JSON.stringify(body),
			keepalive: useBeacon
		}).catch(() => {});
	};

	const send = (event, listenSeconds = 0, useBeacon = false) => {
		postJSON({
			post_id: cfg.postId,
			event,
			listen_seconds: Math.max(0, Math.round(listenSeconds))
		}, useBeacon);
	};


	// --- 2. INSTANT PAGE LOAD IMPRESSION ---
	const trackImpression = () => {
		// 1. Block bots immediately
		if (navigator.webdriver || /bot|googlebot|crawler|spider|robot|crawling/i.test(navigator.userAgent)) {
			return;
		}

		const impressionKey = "ats_impression_" + cfg.postId;
		const now = Date.now();
		const last = localStorage.getItem(impressionKey);
		if (last && now - parseInt(last) < 10 * 60 * 1000) return;
		if (window['ats_imp_sent_' + cfg.postId]) return;
		window['ats_imp_sent_' + cfg.postId] = true;
		localStorage.setItem(impressionKey, String(now));
		send("impression");
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', trackImpression);
	} else {
		setTimeout(trackImpression, 0);
	}


	// --- 3. AUDIO PLAYER TRACKING (ONLY tracks 'play', 'listen', 'complete') ---
	const HEARTBEAT_SECONDS = 10;
	const bound = new WeakSet();

	const bindPlayer = (player) => {
		if (!player || bound.has(player)) return;
		bound.add(player);

		let lastTime = player.currentTime || 0;
		let accumulated = 0;
		let completedForThisPlay = false;
		let heartbeatTimer = null;

		const flush = () => {
			if (accumulated > 0) {
				send('listen', accumulated, true);
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