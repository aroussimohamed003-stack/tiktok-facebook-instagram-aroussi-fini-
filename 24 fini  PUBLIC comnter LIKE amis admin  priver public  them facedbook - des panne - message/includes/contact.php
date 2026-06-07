<?php
session_start();
include("config.php");

// معالجة إرسال النموذج
$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['contact_submit'])) {
    $name = mysqli_real_escape_string($con, $_POST['name']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $subject = mysqli_real_escape_string($con, $_POST['subject']);
    $message = mysqli_real_escape_string($con, $_POST['message']);
    
    // التحقق من البيانات
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error_message = "يرجى ملء جميع الحقول المطلوبة";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "يرجى إدخال بريد إلكتروني صحيح";
    } else {
        // إنشاء جدول الرسائل إذا لم يكن موجودًا
        mysqli_query($con, "
            CREATE TABLE IF NOT EXISTS contact_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                user_id INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");
        
        // إدراج الرسالة
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NULL';
        $query = "INSERT INTO contact_messages (name, email, subject, message, user_id) 
                  VALUES ('$name', '$email', '$subject', '$message', $user_id)";
        
        if (mysqli_query($con, $query)) {
            $success_message = "تم إرسال رسالتك بنجاح. سنتواصل معك قريبًا.";
            // إعادة تعيين النموذج
            $name = $email = $subject = $message = '';
        } else {
            $error_message = "حدث خطأ أثناء إرسال الرسالة. يرجى المحاولة مرة أخرى.";
        }
    }
}

// Set page title
$pageTitle = "اتصل بنا";

// Additional CSS
$additionalCss = [];

// Inline styles specific to this page
$inlineStyles = '
.contact-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 30px 20px;
}

.contact-info {
    margin-bottom: 40px;
}

.contact-card {
    background-color: var(--card-bg);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 5px 15px var(--shadow-color);
    transition: transform 0.3s ease;
}

.contact-card:hover {
    transform: translateY(-5px);
}

.contact-icon {
    font-size: 30px;
    color: var(--primary-color);
    margin-bottom: 15px;
}

.contact-form {
    background-color: var(--card-bg);
    border-radius: 10px;
    padding: 30px;
    box-shadow: 0 5px 15px var(--shadow-color);
}

.form-control {
    background-color: var(--bg-color);
    border-color: var(--border-color);
    color: var(--text-color);
}

.form-control:focus {
    background-color: var(--bg-color);
    color: var(--text-color);
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.25rem rgba(103, 61, 230, 0.25);
}

.form-label {
    color: var(--text-color);
}

.submit-btn {
    background-color: var(--primary-color);
    border: none;
    padding: 10px 30px;
    border-radius: 30px;
    transition: all 0.3s ease;
}

.submit-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px var(--shadow-color);
}

.alert {
    border-radius: 10px;
}
';

// Include header
include("includes/header.php");

// Include navbar
include("includes/navbar.php");
?>

<div class="contact-container">
    <h1 class="text-center mb-5">اتصل بنا</h1>
    
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-4">
            <div class="contact-info">
                <div class="contact-card text-center">
                    <div class="contact-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h4>العنوان</h4>
                    <p>123 شارع الرئيسي، المدينة</p>
                </div>
                
                <div class="contact-card text-center">
                    <div class="contact-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <h4>الهاتف</h4>
                    <p>+123 456 7890</p>
                </div>
                
                <div class="contact-card text-center">
                    <div class="contact-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h4>البريد الإلكتروني</h4>
                    <p>contact@mohamedaroussi.com</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="contact-form">
                <h3 class="mb-4">أرسل لنا رسالة</h3>
                <form method="POST" action="contact.php">
                    <div class="mb-3">
                        <label for="name" class="form-label">الاسم</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">البريد الإلكتروني</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="subject" class="form-label">الموضوع</label>
                        <input type="text" class="form-control" id="subject" name="subject" value="<?php echo isset($subject) ? htmlspecialchars($subject) : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">الرسالة</label>
                        <textarea class="form-control" id="message" name="message" rows="5" required><?php echo isset($message) ? htmlspecialchars($message) : ''; ?></textarea>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" name="contact_submit" class="btn btn-primary submit-btn">إرسال الرسالة</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="mt-5">
        <h3 class="mb-4">موقعنا</h3>
        <div class="ratio ratio-16x9">
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d387193.30591910525!2d-74.25986432970718!3d40.697149422113014!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x89c24fa5d33f083b%3A0xc80b8f06e177fe62!2sNew%20York%2C%20NY%2C%20USA!5e0!3m2!1sen!2s!4v1625124237100!5m2!1sen!2s" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
        </div>
    </div>
</div>

<?php
// Include footer
include("includes/footer.php");
?>
