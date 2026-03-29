(function () {
	window.MoknahTTS = {
		init: async function (config) {
			const {
				srtSrc,
				contentSelector,
				audioID,
				skipClasses = ['highlighter-skip'],
				debug = false,
				styles = {
					baseColor: '#333',
					highlightColor: '#f6a21f',
					highlightTextColor: '#000',
					highlightFontWeight: 'bold',
					underlineHeight: '3px',
					underlineOffset: '-2px',
					animationDuration: '0.3s'
				}
			} = config;
			this.debug = debug;

			this._injectStyles(styles);

			this.skipClasses = skipClasses;
			const audio = document.getElementById(audioID);
			if (!audio) {
				console.error(`Audio element with ID "${audioID}" not found.`);
				return;
			}

			const srtData = await this._fetchSRT(srtSrc);
			if (!srtData) {
				console.error('Failed to load SRT file.');
				return;
			}

			this._processContent(contentSelector, srtData, audio);
			this._setupCleanup(audio);
		},

		_setupCleanup: function (audio) {
			const clear = () => {
				document.querySelectorAll('.moknah-word').forEach(word => {
					word.classList.remove('highlight');
				});
			};
			audio.addEventListener('ended', clear);
			audio.addEventListener('pause', clear);
			audio.addEventListener('seeking', clear);
		},

		_injectStyles: function (styles) {
			const styleSheet = document.createElement('style');
			styleSheet.textContent = `
                .moknah-word {
                    display: inline-block;
                    position: relative;
                    cursor: pointer;
                    transition: all ${styles.animationDuration} cubic-bezier(0.4, 0, 0.2, 1);
                }

                .moknah-word::before {
                    content: '';
                    position: absolute;
                    bottom: ${styles.underlineOffset};
                    left: 0;
                    width: 100%;
                    height: ${styles.underlineHeight};
                    background-color: ${styles.highlightColor};
                    transform: scaleX(0);
                    transform-origin: left;
                    transition: transform ${styles.animationDuration} cubic-bezier(0.4, 0, 0.2, 1);
                    opacity: 0;
                }

                .moknah-word::after {
                    content: '';
                    position: absolute;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    top: 0;
                    background-color: ${styles.highlightColor}20;
                    transform: scaleX(0);
                    transform-origin: left;
                    transition: transform ${styles.animationDuration} cubic-bezier(0.4, 0, 0.2, 1);
                    z-index: -1;
                }

                .moknah-word.highlight {
                    color: ${styles.highlightTextColor};
                    font-weight: ${styles.highlightFontWeight};
                    transform: scale(1.05);
                    animation: pulse 2s infinite;
                }

                .moknah-word.highlight::before { transform: scaleX(1); opacity: 1; }
                .moknah-word.highlight::after  { transform: scaleX(1); }

                .moknah-dir-wrapper {
                    unicode-bidi: isolate;
                    display: inline;
                }

                @keyframes pulse {
                    0% { transform: scale(1.05); }
                    50% { transform: scale(1.02); }
                    100% { transform: scale(1.05); }
                }
            `;
			document.head.appendChild(styleSheet);
		},

		destroy: function () {
			if (this.timeUpdateHandler) {
				this.audio?.removeEventListener('timeupdate', this.timeUpdateHandler);
			}
			document.querySelectorAll('.moknah-word').forEach(word => {
				word.replaceWith(document.createTextNode(word.textContent));
			});
			this.audio = null;
		},

		_fetchSRT: async function (url) {
			try {
				const res = await fetch(url);
				if (!res.ok) throw new Error(res.status);
				return this._parseSRT(await res.text());
			} catch (e) {
				console.error(e);
				return null;
			}
		},

		_parseSRT: function (srtContent) {
			const out = [];
			const lines = srtContent.split('\n');
			let start = 0, end = 0, text = '';

			for (const raw of lines) {
				const line = raw.trim();
				if (/^\d+$/.test(line)) {
					if (text) out.push({ startTime: start, endTime: end, text: text.trim() });
					text = '';
				} else if (line.includes('-->')) {
					const [s, e] = line.split(' --> ');
					start = this._timeToSeconds(s);
					end   = this._timeToSeconds(e);
				} else if (line) {
					text += (text ? ' ' : '') + line;
				}
			}
			if (text) out.push({ startTime: start, endTime: end, text: text.trim() });
			return out;
		},

		_timeToSeconds: t => {
			const [h, m, s] = t.replace(',', '.').split(':').map(parseFloat);
			return h * 3600 + m * 60 + s;
		},

		_detectDirection: function (text) {
			const hasArabic = /[\u0600-\u06FF]/.test(text);
			const hasLatin = /[A-Za-z]/.test(text);
			if (hasArabic && !hasLatin) return 'rtl';
			if (hasLatin && !hasArabic) return 'ltr';
			return 'neutral';
		},

		_getContainerDir: function (el) {
			return getComputedStyle(el).direction === 'rtl' ? 'rtl' : 'ltr';
		},

		_processContent: function (selector, srtData, audio) {
			const content = document.querySelector(selector);
			if (!content) return;

			const containerDir = this._getContainerDir(content);
			let currentIndex = 0;
			const textNodes = this._getTextNodes(content);

			// إضافة المزيد من علامات الترقيم والرياضيات (+، -، /) لضمان فصلها عن الأرقام
			const punctRegex = /^([.,!?;:'"()[\]{}\-،؟٪«»“”‘’+=/\\]*)(.*?)([.,!?;:'"()[\]{}\-،؟٪«»“”‘’+=/\\]*)$/;

			textNodes.forEach(node => {
				if (this._isInsideSkipClass(node)) return;

				const tokens = node.textContent.match(/\S+|\s+/g) || [];
				let html = '';

				let currentGroupDir = null;
				let currentGroupHtml = '';
				let pendingSpace = ''; // خزان مؤقت للمسافات لحل مشكلة الالتصاق

				const flushGroup = () => {
					if (currentGroupHtml) {
						if (currentGroupDir && currentGroupDir !== containerDir) {
							html += `<span class="moknah-dir-wrapper" dir="${currentGroupDir}">${currentGroupHtml}</span>`;
						} else {
							html += currentGroupHtml;
						}
						currentGroupHtml = '';
						currentGroupDir = null;
					}
				};

				tokens.forEach(token => {
					// 1. تجميع المسافات في الخزان المؤقت بدلاً من إضافتها مباشرة
					if (!token.trim()) {
						pendingSpace += token;
						return;
					}

					const match = token.match(punctRegex);
					const prefix = match[1] || '';
					const core = match[2] || '';
					const suffix = match[3] || '';

					// إذا كان هناك علامة ترقيم في البداية، نفرغ المسافات والعلامة خارج المجموعة
					if (prefix) {
						flushGroup();
						html += pendingSpace + prefix;
						pendingSpace = '';
					}

					if (core) {
						const lettersOnly = core.replace(/[^A-Za-z\u0600-\u06FF]/g, '');
						const dir = lettersOnly ? this._detectDirection(lettersOnly) : 'neutral';

						let effectiveDir = dir;
						if (dir === 'neutral' && currentGroupDir !== null) {
							effectiveDir = currentGroupDir;
						}

						// إذا تغير الاتجاه، نغلق المجموعة ونضع المسافة المعلقة بالخارج كفاصل طبيعي!
						if (currentGroupDir !== null && effectiveDir !== currentGroupDir) {
							flushGroup();
							html += pendingSpace;
							pendingSpace = '';
						} else if (!prefix) {
							// إذا لم يتغير الاتجاه، المسافة تنتمي لداخل المجموعة (مثل Cornwall Insight)
							if (currentGroupDir !== null) {
								currentGroupHtml += pendingSpace;
							} else {
								html += pendingSpace;
							}
							pendingSpace = '';
						}

						if (effectiveDir && effectiveDir !== containerDir && effectiveDir !== 'neutral') {
							currentGroupDir = effectiveDir;
						}

						let wordHtml = core;
						for (let j = currentIndex; j < currentIndex + 10 && j < srtData.length; j++) {
							const s = srtData[j];
							if (this._normalizeText(core) === this._normalizeText(s.text)) {
								currentIndex = j + 1;
								wordHtml = `<span class="moknah-word" data-start="${s.startTime}" data-end="${s.endTime}">${core}</span>`;
								break;
							}
						}

						if (currentGroupDir !== null) {
							currentGroupHtml += wordHtml;
						} else {
							html += wordHtml;
						}
					}

					// علامات الترقيم الختامية تكسر المجموعة وتخرج المسافات معها
					if (suffix) {
						flushGroup();
						html += suffix;
					}
				});

				// إغلاق أي مجموعة متبقية وطباعة أي مسافات زائدة في النهاية
				flushGroup();
				html += pendingSpace;

				const span = document.createElement('span');
				span.innerHTML = html;
				node.replaceWith(span);
			});

			this._synchronizeAudio(audio);
		},
		_normalizeText: function (text) {
			return text
				.replace(/[\u0660-\u0669]/g, d => d.charCodeAt(0) - 0x0660)
				.replace(/[.,!?;:'"()[\]{}\-،؟٪«»“”‘’]/g, '')
				.trim()
				.toLowerCase();
		},

		_isInsideSkipClass: function (node) {
			let p = node.parentNode;
			while (p && p.nodeType === 1) {
				if (p.classList && this.skipClasses.some(c => p.classList.contains(c))) return true;
				if (p.tagName === 'A') return true;
				p = p.parentNode;
			}
			return false;
		},

		_getTextNodes: function (el) {
			const w = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
			const out = [];
			let n;
			while ((n = w.nextNode())) if (n.nodeValue.trim()) out.push(n);
			return out;
		},

		_synchronizeAudio: function (audio) {
			this.audio = audio;
			const words = document.querySelectorAll('.moknah-word');

			let raf;
			this.timeUpdateHandler = () => {
				cancelAnimationFrame(raf);
				raf = requestAnimationFrame(() => {
					const t = audio.currentTime;
					words.forEach(w => {
						const s = +w.dataset.start, e = +w.dataset.end;
						w.classList.toggle('highlight', t >= s && t <= e);
					});
				});
			};

			audio.addEventListener('timeupdate', this.timeUpdateHandler);

			words.forEach(w => {
				w.addEventListener('click', () => {
					audio.currentTime = +w.dataset.start;
					if (audio.paused) audio.play().catch(() => {});
				});
			});
		}
	};
})();