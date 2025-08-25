<?php
// Start session and initialize database connection
session_start();
require_once 'admin/config/database.php';

// Initialize database
$db = Database::getInstance();

// Get current language (default to Arabic)
$currentLanguage = isset($_GET['lang']) ? $_GET['lang'] : (isset($_SESSION['language']) ? $_SESSION['language'] : 'ar');
$_SESSION['language'] = $currentLanguage;

// Function to get content by key
function getContent($key, $language = 'ar')
{
    global $db;
    try {
        $content = $db->fetchOne(
            "SELECT * FROM website_content WHERE content_key = :key AND is_active = 1",
            ['key' => $key]
        );

        if ($content) {
            if ($language === 'ar') {
                return $content['title_ar'] ?: $content['content_ar'] ?: $content['title_en'] ?: $content['content_en'];
            } else {
                return $content['title_en'] ?: $content['content_en'] ?: $content['title_ar'] ?: $content['content_ar'];
            }
        }

        // Fallback for missing content
        return $key;
    } catch (Exception $e) {
        return $key; // Return key as fallback
    }
}

// Function to get setting by key
function getSetting($key)
{
    global $db;
    try {
        $setting = $db->fetchOne(
            "SELECT setting_value FROM site_settings WHERE setting_key = :key",
            ['key' => $key]
        );
        return $setting ? $setting['setting_value'] : '';
    } catch (Exception $e) {
        return '';
    }
}

// Function to get logo image URL
function getLogoImage()
{
    global $db;
    try {
        $logo = $db->fetchOne(
            "SELECT file_path FROM file_uploads WHERE upload_category = 'logo' OR original_name LIKE '%logo%' ORDER BY created_at DESC LIMIT 1"
        );
        return $logo ? $logo['file_path'] : 'images/logo.png';
    } catch (Exception $e) {
        return 'images/logo.png';
    }
}

// Get website settings
$logoImage = getLogoImage();
$companyPhone = getSetting('company_phone') ?: '+20 122 033 3352';
$companyEmail = getSetting('company_email') ?: 'info@suncompany-egypt.org';
$companyAddress = getSetting($currentLanguage === 'ar' ? 'company_address_ar' : 'company_address_en') ?: ($currentLanguage === 'ar' ? 'القاهرة، مصر' : 'Cairo, Egypt');
$currentTheme = getSetting('default_theme') ?: 'golden';
$headerBackground = getSetting('header_background') ?: 'images/background2.png';

// Get maintenance settings
$maintenanceTitle = getSetting('maintenance_title') ?: ($currentLanguage === 'ar' ? 'الموقع تحت الصيانة' : 'Website Under Maintenance');
$maintenanceMessage = getSetting('maintenance_message') ?: ($currentLanguage === 'ar' ? 'نحن نعمل على تحسين موقعنا لتقديم خدمة أفضل لكم. سنعود قريباً!' : 'We are working on improving our website to provide you with better service. We will be back soon!');
$maintenanceEstimate = getSetting('maintenance_estimate') ?: ($currentLanguage === 'ar' ? 'العودة المتوقعة خلال 24 ساعة' : 'Expected return within 24 hours');
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLanguage; ?>" dir="<?php echo $currentLanguage === 'ar' ? 'rtl' : 'ltr'; ?>" data-theme="<?php echo htmlspecialchars($currentTheme); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $maintenanceTitle; ?> - Sun Trading Company</title>
    <meta name="description" content="<?php echo $maintenanceMessage; ?>">
    <meta name="robots" content="noindex, nofollow">

    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#E9A319">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Sun Trading">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo $logoImage; ?>">
    <link rel="apple-touch-icon" href="<?php echo $logoImage; ?>">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- External CSS for theme support -->
    <link rel="stylesheet" href="styles.css">

    <style>
        /* Maintenance-specific styles */
        .maintenance-bg {
            background: linear-gradient(135deg, rgba(var(--secondary-color-rgb), 0.9) 0%, rgba(var(--primary-color-rgb), 0.95) 100%),
                url('<?php echo htmlspecialchars($headerBackground); ?>');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }

        .maintenance-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Animated background elements */
        .maintenance-particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .maintenance-particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 20s infinite linear;
        }

        .maintenance-particle:nth-child(1) {
            width: 20px;
            height: 20px;
            left: 10%;
            animation-delay: 0s;
        }

        .maintenance-particle:nth-child(2) {
            width: 15px;
            height: 15px;
            left: 20%;
            animation-delay: -5s;
        }

        .maintenance-particle:nth-child(3) {
            width: 25px;
            height: 25px;
            left: 30%;
            animation-delay: -10s;
        }

        .maintenance-particle:nth-child(4) {
            width: 18px;
            height: 18px;
            left: 40%;
            animation-delay: -15s;
        }

        .maintenance-particle:nth-child(5) {
            width: 22px;
            height: 22px;
            left: 50%;
            animation-delay: -20s;
        }

        .maintenance-particle:nth-child(6) {
            width: 16px;
            height: 16px;
            left: 60%;
            animation-delay: -25s;
        }

        .maintenance-particle:nth-child(7) {
            width: 24px;
            height: 24px;
            left: 70%;
            animation-delay: -30s;
        }

        .maintenance-particle:nth-child(8) {
            width: 19px;
            height: 19px;
            left: 80%;
            animation-delay: -35s;
        }

        .maintenance-particle:nth-child(9) {
            width: 21px;
            height: 21px;
            left: 90%;
            animation-delay: -40s;
        }

        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }

            10% {
                opacity: 1;
            }

            90% {
                opacity: 1;
            }

            100% {
                transform: translateY(-100px) rotate(360deg);
                opacity: 0;
            }
        }

        /* Central maintenance content */
        .maintenance-content {
            position: relative;
            z-index: 10;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 3rem;
            max-width: 600px;
            margin: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
            animation: slideIn 1s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Maintenance icon animation */
        .maintenance-icon {
            font-size: 4rem;
            color: var(--secondary-color);
            margin-bottom: 1.5rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        /* Agricultural elements */
        .agricultural-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 2;
        }

        .grain-element {
            position: absolute;
            font-size: 2rem;
            opacity: 0.3;
            animation: rotate 30s infinite linear;
        }

        .grain-element:nth-child(1) {
            top: 10%;
            left: 15%;
            animation-delay: 0s;
        }

        .grain-element:nth-child(2) {
            top: 20%;
            right: 20%;
            animation-delay: -10s;
        }

        .grain-element:nth-child(3) {
            bottom: 25%;
            left: 10%;
            animation-delay: -20s;
        }

        .grain-element:nth-child(4) {
            bottom: 15%;
            right: 15%;
            animation-delay: -30s;
        }

        .grain-element:nth-child(5) {
            top: 50%;
            left: 5%;
            animation-delay: -40s;
        }

        .grain-element:nth-child(6) {
            top: 60%;
            right: 8%;
            animation-delay: -50s;
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        /* Progress indicator */
        .maintenance-progress {
            margin-top: 2rem;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            overflow: hidden;
            height: 8px;
        }

        .maintenance-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--secondary-color), var(--accent-color));
            border-radius: 10px;
            animation: progress 3s ease-in-out infinite;
        }

        @keyframes progress {

            0%,
            100% {
                width: 30%;
            }

            50% {
                width: 70%;
            }
        }

        /* Contact information */
        .maintenance-contact {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        .maintenance-contact-item {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.5rem 1rem;
            color: var(--text-dark);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .maintenance-contact-item:hover {
            color: var(--secondary-color);
        }

        /* Language toggle */
        .maintenance-lang-toggle {
            position: absolute;
            top: 2rem;
            right: 2rem;
            z-index: 20;
        }

        .lang-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .lang-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
            color: white;
            text-decoration: none;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .maintenance-content {
                margin: 1rem;
                padding: 2rem 1.5rem;
            }

            .maintenance-icon {
                font-size: 3rem;
            }

            .grain-element {
                font-size: 1.5rem;
            }

            .maintenance-bg {
                background-attachment: scroll;
            }

            .maintenance-lang-toggle {
                top: 1rem;
                right: 1rem;
            }
        }
    </style>
</head>

<body class="maintenance-bg">
    <!-- Language Toggle -->
    <div class="maintenance-lang-toggle">
        <a href="?lang=<?php echo $currentLanguage === 'ar' ? 'en' : 'ar'; ?>" class="lang-btn">
            <?php echo $currentLanguage === 'ar' ? 'English' : 'العربية'; ?>
        </a>
    </div>

    <!-- Animated Background Particles -->
    <div class="maintenance-particles">
        <div class="maintenance-particle"></div>
        <div class="maintenance-particle"></div>
        <div class="maintenance-particle"></div>
        <div class="maintenance-particle"></div>
        <div class="maintenance-particle"></div>
        <div class="maintenance-particle"></div>
        <div class="maintenance-particle"></div>
        <div class="maintenance-particle"></div>
        <div class="maintenance-particle"></div>
    </div>

    <!-- Agricultural Elements -->
    <div class="agricultural-elements">
        <div class="grain-element">🌾</div>
        <div class="grain-element">🌽</div>
        <div class="grain-element">🫘</div>
        <div class="grain-element">🫛</div>
        <div class="grain-element">🌾</div>
        <div class="grain-element">🌽</div>
    </div>

    <!-- Main Maintenance Container -->
    <div class="maintenance-container">
        <div class="maintenance-content">
            <!-- Logo -->
            <div class="mb-6">
                <img src="<?php echo $logoImage; ?>" alt="Sun Trading Company" class="mx-auto h-16 object-contain">
            </div>

            <!-- Maintenance Icon -->
            <div class="maintenance-icon">
                <i class="fas fa-tools"></i>
            </div>

            <!-- Main Title -->
            <h1 class="text-3xl font-bold mb-4" style="color: var(--primary-color);">
                <?php echo htmlspecialchars($maintenanceTitle); ?>
            </h1>

            <!-- Main Message -->
            <p class="text-lg mb-6" style="color: var(--text-dark);">
                <?php echo htmlspecialchars($maintenanceMessage); ?>
            </p>

            <!-- Time Estimate -->
            <div class="mb-6 p-4 rounded-lg" style="background-color: var(--accent-color); color: var(--primary-color);">
                <i class="fas fa-clock mr-2"></i>
                <strong><?php echo htmlspecialchars($maintenanceEstimate); ?></strong>
            </div>

            <!-- Progress Indicator -->
            <div class="maintenance-progress">
                <div class="maintenance-progress-bar"></div>
            </div>

            <!-- Additional Information -->
            <div class="mt-6 text-sm" style="color: var(--text-dark);">
                <p class="mb-2">
                    <?php echo $currentLanguage === 'ar' ? 'نعتذر عن أي إزعاج قد يسببه هذا التوقف المؤقت' : 'We apologize for any inconvenience this temporary downtime may cause'; ?>
                </p>
                <p>
                    <?php echo $currentLanguage === 'ar' ? 'نحن نعمل بجد لتحسين تجربتكم معنا' : 'We are working hard to improve your experience with us'; ?>
                </p>
            </div>

            <!-- Contact Information -->
            <div class="maintenance-contact">
                <h3 class="text-lg font-semibold mb-3" style="color: var(--primary-color);">
                    <?php echo $currentLanguage === 'ar' ? 'تواصل معنا' : 'Contact Us'; ?>
                </h3>

                <div class="flex flex-wrap justify-center items-center">
                    <a href="mailto:<?php echo $companyEmail; ?>" class="maintenance-contact-item">
                        <i class="fas fa-envelope"></i>
                        <span><?php echo $companyEmail; ?></span>
                    </a>

                    <a href="tel:<?php echo $companyPhone; ?>" class="maintenance-contact-item">
                        <i class="fas fa-phone"></i>
                        <span><?php echo $companyPhone; ?></span>
                    </a>

                    <div class="maintenance-contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo htmlspecialchars($companyAddress); ?></span>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-6 pt-4 border-t border-gray-200 text-sm text-gray-600">
                <p>© <?php echo date('Y'); ?> Sun Trading Company. <?php echo $currentLanguage === 'ar' ? 'جميع الحقوق محفوظة' : 'All rights reserved'; ?>.</p>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh page every 5 minutes to check if maintenance is over
        setTimeout(function() {
            window.location.reload();
        }, 300000); // 5 minutes

        // Add some interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            // Add click animation to maintenance icon
            const icon = document.querySelector('.maintenance-icon');
            icon.addEventListener('click', function() {
                this.style.animation = 'none';
                setTimeout(() => {
                    this.style.animation = 'pulse 1s ease-in-out';
                }, 10);
            });

            // Show estimated time dynamically
            const currentTime = new Date();
            const estimatedTime = new Date(currentTime.getTime() + (24 * 60 * 60 * 1000)); // Add 24 hours

            // You could add more interactive features here
            console.log('Maintenance mode active. Expected return:', estimatedTime.toLocaleString());
        });
    </script>
</body>

</html>