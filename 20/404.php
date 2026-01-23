<?php
// Set page title
$pageTitle = "404 - الصفحة غير موجودة";

// Additional CSS
$additionalCss = [];

// Inline styles specific to this page
$inlineStyles = '
.error-container {
    text-align: center;
    padding: 50px 20px;
    max-width: 600px;
    margin: 0 auto;
}

.error-code {
    font-size: 120px;
    font-weight: bold;
    color: var(--primary-color);
    margin-bottom: 20px;
    line-height: 1;
}

.error-message {
    font-size: 24px;
    margin-bottom: 30px;
}

.error-description {
    margin-bottom: 30px;
    color: var(--text-color);
    opacity: 0.8;
}

.error-image {
    max-width: 100%;
    height: auto;
    margin: 20px 0;
    border-radius: 10px;
    box-shadow: 0 5px 15px var(--shadow-color);
}

.home-button {
    display: inline-block;
    padding: 12px 30px;
    background-color: var(--primary-color);
    color: white;
    text-decoration: none;
    border-radius: 30px;
    font-weight: bold;
    transition: all 0.3s ease;
    margin-top: 20px;
}

.home-button:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px var(--shadow-color);
}
';

// Include header
include("includes/header.php");

// Include navbar
include("includes/navbar.php");
?>

<div class="error-container">
    <div class="error-code">404</div>
    <h1 class="error-message">الصفحة غير موجودة</h1>
    <p class="error-description">عذراً، الصفحة التي تبحث عنها غير موجودة أو تم نقلها أو حذفها.</p>
    
    <img src="https://media.giphy.com/media/14uQ3cOFteDaU/giphy.gif" alt="Error" class="error-image">
    
    <div>
        <a href="indexmo.php" class="home-button">العودة للصفحة الرئيسية</a>
    </div>
</div>

<?php
// Include footer
include("includes/footer.php");
?>
