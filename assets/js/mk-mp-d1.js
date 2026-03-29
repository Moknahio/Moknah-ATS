document.addEventListener("DOMContentLoaded", function () {

	const mkMpD1 = document.getElementById('mk-mp-d1');
	const mkMpD1Audio = document.getElementById('mk-mp-d1-audio');
	const mkMpD1PlayPauseButton = document.getElementById('mk-mp-d1-play-pause');
	const mkMpD1ProgressBar = document.getElementById('mk-mp-d1-progress-bar');
	const mkMpD1CurrentTimeDisplay = document.getElementById('mk-mp-d1-current-time');
	const mkMpD1DurationDisplay = document.getElementById('mk-mp-d1-duration');
	const mkMpD1VolumeControl = document.getElementById('mk-mp-d1-volume-control');
	const mkMpD1VolumeIcon = document.getElementById('mk-mp-d1-volume-icon');
	const mkMpD1Visualizer = document.getElementById('mk-mp-d1-visualizer');
	const mkMpD1PinToggle = document.getElementById('mk-mp-d1-pin-toggle');

	if (!mkMpD1 || !mkMpD1Audio) return;

	let userSelectedPin = localStorage.getItem("pin");
	if (userSelectedPin) {
		mkMpD1.className = userSelectedPin
	}

	mkMpD1PinToggle.addEventListener('click', () => {
		mkMpD1.classList.toggle('mk-mp-pinned');
		mkMpD1.classList.toggle('mk-mp-unpinned');
		localStorage.setItem("pin", mkMpD1.classList.value)
	});


// Initialize Visualizer Bars
	for (let i = 0; i < 20; i++) {
		const mkMpD1Bar = document.createElement('div');
		mkMpD1Bar.className = 'mk-mp-d1-bar';
		mkMpD1Visualizer.appendChild(mkMpD1Bar);
	}

// Format Time Function
	const mkMpD1FormatTime = (time) => {
		const minutes = Math.floor(time / 60);
		const seconds = Math.floor(time % 60).toString().padStart(2, '0');
		return `${minutes}:${seconds}`;
	};

// Update Progress Bar and Time Displays
	mkMpD1Audio.addEventListener('timeupdate', () => {
		if (mkMpD1Audio.duration) {
			const percentage = (mkMpD1Audio.currentTime / mkMpD1Audio.duration) * 100;
			mkMpD1ProgressBar.value = percentage;
			mkMpD1ProgressBar.style.setProperty('--mk-mp-d1-progress-percentage', `${percentage}%`);
		}
		mkMpD1CurrentTimeDisplay.textContent = mkMpD1FormatTime(mkMpD1Audio.currentTime);
		mkMpD1UpdateVisualizer();
	});

	const mkMpD1UpdateDuration = () => {
		// Ensure duration is a valid number before updating
		if (mkMpD1Audio.duration && !isNaN(mkMpD1Audio.duration)) {
			mkMpD1DurationDisplay.textContent = mkMpD1FormatTime(mkMpD1Audio.duration);
		}
	};

	// 1. Check if the browser already loaded the metadata from cache
	if (mkMpD1Audio.readyState >= 1) { // 1 = HAVE_METADATA
		mkMpD1UpdateDuration();
	}

	// 2. Listen to events in case it hasn't loaded yet
	mkMpD1Audio.addEventListener('loadedmetadata', mkMpD1UpdateDuration);
	mkMpD1Audio.addEventListener('durationchange', mkMpD1UpdateDuration);
	mkMpD1Audio.addEventListener('canplay', mkMpD1UpdateDuration); // Extra fallback

	const mkMpD1UpdatePlayPauseButton = () => {
		mkMpD1PlayPauseButton.innerHTML = mkMpD1Audio.paused ? '<i class="fas fa-play"></i>' : '<i class="fas fa-pause"></i>';
	};

	mkMpD1Audio.addEventListener('play', mkMpD1UpdatePlayPauseButton);
	mkMpD1Audio.addEventListener('pause', mkMpD1UpdatePlayPauseButton);

	mkMpD1PlayPauseButton.addEventListener('click', () => {
		if (mkMpD1Audio.paused) {
			mkMpD1Audio.play();
		} else {
			mkMpD1Audio.pause();
		}
	});

	mkMpD1ProgressBar.addEventListener('input', (e) => {
		const percentage = e.target.value;
		mkMpD1ProgressBar.style.setProperty('--mk-mp-d1-progress-percentage', `${percentage}%`);
		mkMpD1Audio.currentTime = (percentage / 100) * mkMpD1Audio.duration;
	});

	mkMpD1VolumeControl.addEventListener('input', (e) => {
		const volumeValue = e.target.value;
		const percentage = volumeValue * 100;
		mkMpD1VolumeControl.style.setProperty('--mk-mp-d1-volume-percentage', `${percentage}%`);
		mkMpD1Audio.volume = volumeValue;
		mkMpD1UpdateVolumeIcon(volumeValue);
	});

	const mkMpD1UpdateVolumeIcon = (volume) => {
		if (volume > 0 && mkMpD1Audio.muted) {
			mkMpD1Audio.muted = !mkMpD1Audio.muted;
		}
		if (volume === 0) {
			mkMpD1VolumeIcon.className = 'mk-mp-d1-volume-icon fas fa-volume-mute';
		} else if (volume > 0 && volume <= 0.25) {
			mkMpD1VolumeIcon.className = 'mk-mp-d1-volume-icon fas fa-volume-off';
		} else if (volume > 0.25 && volume <= 0.5) {
			mkMpD1VolumeIcon.className = 'mk-mp-d1-volume-icon fas fa-volume-down';
		} else if (volume > 0.5) {
			mkMpD1VolumeIcon.className = 'mk-mp-d1-volume-icon fas fa-volume-up';
		}
	};

	const mkMpD1UpdateVisualizer = () => {
		const mkMpD1Bars = document.querySelectorAll('.mk-mp-d1-bar');
		mkMpD1Bars.forEach(bar => {
			const height = Math.random() * 40 + 10;
			bar.style.height = mkMpD1Audio.paused ? '5px' : `${height}px`;
		});
	};

	setInterval(() => {
		if (!mkMpD1Audio.paused) {
			mkMpD1UpdateVisualizer();
		}
	}, 100);
// Toggle mute on volume icon click
	mkMpD1VolumeIcon.addEventListener('click', () => {
		mkMpD1Audio.muted = !mkMpD1Audio.muted;
		if (mkMpD1Audio.muted) {
			mkMpD1VolumeIcon.className = 'mk-mp-d1-volume-icon fas fa-volume-mute';
		} else {
			mkMpD1UpdateVolumeIcon(mkMpD1VolumeControl.value);
		}
	});

	const mkMpD1speeds = [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2];
	let mkMpD1currentSpeedIndex = 2; // 1×

	const mkMpD1speedBtn = document.getElementById("mk-mp-d1-speed-btn");
	if (mkMpD1speedBtn) {

		mkMpD1speedBtn.addEventListener("click", function () {
			mkMpD1currentSpeedIndex = (mkMpD1currentSpeedIndex + 1) % mkMpD1speeds.length;
			const mkMpD1newSpeed = mkMpD1speeds[mkMpD1currentSpeedIndex];
			mkMpD1Audio.playbackRate = mkMpD1newSpeed;
			mkMpD1speedBtn.textContent = mkMpD1newSpeed + "×";
		});
	}
});