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
			// Inject CSS styles
			this._injectStyles(styles);

			// Rest of the initialization
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
			// Clean up when audio ends
			audio.addEventListener('ended', () => {
				document.querySelectorAll('.moknah-word').forEach(word => {
					word.classList.remove('highlight');
				});
			});

			// Clean up when audio is paused
			audio.addEventListener('pause', () => {
				document.querySelectorAll('.moknah-word').forEach(word => {
					word.classList.remove('highlight');
				});
			});

			// Clean up when audio is seeking
			audio.addEventListener('seeking', () => {
				document.querySelectorAll('.moknah-word').forEach(word => {
					word.classList.remove('highlight');
				});
			});
		},

		_injectStyles: function (styles) {
			const styleSheet = document.createElement('style');
			styleSheet.textContent = `
                .moknah-word {
                    display: inline-block;
                    position: relative;
                    color: ${styles.baseColor};
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

                .moknah-word:hover {
                    color: ${styles.highlightTextColor};
                }

                .moknah-word:hover::before {
                    transform: scaleX(0.7);
                    opacity: 0.7;
                }

                .moknah-word.highlight {
                    color: ${styles.highlightTextColor};
                    font-weight: ${styles.highlightFontWeight};
                    transform: scale(1.05);
                }

                .moknah-word.highlight::before {
                    transform: scaleX(1);
                    opacity: 1;
                }

                .moknah-word.highlight::after {
                    transform: scaleX(1);
                }

                @keyframes pulse {
                    0% { transform: scale(1.05); }
                    50% { transform: scale(1.02); }
                    100% { transform: scale(1.05); }
                }

                .moknah-word.highlight {
                    animation: pulse 2s infinite;
                }
            `;
			document.head.appendChild(styleSheet);
		},

		destroy: function () {
			if (this.timeUpdateHandler) {
				this.audio?.removeEventListener('timeupdate', this.timeUpdateHandler);
			}
			// Remove all highlights and event listeners
			document.querySelectorAll('.moknah-word').forEach(word => {
				const text = word.textContent;
				const textNode = document.createTextNode(text);
				word.replaceWith(textNode);
			});
			this.audio = null;
		},

		_fetchSRT: async function (url) {
			try {
				const response = await fetch(url);
				if (!response.ok) {
					throw new Error(`HTTP error! status: ${response.status}`);
				}
				const text = await response.text();
				return this._parseSRT(text);
			} catch (error) {
				console.error('Error fetching SRT file:', error);
				return null;
			}
		},

		_parseSRT: function (srtContent) {
			const srtData = [];
			const lines = srtContent.split('\n');
			let startTime = 0, endTime = 0, text = '';

			for (let i = 0; i < lines.length; i++) {
				const line = lines[i].trim();

				if (/^\d+$/.test(line)) {
					if (text) {
						srtData.push({startTime, endTime, text: text.trim()});
						text = '';
					}
				} else if (/-->/.test(line)) {
					const [start, end] = line.split(' --> ');
					startTime = this._timeToSeconds(start);
					endTime = this._timeToSeconds(end);
				} else if (line) {
					text += (text ? ' ' : '') + line;
				}
			}

			if (text) {
				srtData.push({startTime, endTime, text: text.trim()});
			}

			return srtData;
		},

		_timeToSeconds: function (time) {
			const [h, m, s] = time.replace(',', '.').split(':').map(parseFloat);
			return h * 3600 + m * 60 + s;
		},

		_processContent: function (selector, srtData, audio) {
			const content = document.querySelector(selector);
			if (!content) {
				console.error('Content selector not found:', selector);
				return;
			}

			let currentIndex = 0;
			const textNodes = this._getTextNodes(content);

			textNodes.forEach(node => {
				if (this._isInsideSkipClass(node)) {
					return;
				}

				const words = node.textContent.split(/\s+/);
				let newContent = [];

				for (let i = 0; i < words.length; i++) {
					let word = words[i];
					if (!word.trim()) {
						newContent.push(word);
						continue;
					}

					// Try to find a matching SRT entry
					let matched = false;
					let lookAheadLimit = 10;
					let j = currentIndex;

					while (j < currentIndex + lookAheadLimit && j < srtData.length) {
						const srtEntry = srtData[j];
						if (!srtEntry) break;

						// Clean and normalize both texts for comparison
						const cleanWord = this._normalizeText(word);
						const srtWords = this._normalizeText(srtEntry.text);

						// Check for exact match or number match
						if (srtWords === cleanWord ||
							(this._isNumber(cleanWord) && this._isNumber(srtWords) &&
								parseFloat(cleanWord) === parseFloat(srtWords))) {
							matched = true;
							newContent.push(`<span class="moknah-word" 
                        data-start="${srtEntry.startTime}" 
                        data-end="${srtEntry.endTime}">${word}</span>`);
							currentIndex = j + 1;
							break;
						}
						j++;
					}

					if (!matched) {
						// If no match found, just add the word without highlighting
						newContent.push(word);
					}
				}

				const spanWrapper = document.createElement('span');
				spanWrapper.innerHTML = newContent.join(' ');
				node.replaceWith(spanWrapper);
			});

			this._synchronizeAudio(audio);
		},

		_normalizeText: function (text) {
			// Convert Arabic numbers to English numbers
			text = text.replace(/[\u0660-\u0669]/g, d => d.charCodeAt(0) - 0x0660);
			// Remove punctuation and extra spaces
			text = text.replace(/[.,!?;:'"ظ]/g, '').trim();
			return text.toLowerCase();
		},

		_isNumber: function (text) {
			// Check if the text is a number (including Arabic numbers)
			const normalized = text.replace(/[\u0660-\u0669]/g, d => d.charCodeAt(0) - 0x0660)
				.replace(/[.,]/g, '')
				.trim();
			return !isNaN(parseFloat(normalized)) && isFinite(normalized);
		},
		_isInsideSkipClass: function (node) {
			let parent = node.parentNode;
			while (parent && parent.nodeType === 1) {
				// Skip if parent has a skip class
				if (parent.classList && this.skipClasses.some(cls => parent.classList.contains(cls))) {
					return true;
				}

				// Skip if parent is an <a> tag
				if (parent.tagName === 'A') {
					return true;
				}

				parent = parent.parentNode;
			}
			return false;
		},

		_getTextNodes: function (element) {
			const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT, null, false);
			const nodes = [];
			let node;
			while ((node = walker.nextNode())) {
				if (node.nodeValue.trim()) nodes.push(node);
			}
			return nodes;
		},

		_synchronizeAudio: function (audio) {
			this.audio = audio;
			const words = document.querySelectorAll('.moknah-word');

			let frameRequest;
			this.timeUpdateHandler = () => {
				if (frameRequest) {
					cancelAnimationFrame(frameRequest);
				}

				frameRequest = requestAnimationFrame(() => {
					const currentTime = audio.currentTime;
					words.forEach(word => {
						const start = parseFloat(word.getAttribute('data-start'));
						const end = parseFloat(word.getAttribute('data-end'));
						if (currentTime >= start && currentTime <= end) {
							word.classList.add('highlight');
						} else {
							word.classList.remove('highlight');
						}
					});
				});
			};

			audio.addEventListener('timeupdate', this.timeUpdateHandler);

			words.forEach(word => {
				word.addEventListener('click', () => {
					audio.currentTime = parseFloat(word.getAttribute('data-start'));
					if (audio.paused) {
						audio.play().catch(e => console.error('Error playing audio:', e));
					}
				});
			});
		},
	};
})();