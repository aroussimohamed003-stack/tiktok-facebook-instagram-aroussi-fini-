// Theme toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    // Check for saved theme preference or use preferred color scheme
    const currentTheme = localStorage.getItem('theme') || 
                         (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    
    // Apply the theme
    document.documentElement.setAttribute('data-theme', currentTheme);
    
    // Update toggle state
    const toggleSwitch = document.querySelector('.theme-switch input[type="checkbox"]');
    if (toggleSwitch) {
        toggleSwitch.checked = currentTheme === 'dark';
        
        // Add event listener for theme switch
        toggleSwitch.addEventListener('change', switchTheme, false);
    }
    
    // Function to switch themes
    function switchTheme(e) {
        if (e.target.checked) {
            document.documentElement.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
        } else {
            document.documentElement.setAttribute('data-theme', 'light');
            localStorage.setItem('theme', 'light');
        }
    }
});

/* 
// Video player enhancements
document.addEventListener('DOMContentLoaded', function() {
    // Add custom controls to videos
    const videos = document.querySelectorAll('video');
    
    videos.forEach(video => {
        // Only add controls if they don't already exist
        if (!video.parentElement.querySelector('.video-controls')) {
            // Create controls container
            const controlsContainer = document.createElement('div');
            controlsContainer.className = 'video-controls';
            
            // Play/Pause button
            const playPauseBtn = document.createElement('button');
            playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
            playPauseBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (video.paused) {
                    video.play();
                    playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
                } else {
                    video.pause();
                    playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
                }
            });
            
            // Mute button
            const muteBtn = document.createElement('button');
            muteBtn.innerHTML = '<i class="fas fa-volume-up"></i>';
            muteBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                video.muted = !video.muted;
                muteBtn.innerHTML = video.muted ? 
                    '<i class="fas fa-volume-mute"></i>' : 
                    '<i class="fas fa-volume-up"></i>';
            });
            
            // Fullscreen button
            const fullscreenBtn = document.createElement('button');
            fullscreenBtn.innerHTML = '<i class="fas fa-expand"></i>';
            fullscreenBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (video.requestFullscreen) {
                    video.requestFullscreen();
                } else if (video.webkitRequestFullscreen) {
                    video.webkitRequestFullscreen();
                } else if (video.msRequestFullscreen) {
                    video.msRequestFullscreen();
                }
            });
            
            // Add buttons to controls
            controlsContainer.appendChild(playPauseBtn);
            controlsContainer.appendChild(muteBtn);
            controlsContainer.appendChild(fullscreenBtn);
            
            // Add controls to video container
            const videoContainer = video.parentElement;
            if (videoContainer.classList.contains('video-item') || 
                videoContainer.classList.contains('video-container')) {
                videoContainer.appendChild(controlsContainer);
            } else {
                // If video is not in a container, wrap it
                const wrapper = document.createElement('div');
                wrapper.className = 'video-container';
                video.parentNode.insertBefore(wrapper, video);
                wrapper.appendChild(video);
                wrapper.appendChild(controlsContainer);
            }
            
            // Update play/pause button when video state changes
            video.addEventListener('play', function() {
                playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
            });
            
            video.addEventListener('pause', function() {
                playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
            });
        }
    });
});
*/
