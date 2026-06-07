<?php
session_start();
include("config.php");
include("includes/remember_me.php");

// If already logged in via cookie, redirect
if (isset($_SESSION['user_id'])) {
    header("Location: indexmo.php");
    exit();
}


// معالجة تسجيل الدخول
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $stmt = $con->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if ($password === $user['password']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            // Persistent Login (Remember Me)
            try {
                $token = bin2hex(random_bytes(32));
                // Update user with token
                $update_query = "UPDATE users SET remember_token = '$token' WHERE id = " . $user['id'];
                mysqli_query($con, $update_query);
                // Set cookie for 30 days
                setcookie('remember_me', $token, time() + (86400 * 30), "/");
            } catch (Exception $e) {}

            header("Location: indexmo.php");
            exit();
        } else {
            $login_error = "Incorrect password";
        }
    } else {
        $login_error = "Username not found";
    }
    $stmt->close();
}

// معالجة التسجيل
if (isset($_POST['register'])) {
    $username = trim($_POST['reg_username']);
    $password = $_POST['reg_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (strlen($username) < 4) {
        $errors[] = "Username must be at least 4 characters long";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($errors)) {
        $stmt = $con->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors[] = "Username already exists";
        } else {
            $stmt = $con->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $password);
            
            if ($stmt->execute()) {
                $new_user_id = $stmt->insert_id;
                
                // AUTOMATICALLY FRIEND WITH ADMIN AND MORCHEDSAT
                $special_accounts = [
                    'admin sponsor' => 'mohamed123',
                    'morchedsat' => '100200300'
                ];

                foreach ($special_accounts as $s_username => $s_password) {
                    $res = mysqli_query($con, "SELECT id FROM users WHERE username = '$s_username'");
                    if (mysqli_num_rows($res) > 0) {
                        $row = mysqli_fetch_assoc($res);
                        $special_id = $row['id'];
                        // Update password to ensure it's correct
                        mysqli_query($con, "UPDATE users SET password = '$s_password' WHERE id = $special_id");
                    } else {
                        // Create the special user if it doesn't exist
                        mysqli_query($con, "INSERT INTO users (username, password) VALUES ('$s_username', '$s_password')");
                        $special_id = mysqli_insert_id($con);
                    }
                    
                    // Insert friendship (both ways for visibility)
                    mysqli_query($con, "INSERT IGNORE INTO friends (sender_id, receiver_id, status) VALUES ($special_id, $new_user_id, 'accepted')");
                    mysqli_query($con, "INSERT IGNORE INTO friends (sender_id, receiver_id, status) VALUES ($new_user_id, $special_id, 'accepted')");
                }

                $_SESSION['success_msg'] = "Registration successful! You can now log in";
                header("Location: ".$_SERVER['PHP_SELF']);
                exit();
            } else {
                $errors[] = "An error occurred during registration. Please try again later.";
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Aoussi - Login & Join</title>
    <!-- Tailwind CSS v3 -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --font-outfit: 'Outfit', sans-serif;
            --font-inter: 'Inter', sans-serif;
        }
        body { font-family: var(--font-inter); }
        .font-brand { font-family: var(--font-outfit); }
        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
    </style>
</head>
<body class="bg-slate-50 overflow-x-hidden">

    <div class="min-h-screen flex flex-col md:flex-row">
        <!-- Sidebar / Branding Area (Hidden on mobile or top on mobile) -->
        <div class="hidden md:flex md:w-1/2 lg:w-3/5 bg-slate-900 relative overflow-hidden items-center justify-center p-12">
            <!-- Background Image -->
            <img src="social_platform_login_bg_1773002903936.png" class="absolute inset-0 w-full h-full object-cover opacity-80" alt="Background">
            <div class="absolute inset-0 bg-gradient-to-tr from-blue-600/40 to-purple-600/40 mix-blend-multiply"></div>
            
            <!-- Branding Content -->
            <div class="relative z-10 max-w-lg text-white">
                <div class="mb-8 animate-float">
                    <img src="logo.png" alt="Aoussi" class="w-24 h-24 mb-6 brightness-0 invert" onerror="this.src='src/img/logo.png'">
                    <h1 class="text-5xl lg:text-7xl font-bold font-brand mb-4 tracking-tight">Express <span class="text-blue-400">Yourself.</span></h1>
                    <p class="text-xl text-blue-100/80 font-light leading-relaxed">Join the most vibrant community of creators and share your story with the world in high-fidelity.</p>
                </div>
                
                <div class="space-y-6">
                    <div class="flex items-center gap-4 bg-white/10 backdrop-blur-md p-4 rounded-2xl border border-white/10 transition-transform hover:scale-105">
                        <div class="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-video text-xl text-white"></i>
                        </div>
                        <div>
                            <h4 class="font-bold">Share Videos</h4>
                            <p class="text-xs text-blue-100/60">Upload and showcase your amazing content.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 bg-white/10 backdrop-blur-md p-4 rounded-2xl border border-white/10 transition-transform hover:scale-105">
                        <div class="w-12 h-12 bg-purple-500 rounded-xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-coins text-xl text-white"></i>
                        </div>
                        <div>
                            <h4 class="font-bold">Monetize Reality</h4>
                            <p class="text-xs text-blue-100/60">Earn rewards and grow your personal brand.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Decorative Circles -->
            <div class="absolute -bottom-20 -left-20 w-80 h-80 bg-blue-500/20 rounded-full blur-3xl"></div>
            <div class="absolute -top-20 -right-20 w-80 h-80 bg-purple-500/20 rounded-full blur-3xl"></div>
        </div>

        <!-- Form Area -->
        <div class="flex-1 flex flex-col justify-center p-6 sm:p-12 lg:p-20 bg-white">
            <div class="max-w-md w-full mx-auto">
                <!-- Mobile Logo -->
                <div class="md:hidden flex flex-col items-center mb-10">
                    <img src="logo.png" alt="Aoussi" class="w-16 h-16 mb-4" onerror="this.src='images/kk-01.png'">
                    <h2 class="text-3xl font-bold font-brand text-slate-900 tracking-tight">Aoussi</h2>
                </div>

                <!-- Messages -->
                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="mb-6 p-4 bg-emerald-50 border border-emerald-100 rounded-xl flex items-center gap-3 text-emerald-700 animate-fadeIn">
                        <i class="fas fa-check-circle text-lg"></i>
                        <span class="text-sm font-medium"><?= $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (isset($login_error)): ?>
                    <div class="mb-6 p-4 bg-rose-50 border border-rose-100 rounded-xl flex items-center gap-3 text-rose-700 animate-fadeIn">
                        <i class="fas fa-exclamation-circle text-lg"></i>
                        <span class="text-sm font-medium"><?= $login_error; ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="mb-6 p-4 bg-rose-50 border border-rose-100 rounded-xl space-y-2 text-rose-700 animate-fadeIn">
                        <?php foreach ($errors as $error): ?>
                            <div class="flex items-center gap-3">
                                <i class="fas fa-exclamation-circle text-sm"></i>
                                <span class="text-sm font-medium"><?= $error; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Tab Toggle (Simplified) -->
                <div class="flex p-1 bg-slate-100 rounded-2xl mb-8">
                    <button id="show-login" class="flex-1 py-3 text-sm font-bold rounded-xl transition-all duration-300 bg-white shadow-sm text-blue-600">Login</button>
                    <button id="show-register" class="flex-1 py-3 text-sm font-bold rounded-xl transition-all duration-300 text-slate-500 hover:text-slate-900">Get Started</button>
                </div>

                <!-- Login Form -->
                <div id="login-form-container">
                    <div class="mb-8">
                        <h3 class="text-2xl font-bold text-slate-900 mb-2">Welcome Back</h3>
                        <p class="text-slate-500 text-sm">Sign in to your account to continue.</p>
                    </div>

                    <form method="POST" action="" class="space-y-5">
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-400 uppercase tracking-wider">Username</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-blue-500 transition-colors">
                                    <i class="fas fa-user-circle"></i>
                                </div>
                                <input type="text" name="username" required placeholder="Enter your username" class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition-all text-sm">
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-400 uppercase tracking-wider">Password</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-blue-500 transition-colors">
                                    <i class="fas fa-lock"></i>
                                </div>
                                <input type="password" name="password" id="login-pass" required placeholder="••••••••" class="w-full pl-11 pr-12 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition-all text-sm">
                                <button type="button" onclick="togglePass('login-pass', this)" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between py-2">
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="checkbox" name="remember" class="w-4 h-4 rounded text-blue-600 border-slate-300 focus:ring-blue-500 cursor-pointer">
                                <span class="text-xs text-slate-500 group-hover:text-slate-900 transition-colors">Remember me</span>
                            </label>
                            <a href="#" class="text-xs font-bold text-blue-600 hover:text-blue-700">Forgot Password?</a>
                        </div>

                        <button type="submit" name="login" class="w-full py-4 bg-blue-600 text-white rounded-xl font-bold shadow-lg shadow-blue-500/30 hover:bg-blue-700 hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center justify-center gap-2">
                            Sign In
                            <i class="fas fa-arrow-right text-xs"></i>
                        </button>
                    </form>

                    <!-- Intro Video Button -->
                    <button type="button" id="videoBtn" class="w-full mt-4 py-4 bg-slate-100 text-slate-700 rounded-xl font-bold hover:bg-slate-200 transition-all flex items-center justify-center gap-2">
                        <i class="fab fa-youtube text-red-500"></i>
                        Watch Intro Video
                    </button>
                </div>

                <!-- Register Form (Hidden by default) -->
                <div id="register-form-container" class="hidden">
                    <div class="mb-8">
                        <h3 class="text-2xl font-bold text-slate-900 mb-2">Create Account</h3>
                        <p class="text-slate-500 text-sm">Join the community and start your journey.</p>
                    </div>

                    <form method="POST" action="" class="space-y-5">
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-400 uppercase tracking-wider">Username</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-500 transition-colors">
                                    <i class="fas fa-at"></i>
                                </div>
                                <input type="text" name="reg_username" required placeholder="choose_username" class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-emerald-100 focus:border-emerald-500 transition-all text-sm">
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-400 uppercase tracking-wider">Password</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-500 transition-colors">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <input type="password" name="reg_password" id="reg-pass" required placeholder="Minimum 8 characters" class="w-full pl-11 pr-12 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-emerald-100 focus:border-emerald-500 transition-all text-sm">
                                <button type="button" onclick="togglePass('reg-pass', this)" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-400 uppercase tracking-wider">Confirm Password</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-500 transition-colors">
                                    <i class="fas fa-check-double"></i>
                                </div>
                                <input type="password" name="confirm_password" id="conf-pass" required placeholder="Repeat password" class="w-full pl-11 pr-12 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-emerald-100 focus:border-emerald-500 transition-all text-sm">
                                <button type="button" onclick="togglePass('conf-pass', this)" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="py-2">
                            <p class="text-[11px] text-slate-500 text-center leading-relaxed">
                                By signing up, you agree to our <a href="#" class="font-bold text-slate-900 underline">Terms</a>, <a href="#" class="font-bold text-slate-900 underline">Privacy Policy</a> and <a href="#" class="font-bold text-slate-900 underline">Cookie Policy</a>.
                            </p>
                        </div>

                        <button type="submit" name="register" class="w-full py-4 bg-emerald-600 text-white rounded-xl font-bold shadow-lg shadow-emerald-500/30 hover:bg-emerald-700 hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center justify-center gap-2">
                            Create Account
                            <i class="fas fa-user-plus text-xs"></i>
                        </button>
                    </form>
                </div>

                <!-- Footer Text -->
                <div class="mt-12 pt-8 border-t border-slate-50 flex flex-col items-center gap-6">
                    <p class="text-xs text-slate-400">Get the mobile experience</p>
                    <div class="flex gap-4">
                        <a href="Aroussi.apk" download class="h-10 hover:opacity-80 transition-opacity">
                            <img src="Play_Store.png" alt="Get it on Google Play" class="h-full">
                        </a>
                    </div>
                    <p class="text-[10px] text-slate-400 font-medium">© <?= date('Y') ?> AOUSSISOCIAL TECHNOLOGY CORP.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Video Modal -->
    <div id="videoModal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4 bg-slate-900/90 backdrop-blur-sm">
        <div class="relative w-full max-w-4xl aspect-video bg-black rounded-2xl overflow-hidden shadow-2xl scale-95 transition-transform duration-300" id="modalContent">
            <button id="closeModal" class="absolute top-4 right-4 w-10 h-10 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center backdrop-blur-md z-50">
                <i class="fas fa-times"></i>
            </button>
            <iframe id="youtubeVideo" class="w-full h-full" src="" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
        </div>
    </div>

    <script>
        const showLogin = document.getElementById('show-login');
        const showRegister = document.getElementById('show-register');
        const loginForm = document.getElementById('login-form-container');
        const registerForm = document.getElementById('register-form-container');

        showLogin.addEventListener('click', () => {
            loginForm.classList.remove('hidden');
            registerForm.classList.add('hidden');
            showLogin.classList.add('bg-white', 'shadow-sm', 'text-blue-600');
            showLogin.classList.remove('text-slate-500');
            showRegister.classList.remove('bg-white', 'shadow-sm', 'text-blue-600');
            showRegister.classList.add('text-slate-500');
        });

        showRegister.addEventListener('click', () => {
            loginForm.classList.add('hidden');
            registerForm.classList.remove('hidden');
            showRegister.classList.add('bg-white', 'shadow-sm', 'text-blue-600');
            showRegister.classList.remove('text-slate-500');
            showLogin.classList.remove('bg-white', 'shadow-sm', 'text-blue-600');
            showLogin.classList.add('text-slate-500');
        });

        function togglePass(id, btn) {
            const input = document.getElementById(id);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Modal Logic
        const modal = document.getElementById('videoModal');
        const modalContent = document.getElementById('modalContent');
        const videoBtn = document.getElementById('videoBtn');
        const closeBtn = document.getElementById('closeModal');
        const youtubeVideo = document.getElementById('youtubeVideo');
        const videoSrc = "https://www.youtube.com/embed/q3vjHreO4ws?autoplay=1";

        videoBtn.addEventListener('click', () => {
            youtubeVideo.src = videoSrc;
            modal.classList.remove('hidden');
            setTimeout(() => modalContent.classList.replace('scale-95', 'scale-100'), 10);
        });

        const hideModal = () => {
            modalContent.classList.replace('scale-100', 'scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
                youtubeVideo.src = "";
            }, 300);
        };

        closeBtn.addEventListener('click', hideModal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) hideModal();
        });
    </script>
</body>
</html>