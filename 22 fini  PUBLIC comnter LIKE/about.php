<?php
// Set page title
$pageTitle = "About";

// Additional CSS
$additionalCss = [];

// Inline styles specific to this page
$inlineStyles = '
.about-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 30px 20px;
}

.about-section {
    background-color: var(--card-bg);
    border-radius: 10px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px var(--shadow-color);
}

.about-image {
    width: 100%;
    height: auto;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 5px 15px var(--shadow-color);
}

.team-member {
    text-align: center;
    margin-bottom: 30px;
}

.team-member img {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    margin-bottom: 15px;
    box-shadow: 0 5px 15px var(--shadow-color);
    transition: transform 0.3s ease;
}

.team-member img:hover {
    transform: scale(1.05);
}

.feature-icon {
    font-size: 40px;
    color: var(--primary-color);
    margin-bottom: 15px;
}

.timeline {
    position: relative;
    padding: 20px 0;
}

.timeline::before {
    content: "";
    position: absolute;
    top: 0;
    bottom: 0;
    width: 4px;
    background-color: var(--primary-color);
    left: 50%;
    margin-left: -2px;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-content {
    background-color: var(--card-bg);
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 5px 15px var(--shadow-color);
    width: 45%;
    position: relative;
}

.timeline-item:nth-child(odd) .timeline-content {
    margin-left: auto;
}

.timeline-date {
    background-color: var(--primary-color);
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    display: inline-block;
    margin-bottom: 10px;
}

@media (max-width: 768px) {
    .timeline::before {
        left: 30px;
    }
    
    .timeline-content {
        width: calc(100% - 60px);
        margin-left: 60px !important;
    }
}
';

// Include header
include("includes/header.php");

// Include navbar
include("includes/navbar.php");
?>

<div class="page-container">
    <h1 class="page-title">About</h1>
    
    <div class="about-section card">
        <div class="row">
            <div class="col-md-6">
                <h2 class="section-title">Who We Are</h2>
                <p>Welcome to Mohamed Aroussi website, your favorite platform for sharing videos and stories with friends and family.</p>
                <p>The site was founded in 2023 with the goal of providing an easy-to-use and secure platform for sharing special moments with others.</p>
                <p>We believe that everyone has a story worth sharing, and we strive to provide the best possible experience for our users.</p>
            </div>
            <div class="col-md-6">
                <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f" alt="Team" class="about-image">
            </div>
        </div>
    </div>
    
    <div class="about-section card">
        <h2 class="section-title text-center">Site Features</h2>
        <div class="row text-center">
            <div class="col-md-4 mb-4">
                <div class="feature-icon">
                    <i class="fas fa-video"></i>
                </div>
                <h4>Video Sharing</h4>
                <p>Easily upload and share videos with the option to add a title and description.</p>
            </div>
            <div class="col-md-4 mb-4">
                <div class="feature-icon">
                    <i class="fas fa-photo-video"></i>
                </div>
                <h4>Stories</h4>
                <p>Share your daily moments through stories that disappear after 24 hours.</p>
            </div>
            <div class="col-md-4 mb-4">
                <div class="feature-icon">
                    <i class="fas fa-comments"></i>
                </div>
                <h4>Comments and Interaction</h4>
                <p>Interact with others' content through likes and comments.</p>
            </div>
        </div>
    </div>
    
    <div class="about-section card">
        <h2 class="section-title text-center">Team</h2>
        <div class="row">
            <div class="col-md-4">
                <div class="team-member">
                    <img src="https://scontent.ftun17-1.fna.fbcdn.net/v/t39.30808-6/605197016_3284447475055405_3561514691522201055_n.jpg?_nc_cat=110&ccb=1-7&_nc_sid=6ee11a&_nc_ohc=pQHuFdtxc4YQ7kNvwHD00el&_nc_oc=AdlRF_gpaPn4QZtcVHo-sHMahSCkU9g_WFmDKBBJ03zzYNgSJjn7Ag_mvmkyO07qg3Q&_nc_zt=23&_nc_ht=scontent.ftun17-1.fna&_nc_gid=TO1kBi2IqMa-J6ltjzpiRw&oh=00_Afo_WyVSPp57kUv9UkTTsHEVS-Cwp1dVIlUYYXIwjfW7kA&oe=697AD0FA" alt="Mohamed Aroussi">
                    <h4>Mohamed Aroussi</h4>
                    <p>Founder and CEO</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="team-member">
                    <img src="https://scontent.ftun17-1.fna.fbcdn.net/v/t39.30808-6/605197016_3284447475055405_3561514691522201055_n.jpg?_nc_cat=110&ccb=1-7&_nc_sid=6ee11a&_nc_ohc=pQHuFdtxc4YQ7kNvwHD00el&_nc_oc=AdlRF_gpaPn4QZtcVHo-sHMahSCkU9g_WFmDKBBJ03zzYNgSJjn7Ag_mvmkyO07qg3Q&_nc_zt=23&_nc_ht=scontent.ftun17-1.fna&_nc_gid=TO1kBi2IqMa-J6ltjzpiRw&oh=00_Afo_WyVSPp57kUv9UkTTsHEVS-Cwp1dVIlUYYXIwjfW7kA&oe=697AD0FA" alt="Sarah Ahmed">
                    <h4>Mohamed Aroussi</h4>
                    <p>Marketing Director</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="team-member">
                    <img src="https://scontent.ftun17-1.fna.fbcdn.net/v/t39.30808-6/605197016_3284447475055405_3561514691522201055_n.jpg?_nc_cat=110&ccb=1-7&_nc_sid=6ee11a&_nc_ohc=pQHuFdtxc4YQ7kNvwHD00el&_nc_oc=AdlRF_gpaPn4QZtcVHo-sHMahSCkU9g_WFmDKBBJ03zzYNgSJjn7Ag_mvmkyO07qg3Q&_nc_zt=23&_nc_ht=scontent.ftun17-1.fna&_nc_gid=TO1kBi2IqMa-J6ltjzpiRw&oh=00_Afo_WyVSPp57kUv9UkTTsHEVS-Cwp1dVIlUYYXIwjfW7kA&oe=697AD0FA" alt="Ahmed Mohamed">
                    <h4>Mohamed Aroussi</h4>
                    <p>Software Developer</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="about-section card">
        <h2 class="section-title text-center">Our History</h2>
        <div class="timeline">
            <div class="timeline-item">
                <div class="timeline-content">
                    <div class="timeline-date">2023</div>
                    <h4>Site Foundation</h4>
                    <p>We began our journey with a simple idea: creating a platform for sharing videos in an easy and enjoyable way.</p>
                </div>
            </div>
            <div class="timeline-item">
                <div class="timeline-content">
                    <div class="timeline-date">2024</div>
                    <h4>Stories Feature Launch</h4>
                    <p>We added the stories feature to enable users to share their daily moments.</p>
                </div>
            </div>
            <div class="timeline-item">
                <div class="timeline-content">
                    <div class="timeline-date">2025</div>
                    <h4>Comprehensive Site Update</h4>
                    <p>We completely updated the site with many new features and improved user experience.</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="about-section card">
        <h2 class="section-title text-center">Our Future Vision</h2>
        <p>We always strive to develop our platform and provide the best possible experience for our users. We are currently working on developing a mobile app and adding more interactive features.</p>
        <p>Our goal is to become the leading platform in the field of video and story sharing in the Arab world, while maintaining our core values of ease of use, security, and privacy.</p>
    </div>
</div>

<?php
// Include footer
include("includes/footer.php");
?>
