console.log("Custom Actions Loaded v" + new Date().getTime());

// Expose functions to window immediately
window.viewUserStories = viewUserStories;
window.openVideoComments = openVideoComments;
window.toggleMusicSearch = toggleMusicSearch;
window.searchMusic = searchMusic;
window.removeMusic = removeMusic;
window.postStoryComment = postStoryComment;
window.postVideoComment = postVideoComment;
window.deleteStoryComment = deleteStoryComment;
window.deleteVideoComment = deleteVideoComment;
window.shareWithFriend = shareWithFriend;
window.openShareModal = openShareModal;
window.toggleStoryComments = toggleStoryComments;
window.toggleStoryViewers = toggleStoryViewers;
window.prevStory = prevStory;
window.nextStory = nextStory;
window.closeStoryModal = closeStoryModal;
window.deleteStory = deleteStory;
window.recordStoryView = recordStoryView;
window.selectMusic = selectMusic;
window.togglePreview = togglePreview;
window.toggleLike = toggleLike;
window.toggleVideoMute = toggleVideoMute;
window.copyToClipboard = copyToClipboard;
window.openVideoViewers = openVideoViewers;
window.toggleVideoViewers = toggleVideoViewers;
window.openVideoLikes = openVideoLikes;
window.toggleVideoLikes = toggleVideoLikes;
window.toggleVideoComments = toggleVideoComments;
window.toggleFullScreen = toggleFullScreen;

function toggleFullScreen(videoId, btn) {
    let video = document.querySelector(`video[data-id="${videoId}"]`);
    if (!video && btn) video = btn.closest('.relative').querySelector('video');

    if (!video) {
        // Fallback for any video player in the article
        video = $(btn).closest('article').find('video.video-player')[0];
    }

    if (!video) return;

    // Mobile improvement: Unmute and play before fullscreen
    video.muted = false;
    const playPromise = video.play();

    const triggerFS = () => {
        if (video.webkitEnterFullscreen) {
            video.webkitEnterFullscreen();
        } else if (video.requestFullscreen) {
            video.requestFullscreen();
        } else if (video.webkitRequestFullscreen) {
            video.webkitRequestFullscreen();
        } else if (video.mozRequestFullScreen) {
            video.mozRequestFullScreen();
        } else if (video.msRequestFullscreen) {
            video.msRequestFullscreen();
        }
    };

    if (playPromise !== undefined) {
        playPromise.then(() => {
            triggerFS();
        }).catch(() => {
            // Even if play fails (user gesture issue), try FS
            triggerFS();
        });
    } else {
        triggerFS();
    }
}

// Initialize when document is ready
$(document).ready(function () {
    console.log("Document Ready: Initializing Custom Actions");

    // Force pointer events on buttons for interaction
    $('.action-buttons, .action-btn').css('pointer-events', 'auto');
    $('.story-user-card').css('pointer-events', 'auto');

    // Handling Video Views
    $('video').each(function () {
        const video = this;
        const videoId = $(this).data('id');
        $(video).data('viewed', false);

        $(video).on('play', function () {
            if (!$(this).data('viewed') && videoId) {
                $.ajax({
                    url: 'indexmo.php',
                    method: 'POST',
                    data: { update_views_id: videoId },
                    dataType: 'json',
                    success: function (data) {
                        $('#views-' + videoId).text(data.views);
                    }
                });
                $(video).data('viewed', true);
            }
        });
    });

    // Likes Logic - Event Delegation
    $(document).on('click', '.like-trigger', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const videoId = $(this).data('video-id');
        const likeBtn = $(this);

        $.ajax({
            url: 'indexmo.php',
            method: 'POST',
            data: { like_video_id: videoId },
            dataType: 'json',
            success: function (data) {
                if (data.success) {
                    $('#likes-' + videoId).text(data.likes);
                    if (data.action === 'added') {
                        likeBtn.addClass('liked');
                    } else {
                        likeBtn.removeClass('liked');
                    }

                    // Check for public.php disappearance
                    if (window.location.pathname.includes('public.php')) {
                        const commentCount = parseInt($('#comments-count-' + videoId).text()) || 0;
                        if (data.likes < 50 || commentCount < 20) {
                            likeBtn.closest('.video-item').fadeOut(500);
                        }
                    }
                }
            },
            error: function (err) {
                console.error("Like error:", err);
            }
        });
    });

    // Viewers Button Logic
    $(document).on('click', '.viewers-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const videoId = $(this).data('video-id');
        $('.likes-popup, .viewers-popup').hide();
        $('#viewers-popup-' + videoId).fadeToggle(200);
    });

    // Likes Count Click Logic
    $(document).on('click', '.likes-count-trigger', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const videoId = $(this).data('video-id');
        $('.likes-popup, .viewers-popup').hide();
        $('#likes-popup-' + videoId).fadeToggle(200);
    });

    // Optimized Video Handling: SNR (Snappy, No-lag, Responsive)
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            const video = entry.target;
            if (entry.isIntersecting) {
                video.play().catch(() => {
                    video.muted = true;
                    video.play().catch(e => { });
                });
                // Preload the next one immediately
                const nextVid = $(video).closest('article').next().find('video.video-player')[0];
                if (nextVid) nextVid.preload = "auto";
            } else {
                video.pause();
            }
        });
    }, { threshold: 0.3 }); // Start sooner

    $('video.video-player').each(function () {
        this.preload = "metadata";
        observer.observe(this);
    });

    // 2x Speed Feature (Long Press) & Snappy Playback
    let speedTimer;
    $(document).on('mousedown touchstart', 'video.video-player', function (e) {
        const video = this;
        speedTimer = setTimeout(() => {
            video.playbackRate = 2.0;
            if (!$('#speed-indicator').length) {
                $('body').append('<div id="speed-indicator" class="fixed top-24 left-1/2 -translate-x-1/2 bg-black/60 text-white px-4 py-2 rounded-full z-[10000] font-bold flex items-center gap-2 animate-pulse"><i class="fas fa-bolt text-yellow-400"></i> 2x Speed</div>');
            }
        }, 500);
    }).on('mouseup mouseleave touchend', 'video.video-player', function () {
        clearTimeout(speedTimer);
        this.playbackRate = 1.0;
        $('#speed-indicator').remove();
    });

    // Handle Manual Click on Video (Toggle Play/Pause + Unmute)
    $(document).on('click', 'video.video-player', function () {
        const video = this;
        if (video.paused) {
            video.play();
            video.muted = false;
        } else {
            video.pause();
        }
    });

    // Popup Close Logic
    $(document).on('click', '.close-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).closest('.viewers-popup, .likes-popup').fadeOut(200);
    });

    // Global Close on Click Outside
    $(document).on('click', function (e) {
        if ($(e.target).closest('.viewers-popup, .likes-popup').length === 0 &&
            $(e.target).closest('.viewers-btn, .likes-count-trigger').length === 0) {
            $('.viewers-popup, .likes-popup').fadeOut(200);
        }
    });

    $(document).on('click', '.viewers-popup, .likes-popup', function (e) {
        e.stopPropagation();
    });

    // Auto search music on input
    let musicSearchTimeout;
    $('#musicSearchInput').on('input', function () {
        const query = $(this).val();
        clearTimeout(musicSearchTimeout);

        if (query.trim().length > 0) {
            musicSearchTimeout = setTimeout(function () {
                searchMusic(query);
            }, 500);
        } else {
            $('#musicSearchResults').html('');
        }
    });

    // Handle Story Upload Loading
    $('#uploadStoryForm').on('submit', function (e) {
        var fileInput = document.getElementById('storyFileInput');
        if (fileInput && fileInput.files.length > 0) {
            var file = fileInput.files[0];
            var fileSize = file.size;
            var maxSize = 50 * 1024 * 1024; // 50MB

            if (fileSize > maxSize) {
                e.preventDefault();
                alert('عذراً، حجم الملف كبير جداً. الحد الأقصى 50 ميجابايت.');
                return false;
            }

            // Show loading overlay
            $('#loadingOverlay').css('display', 'flex');
        }
    });
});


// --- Profile Logic ---
function showProfile(userId, username, profilePicture) {
    $('#profileOverlay').show();
    $('body').css('overflow', 'hidden'); // Prevent scrolling background
    $('#profileContent').html('<div class="text-center p-5" style="margin-top:100px;"><div class="spinner-border text-light"></div></div>');

    $.ajax({
        url: 'get_profile.php',
        method: 'GET',
        data: { user_id: userId },
        success: function (response) {
            const videoCount = response.videos ? response.videos.length : 0;
            let profileHtml = `
                <div class="profile-header">
                    <div class="profile-picture-container">
                        <img src="${profilePicture}" class="profile-picture-img" onerror="this.src='uploads/profile.jpg'">
                    </div>
                    <h3 style="margin:0; font-weight:bold; color: #fff;">${username}</h3>
                    
                    <div class="profile-stats">
                        <div class="profile-stat-item">
                            <span class="profile-stat-value">${videoCount}</span>
                            <span class="profile-stat-label">فيديو</span>
                        </div>
                        <div class="profile-stat-item">
                            <span class="profile-stat-value">0</span>
                            <span class="profile-stat-label">متابع</span>
                        </div>
                        <div class="profile-stat-item">
                            <span class="profile-stat-value">0</span>
                            <span class="profile-stat-label">إعجاب</span>
                        </div>
                    </div>

                    ${response.bio ? `<div class="profile-bio">${response.bio}</div>` : '<div class="profile-bio text-muted">لا يوجد وصف حالياً</div>'}
                </div>
                
                <div class="profile-videos-title">
                    <i class="fas fa-th"></i>
                    الفيديوهات
                </div>
                
                <div class="profile-videos">`;

            if (response.videos && response.videos.length > 0) {
                response.videos.forEach(v => {
                    profileHtml += `
                        <div class="profile-video">
                            <video src="${v.location}" loop muted playsinline onclick="this.paused ? this.play() : this.pause()"></video>
                            <div class="profile-video-overlay">
                                <i class="fas fa-play"></i> ${v.views || 0}
                            </div>
                        </div>`;
                });
            } else {
                profileHtml += '<div class="text-center text-muted w-100 p-5 mt-4" style="grid-column: 1 / -1;">لا توجد فيديوهات بعد.</div>';
            }
            profileHtml += '</div>';
            $('#profileContent').html(profileHtml);
        },
        error: function () {
            $('#profileContent').html('<div class="text-center p-5 mt-5"><p class="text-danger">خطأ في تحميل الملف الشخصي</p></div>');
        }
    });
}



function closeProfile() {
    $('#profileOverlay').hide();
    $('body').css('overflow', 'auto'); // Restore scrolling
    $('#profileContent video').each(function () { this.pause(); });
}


// --- Video Comments Logic ---
let currentVideoIdForComments = null;
function openVideoComments(videoId) {
    currentVideoIdForComments = videoId;
    $('#videoCommentsModal').addClass('active');
    loadVideoComments(videoId);
}

function toggleVideoComments() {
    $('#videoCommentsModal').removeClass('active');
}

function loadVideoComments(videoId) {
    $('#videoCommentsList').html('<div class="text-center p-4 text-muted">جاري التحميل...</div>');
    $.get('indexmo.php', { action: 'get_video_comments', video_id: videoId }, function (data) {
        try {
            const res = (typeof data === 'string') ? JSON.parse(data) : data;
            let html = '';
            // Update main page counter
            $('#comments-count-' + videoId).text(res.comments ? res.comments.length : 0);

            if (res.success && res.comments.length > 0) {
                res.comments.forEach(c => {
                    const pic = c.profile_picture || 'uploads/profile.jpg';
                    const del = (res.current_user_id == c.user_id) ? `<i class="fas fa-trash story-comment-delete" onclick="deleteVideoComment(${c.id})"></i>` : '';
                    html += `
                        <div class="story-comment-item">
                            <img src="${pic}" class="story-comment-avatar" onerror="this.src='uploads/profile.jpg'">
                            <div class="story-comment-content">
                                <div class="story-comment-user">${c.username}</div>
                                <div class="story-comment-text">${c.comment} ${del}</div>
                            </div>
                        </div>`;
                });
            } else {
                html = '<div class="text-center p-4 text-muted">لا توجد تعليقات بعد.</div>';
            }
            $('#videoCommentsList').html(html);
        } catch (e) { console.error(e); }
    });
}

function postVideoComment() {
    const txt = $('#videoCommentInput').val().trim();
    if (!txt || !currentVideoIdForComments) return;
    $('#videoCommentInput').val('');
    $.post('indexmo.php', { action: 'add_video_comment', video_id: currentVideoIdForComments, comment: txt }, function () {
        loadVideoComments(currentVideoIdForComments);
    });
}

function deleteVideoComment(id) {
    if (confirm('حذف التعليق؟')) {
        $.post('indexmo.php', { action: 'delete_video_comment', comment_id: id }, function () {
            loadVideoComments(currentVideoIdForComments);

            // Check for public.php disappearance
            if (window.location.pathname.includes('public.php')) {
                setTimeout(() => {
                    const likesCount = parseInt($('#likes-' + currentVideoIdForComments).text()) || 0;
                    const commentCount = parseInt($('#comments-count-' + currentVideoIdForComments).text()) || 0;
                    if (likesCount < 50 || commentCount < 20) {
                        $('#comments-count-' + currentVideoIdForComments).closest('.video-item').fadeOut(500);
                        toggleVideoComments(); // Close modal too
                    }
                }, 500);
            }
        });
    }
}

// --- Video Viewers Logic ---
function openVideoViewers(videoId) {
    $('#videoViewersModal').addClass('active');
    loadVideoViewers(videoId);
}

function toggleVideoViewers() {
    $('#videoViewersModal').removeClass('active');
}

function loadVideoViewers(videoId) {
    $('#videoViewersList').html('<div class="text-center p-4 text-muted">جاري التحميل...</div>');
    $.get('indexmo.php', { get_video_viewers: videoId }, function (data) {
        try {
            const res = (typeof data === 'string') ? JSON.parse(data) : data;
            let html = '';
            if (res.success && res.viewers.length > 0) {
                res.viewers.forEach(v => {
                    const pic = v.profile_picture || 'uploads/profile.jpg';
                    html += `
                        <div class="story-comment-item">
                            <img src="${pic}" class="story-comment-avatar" onerror="this.src='uploads/profile.jpg'">
                            <div class="story-comment-content">
                                <div class="story-comment-user" style="color: #fff; font-weight: bold;">${v.username}</div>
                            </div>
                        </div>`;
                });
            } else {
                html = '<div class="text-center p-4 text-muted">لا يوجد مشاهدون بعد.</div>';
            }
            $('#videoViewersList').html(html);
        } catch (e) { console.error(e); }
    });
}

// --- Video Likes Logic ---
function openVideoLikes(videoId) {
    $('#videoLikesModal').addClass('active');
    loadVideoLikes(videoId);
}

function toggleVideoLikes() {
    $('#videoLikesModal').removeClass('active');
}

function loadVideoLikes(videoId) {
    $('#videoLikesList').html('<div class="text-center p-4 text-muted">جاري التحميل...</div>');
    $.get('get_likers.php', { video_id: videoId }, function (data) {
        if (data) {
            $('#videoLikesList').html(data);
        } else {
            $('#videoLikesList').html('<div class="text-center p-4 text-muted">لا توجد إعجابات بعد.</div>');
        }
    });
}

// --- Video Comments Logic ---
let currentVideoId = null;

function openVideoComments(videoId) {
    currentVideoId = videoId;
    $('#videoCommentsModal').addClass('active');
    loadVideoComments(videoId);
}

function toggleVideoComments() {
    $('#videoCommentsModal').removeClass('active');
    currentVideoId = null;
}

function loadVideoComments(videoId) {
    $('#videoCommentsList').html('<div class="text-center p-4 text-muted">جاري التحميل...</div>');
    $.get('indexmo.php', { action: 'get_video_comments', video_id: videoId }, function (res) {
        if (res.success && res.comments.length > 0) {
            let html = '';
            res.comments.forEach(c => {
                const pic = c.profile_picture || 'uploads/profile.jpg';
                const isMine = (res.current_user_id == c.user_id);
                html += `
                <div class="story-comment-item">
                    <img src="${pic}" class="story-comment-avatar" onerror="this.src='uploads/profile.jpg'">
                    <div class="story-comment-content" style="background:#f1f2f5;">
                        <div class="story-comment-user" style="color: #050505; font-weight: bold;">${c.username}</div>
                        <div class="story-comment-text" style="color: #000;">${c.comment}</div>
                        ${isMine ? `<span class="story-comment-delete" onclick="deleteVideoComment(${c.id}, ${videoId})"><i class="fas fa-trash"></i></span>` : ''}
                    </div>
                </div>`;
            });
            $('#videoCommentsList').html(html);
        } else {
            $('#videoCommentsList').html('<div class="text-center p-4 text-muted">لا توجد تعليقات بعد.</div>');
        }
    }, 'json');
}

function postVideoComment() {
    const text = $('#videoCommentInput').val().trim();
    if (!text || !currentVideoId) return;

    $.post('indexmo.php', { action: 'add_video_comment', video_id: currentVideoId, comment: text }, function (data) {
        if (data.success) {
            $('#videoCommentInput').val('');
            loadVideoComments(currentVideoId);
            const countEl = $('#comments-count-' + currentVideoId);
            if (countEl.length) {
                countEl.text(parseInt(countEl.text()) + 1);
            }
        } else {
            alert('Error adding comment: ' + (data.error || 'Unknown error'));
        }
    }, 'json');
}

function deleteVideoComment(commentId, videoId) {
    if (!confirm('هل تريد حذف هذا التعليق؟')) return;
    $.post('indexmo.php', { action: 'delete_video_comment', comment_id: commentId }, function (data) {
        if (data.success) {
            loadVideoComments(videoId);
            const countEl = $('#comments-count-' + videoId);
            if (countEl.length) {
                countEl.text(Math.max(0, parseInt(countEl.text()) - 1));
            }
        }
    }, 'json');
}

// --- Music Search Logic ---
function toggleMusicSearch() {
    // Rely on jQuery being loaded
    if (window.jQuery) {
        $('#musicSearchContainer').toggle();
    } else {
        // Fallback if jQuery issue
        var el = document.getElementById('musicSearchContainer');
        if (el) el.style.display = (el.style.display === 'none' ? 'block' : 'none');
    }
}

function searchMusic(q) {
    if (!q) q = $('#musicSearchInput').val();
    if (!q) return;

    $('#musicSearchResults').html('<div class="text-center p-3 text-white"><div class="spinner-border text-primary" role="status"></div></div>');

    fetch('search_music.php?q=' + encodeURIComponent(q)).then(r => r.json()).then(data => {
        if (data.tracks && data.tracks.length > 0) {
            let h = data.tracks.map(t => {
                const safeName = t.name.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                const safeArtist = t.artist.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                return `
                <div class="music-item d-flex align-items-center justify-content-between p-2 border-bottom border-secondary">
                    <div class="d-flex align-items-center gap-2 cursor-pointer flex-grow-1" onclick="selectMusic('${t.preview_url}','${safeName}','${safeArtist}','${t.image}')">
                        <img src="${t.image}" class="music-cover" width="40" height="40" style="border-radius:5px; object-fit:cover;">
                        <div class="music-info overflow-hidden">
                            <div class="music-title text-white text-truncate" style="font-size:14px;">${t.name}</div>
                            <div class="music-artist text-muted text-truncate" style="font-size:12px;">${t.artist}</div>
                        </div>
                    </div>
                    <i class="fas fa-play-circle text-primary" style="font-size:24px; cursor:pointer;" onclick="togglePreview('${t.preview_url}', this)"></i>
                </div>`;
            }).join('');
            $('#musicSearchResults').html(h);
        } else {
            $('#musicSearchResults').html('<div class="text-center p-3 text-muted">لا توجد نتائج</div>');
        }
    }).catch(e => {
        $('#musicSearchResults').html('<div class="text-center p-3 text-danger">حدث خطأ في البحث</div>');
    });
}

function selectMusic(u, t, a, i) {
    $('#music_url').val(u);
    $('#music_title').val(t);
    $('#music_artist').val(a);
    $('#music_image').val(i);
    $('#selectedMusicTitle').text(t + ' - ' + a);
    $('#selectedMusicDisplay').show();
    $('#musicSearchContainer').hide();

    // Stop preview
    if (window.currentPreviewAudio) {
        window.currentPreviewAudio.pause();
        window.currentPreviewAudio = null;
    }

    // Play selected in the mini player
    const p = document.getElementById('selectedMusicPlayer');
    if (p) {
        p.src = u;
        p.volume = 1.0;
        p.load(); // Ensure reload
        p.play().catch(e => console.log('Autoplay prevented or error', e));
    }
}

function removeMusic() {
    $('#music_url, #music_title, #music_artist, #music_image').val('');
    $('#selectedMusicDisplay').hide();
    const p = document.getElementById('selectedMusicPlayer');
    if (p) { p.pause(); p.src = ''; }
}

function togglePreview(url, btn) {
    $('.fa-pause-circle').not(btn).removeClass('fa-pause-circle').addClass('fa-play-circle');

    if (window.currentPreviewAudio) {
        window.currentPreviewAudio.pause();
        window.currentPreviewAudio = null;
    }

    if ($(btn).hasClass('fa-play-circle')) {
        window.currentPreviewAudio = new Audio(url);
        window.currentPreviewAudio.play().catch(e => console.error(e));
        $(btn).removeClass('fa-play-circle').addClass('fa-pause-circle');
        window.currentPreviewAudio.onended = function () {
            $(btn).removeClass('fa-pause-circle').addClass('fa-play-circle');
            window.currentPreviewAudio = null;
        };
    } else {
        $(btn).removeClass('fa-pause-circle').addClass('fa-play-circle');
    }
}

// --- Stories Logic ---
let currentStories = [];
let currentStoryIndex = 0;
let progressInterval;
let currentStoryId = null;

function viewUserStories(userId) {
    console.log("Viewing stories for user: " + userId);
    $('#storyModal').show();
    $('#storyMediaContainer').html('<div class="spinner-border text-light"></div>');

    fetch('get_user_stories.php?user_id=' + userId)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.stories.length > 0) {
                currentStories = data.stories;
                currentStoryIndex = 0;
                renderStory();
            } else {
                alert('لا توجد قصص حالية');
                $('#storyModal').hide();
            }
        }).catch((e) => {
            console.error("Error loading stories", e);
            alert('Error loading stories');
            $('#storyModal').hide();
        });
}

function renderStory() {
    if (currentStoryIndex >= currentStories.length || currentStoryIndex < 0) {
        closeStoryModal();
        return;
    }
    const story = currentStories[currentStoryIndex];
    currentStoryId = story.id;

    // UI Updates
    document.getElementById('modalUserAvatar').src = story.profile_picture;
    document.getElementById('modalUsername').innerText = story.username;

    const diff = Math.floor((new Date() - new Date(story.created_at)) / 60000);
    let timeText = diff + 'm';
    if (diff > 60) timeText = Math.floor(diff / 60) + 'h';
    document.getElementById('modalTime').innerText = timeText;

    // Delete Button
    $('.story-delete-btn').remove();
    // Assuming window.currentUserId is set in indexmo.php
    if (window.currentUserId && story.user_id == window.currentUserId) {
        const del = $('<button class="story-delete-btn"><i class="fas fa-trash"></i></button>')
            .css({ background: 'none', border: 'none', color: 'white', fontSize: '20px', cursor: 'pointer', marginRight: '15px' })
            .on('click', () => deleteStory(story.id));
        $('.user-info-story').append(del);
    }

    // Progress Bar
    const progHtml = currentStories.map((_, i) =>
        `<div class="story-progress-bar"><div class="story-progress-fill" style="width:${i < currentStoryIndex ? '100%' : '0%'}" ${i === currentStoryIndex ? 'id="currentProgressFill"' : ''}></div></div>`
    ).join('');
    $('#storyProgress').html(progHtml);

    // Media Container Clear
    const container = document.getElementById('storyMediaContainer');
    container.innerHTML = '';

    // Stop Background Audio
    const oldAud = document.getElementById('storyBackgroundAudio');
    if (oldAud) { oldAud.pause(); oldAud.remove(); }

    // Play new music if exists
    if (story.music_url && story.music_url !== "null") {
        const aud = new Audio(story.music_url);
        aud.id = 'storyBackgroundAudio';
        aud.loop = true;
        aud.volume = 0.5;
        container.appendChild(aud);
        aud.play().catch((e) => console.log("Audio play error", e));

        $('<div class="story-music-label"><i class="fas fa-music"></i> <span>' + (story.music_title || 'Music') + '</span></div>')
            .css({ position: 'absolute', top: '80px', left: '15px', background: 'rgba(0,0,0,0.4)', padding: '5px 10px', borderRadius: '15px', color: 'white', fontSize: '12px', zIndex: 5001 })
            .appendTo(container);
    }

    // Render Image or Video
    if (story.file_type === 'image') {
        $(container).append(`<img src="${story.file_path}" class="story-media-full">`);
        startProgress(60000); // 1 Minute
    } else {
        // Add spinner for video buffering
        const bufferingSpinner = document.createElement('div');
        bufferingSpinner.className = 'spinner-border text-light';
        bufferingSpinner.style.position = 'absolute';
        bufferingSpinner.style.zIndex = '5002';
        container.appendChild(bufferingSpinner);

        const vid = document.createElement('video');
        vid.className = 'story-media-full';
        vid.playsInline = true;
        vid.autoplay = true;
        vid.preload = 'auto'; // Optimize loading
        vid.muted = (story.music_url && story.music_url !== "null") ? true : false;
        vid.src = story.file_path;

        // Handle buffering states
        vid.onwaiting = () => bufferingSpinner.style.display = 'block';
        vid.onplaying = () => bufferingSpinner.style.display = 'none';
        vid.oncanplay = () => bufferingSpinner.style.display = 'none';

        vid.onended = nextStory;
        vid.onloadedmetadata = () => {
            startProgress(vid.duration * 1000);
        };
        // Add click to pause/play for video
        vid.onclick = function () {
            if (vid.paused) vid.play(); else vid.pause();
        };
        container.appendChild(vid);
    }

    // Analytics
    if (story.id) {
        recordStoryView(story.id);
        updateViewersCount(story.id);
        updateCommentsCount(story.id);
    }

    // Comments Button Update
    $('.story-comments-btn').remove();
    const comBtn = $('<div class="story-comments-btn"><i class="fas fa-comment"></i> <span id="storyCommentCount">...</span></div>');
    comBtn.on('click', (e) => { e.stopPropagation(); toggleStoryComments(); });
    $(container.parentElement).append(comBtn);
}

function startProgress(duration) {
    if (!duration || isNaN(duration)) duration = 5000;

    clearInterval(progressInterval);
    const step = 50;
    let elapsed = 0;

    progressInterval = setInterval(() => {
        // Check if paused (e.g. holding screen) - Not implemented yet, assumed auto-play
        elapsed += step;
        let pct = (elapsed / duration) * 100;
        if (pct > 100) pct = 100;

        $('#currentProgressFill').css('width', pct + '%');

        if (elapsed >= duration) {
            clearInterval(progressInterval);
            nextStory();
        }
    }, step);
}

function nextStory() {
    clearInterval(progressInterval);
    if (currentStoryIndex < currentStories.length - 1) {
        currentStoryIndex++;
        renderStory();
    } else {
        closeStoryModal();
    }
}

function prevStory() {
    clearInterval(progressInterval);
    if (currentStoryIndex > 0) {
        currentStoryIndex--;
        renderStory();
    } else {
        // Restart current
        renderStory();
    }
}

function closeStoryModal() {
    $('#storyModal').hide();
    clearInterval(progressInterval);
    const container = document.getElementById('storyMediaContainer');
    if (container) {
        container.innerHTML = ''; // Stops video/audio inside
    }
}

function deleteStory(id) {
    if (confirm('هل أنت متأكد من حذف هذه القصة؟')) {
        // Submit form to delete
        const form = $('<form method="POST" action="indexmo.php"><input type="hidden" name="delete_story_id" value="' + id + '"></form>');
        $('body').append(form);
        form.submit();
    }
}

function recordStoryView(storyId) {
    $.post('indexmo.php', { record_story_view: 1, story_id: storyId });
}

function updateViewersCount(storyId) {
    $.get('indexmo.php?get_story_view_count=' + storyId, function (res) {
        try {
            const data = (typeof res === 'string') ? JSON.parse(res) : res;
            $('#storyViewCount').text(data.count);
        } catch (e) { }
    });
}

function toggleStoryViewers() {
    const modal = $('#storyViewersModal');
    if (modal.is(':visible')) {
        modal.hide();
    } else {
        modal.show();
        loadStoryViewers(currentStoryId);
    }
}

function loadStoryViewers(storyId) {
    $('#storyViewersList').html('<li>جاري التحميل...</li>');
    $.get('indexmo.php?get_story_viewers=' + storyId, function (res) {
        try {
            const data = (typeof res === 'string') ? JSON.parse(res) : res;
            let h = '';
            if (data.viewers && data.viewers.length > 0) {
                data.viewers.forEach(v => {
                    h += `<li>
                            <img src="${v.profile_picture || 'uploads/profile.jpg'}" class="viewer-img" onerror="this.src='uploads/profile.jpg'">
                            <span>${v.username}</span>
                          </li>`;
                });
            } else {
                h = '<li class="text-muted p-2">لا يوجد مشاهدون بعد</li>';
            }
            $('#storyViewersList').html(h);
        } catch (e) { }
    });
}

// Comments Logic for Story
function toggleStoryComments() {
    const modal = $('#storyCommentsModal');
    if (modal.hasClass('active')) {
        modal.removeClass('active');
    } else {
        modal.addClass('active');
        loadStoryComments(currentStoryId);
    }
}

function loadStoryComments(storyId) {
    $('#storyCommentsList').html('<div class="text-center">جاري التحميل...</div>');
    $.get('indexmo.php?action=get_story_comments&story_id=' + storyId, function (res) {
        try {
            const data = (typeof res === 'string') ? JSON.parse(res) : res;
            let h = '';
            if (data.comments && data.comments.length > 0) {
                data.comments.forEach(c => {
                    const del = (data.current_user_id == c.user_id) ? `<i class="fas fa-trash story-comment-delete" onclick="deleteStoryComment(${c.id})"></i>` : '';
                    h += `<div class="story-comment-item">
                            <img src="${c.profile_picture || 'uploads/profile.jpg'}" class="story-comment-avatar" onerror="this.src='uploads/profile.jpg'">
                            <div class="story-comment-content">
                                <div class="story-comment-user">${c.username}</div>
                                <div class="story-comment-text">${c.comment} ${del}</div>
                            </div>
                          </div>`;
                });
            } else {
                h = '<div class="text-center text-muted p-3">لا توجد تعليقات</div>';
            }
            $('#storyCommentsList').html(h);
        } catch (e) { }
    });
}

function postStoryComment() {
    const txt = $('#storyCommentInput').val();
    if (!txt || !currentStoryId) return;
    $('#storyCommentInput').val('');

    $.post('indexmo.php', { action: 'add_story_comment', story_id: currentStoryId, comment: txt }, function () {
        loadStoryComments(currentStoryId);
        updateCommentsCount(currentStoryId);
    });
}

function deleteStoryComment(id) {
    if (confirm('حذف التعليق؟')) {
        $.post('indexmo.php', { action: 'delete_story_comment', comment_id: id }, function () {
            loadStoryComments(currentStoryId);
            updateCommentsCount(currentStoryId);
        });
    }
}

function updateCommentsCount(storyId) {
    // Optional: Update count in UI
}

// Share Logic
let currentShareContentId = null;
let currentShareContentType = null;

function openShareModal(contentId, type) {
    currentShareContentId = contentId;
    currentShareContentType = type;
    $('#shareModal').modal('show'); // Bootstrap modal
    $('#friends-share-list').html('<div class="text-center p-3">جاري التحميل...</div>');

    $.get('get_friends.php', function (data) {
        try {
            const res = (typeof data === 'string') ? JSON.parse(data) : data;
            if (res.success && res.friends.length > 0) {
                let h = '';
                res.friends.forEach(f => {
                    h += `<button type="button" class="list-group-item list-group-item-action d-flex align-items-center" onclick="shareWithFriend(${f.id})">
                             <img src="${f.profile_picture}" class="rounded-circle me-3" width="40" height="40" style="object-fit:cover;" onerror="this.src='uploads/profile.jpg'">
                             <span>${f.username}</span>
                             <i class="fas fa-paper-plane ms-auto text-primary"></i>
                           </button>`;
                });
                $('#friends-share-list').html(h);
            } else {
                $('#friends-share-list').html('<div class="text-center p-3 text-muted">لا يوجد أصدقاء للمشاركة معهم</div>');
            }
        } catch (e) {
            console.error(e);
            $('#friends-share-list').html('<div class="text-center p-3 text-danger">خطأ في التحميل: ' + e + '</div>');
        }
    }).fail(function () {
        $('#friends-share-list').html('<div class="text-center p-3 text-danger">خطأ في الاتصال</div>');
    });
}

function shareWithFriend(userId) {
    if (!currentShareContentId) return;
    $.post('friend_actions.php', {
        action: 'share',
        user_id: userId,
        item_id: currentShareContentId,
        item_type: currentShareContentType
    }, function (res) {
        alert('Shared successfully!');
        var el = document.getElementById('shareModal');
        var modal = bootstrap.Modal.getInstance(el);
        if (modal) modal.hide();
    });
}

function toggleLike(videoId, btn) {
    const likeBtn = $(btn);
    $.ajax({
        url: 'indexmo.php',
        method: 'POST',
        data: { like_video_id: videoId },
        dataType: 'json',
        success: function (data) {
            if (data.success) {
                $('#likes-' + videoId).text(data.likes);
                if (data.action === 'added') {
                    likeBtn.addClass('text-rose-500');
                    likeBtn.find('i').removeClass('far').addClass('fas');
                } else {
                    likeBtn.removeClass('text-rose-500');
                    likeBtn.find('i').removeClass('fas').addClass('far');
                }
            }
        },
        error: function (err) {
            console.error("Like error:", err);
        }
    });
}

function toggleVideoMute(btn, videoId) {
    const video = $(btn).siblings('video')[0];
    const icon = $(btn).find('.mute-icon-' + videoId);

    if (video) {
        video.muted = !video.muted;
        if (video.muted) {
            icon.removeClass('fa-volume-up').addClass('fa-volume-mute');
            $(btn).addClass('bg-black/30').removeClass('bg-blue-600/50');
        } else {
            icon.removeClass('fa-volume-mute').addClass('fa-volume-up');
            $(btn).removeClass('bg-black/30').addClass('bg-blue-600/50');
        }
    }
}

function copyToClipboard(text) {
    const dummy = document.createElement('input');
    document.body.appendChild(dummy);
    dummy.value = text;
    dummy.select();
    document.execCommand('copy');
    document.body.removeChild(dummy);
    alert('Link copied to clipboard!');
}
