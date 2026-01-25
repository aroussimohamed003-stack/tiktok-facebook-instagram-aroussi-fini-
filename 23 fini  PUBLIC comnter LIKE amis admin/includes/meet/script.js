// HTTPS Check
if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
    alert('WebRTC Video Chat requires HTTPS. Please use a secure connection (https://) or localhost.');
}

let localStream;
let peer;
let currentCall;
const peers = {};
const videoGrid = document.getElementById('video-grid');
const myVideo = document.createElement('video');
myVideo.muted = true;
myVideo.setAttribute('playsinline', ''); // Critical for iOS

// Get params
const urlParams = new URLSearchParams(window.location.search);
const ROOM_ID = urlParams.get('room') || 'LiveChat';
const USERNAME = urlParams.get('username') || 'User_' + Math.floor(Math.random() * 1000);
let MY_PEER_ID;

// Media Recorder
let mediaRecorder;
let recordedChunks = [];

function logError(msg) {
    console.error(msg);
    const errDiv = document.getElementById('error-log');
    errDiv.style.display = 'block';
    errDiv.innerText += msg + '\n';
}

function startMeeting() {
    document.getElementById('join-screen').style.display = 'none';
    init();
}

// function logError(msg) { ... } // Duplicate removed
// function startMeeting() { ... } // Duplicate removed

// Auto-init removed to prevent double stream/peer creation. 
// User must click "Join" to start.

function init() {
    // Initialize PeerJS - Use default public cloud
    peer = new Peer(undefined, {
        debug: 2 // Print errors to console
    });

    peer.on('error', err => {
        logError('PeerJS Error: ' + err.type);
    });

    peer.on('open', id => {
        MY_PEER_ID = id;
        console.log('My Peer ID:', id);

        // Join room logic (Heartbeat)
        joinRoom();
        setInterval(heartbeat, 5000);

        // Get User Media - Try Video + Audio first
        navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: "user",
                width: { ideal: 640 },
                height: { ideal: 480 }
            },
            audio: true
        }).then(stream => {
            handleSuccess(stream);
        }).catch(err => {
            logError('Video Access Failed: ' + err.message + '. Trying audio only...');
            // Fallback: Audio only
            navigator.mediaDevices.getUserMedia({
                video: false,
                audio: true
            }).then(stream => {
                handleSuccess(stream);
            }).catch(err2 => {
                logError('Audio Access Failed: ' + err2.message);
                alert('Could not access microphone. Please check permissions.');
            });
        });

        function handleSuccess(stream) {
            localStream = stream;
            addVideoStream(myVideo, stream, USERNAME || 'Me');

            // Answer calls
            peer.on('call', call => {
                // Check if we are already connected to this user to prevent loop/dups
                if (peers[call.peer]) {
                    console.log('Already connected to ' + call.peer + ', ignoring incoming call.');
                    return;
                }

                call.answer(stream);
                peers[call.peer] = call;

                call.on('stream', userVideoStream => {
                    const video = document.createElement('video');
                    video.setAttribute('playsinline', '');

                    // Read username from metadata if available
                    let callerName = 'User';
                    if (call.metadata && call.metadata.username) {
                        callerName = call.metadata.username;
                    }

                    addVideoStream(video, userVideoStream, callerName, call.peer);

                    // Cleanup 
                    call.on('close', () => {
                        removeVideo(call.peer); // correct ID usage
                        delete peers[call.peer];
                    });

                    call.on('error', err => {
                        logError('Call error: ' + err);
                        removeVideo(video);
                        delete peers[call.peer];
                    });
                });
            });

            // Poll for new users to call
            setInterval(checkRoomUsers, 5000);
        }
    });
}

// Remove the automatic DOMContentLoaded init
// document.addEventListener('DOMContentLoaded', init);

function joinRoom() {
    fetch(`api.php?action=join&room=${ROOM_ID}&user=${USERNAME}&id=${MY_PEER_ID}`);
}

function heartbeat() {
    if (!MY_PEER_ID) return;
    fetch(`api.php?action=heartbeat&room=${ROOM_ID}&id=${MY_PEER_ID}`);
}

function checkRoomUsers() {
    if (!localStream || !MY_PEER_ID) return;

    fetch(`api.php?action=list&room=${ROOM_ID}`)
        .then(res => res.json())
        .then(users => {
            Object.keys(users).forEach(userId => {
                if (userId !== MY_PEER_ID && !peers[userId]) {
                    // Prevent duplicate calls: Only the "larger" ID calls the "smaller" ID
                    // The other side will wait to receive the call.
                    if (userId < MY_PEER_ID) {
                        connectToNewUser(userId, localStream);
                    }
                }
            });
        });
}

// Helper to safely remove video elements
function removeVideo(userId) {
    const wrapper = document.querySelector(`.video-wrapper[data-user-id="${userId}"]`);
    if (wrapper) wrapper.remove();
}

function connectToNewUser(userId, stream) {
    if (peers[userId]) return; // Already connected

    console.log('Calling User:', userId);
    // Send our username in metadata
    const call = peer.call(userId, stream, {
        metadata: { username: USERNAME }
    });

    call.on('stream', userVideoStream => {
        const video = document.createElement('video');
        video.setAttribute('playsinline', '');
        // Use userId as label initially, but maybe update if we had a way to get their name back?
        // For distinct "caller" vs "callee", usually we need 2-way handshake or api look up.
        // For now, let's label them "User" or try to fetch name from API if complex.
        // WITHOUT API lookup, we can't know the callee's name easily unless they stream it back.
        // For valid names, we'll settle for "User" on outgoing calls for now,
        // unless we fetch from api.php list. Let's try simple API lookup from the 'users' list we already have?
        // Actually, checkRoomUsers has the list! We can pass it.
        addVideoStream(video, userVideoStream, 'User', userId);

        call.on('close', () => {
            removeVideo(userId);
            delete peers[userId];
        });

        call.on('error', (err) => {
            console.warn('Call error:', err);
            removeVideo(userId);
            delete peers[userId];
        });
    });

    peers[userId] = call;
}

function addVideoStream(video, stream, labelText, userId = null) {
    // Deduplication check
    if (userId) {
        // Remove any existing video for this user before adding new one
        const existingWrapper = document.querySelector(`.video-wrapper[data-user-id="${userId}"]`);
        if (existingWrapper) {
            console.log('Removing existing video for user ' + userId);
            existingWrapper.remove();
        }
    }

    if (video.srcObject) return; // Prevent duplicate src assignment
    video.srcObject = stream;
    video.addEventListener('loadedmetadata', () => {
        video.play().catch(e => logError('Playback failed: ' + e));
    });

    const wrapper = document.createElement('div');
    wrapper.className = 'video-wrapper';
    if (userId) {
        wrapper.setAttribute('data-user-id', userId);
    }

    const label = document.createElement('div');
    label.className = 'user-label';
    label.innerText = labelText;

    wrapper.appendChild(video);
    wrapper.appendChild(label);
    videoGrid.appendChild(wrapper);
}

// Controls
function toggleAudio() {
    const audioTrack = localStream.getAudioTracks()[0];
    if (audioTrack) {
        audioTrack.enabled = !audioTrack.enabled;
        document.getElementById('audio-btn').classList.toggle('danger', !audioTrack.enabled);
        updateIcon('audio-btn', audioTrack.enabled ? 'fa-microphone' : 'fa-microphone-slash');
    }
}

function toggleVideo() {
    const videoTrack = localStream.getVideoTracks()[0];
    if (videoTrack) {
        videoTrack.enabled = !videoTrack.enabled;
        document.getElementById('video-btn').classList.toggle('danger', !videoTrack.enabled);
        updateIcon('video-btn', videoTrack.enabled ? 'fa-video' : 'fa-video-slash');
    }
}

function updateIcon(btnId, iconClass) {
    const btn = document.getElementById(btnId);
    const i = btn.querySelector('i');
    i.className = `fas ${iconClass}`;
}

async function startScreenShare() {
    try {
        const screenStream = await navigator.mediaDevices.getDisplayMedia({
            video: true
        });

        // Replace video track in local stream
        const videoTrack = screenStream.getVideoTracks()[0];
        const sender = currentCall ? currentCall.peerConnection.getSenders().find(s => s.track.kind === 'video') : null;

        if (sender) {
            sender.replaceTrack(videoTrack);
        }

        myVideo.srcObject = screenStream;

        videoTrack.onended = () => {
            stopScreenShare(); // Revert when stopped
        };

        // Also update for FUTURE calls (hacky, simple Replace)
        // Ideally we negotiate a new stream.

    } catch (err) {
        console.error("Error sharing screen", err);
    }
}

function stopScreenShare() {
    // Revert to camera
    const constraints = {
        video: { facingMode: "user" },
        audio: true
    };
    navigator.mediaDevices.getUserMedia(constraints).then(stream => {
        const videoTrack = stream.getVideoTracks()[0];
        // Replace logic similar to above
        if (myVideo) myVideo.srcObject = stream;
    });
}

// Recording
function toggleRecord() {
    const btn = document.getElementById('record-btn');
    if (btn.classList.contains('active')) {
        // Stop
        mediaRecorder.stop();
        btn.classList.remove('active');
    } else {
        // Start
        startRecording();
        btn.classList.add('active');
    }
}

function startRecording() {
    recordedChunks = [];
    // Record the local stream (or we could use a canvas to record composite)
    // For simplicity, record local stream or screen share
    const stream = myVideo.srcObject || localStream;

    const options = { mimeType: 'video/webm; codecs=vp9' };
    mediaRecorder = new MediaRecorder(stream, options);

    mediaRecorder.ondataavailable = handleDataAvailable;
    mediaRecorder.onstop = handleStop;
    mediaRecorder.start();
}

function handleDataAvailable(event) {
    if (event.data.size > 0) {
        recordedChunks.push(event.data);
    }
}

function handleStop() {
    const blob = new Blob(recordedChunks, {
        type: 'video/webm'
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    document.body.appendChild(a);
    a.style = 'display: none';
    a.href = url;
    a.download = 'recording.webm';
    a.click();
    window.URL.revokeObjectURL(url);
}

// document.addEventListener('DOMContentLoaded', init);
