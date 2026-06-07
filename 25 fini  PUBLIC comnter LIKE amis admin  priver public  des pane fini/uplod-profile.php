<?php
session_start();
include("config.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Allow long execution for large file assembly
ini_set('max_execution_time', 3600); // 1 hour
ini_set('memory_limit', '512M');

// Processing chunk uploads (AJAX) - Optimized with incremental appending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['chunk'])) {
    header('Content-Type: application/json');
    $uploadDir = "videos/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    
    // Clean fileId for safety
    $fileId = preg_replace('/[^A-Za-z0-9_]/', '', $_POST['fileId'] ?? '');
    $chunkIndex = intval($_POST['chunkIndex'] ?? 0);
    $totalChunks = intval($_POST['totalChunks'] ?? 0);
    $fileNameArr = explode('.', $_POST['fileName'] ?? 'video.mp4');
    $ext = end($fileNameArr);
    $subject = $_POST['subject'] ?? '';
    $title = $_POST['title'] ?? '';
    $privacy = $_POST['privacy'] ?? 'public';
    
    $tempFile = $uploadDir . "part_" . $fileId . ".tmp";
    
    // Append chunk to the temporary file
    $out = fopen($tempFile, ($chunkIndex == 0) ? "wb" : "ab");
    if ($out) {
        $in = fopen($_FILES['chunk']['tmp_name'], "rb");
        if ($in) {
            while ($buff = fread($in, 8192)) {
                fwrite($out, $buff);
            }
            fclose($in);
        }
        fclose($out);
        
        // If this was the last chunk, finalize the file and database entry
        if ($chunkIndex == $totalChunks - 1) {
            $finalFileName = time() . "_" . $user_id . "." . $ext;
            $finalPath = "videos/" . $finalFileName;
            
            if (rename($tempFile, $finalPath)) {
                $stmt = $con->prepare("INSERT INTO videos (location, subject, title, user_id, privacy) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssis", $finalPath, $subject, $title, $user_id, $privacy);
                if ($stmt->execute()) {
                    echo json_encode(["status" => "success", "message" => "Video uploaded successfully."]);
                } else {
                    echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
                }
                $stmt->close();
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to finalize video file."]);
            }
        } else {
            echo json_encode(["status" => "progress", "index" => $chunkIndex]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Could not write to temporary file."]);
    }
    exit;
}

$pageTitle = "Upload High-Fidelity Video";
include("includes/layout_start.php");
?>

<div class="max-w-2xl mx-auto py-8">
    <div class="bg-white rounded-[2.5rem] shadow-xl border border-slate-100 overflow-hidden">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-8 pb-12 flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-black text-white font-brand mb-1">Create Moment</h2>
                <p class="text-blue-100 text-xs font-medium">Capture and share in high fidelity.</p>
            </div>
            <div class="w-16 h-16 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center text-white text-2xl">
                <i class="fas fa-video"></i>
            </div>
        </div>

        <div class="p-8 -mt-6 bg-white rounded-t-[2.5rem]">
            <form id="uploadForm" class="space-y-6">
                <!-- Dropzone Area -->
                <div class="relative group">
                    <label for="fileInput" class="block w-full cursor-pointer">
                        <div class="border-2 border-dashed border-slate-200 rounded-3xl p-10 text-center transition-all group-hover:border-blue-500 group-hover:bg-blue-50/30">
                            <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform">
                                <i class="fas fa-cloud-arrow-up text-3xl text-slate-400 group-hover:text-blue-500"></i>
                            </div>
                            <h4 class="text-sm font-black text-slate-700 mb-1">Select Video (Vertical or Horizontal)</h4>
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">MP4, WEBM (No Size Limit)</p>
                            <div id="fileNameDisplay" class="mt-4 px-4 py-2 bg-blue-100 text-blue-600 rounded-xl text-[11px] font-black hidden"></div>
                        </div>
                    </label>
                    <input type="file" id="fileInput" accept="video/*" class="hidden" required>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 px-1">Hook Title</label>
                        <input type="text" id="subject" placeholder="What's the catch?" required class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-sm font-medium">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 px-1">Topic Category</label>
                        <select id="privacy" class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-sm font-bold text-slate-700 appearance-none">
                            <option value="public">🌍 Public (Discovery)</option>
                            <option value="private">🔒 Private (Friends Only)</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-2 text-right dir-rtl">
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 px-1">وصف الفيديو</label>
                    <textarea id="title" rows="3" placeholder="أدخل وصفاً مشوقاً..." required class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-blue-500 text-sm font-medium text-right"></textarea>
                </div>

                <!-- Progress / Status Area -->
                <div id="statusArea" class="hidden space-y-3">
                    <div class="flex items-center justify-between px-1">
                        <span id="statusText" class="text-[10px] font-black uppercase tracking-widest text-blue-600 animate-pulse">Processing...</span>
                        <span id="percentText" class="text-[10px] font-black text-slate-400">0%</span>
                    </div>
                    <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                        <div id="progressBar" class="h-full bg-blue-600 transition-all duration-300" style="width: 0%"></div>
                    </div>
                </div>

                <div id="message" class="hidden p-4 rounded-2xl text-[11px] font-bold text-center"></div>

                <button type="button" id="uploadBtn" class="w-full py-4 bg-slate-900 text-white rounded-2xl font-black text-sm hover:bg-slate-800 transition-all flex items-center justify-center gap-3 group shadow-xl shadow-slate-900/10">
                    <span>Upload Now</span>
                    <i class="fas fa-arrow-right transition-transform group-hover:translate-x-1"></i>
                </button>
                
                <p class="text-center text-[9px] text-slate-400 font-bold uppercase tracking-widest pt-2">
                    By uploading, you agree to our <a href="#" class="text-blue-500">Community Terms</a>
                </p>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadBtn = document.getElementById("uploadBtn");
    const fileInput = document.getElementById("fileInput");
    const fileNameDisplay = document.getElementById("fileNameDisplay");
    const progressBar = document.getElementById("progressBar");
    const percentText = document.getElementById("percentText");
    const statusArea = document.getElementById("statusArea");
    const messageDiv = document.getElementById("message");
    const chunkSize = 1 * 1024 * 1024; // 1MB chunks for safer upload on shared hosting
    
    fileInput.addEventListener('change', function() {
        const file = fileInput.files[0];
        if (!file) {
            fileNameDisplay.classList.add('hidden');
            return;
        }

        fileNameDisplay.textContent = 'Ready: ' + file.name;
        fileNameDisplay.classList.remove('hidden');
        messageDiv.classList.add('hidden');
    });
    
    uploadBtn.addEventListener("click", function() {
        const file = fileInput.files[0];
        const subject = document.getElementById("subject").value;
        const title = document.getElementById("title").value;
        const privacy = document.getElementById("privacy").value;
        
        if (!file) {
            showMessage('Select a video file first', 'bg-amber-50 text-amber-600');
            return;
        }
        
        if (!subject || !title) {
            showMessage('Title and description required', 'bg-amber-50 text-amber-600');
            return;
        }
        
        statusArea.classList.remove('hidden');
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<div class="w-5 h-5 border-2 border-white/50 border-t-white animate-spin rounded-full"></div> Sending...';
        messageDiv.classList.add('hidden');
        
        // Create a truly unique ID for the session
        const fileId = Date.now() + "_" + Math.floor(Math.random() * 1000000);
        let chunkIndex = 0;
        const totalChunks = Math.ceil(file.size / chunkSize);
        
        function uploadChunk(start) {
            const end = Math.min(start + chunkSize, file.size);
            const chunk = file.slice(start, end);
            
            const formData = new FormData();
            formData.append("fileId", fileId);
            formData.append("chunkIndex", chunkIndex);
            formData.append("totalChunks", totalChunks);
            formData.append("fileName", file.name);
            formData.append("subject", subject);
            formData.append("title", title);
            formData.append("privacy", privacy);
            formData.append("chunk", chunk);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(res => {
                if (res.status === 'error') {
                   throw new Error(res.message);
                }
                
                const percent = Math.min(100, Math.floor((end / file.size) * 100));
                progressBar.style.width = percent + "%";
                percentText.textContent = percent + "%";
                
                if (end < file.size) {
                    chunkIndex++;
                    uploadChunk(end);
                } else {
                    showMessage('Moment created successfully!', 'bg-emerald-50 text-emerald-600');
                    document.getElementById('statusText').textContent = 'Completed';
                    document.getElementById('statusText').classList.remove('animate-pulse');
                    setTimeout(() => window.location.href = 'indexmo.php', 1500);
                }
            })
            .catch(error => {
                console.error(error);
                showMessage('Upload failed: ' + error.message, 'bg-rose-50 text-rose-600');
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Try Again';
            });
        }
        
        uploadChunk(0);
    });
    
    function showMessage(text, classes) {
        messageDiv.textContent = text;
        messageDiv.className = `p-4 rounded-2xl text-[11px] font-bold text-center ${classes}`;
        messageDiv.classList.remove('hidden');
    }
});
</script>

<?php
include("includes/layout_end.php");
?>