<?php
// Start session and initialize database connection
session_start();
require_once 'admin/config/database.php';

// Initialize database
$db = Database::getInstance();

// Check for maintenance mode first
$maintenanceMode = false;
try {
    $maintenanceSetting = $db->fetchOne(
        "SELECT setting_value FROM site_settings WHERE setting_key = 'maintenance_mode'"
    );

    if ($maintenanceSetting) {
        $value = trim(strtolower($maintenanceSetting['setting_value']));
        // Check for various "enabled" values: '1', 'true', 'on', 'yes', 'enabled'
        $maintenanceMode = in_array($value, ['1', 'true', 'on', 'yes', 'enabled']);
    }
} catch (Exception $e) {
    // If there's an error checking maintenance mode, continue normally
    $maintenanceMode = false;
}

// If maintenance mode is enabled and this is not an admin session, redirect to maintenance page
if ($maintenanceMode) {
    // Check if user is admin (handle both boolean and string values)
    $adminLoggedIn = $_SESSION['admin_logged_in'] ?? false;
    $isAdminSession = ($adminLoggedIn === true || $adminLoggedIn === '1' || $adminLoggedIn === 1);

    // Allow access if coming from admin panel or if admin is logged in
    $isAdminAccess = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false || $isAdminSession;

    // Also check if we're already on the maintenance page to prevent redirect loops
    $isMaintenancePage = strpos($_SERVER['REQUEST_URI'], 'maintenance.php') !== false;

    if (!$isAdminAccess && !$isMaintenancePage) {
        $redirectUrl = 'maintenance.php' . (isset($_GET['lang']) ? '?lang=' . $_GET['lang'] : '');
        header('Location: ' . $redirectUrl);
        exit();
    }
}

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

// Function to get all products with primary images
function getProducts()
{
    global $db;
    try {
        return $db->fetchAll(
            "SELECT p.*, 
                    (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image
                    FROM products p 
                    WHERE p.is_active = 1 
                    ORDER BY p.is_featured DESC, p.created_at DESC"
        );
    } catch (Exception $e) {
        return [];
    }
}

// Function to get images by category from file_uploads
function getImagesByCategory($category)
{
    global $db;
    try {
        return $db->fetchAll(
            "SELECT * FROM file_uploads WHERE upload_category = :category ORDER BY created_at DESC",
            ['category' => $category]
        );
    } catch (Exception $e) {
        return [];
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

// Get all content and settings
$products = getProducts();
$logoImage = getLogoImage();
$generalImages = getImagesByCategory('general');
$contentImages = getImagesByCategory('content');
$companyPhone = getSetting('company_phone') ?: '+20 122 033 3352';
$companyEmail = getSetting('company_email') ?: 'info@suncompany-egypt.org';
$companyAddress = getSetting($currentLanguage === 'ar' ? 'company_address_ar' : 'company_address_en') ?: ($currentLanguage === 'ar' ? 'القاهرة، مصر' : 'Cairo, Egypt');

// Get background images from settings
$headerBackground = getSetting('header_background') ?: 'images/background2.png';
$heroBackground = getSetting('hero_background') ?: 'images/hero-background.jpg';
$contactBackground = getSetting('contact_background') ?: 'images/background3.jpg';
$currentTheme = getSetting('default_theme') ?: 'golden';
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLanguage; ?>" dir="<?php echo $currentLanguage === 'ar' ? 'rtl' : 'ltr'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getContent('page_title', $currentLanguage); ?></title>
    <meta name="description" content="<?php echo getContent('page_description', $currentLanguage); ?>">

    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#E9A319">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Sun Trading">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Sun Trading">

    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">

    <!-- Favicon and PWA Icons -->
    <link rel="icon" type="image/png" href="<?php echo $logoImage; ?>">
    <link rel="apple-touch-icon" href="<?php echo $logoImage; ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $logoImage; ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $logoImage; ?>">
    <link rel="shortcut icon" href="<?php echo $logoImage; ?>">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome Icons CDN - Multiple sources for reliability -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Backup Font Awesome CDN -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v6.5.1/css/all.css" crossorigin="anonymous" />
    <!-- Alternative CDN -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" />

    <!-- Bootstrap CSS -->
    <link href="packages/bootstrap/bootstrap.min.css" rel="stylesheet">

    <!-- jQuery -->
    <script src="packages/jquery/jquery-3.7.1.min.js"></script>

    <!-- External CSS -->
    <link rel="stylesheet" href="styles.css">

    <!-- Dynamic Background Styles from Database -->
    <style>
        /* Override static backgrounds with database settings */
        .hero-bg {
            background: url('<?php echo htmlspecialchars($headerBackground); ?>') !important;
            background-size: cover !important;
            background-position: center !important;
            background-attachment: fixed !important;
        }

        .about-bg {
            background: url('<?php echo htmlspecialchars($heroBackground); ?>') !important;
            background-size: cover !important;
            background-position: center !important;
            background-attachment: fixed !important;
        }

        .contact-green-bg {
            background-image:
                linear-gradient(135deg, rgba(45, 80, 22, 0.85) 0%, rgba(74, 124, 89, 0.8) 25%, rgba(93, 138, 102, 0.75) 50%, rgba(107, 155, 122, 0.8) 75%, rgba(127, 176, 105, 0.85) 100%),
                url('<?php echo htmlspecialchars($contactBackground); ?>') !important;
            background-size: cover, cover !important;
            background-position: center, center !important;
            background-attachment: fixed, fixed !important;
        }

        /* Apply theme data attribute */
        html[data-theme="<?php echo htmlspecialchars($currentTheme); ?>"] {
            /* Theme variables will be applied via CSS */
        }

        /* Mobile fallback for fixed backgrounds */
        @media (max-width: 768px) {

            .hero-bg,
            .about-bg,
            .contact-green-bg {
                background-attachment: scroll !important;
            }
        }
    </style>

    <!-- Translations for JavaScript functions -->
    <script src="translations.js"></script>

    <!-- Navbar Background Slideshow -->
    <script src="navbar-slideshow.js"></script>

    <!-- Loader Auto-Hide Script -->
    <script>
        // Auto-hide loader after 3 seconds if it's still showing
        setTimeout(function() {
            const loader = document.getElementById('loader');
            if (loader) {
                loader.style.opacity = '0';
                loader.style.transition = 'opacity 0.5s ease-out';
                setTimeout(() => {
                    loader.style.display = 'none';
                }, 500);
            }
        }, 3000);

        // Apply theme from database
        document.addEventListener('DOMContentLoaded', function() {
            const currentTheme = '<?php echo htmlspecialchars($currentTheme); ?>';

            // Apply theme immediately
            document.documentElement.setAttribute('data-theme', currentTheme);

            // Update localStorage to match database
            localStorage.setItem('preferred-theme', currentTheme);

            // Update theme switcher to show current theme
            const themeOptions = document.querySelectorAll('.theme-option');
            themeOptions.forEach(option => {
                option.classList.remove('active');
                if (option.getAttribute('data-theme') === currentTheme) {
                    option.classList.add('active');
                }
            });

            // Update theme preview elements
            const currentThemePreview = document.getElementById('current-theme-preview');
            if (currentThemePreview) {
                currentThemePreview.setAttribute('data-theme', currentTheme);
            }

            const mobileCurrentThemePreview = document.getElementById('mobile-current-theme-preview');
            if (mobileCurrentThemePreview) {
                mobileCurrentThemePreview.setAttribute('data-theme', currentTheme);
            }

            // Ensure script.js theme system is synchronized
            if (typeof window.setTheme === 'function') {
                window.setTheme(currentTheme);
            }
        });
    </script>
</head>

<body class="bg-gray-50">
    <!-- Agricultural Trading Company Loader -->
    <div class="loader-container" id="loader">
        <div class="agricultural-loader">
            <!-- Animated Background Elements -->
            <div class="loader-particles"></div>
            <div class="loader-glow-orb loader-glow-1"></div>
            <div class="loader-glow-orb loader-glow-2"></div>
            <div class="loader-glow-orb loader-glow-3"></div>

            <!-- Main Logo Container -->
            <div class="loader-logo-container">
                <div class="loader-logo-circle">
                    <!-- Rotating Grain Elements -->
                    <div class="grain-orbit grain-orbit-1">
                        <div class="grain-item grain-wheat">🌾</div>
                        <div class="grain-item grain-corn">🌽</div>
                        <div class="grain-item grain-rice">🌾</div>
                    </div>
                    <div class="grain-orbit grain-orbit-2">
                        <div class="grain-item grain-beans">🫘</div>
                        <div class="grain-item grain-lentils">🫛</div>
                        <div class="grain-item grain-chickpeas">🫛</div>
                    </div>

                    <!-- Central Sun Logo -->
                    <div class="loader-sun-center">
                        <div class="sun-rays"></div>
                        <div class="sun-core-new">
                            <span class="sun-icon">☀️</span>
                        </div>
                    </div>
                </div>

                <!-- Company Name -->
                <div class="loader-company-name">
                    <div class="loader-name-arabic">شركة الشمس</div>
                    <div class="loader-name-english">SUN COMPANY</div>
                    <div class="loader-subtitle">Export & Import</div>
                    <div class="loader-tagline">Agricultural Trading Excellence</div>
                </div>

                <!-- Loading Progress -->
                <div class="loading-progress">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <div class="loading-text">Loading...</div>
                </div>
            </div>
        </div>

        <!-- Developer Credit -->
        <div class="developer-credit">
            <span class="developer-text">Design and Developed by </span>
            <a href="mailto:ahmedrmohamed2017@gmail.com" class="developer-link">Elnakieb</a>
            <div class="copyright-text">© 2024 All Rights Reserved</div>
        </div>
    </div>

    <!-- Navigation -->
    <nav id="navbar" class="fixed top-0 w-full z-50 transition-all duration-300 py-4" style="background: transparent;">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center">
                <!-- Logo -->
                <div class="flex items-center space-x-4 space-x-reverse">
                    <div class="flex items-center justify-center">
                        <img src="<?php echo $logoImage; ?>" alt="شركة الشمس" class="h-20 object-contain">
                    </div>
                    <!-- LOGO_TEXT_NAVBAR: Uncomment to show company name beside logo
                    <div>
                        <h1 class="text-xl font-bold text-white" id="logo-text"><?php echo getContent('logo_title', $currentLanguage); ?></h1>
                        <p class="text-sm" id="logo-subtitle" style="color: var(--accent-color);"><?php echo getContent('logo_subtitle', $currentLanguage); ?></p>
                    </div>
                    -->
                </div>

                <!-- Desktop Menu -->
                <div class="hidden md:flex space-x-8 space-x-reverse items-center">
                    <a href="#home" class="text-white transition-colors nav-link nav-item nav-delay-1"><?php echo getContent('nav_home', $currentLanguage); ?></a>
                    <a href="#about" class="text-white transition-colors nav-link nav-item nav-delay-2"><?php echo getContent('nav_about', $currentLanguage); ?></a>
                    <a href="#products" class="text-white transition-colors nav-link nav-item nav-delay-3"><?php echo getContent('nav_products', $currentLanguage); ?></a>
                    <a href="#administration" class="text-white transition-colors nav-link nav-item nav-delay-4"><?php echo getContent('nav_administration', $currentLanguage); ?></a>
                    <a href="#services" class="text-white transition-colors nav-link nav-item nav-delay-5"><?php echo getContent('nav_services', $currentLanguage); ?></a>
                    <a href="#contact" class="text-white transition-colors nav-link nav-item nav-delay-6"><?php echo getContent('nav_contact', $currentLanguage); ?></a>

                    <!-- Theme Switcher -->
                    <div class="theme-switcher">
                        <button class="theme-button" id="theme-toggle">
                            <div class="theme-color-preview" id="current-theme-preview"></div>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <div class="theme-dropdown" id="theme-dropdown">
                            <div class="theme-option active" data-theme="golden">
                                <div class="theme-color-preview"></div>
                                <span><?php echo getContent('theme_golden', $currentLanguage); ?></span>
                            </div>
                            <div class="theme-option" data-theme="ocean">
                                <div class="theme-color-preview"></div>
                                <span><?php echo getContent('theme_ocean', $currentLanguage); ?></span>
                            </div>
                            <div class="theme-option" data-theme="forest">
                                <div class="theme-color-preview"></div>
                                <span><?php echo getContent('theme_forest', $currentLanguage); ?></span>
                            </div>
                            <div class="theme-option" data-theme="purple">
                                <div class="theme-color-preview"></div>
                                <span><?php echo getContent('theme_purple', $currentLanguage); ?></span>
                            </div>
                            <div class="theme-option" data-theme="sunset">
                                <div class="theme-color-preview"></div>
                                <span><?php echo getContent('theme_sunset', $currentLanguage); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Language Toggle Button -->
                    <button id="lang-toggle" onclick="toggleLanguage()"
                        class="text-white bg-white/20 hover:bg-white/30 px-3 py-1 rounded-full text-sm transition-all duration-300 border border-white/30">
                        <?php echo $currentLanguage === 'ar' ? 'English' : 'العربية'; ?>
                    </button>
                </div>

                <!-- Mobile Menu Button & Language Toggle -->
                <div class="md:hidden flex items-center space-x-3 space-x-reverse">
                    <!-- Mobile Language Toggle -->
                    <button onclick="toggleLanguage()"
                        class="text-white bg-white/20 hover:bg-white/30 px-3 py-1 rounded-full text-sm transition-all duration-300 border border-white/30"
                        id="mobile-lang-toggle">
                        <?php echo $currentLanguage === 'ar' ? 'English' : 'العربية'; ?>
                    </button>
                    <!-- Mobile Menu Button -->
                    <button id="mobile-menu-btn" class="text-white"
                        type="button"
                        aria-expanded="false"
                        aria-controls="mobile-menu"
                        aria-label="Toggle navigation menu">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Mobile Menu -->
            <div id="mobile-menu"
                class="hidden md:hidden mt-4 rounded-lg shadow-lg p-4"
                style="background: var(--background-color); border: 1px solid var(--accent-color);"
                role="menu"
                aria-labelledby="mobile-menu-btn">
                <a href="#home" class="block py-2 transition-colors nav-link nav-item nav-delay-1"
                    style="color: var(--secondary-color);"><?php echo getContent('nav_home', $currentLanguage); ?></a>
                <a href="#about" class="block py-2 transition-colors nav-link nav-item nav-delay-2"
                    style="color: var(--secondary-color);"><?php echo getContent('nav_about', $currentLanguage); ?></a>
                <a href="#products" class="block py-2 transition-colors nav-link nav-item nav-delay-3"
                    style="color: var(--secondary-color);"><?php echo getContent('nav_products', $currentLanguage); ?></a>
                <a href="#administration" class="block py-2 transition-colors nav-link nav-item nav-delay-4"
                    style="color: var(--secondary-color);"><?php echo getContent('nav_administration', $currentLanguage); ?></a>
                <a href="#services" class="block py-2 transition-colors nav-link nav-item nav-delay-5"
                    style="color: var(--secondary-color);"><?php echo getContent('nav_services', $currentLanguage); ?></a>
                <a href="#contact" class="block py-2 transition-colors nav-link nav-item nav-delay-6"
                    style="color: var(--secondary-color);"><?php echo getContent('nav_contact', $currentLanguage); ?></a>

                <!-- Mobile Theme & Language Toggle -->
                <div class="mt-4 pt-4 border-t border-gray-300">
                    <!-- Mobile Theme Switcher -->
                    <div class="mobile-theme-switcher">
                        <button class="mobile-theme-button" id="mobile-theme-toggle">
                            <div class="theme-color-preview" id="mobile-current-theme-preview"></div>
                            <i class="fas fa-palette"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home"
        class="hero-bg min-h-screen flex items-center justify-center text-white relative overflow-hidden">
        <!-- Animated Background Elements -->
        <div class="hero-particles"></div>
        <div class="hero-glow-orb hero-glow-orb-1"></div>
        <div class="hero-glow-orb hero-glow-orb-2"></div>
        <div class="hero-glow-orb hero-glow-orb-3"></div>

        <div class="container mx-auto px-4 text-center relative z-10">
            <div class="max-w-4xl mx-auto">
                <!-- Main Title with Spectacular Animation -->
                <h1 class="text-5xl md:text-7xl font-bold mb-6 hero-title-animate">
                    <?php
                    $heroTitle = getContent('hero_title', $currentLanguage);
                    $words = explode(' ', $heroTitle);
                    foreach ($words as $index => $word) {
                        echo '<span class="hero-word hero-word-' . ($index + 1) . '">' . htmlspecialchars($word) . '</span>';
                        if ($index < count($words) - 1) echo ' ';
                    }
                    ?>
                </h1>

                <!-- Subtitle with Slide Animation -->
                <h2 class="text-2xl md:text-4xl font-medium mb-8 hero-subtitle-animate"
                    style="color: var(--accent-color);">
                    <?php echo getContent('company_subtitle', $currentLanguage); ?>
                </h2>

                <!-- Description with Fade Up Animation -->
                <p class="text-xl md:text-2xl mb-12 leading-relaxed text-white hero-description-animate">
                    <?php echo getContent('hero_subtitle', $currentLanguage); ?>
                </p>

                <!-- Buttons with Slide Up Animation -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center hero-buttons-animate">
                    <a href="#services"
                        class="text-white px-8 py-4 rounded-full text-lg font-medium transition-all duration-300 transform hover:scale-105 hero-button-primary"
                        style="background: var(--secondary-color); box-shadow: 0 8px 25px var(--shadow-golden);">
                        <?php echo getContent('hero_discover', $currentLanguage); ?>
                    </a>
                    <button onclick="openContactModal()"
                        class="border-2 text-white px-8 py-4 rounded-full text-lg font-medium transition-all duration-300 transform hover:scale-105 cursor-pointer hero-button-secondary"
                        style="border-color: var(--accent-color);">
                        <?php echo getContent('hero_contact', $currentLanguage); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Scroll Down Arrow with Enhanced Animation -->
        <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 hero-scroll-animate">
            <a href="#features" class="text-2xl hero-scroll-arrow" style="color: var(--accent-color);">
                <i class="fas fa-chevron-down"></i>
            </a>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="min-h-screen flex items-center relative overflow-hidden about-bg">
        <!-- Background Overlay for Better Text Readability -->
        <div class="absolute inset-0 bg-gradient-to-r from-black/75 via-black/60 to-black/40"></div>
        <div class="absolute inset-0 bg-black/20"></div>

        <!-- Content -->
        <div class="container mx-auto px-4 relative z-10">
            <div class="grid lg:grid-cols-2 gap-16 items-center min-h-screen py-20">
                <!-- Text Content -->
                <div class="slide-in-left text-white">
                    <div class="mb-8">
                        <h2 class="text-6xl lg:text-7xl font-bold mb-6 leading-tight text-fade-up"
                            style="color: var(--accent-color);">
                            <?php echo getContent('about_title', $currentLanguage); ?>
                        </h2>
                        <div class="w-24 h-1 rounded-full mb-8 scale-in animate-delay-1"
                            style="background: var(--secondary-color);"></div>
                    </div>

                    <div class="space-y-6 text-lg leading-relaxed">
                        <p class="text-white/90">
                            <?php echo getContent('about_text1', $currentLanguage); ?>
                        </p>
                        <p class="text-white/90">
                            <?php echo getContent('about_text2', $currentLanguage); ?>
                        </p>
                    </div>
                </div>

                <!-- Image/Stats Side -->
                <div class="slide-in-right">
                    <div class="grid grid-cols-2 gap-6">
                        <div class="stat-card bg-white/10 backdrop-blur-md p-6 rounded-xl text-center">
                            <div class="text-4xl font-bold mb-2" style="color: var(--accent-color);">15+</div>
                            <div class="text-white/80"><?php echo getContent('stats_years_experience', $currentLanguage); ?></div>
                        </div>
                        <div class="stat-card bg-white/10 backdrop-blur-md p-6 rounded-xl text-center">
                            <div class="text-4xl font-bold mb-2" style="color: var(--accent-color);">50+</div>
                            <div class="text-white/80"><?php echo getContent('stats_products', $currentLanguage); ?></div>
                        </div>
                        <div class="stat-card bg-white/10 backdrop-blur-md p-6 rounded-xl text-center">
                            <div class="text-4xl font-bold mb-2" style="color: var(--accent-color);">100+</div>
                            <div class="text-white/80"><?php echo getContent('stats_happy_clients', $currentLanguage); ?></div>
                        </div>
                        <div class="stat-card bg-white/10 backdrop-blur-md p-6 rounded-xl text-center">
                            <div class="text-4xl font-bold mb-2" style="color: var(--accent-color);">25+</div>
                            <div class="text-white/80"><?php echo getContent('stats_countries', $currentLanguage); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20" style="background: var(--background-color);">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold mb-4 text-fade-up" style="color: var(--primary-color);">
                    <?php echo getContent('features_title', $currentLanguage); ?>
                </h2>
                <p class="text-xl max-w-3xl mx-auto text-fade-up animate-delay-1" style="color: var(--text-dark);">
                    <?php echo getContent('features_subtitle', $currentLanguage); ?>
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Feature 1 -->
                <div class="feature-card card-fade-in animate-delay-1 p-8 rounded-xl shadow-lg text-center">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6 icon-scale-in animate-delay-2"
                        style="background: var(--accent-color);">
                        <i class="fas fa-globe text-2xl" style="color: var(--primary-color);"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4 text-fade-up animate-delay-3" style="color: var(--primary-color);">
                        <?php echo getContent('feature1_title', $currentLanguage); ?>
                    </h3>
                    <p class="text-fade-up animate-delay-4" style="color: var(--text-dark);">
                        <?php echo getContent('feature1_desc', $currentLanguage); ?>
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="feature-card card-fade-in animate-delay-2 p-8 rounded-xl shadow-lg text-center">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6 icon-scale-in animate-delay-3"
                        style="background: var(--accent-color);">
                        <i class="fas fa-award text-2xl" style="color: var(--primary-color);"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4 text-fade-up animate-delay-4" style="color: var(--primary-color);">
                        <?php echo getContent('feature2_title', $currentLanguage); ?>
                    </h3>
                    <p class="text-fade-up animate-delay-5" style="color: var(--text-dark);">
                        <?php echo getContent('feature2_desc', $currentLanguage); ?>
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="feature-card card-fade-in animate-delay-3 p-8 rounded-xl shadow-lg text-center">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6 icon-scale-in animate-delay-4"
                        style="background: var(--accent-color);">
                        <i class="fas fa-headset text-2xl" style="color: var(--primary-color);"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4 text-fade-up animate-delay-5" style="color: var(--primary-color);">
                        <?php echo getContent('feature3_title', $currentLanguage); ?>
                    </h3>
                    <p class="text-fade-up animate-delay-6" style="color: var(--text-dark);">
                        <?php echo getContent('feature3_desc', $currentLanguage); ?>
                    </p>
                </div>

                <!-- Feature 4 -->
                <div class="feature-card card-fade-in animate-delay-4 p-8 rounded-xl shadow-lg text-center">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6 icon-scale-in animate-delay-5"
                        style="background: var(--accent-color);">
                        <i class="fas fa-clock text-2xl" style="color: var(--primary-color);"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4 text-fade-up animate-delay-6" style="color: var(--primary-color);">
                        <?php echo getContent('feature4_title', $currentLanguage); ?>
                    </h3>
                    <p class="text-fade-up animate-delay-6" style="color: var(--text-dark);">
                        <?php echo getContent('feature4_desc', $currentLanguage); ?>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Administration Section -->
    <section id="administration" class="py-20 bg-white">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold mb-4" style="color: var(--primary-color);">
                    <?php echo getContent('admin_title', $currentLanguage); ?>
                </h2>
                <p class="text-xl max-w-3xl mx-auto" style="color: var(--text-dark);">
                    <?php echo getContent('admin_subtitle', $currentLanguage); ?>
                </p>
            </div>

            <!-- 4 Column Cards Layout -->
            <div class="flex justify-center mb-16">
                <div class="admin-cards-container flex gap-4 container mx-auto px-4" style="height: 750px;">
                    <!-- فريق العمل Card -->
                    <div class="admin-card admin-card-team flex-1 overflow-hidden cursor-pointer transform transition-all duration-300 hover:scale-105 hover:shadow-2xl rounded-2xl"
                        data-section="team" style="transform: skewX(-8deg); min-width: 280px;">
                        <div class="admin-card-overlay"></div>
                        <div class="admin-card-content h-full flex flex-col items-center justify-center p-8 text-center relative z-10"
                            style="transform: skewX(8deg);">
                            <div class="w-20 h-20 rounded-full flex items-center justify-center mb-8"
                                style="background: var(--secondary-color); box-shadow: 0 4px 15px rgba(0,0,0,0.3);">
                                <i class="fas fa-users text-white text-3xl"></i>
                            </div>
                            <h3 class="text-3xl font-bold text-white mb-6 drop-shadow-lg"><?php echo getContent('admin_team_title', $currentLanguage); ?></h3>
                            <div class="w-16 h-1 bg-white rounded-full mb-4"></div>
                            <p class="text-white/80 text-sm leading-relaxed">
                                <?php echo getContent('admin_team_desc', $currentLanguage); ?>
                            </p>
                        </div>
                    </div>

                    <!-- إدارة الشركة Card -->
                    <div class="admin-card admin-card-management flex-1 overflow-hidden cursor-pointer transform transition-all duration-300 hover:scale-105 hover:shadow-2xl rounded-2xl"
                        data-section="management" style="transform: skewX(-8deg); min-width: 280px;">
                        <div class="admin-card-overlay"></div>
                        <div class="admin-card-content h-full flex flex-col items-center justify-center p-8 text-center relative z-10"
                            style="transform: skewX(8deg);">
                            <div class="w-20 h-20 rounded-full flex items-center justify-center mb-8"
                                style="background: var(--accent-color); box-shadow: 0 4px 15px rgba(0,0,0,0.3);">
                                <i class="fas fa-building text-3xl" style="color: var(--primary-color);"></i>
                            </div>
                            <h3 class="text-3xl font-bold text-white mb-6 drop-shadow-lg"><?php echo getContent('admin_management_title', $currentLanguage); ?></h3>
                            <div class="w-16 h-1 bg-white rounded-full mb-4"></div>
                            <p class="text-white/80 text-sm leading-relaxed">
                                <?php echo getContent('admin_management_desc', $currentLanguage); ?>
                            </p>
                        </div>
                    </div>

                    <!-- المدير العام Card -->
                    <div class="admin-card admin-card-ceo flex-1 overflow-hidden cursor-pointer transform transition-all duration-300 hover:scale-105 hover:shadow-2xl rounded-2xl"
                        data-section="ceo" style="transform: skewX(-8deg); min-width: 280px;">
                        <div class="admin-card-overlay"></div>
                        <div class="admin-card-content h-full flex flex-col items-center justify-center p-8 text-center relative z-10"
                            style="transform: skewX(8deg);">
                            <div class="w-20 h-20 rounded-full flex items-center justify-center mb-8"
                                style="background: linear-gradient(135deg, #FFD700, #FFA500); box-shadow: 0 4px 15px rgba(0,0,0,0.3);">
                                <i class="fas fa-user-tie text-white text-3xl"></i>
                            </div>
                            <h3 class="text-3xl font-bold text-white mb-6 drop-shadow-lg"><?php echo getContent('admin_ceo_title', $currentLanguage); ?></h3>
                            <div class="w-16 h-1 bg-white rounded-full mb-4"></div>
                            <p class="text-white/80 text-sm leading-relaxed">
                                <?php echo getContent('admin_ceo_desc', $currentLanguage); ?>
                            </p>
                        </div>
                    </div>

                    <!-- قيمنا Card -->
                    <div class="admin-card admin-card-values flex-1 overflow-hidden cursor-pointer transform transition-all duration-300 hover:scale-105 hover:shadow-2xl rounded-2xl"
                        data-section="values" style="transform: skewX(-8deg); min-width: 280px;">
                        <div class="admin-card-overlay"></div>
                        <div class="admin-card-content h-full flex flex-col items-center justify-center p-8 text-center relative z-10"
                            style="transform: skewX(8deg);">
                            <div class="w-20 h-20 rounded-full flex items-center justify-center mb-8"
                                style="background: linear-gradient(135deg, #FF6B6B, #FF8E8E); box-shadow: 0 4px 15px rgba(0,0,0,0.3);">
                                <i class="fas fa-heart text-white text-3xl"></i>
                            </div>
                            <h3 class="text-3xl font-bold text-white mb-6 drop-shadow-lg"><?php echo getContent('admin_values_title', $currentLanguage); ?></h3>
                            <div class="w-16 h-1 bg-white rounded-full mb-4"></div>
                            <p class="text-white/80 text-sm leading-relaxed">
                                <?php echo getContent('admin_values_desc', $currentLanguage); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Detail Modal/Overlay -->
    <div id="admin-detail-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-2xl max-w-5xl w-full h-auto max-h-[85vh] overflow-hidden">
                <!-- Close Button -->
                <div class="flex justify-end p-4 pb-2">
                    <button id="close-modal" class="text-gray-500 hover:text-gray-700 text-2xl transition-colors">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Modal Content -->
                <div id="modal-content" class="px-8 pb-8 pt-0 h-full">
                    <!-- Content will be dynamically loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Products Section -->
    <section id="products" class="py-20" style="background: var(--background-color);">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold mb-6 text-fade-up" style="color: var(--primary-color);">
                    <?php echo getContent('products_title', $currentLanguage); ?>
                </h2>
                <p class="text-xl max-w-3xl mx-auto text-fade-up animate-delay-1" style="color: var(--text-dark);">
                    <?php echo getContent('products_subtitle', $currentLanguage); ?>
                </p>
            </div>

            <!-- Category Filter Buttons -->
            <div class="flex flex-wrap justify-center gap-4 mb-12">
                <button class="category-btn px-6 py-3 rounded-full font-medium transition-all duration-300 active"
                    data-category="all" style="background: var(--secondary-color); color: white;">
                    <?php echo getContent('category_all', $currentLanguage); ?>
                </button>
                <button class="category-btn px-6 py-3 rounded-full font-medium transition-all duration-300"
                    data-category="grains" style="background: var(--accent-color); color: var(--primary-color);">
                    <?php echo getContent('category_grains', $currentLanguage); ?>
                </button>
                <button class="category-btn px-6 py-3 rounded-full font-medium transition-all duration-300"
                    data-category="legumes" style="background: var(--accent-color); color: var(--primary-color);">
                    <?php echo getContent('category_legumes', $currentLanguage); ?>
                </button>
            </div>

            <!-- Products Grid -->
            <div class="grid md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8" id="dynamic-products">
                <?php
                // Debug: Check if we have products
                if (empty($products)) {
                    echo "<!-- No products found in database -->";
                } else {
                    echo "<!-- Found " . count($products) . " products -->";
                }

                foreach ($products as $index => $product):
                    $name = $currentLanguage === 'ar' ? ($product['name_ar'] ?: $product['name_en']) : ($product['name_en'] ?: $product['name_ar']);
                    $description = $currentLanguage === 'ar' ? ($product['description_ar'] ?: $product['description_en']) : ($product['description_en'] ?: $product['description_ar']);
                    $features = $currentLanguage === 'ar' ? ($product['features_ar'] ?: $product['features_en']) : ($product['features_en'] ?: $product['features_ar']);
                    $origin = $currentLanguage === 'ar' ? ($product['origin_ar'] ?: $product['origin_en']) : ($product['origin_en'] ?: $product['origin_ar']);
                    $uses = $currentLanguage === 'ar' ? ($product['uses_ar'] ?: $product['uses_en']) : ($product['uses_en'] ?: $product['uses_ar']);
                    $color = $currentLanguage === 'ar' ? ($product['color_ar'] ?: $product['color_en']) : ($product['color_en'] ?: $product['color_ar']);

                    // Handle different image path types
                    $primaryImage = $product['primary_image'];
                    if ($primaryImage) {
                        // Check if it's a new upload or static image
                        if (strpos($primaryImage, 'uploads/products/') === 0) {
                            // New uploaded images - keep the path as is
                            $primaryImage = $primaryImage;
                        } else if (strpos($primaryImage, 'images/products/') === 0) {
                            // Static images - keep the path as is
                            $primaryImage = $primaryImage;
                        } else {
                            // Fallback - prepend images/products/ if no clear path
                            $primaryImage = 'images/products/' . basename($primaryImage);
                        }
                    } else {
                        $primaryImage = 'images/products/default.png';
                    }
                ?>
                    <!-- Debug: Product image path: <?php echo $primaryImage; ?> -->
                    <div class="product-card rounded-xl shadow-lg overflow-hidden cursor-pointer transform transition-all duration-300 hover:scale-105 hover:shadow-2xl animate-delay-<?php echo $index + 1; ?>"
                        data-category="<?php echo $product['category'] ?: 'general'; ?>"
                        data-product="<?php echo strtolower(str_replace(' ', '-', $product['name_en'] ?: 'product-' . $product['id'])); ?>"
                        data-product-id="<?php echo $product['id']; ?>">

                        <div class="relative overflow-hidden">
                            <img src="<?php echo $primaryImage; ?>"
                                alt="<?php echo htmlspecialchars($name); ?>"
                                class="w-full object-cover"
                                style="height: 280px; width: 100%;"
                                onclick="openFullscreenImage('<?php echo $primaryImage; ?>', '<?php echo htmlspecialchars($name); ?>')"
                                onerror="this.src='images/products/default.png'">

                            <div class="absolute top-4 right-4">
                                <span class="bg-white bg-opacity-90 px-3 py-1 rounded-full text-sm font-medium"
                                    style="color: var(--primary-color);">
                                    <?php echo $product['category'] ?: 'general'; ?>
                                </span>
                            </div>

                            <?php if ($product['is_featured']): ?>
                                <div class="absolute top-4 left-4">
                                    <span class="bg-yellow-500 text-white px-2 py-1 rounded-full text-xs font-bold">
                                        <?php echo getContent('product_featured', $currentLanguage); ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <!-- Hover Details Overlay -->
                            <div class="hover-overlay">
                                <div class="hover-content">
                                    <h3><?php echo htmlspecialchars($name); ?></h3>
                                    <p><?php echo htmlspecialchars($description ?: getContent('product_default_description', $currentLanguage)); ?></p>

                                    <div class="features-section">
                                        <div class="features-title"><?php echo getContent('product_features', $currentLanguage); ?>:</div>
                                        <div>
                                            <?php if ($features): ?>
                                                <?php
                                                $featuresList = explode(',', $features);
                                                $colors = ['red', 'orange', 'green', 'blue', 'yellow', 'purple', 'indigo'];
                                                foreach ($featuresList as $index => $feature):
                                                    $color = $colors[$index % count($colors)];
                                                ?>
                                                    <span class="feature-badge <?php echo $color; ?>"><?php echo trim(htmlspecialchars($feature)); ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="feature-badge green"><?php echo getContent('feature_high_purity', $currentLanguage); ?></span>
                                                <span class="feature-badge blue"><?php echo getContent('feature_international_standards', $currentLanguage); ?></span>
                                                <span class="feature-badge yellow"><?php echo getContent('feature_safe_storage', $currentLanguage); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if ($origin || $uses): ?>
                                        <div class="info-grid">
                                            <?php if ($origin): ?>
                                                <div class="info-item">
                                                    <div class="info-label"><?php echo getContent('product_origin', $currentLanguage); ?>:</div>
                                                    <div><?php echo htmlspecialchars($origin); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($uses): ?>
                                                <div class="info-item">
                                                    <div class="info-label"><?php echo getContent('product_uses', $currentLanguage); ?>:</div>
                                                    <div><?php echo htmlspecialchars($uses); ?></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="p-6">
                            <h3 class="text-xl font-bold mb-3" style="color: var(--primary-color);">
                                <?php echo htmlspecialchars($name); ?>
                            </h3>
                            <p class="mb-4 text-sm" style="color: var(--text-dark);">
                                <?php echo htmlspecialchars($description ?: getContent('product_default_description', $currentLanguage)); ?>
                            </p>
                            <div class="flex items-center justify-between">
                                <span class="font-semibold px-3 py-1 rounded-full text-sm"
                                    style="background: var(--accent-color); color: var(--primary-color);">
                                    <?php echo $product['category'] ?: 'general'; ?>
                                </span>
                                <i class="fas fa-seedling" style="color: var(--secondary-color);"></i>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20" style="background: var(--background-color);">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold mb-4 text-fade-up" style="color: var(--primary-color);">
                    <?php echo getContent('features_title', $currentLanguage); ?>
                </h2>
                <p class="text-xl max-w-3xl mx-auto text-fade-up animate-delay-1" style="color: var(--text-dark);">
                    <?php echo getContent('features_subtitle', $currentLanguage); ?>
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Feature 1 -->
                <div class="feature-card card-fade-in animate-delay-1 p-8 rounded-xl shadow-lg text-center">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6 icon-scale-in animate-delay-2"
                        style="background: var(--accent-color);">
                        <i class="fas fa-globe text-2xl" style="color: var(--primary-color);"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4 text-fade-up animate-delay-3" style="color: var(--primary-color);">
                        <?php echo getContent('feature1_title', $currentLanguage); ?>
                    </h3>
                    <p class="text-fade-up animate-delay-4" style="color: var(--text-dark);">
                        <?php echo getContent('feature1_desc', $currentLanguage); ?>
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="feature-card card-fade-in animate-delay-2 p-8 rounded-xl shadow-lg text-center">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6 icon-scale-in animate-delay-3"
                        style="background: var(--accent-color);">
                        <i class="fas fa-award text-2xl" style="color: var(--primary-color);"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4 text-fade-up animate-delay-4" style="color: var(--primary-color);">
                        <?php echo getContent('feature2_title', $currentLanguage); ?>
                    </h3>
                    <p class="text-fade-up animate-delay-5" style="color: var(--text-dark);">
                        <?php echo getContent('feature2_desc', $currentLanguage); ?>
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="feature-card card-fade-in animate-delay-3 p-8 rounded-xl shadow-lg text-center">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6 icon-scale-in animate-delay-4"
                        style="background: var(--accent-color);">
                        <i class="fas fa-headset text-2xl" style="color: var(--primary-color);"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4 text-fade-up animate-delay-5" style="color: var(--primary-color);">
                        <?php echo getContent('feature3_title', $currentLanguage); ?>
                    </h3>
                    <p class="text-fade-up animate-delay-6" style="color: var(--text-dark);">
                        <?php echo getContent('feature3_desc', $currentLanguage); ?>
                    </p>
                </div>

                <!-- Feature 4 -->
                <div class="feature-card card-fade-in animate-delay-4 p-8 rounded-xl shadow-lg text-center">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6 icon-scale-in animate-delay-5"
                        style="background: var(--accent-color);">
                        <i class="fas fa-clock text-2xl" style="color: var(--primary-color);"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-4 text-fade-up animate-delay-6" style="color: var(--primary-color);">
                        <?php echo getContent('feature4_title', $currentLanguage); ?>
                    </h3>
                    <p class="text-fade-up animate-delay-6" style="color: var(--text-dark);">
                        <?php echo getContent('feature4_desc', $currentLanguage); ?>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="py-20 relative overflow-hidden services-section">
        <!-- Animated Background Elements -->
        <div class="services-particles"></div>
        <div class="services-glow-orb services-glow-orb-1"></div>
        <div class="services-glow-orb services-glow-orb-2"></div>

        <!-- Background with gradient overlay -->
        <div class="absolute inset-0 bg-gradient-to-br from-gray-50 to-gray-100"></div>

        <div class="container mx-auto px-4 relative z-10">
            <!-- Section Header -->
            <div class="text-center mb-16">
                <h2 class="text-5xl font-bold mb-6 services-title-animate" style="color: var(--primary-color);">
                    <?php echo getContent('services_title', $currentLanguage); ?>
                </h2>
                <p class="text-xl max-w-3xl mx-auto leading-relaxed services-subtitle-animate" style="color: var(--text-dark);">
                    <?php echo getContent('services_subtitle', $currentLanguage); ?>
                </p>
                <div class="w-24 h-1 mx-auto mt-6 rounded-full services-line-animate" style="background: linear-gradient(to right, var(--secondary-color), var(--primary-color));"></div>
            </div>

            <!-- Services Grid -->
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 mb-16">
                <!-- Service 1: Export/Import Consulting -->
                <div class="service-card service-card-1 bg-white rounded-2xl shadow-xl p-8 transform hover:scale-105 transition-all duration-300 hover:shadow-2xl">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mb-6 mx-auto" style="background: linear-gradient(to right, var(--primary-color), var(--secondary-color));">
                        <i class="fas fa-handshake text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-center mb-4" style="color: var(--primary-color);">
                        <?php echo getContent('service1_title', $currentLanguage); ?>
                    </h3>
                    <p class="text-center leading-relaxed mb-6" style="color: var(--text-dark);">
                        <?php echo getContent('service1_desc', $currentLanguage); ?>
                    </p>
                    <div class="text-center">
                        <button onclick="openContactModal()" class="inline-flex items-center px-6 py-3 rounded-full text-white font-medium transition-all duration-300 transform hover:scale-105 cursor-pointer" style="background: var(--secondary-color);">
                            <span><?php echo getContent('learn_more', $currentLanguage); ?></span>
                            <i class="fas fa-arrow-left icon-dynamic ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Service 2: Price Quotations -->
                <div class="service-card service-card-2 bg-white rounded-2xl shadow-xl p-8 transform hover:scale-105 transition-all duration-300 hover:shadow-2xl">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mb-6 mx-auto" style="background: linear-gradient(to right, var(--secondary-color), var(--accent-color));">
                        <i class="fas fa-calculator text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-center mb-4" style="color: var(--primary-color);">
                        <?php echo getContent('service2_title', $currentLanguage); ?>
                    </h3>
                    <p class="text-center leading-relaxed mb-6" style="color: var(--text-dark);">
                        <?php echo getContent('service2_desc', $currentLanguage); ?>
                    </p>
                    <div class="text-center">
                        <button onclick="openContactModal()" class="inline-flex items-center px-6 py-3 rounded-full text-white font-medium transition-all duration-300 transform hover:scale-105 cursor-pointer" style="background: var(--secondary-color);">
                            <span><?php echo getContent('get_quote', $currentLanguage); ?></span>
                            <i class="fas fa-arrow-left icon-dynamic ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Service 3: Machinery Import -->
                <div class="service-card service-card-3 bg-white rounded-2xl shadow-xl p-8 transform hover:scale-105 transition-all duration-300 hover:shadow-2xl">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mb-6 mx-auto" style="background: linear-gradient(to right, var(--accent-color), var(--primary-color));">
                        <i class="fas fa-cogs text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-center mb-4" style="color: var(--primary-color);">
                        <?php echo getContent('service3_title', $currentLanguage); ?>
                    </h3>
                    <p class="text-center leading-relaxed mb-6" style="color: var(--text-dark);">
                        <?php echo getContent('service3_desc', $currentLanguage); ?>
                    </p>
                    <div class="text-center">
                        <button onclick="openContactModal()" class="inline-flex items-center px-6 py-3 rounded-full text-white font-medium transition-all duration-300 transform hover:scale-105 cursor-pointer" style="background: var(--secondary-color);">
                            <span><?php echo getContent('contact_now', $currentLanguage); ?></span>
                            <i class="fas fa-arrow-left icon-dynamic ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Service 4: International Marketing -->
                <div class="service-card service-card-4 bg-white rounded-2xl shadow-xl p-8 transform hover:scale-105 transition-all duration-300 hover:shadow-2xl">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mb-6 mx-auto" style="background: linear-gradient(to right, var(--primary-color), var(--accent-color));">
                        <i class="fas fa-globe text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-center mb-4" style="color: var(--primary-color);">
                        <?php echo getContent('service4_title', $currentLanguage); ?>
                    </h3>
                    <p class="text-center leading-relaxed mb-6" style="color: var(--text-dark);">
                        <?php echo getContent('service4_desc', $currentLanguage); ?>
                    </p>
                    <div class="text-center">
                        <button onclick="openContactModal()" class="inline-flex items-center px-6 py-3 rounded-full text-white font-medium transition-all duration-300 transform hover:scale-105 cursor-pointer" style="background: var(--secondary-color);">
                            <span><?php echo getContent('start_now', $currentLanguage); ?></span>
                            <i class="fas fa-arrow-left icon-dynamic ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Service 5: Shipping Consulting -->
                <div class="service-card service-card-5 bg-white rounded-2xl shadow-xl p-8 transform hover:scale-105 transition-all duration-300 hover:shadow-2xl">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mb-6 mx-auto" style="background: linear-gradient(to right, var(--secondary-color), var(--primary-color));">
                        <i class="fas fa-ship text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-center mb-4" style="color: var(--primary-color);">
                        <?php echo getContent('service5_title', $currentLanguage); ?>
                    </h3>
                    <p class="text-center leading-relaxed mb-6" style="color: var(--text-dark);">
                        <?php echo getContent('service5_desc', $currentLanguage); ?>
                    </p>
                    <div class="text-center">
                        <button onclick="openContactModal()" class="inline-flex items-center px-6 py-3 rounded-full text-white font-medium transition-all duration-300 transform hover:scale-105 cursor-pointer" style="background: var(--secondary-color);">
                            <?php echo getContent('consult_experts', $currentLanguage); ?>
                            <i class="fas fa-arrow-left icon-dynamic ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Service 6: Storage & Distribution -->
                <div class="service-card service-card-6 bg-white rounded-2xl shadow-xl p-8 transform hover:scale-105 transition-all duration-300 hover:shadow-2xl">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mb-6 mx-auto" style="background: linear-gradient(to right, var(--accent-color), var(--secondary-color));">
                        <i class="fas fa-warehouse text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-center mb-4" style="color: var(--primary-color);">
                        <?php echo getContent('service6_title', $currentLanguage); ?>
                    </h3>
                    <p class="text-center leading-relaxed mb-6" style="color: var(--text-dark);">
                        <?php echo getContent('service6_desc', $currentLanguage); ?>
                    </p>
                    <div class="text-center">
                        <button onclick="openContactModal()" class="inline-flex items-center px-6 py-3 rounded-full text-white font-medium transition-all duration-300 transform hover:scale-105 cursor-pointer" style="background: var(--secondary-color);">
                            <?php echo getContent('book_space', $currentLanguage); ?>
                            <i class="fas fa-arrow-left icon-dynamic ml-2"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Why Choose Us Section -->
            <div class="bg-white rounded-3xl shadow-2xl p-12 mb-16">
                <h3 class="text-4xl font-bold text-center mb-12" style="color: var(--primary-color);">
                    <?php echo getContent('why_choose_title', $currentLanguage); ?>
                </h3>
                <div class="grid md:grid-cols-3 gap-8">
                    <!-- Feature 1: Speed -->
                    <div class="text-center">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mb-6 mx-auto" style="background: linear-gradient(to right, var(--primary-color), var(--secondary-color));">
                            <i class="fas fa-bolt text-white text-3xl"></i>
                        </div>
                        <h4 class="text-2xl font-bold mb-4" style="color: var(--primary-color);">
                            <?php echo getContent('why_speed_title', $currentLanguage); ?>
                        </h4>
                        <p class="leading-relaxed" style="color: var(--text-dark);">
                            <?php echo getContent('why_speed_desc', $currentLanguage); ?>
                        </p>
                    </div>

                    <!-- Feature 2: Reliability -->
                    <div class="text-center">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mb-6 mx-auto" style="background: linear-gradient(to right, var(--secondary-color), var(--accent-color));">
                            <i class="fas fa-shield-alt text-white text-3xl"></i>
                        </div>
                        <h4 class="text-2xl font-bold mb-4" style="color: var(--primary-color);">
                            <?php echo getContent('why_credibility_title', $currentLanguage); ?>
                        </h4>
                        <p class="leading-relaxed" style="color: var(--text-dark);">
                            <?php echo getContent('why_credibility_desc', $currentLanguage); ?>
                        </p>
                    </div>

                    <!-- Feature 3: Solutions -->
                    <div class="text-center">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mb-6 mx-auto" style="background: linear-gradient(to right, var(--accent-color), var(--primary-color));">
                            <i class="fas fa-lightbulb text-white text-3xl"></i>
                        </div>
                        <h4 class="text-2xl font-bold mb-4" style="color: var(--primary-color);">
                            <?php echo getContent('why_solutions_title', $currentLanguage); ?>
                        </h4>
                        <p class="leading-relaxed" style="color: var(--text-dark);">
                            <?php echo getContent('why_solutions_desc', $currentLanguage); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Call to Action -->
            <div class="text-center">
                <h3 class="text-3xl font-bold mb-6" style="color: var(--primary-color);">
                    <?php echo getContent('cta_question', $currentLanguage); ?>
                </h3>
                <p class="text-xl mb-8" style="color: var(--text-dark);">
                    <?php echo getContent('cta_description', $currentLanguage); ?>
                </p>
                <button onclick="openContactModal()" class="inline-flex items-center px-12 py-4 rounded-full text-white text-xl font-bold transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl" style="background: linear-gradient(to right, var(--primary-color), var(--secondary-color));">
                    <span><?php echo getContent('cta_button', $currentLanguage); ?></span>
                    <i class="fas fa-phone icon-dynamic ml-3"></i>
                </button>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-20 relative overflow-hidden contact-section"
        style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));">
        <div class="container mx-auto px-6 relative z-10">
            <div class="text-center">
                <h2 class="text-4xl md:text-5xl font-bold mb-6 text-white contact-title-animate">
                    <?php echo getContent('contact_title', $currentLanguage); ?>
                </h2>
                <p class="text-xl mb-12 text-white/90 max-w-3xl mx-auto contact-subtitle-animate">
                    <?php echo getContent('contact_subtitle', $currentLanguage); ?>
                </p>

                <div class="grid md:grid-cols-3 gap-8 mb-12">
                    <!-- Phone -->
                    <div class="text-center contact-card contact-card-1">
                        <div class="w-16 h-16 rounded-full bg-white/20 flex items-center justify-center mx-auto mb-4 contact-icon-animate">
                            <i class="fas fa-phone text-white text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-2">
                            <?php echo getContent('contact_phone', $currentLanguage); ?>
                        </h3>
                        <p class="text-white/80" dir="ltr" style="direction: ltr;">
                            <?php echo $companyPhone; ?>
                        </p>
                    </div>

                    <!-- Email -->
                    <div class="text-center contact-card contact-card-2">
                        <div class="w-16 h-16 rounded-full bg-white/20 flex items-center justify-center mx-auto mb-4 contact-icon-animate">
                            <i class="fas fa-envelope text-white text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-2">
                            <?php echo getContent('contact_email', $currentLanguage); ?>
                        </h3>
                        <p class="text-white/80">
                            <?php echo $companyEmail; ?>
                        </p>
                    </div>

                    <!-- Location -->
                    <div class="text-center contact-card contact-card-3">
                        <div class="w-16 h-16 rounded-full bg-white/20 flex items-center justify-center mx-auto mb-4 contact-icon-animate">
                            <i class="fas fa-map-marker-alt text-white text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-2">
                            <?php echo getContent('contact_location', $currentLanguage); ?>
                        </h3>
                        <p class="text-white/80">
                            <?php echo $companyAddress; ?>
                        </p>
                    </div>
                </div>

                <button onclick="openContactModal()"
                    class="contact-button-animate inline-flex items-center px-12 py-4 rounded-full text-xl font-bold transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl"
                    style="background: white; color: var(--primary-color);">
                    <span><?php echo getContent('contact_send', $currentLanguage); ?></span>
                    <i class="fas fa-paper-plane icon-dynamic ml-3"></i>
                </button>
            </div>
        </div>
    </section>

    <!-- Contact Modal -->
    <div id="contactModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4"
        onclick="closeContactModal(event)">
        <div class="bg-white rounded-2xl shadow-2xl max-w-6xl w-full max-h-[95vh] overflow-hidden"
            onclick="event.stopPropagation()">
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <h2 class="text-3xl font-bold" style="color: var(--primary-color);">
                    <?php echo getContent('contact_title', $currentLanguage); ?>
                </h2>
                <button onclick="closeContactModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <div class="p-6">
                <form id="contact-form" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <input type="text" name="name" placeholder="<?php echo getContent('form_name', $currentLanguage); ?>"
                            class="w-full px-3 py-2 border rounded-lg" required>
                        <input type="email" name="email" placeholder="<?php echo getContent('form_email', $currentLanguage); ?>"
                            class="w-full px-3 py-2 border rounded-lg" required>
                        <input type="tel" name="phone" placeholder="<?php echo getContent('form_phone', $currentLanguage); ?>"
                            class="w-full px-3 py-2 border rounded-lg">
                        <select name="subject" class="w-full px-3 py-2 border rounded-lg" required>
                            <option value=""><?php echo getContent('form_select_subject', $currentLanguage); ?></option>
                            <option value="import"><?php echo getContent('form_option_import', $currentLanguage); ?></option>
                            <option value="export"><?php echo getContent('form_option_export', $currentLanguage); ?></option>
                            <option value="consultation"><?php echo getContent('form_option_consultation', $currentLanguage); ?></option>
                        </select>
                    </div>
                    <textarea name="message" placeholder="<?php echo getContent('form_message_placeholder', $currentLanguage); ?>"
                        class="w-full px-3 py-2 border rounded-lg" rows="4" required></textarea>
                    <button type="submit" class="w-full py-3 bg-blue-600 text-white rounded-lg">
                        <?php echo getContent('contact_send', $currentLanguage); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="py-16 relative overflow-hidden"
        style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));">
        <div class="container mx-auto px-6">
            <div class="grid md:grid-cols-3 gap-8 mb-8">
                <div>
                    <h3 class="text-2xl font-bold text-white mb-4">
                        <?php echo getContent('footer_company_name', $currentLanguage); ?>
                    </h3>
                    <p class="text-white/80">
                        <?php echo getContent('hero_subtitle', $currentLanguage); ?>
                    </p>
                </div>
                <div>
                    <h4 class="text-xl font-bold text-white mb-4">
                        <?php echo getContent('footer_quick_links', $currentLanguage); ?>
                    </h4>
                    <div class="space-y-2">
                        <a href="#home" class="block text-white/80 hover:text-white transition-colors">
                            <?php echo getContent('nav_home', $currentLanguage); ?>
                        </a>
                        <a href="#about" class="block text-white/80 hover:text-white transition-colors">
                            <?php echo getContent('nav_about', $currentLanguage); ?>
                        </a>
                        <a href="#products" class="block text-white/80 hover:text-white transition-colors">
                            <?php echo getContent('nav_products', $currentLanguage); ?>
                        </a>
                    </div>
                </div>
                <div>
                    <h4 class="text-xl font-bold text-white mb-4">
                        <?php echo getContent('footer_contact_info', $currentLanguage); ?>
                    </h4>
                    <p class="text-white/80"><?php echo $companyEmail; ?></p>
                    <p class="text-white/80"><?php echo $companyPhone; ?></p>
                </div>
            </div>
            <div class="mt-8 pt-8 text-center" style="border-top: 1px solid var(--accent-color);">
                <p style="color: rgba(255, 255, 255, 0.8);">
                    <?php echo getContent('footer_copyright', $currentLanguage); ?>
                </p>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="packages/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>

    <script>
        function openFullscreenImage(imageSrc, imageAlt) {
            console.log('Opening fullscreen image:', imageAlt);
        }

        function showProductDetails(productId) {
            alert('Product ID: ' + productId + '\nThis will show detailed product information.');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const categoryBtns = document.querySelectorAll('.category-btn');
            const productCards = document.querySelectorAll('.product-card');

            categoryBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const category = this.getAttribute('data-category');

                    categoryBtns.forEach(b => {
                        b.classList.remove('active');
                        b.style.background = 'var(--accent-color)';
                        b.style.color = 'var(--primary-color)';
                    });
                    this.classList.add('active');
                    this.style.background = 'var(--secondary-color)';
                    this.style.color = 'white';

                    productCards.forEach(card => {
                        if (category === 'all' || card.getAttribute('data-category') === category) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });

            // Mobile menu functionality with comprehensive error handling
            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            const mobileMenu = document.getElementById('mobile-menu');

            if (mobileMenuBtn && mobileMenu) {
                // Ensure menu starts in hidden state
                mobileMenu.classList.add('hidden');

                // Main toggle functionality
                mobileMenuBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    try {
                        // Toggle menu visibility with animation
                        const isCurrentlyHidden = mobileMenu.classList.contains('hidden');

                        if (isCurrentlyHidden) {
                            // Show menu
                            mobileMenu.classList.remove('hidden');
                            mobileMenuBtn.setAttribute('aria-expanded', 'true');

                            // Update button icon to close icon
                            const icon = mobileMenuBtn.querySelector('i');
                            if (icon) {
                                icon.className = 'fas fa-times text-xl';
                            }
                        } else {
                            // Hide menu
                            mobileMenu.classList.add('hidden');
                            mobileMenuBtn.setAttribute('aria-expanded', 'false');

                            // Update button icon to menu icon
                            const icon = mobileMenuBtn.querySelector('i');
                            if (icon) {
                                icon.className = 'fas fa-bars text-xl';
                            }
                        }
                    } catch (error) {
                        console.error('Mobile menu toggle error:', error);
                        // Fallback: ensure menu is hidden on error
                        mobileMenu.classList.add('hidden');
                        mobileMenuBtn.setAttribute('aria-expanded', 'false');
                    }
                });

                // Close menu when clicking menu links (for smooth navigation)
                const mobileMenuLinks = mobileMenu.querySelectorAll('a[href^="#"]');
                mobileMenuLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        try {
                            mobileMenu.classList.add('hidden');
                            mobileMenuBtn.setAttribute('aria-expanded', 'false');

                            const icon = mobileMenuBtn.querySelector('i');
                            if (icon) {
                                icon.className = 'fas fa-bars text-xl';
                            }
                        } catch (error) {
                            console.error('Mobile menu link click error:', error);
                        }
                    });
                });

                // Close menu when clicking outside
                document.addEventListener('click', function(e) {
                    try {
                        if (!mobileMenuBtn.contains(e.target) && !mobileMenu.contains(e.target)) {
                            if (!mobileMenu.classList.contains('hidden')) {
                                mobileMenu.classList.add('hidden');
                                mobileMenuBtn.setAttribute('aria-expanded', 'false');

                                const icon = mobileMenuBtn.querySelector('i');
                                if (icon) {
                                    icon.className = 'fas fa-bars text-xl';
                                }
                            }
                        }
                    } catch (error) {
                        console.error('Mobile menu outside click error:', error);
                    }
                });

                // Keyboard support for accessibility
                mobileMenuBtn.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        mobileMenuBtn.click();
                    }
                    if (e.key === 'Escape') {
                        mobileMenu.classList.add('hidden');
                        mobileMenuBtn.setAttribute('aria-expanded', 'false');
                        const icon = mobileMenuBtn.querySelector('i');
                        if (icon) {
                            icon.className = 'fas fa-bars text-xl';
                        }
                    }
                });

                // Handle window resize to hide menu on desktop
                window.addEventListener('resize', function() {
                    if (window.innerWidth >= 768) { // md breakpoint
                        mobileMenu.classList.add('hidden');
                        mobileMenuBtn.setAttribute('aria-expanded', 'false');
                        const icon = mobileMenuBtn.querySelector('i');
                        if (icon) {
                            icon.className = 'fas fa-bars text-xl';
                        }
                    }
                });

            } else {
                console.warn('Mobile menu elements not found:', {
                    button: !!mobileMenuBtn,
                    menu: !!mobileMenu
                });
            }
        });
    </script>

</body>

</html>