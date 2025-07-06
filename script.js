// Sun Trading Company - Main JavaScript

// Language System
// Note: translations object is loaded from translations.js file
let currentLanguage = 'ar'; // Default to Arabic

// Check if translations are loaded from translations.js
function checkTranslations() {
    if (typeof translations !== 'undefined') {
        console.log('Translations loaded successfully from translations.js');
        console.log('Available languages:', Object.keys(translations));
        return true;
    } else {
        console.error('Translations not found! Make sure translations.js is loaded.');
        return false;
    }
}

// Helper function to get translation
function getTranslation(key) {
    // Debug logging
    console.log(`Getting translation for key: ${key}, language: ${currentLanguage}`);
    
    if (typeof translations === 'undefined') {
        console.error('Translations object is undefined!');
        return key;
    }
    
    if (!translations[currentLanguage]) {
        console.error(`Language ${currentLanguage} not found in translations!`);
        return key;
    }
    
    if (!translations[currentLanguage][key]) {
        console.warn(`Translation not found for key: ${key} in language: ${currentLanguage}`);
        console.log('Available keys:', Object.keys(translations[currentLanguage]));
        return key;
    }
    
    const translation = translations[currentLanguage][key];
    console.log(`Translation found: ${translation}`);
    return translation;
}

// Language Functions
function initializeLanguage() {
    // Check if translations are loaded from translations.js
    if (!checkTranslations()) {
        console.error('Cannot initialize language system - translations not loaded');
        return;
    }

    // Check current page language from document or localStorage
    const savedLang = localStorage.getItem('preferred-language');
    const docLang = document.documentElement.lang;
    const docDir = document.documentElement.dir;
    const browserLang = navigator.language || navigator.userLanguage;

    // Check current page content to detect active language
    const pageContent = document.body.textContent || '';
    const hasEnglishNav = document.querySelector('[data-translate="nav_home"]')?.textContent === 'Home';
    const hasArabicNav = document.querySelector('[data-translate="nav_home"]')?.textContent === 'الرئيسية';
    const langToggleButton = document.querySelector('#lang-toggle');
    const isCurrentlyEnglish = langToggleButton?.textContent?.includes('العربية') || hasEnglishNav;
    
    console.log('Language detection:', {
        docLang, docDir, savedLang, browserLang,
        hasEnglishNav, hasArabicNav, isCurrentlyEnglish,
        langToggleText: langToggleButton?.textContent
    });
    
    // Priority: 1. Current page state 2. Document lang/dir 3. Saved preference 4. Browser language
    if (isCurrentlyEnglish) {
        currentLanguage = 'en';
    } else if (hasArabicNav) {
        currentLanguage = 'ar';
    } else if (docLang === 'en' || docDir === 'ltr') {
        currentLanguage = 'en';
    } else if (docLang === 'ar' || docDir === 'rtl') {
        currentLanguage = 'ar';
    } else if (savedLang) {
        currentLanguage = savedLang;
    } else if (browserLang.startsWith('ar')) {
        currentLanguage = 'ar';
    } else {
        currentLanguage = 'en';
    }

    console.log('Initialized language to:', currentLanguage);
    setLanguage(currentLanguage);
}

function toggleLanguage() {
    currentLanguage = currentLanguage === 'ar' ? 'en' : 'ar';
    setLanguage(currentLanguage);
    localStorage.setItem('preferred-language', currentLanguage);
    
    // If modal is open, update its content
    if ($('#admin-detail-modal').is(':visible')) {
        // Get the current section from the modal
        const openSection = $('#admin-detail-modal').data('current-section');
        if (openSection && typeof window.showAdminDetail === 'function') {
            console.log('Updating modal content for language change, section:', openSection);
            window.showAdminDetail(openSection);
        }
    }
}

// Force update translations
function forceUpdateTranslations() {
    console.log('Manually updating translations...');
    updateTranslations(currentLanguage);
}

function setLanguage(lang) {
    console.log('Setting language to:', lang);
    currentLanguage = lang;
    const isRTL = lang === 'ar';
    console.log('Current language updated to:', currentLanguage);

    // Set document direction and language
    document.documentElement.dir = isRTL ? 'rtl' : 'ltr';
    document.documentElement.lang = lang;

    // Update body class for styling
    document.body.classList.toggle('rtl', isRTL);
    document.body.classList.toggle('ltr', !isRTL);

    // Update all translatable elements
    updateTranslations(lang);
    updateIconDirection(isRTL);

    // Update language toggle buttons
    const langToggle = document.getElementById('lang-toggle');
    const mobileLangToggle = document.getElementById('mobile-lang-toggle');

    if (langToggle && translations[lang] && translations[lang].lang_toggle) {
        langToggle.textContent = translations[lang].lang_toggle;
    }
    if (mobileLangToggle && translations[lang] && translations[lang].lang_toggle) {
        mobileLangToggle.textContent = translations[lang].lang_toggle;
    }
}

// Function to update icon direction
function updateIconDirection(isRTL) {
    const iconElements = document.querySelectorAll('.icon-dynamic');

    iconElements.forEach(icon => {
        const hasPl2 = icon.classList.contains('pl-2');
        const hasMl2 = icon.classList.contains('ml-2');
        const hasPl3 = icon.classList.contains('pl-3');
        const hasMl3 = icon.classList.contains('ml-3');

        if (isRTL) {
            // For Arabic (RTL)
            if (hasPl2) icon.classList.remove('pl-2');
            if (hasPl3) icon.classList.remove('pl-3');
            if (!hasMl2 && (hasPl2 || hasMl2)) icon.classList.add('ml-2');
            if (!hasMl3 && (hasPl3 || hasMl3)) icon.classList.add('ml-3');

            if (icon.classList.contains('fa-arrow-right')) {
                icon.classList.remove('fa-arrow-right');
                icon.classList.add('fa-arrow-left');
            }
        } else {
            // For English (LTR)
            if (hasMl2) icon.classList.remove('ml-2');
            if (hasMl3) icon.classList.remove('ml-3');
            if (!hasPl2 && (hasMl2 || hasPl2)) icon.classList.add('pl-2');
            if (!hasPl3 && (hasMl3 || hasPl3)) icon.classList.add('pl-3');

            if (icon.classList.contains('fa-arrow-left')) {
                icon.classList.remove('fa-arrow-left');
                icon.classList.add('fa-arrow-right');
            }
        }
    });
}

function updateTranslations(lang) {
    const elements = document.querySelectorAll('[data-translate]');

    elements.forEach(element => {
        const key = element.getAttribute('data-translate');

        if (translations[lang] && translations[lang][key]) {
            if (element.tagName === 'INPUT' && element.type !== 'submit') {
                element.placeholder = translations[lang][key];
            } else if (element.tagName === 'TITLE') {
                element.textContent = translations[lang][key];
            } else if (element.tagName === 'META' && element.getAttribute('name') === 'description') {
                element.setAttribute('content', translations[lang][key]);
            } else {
                element.textContent = translations[lang][key];
            }
        }
    });

    // Handle placeholder translations
    const placeholderElements = document.querySelectorAll('[data-translate-placeholder]');
    placeholderElements.forEach(element => {
        const key = element.getAttribute('data-translate-placeholder');
        if (translations[lang] && translations[lang][key]) {
            element.placeholder = translations[lang][key];
        }
    });
}

// Navbar scroll effect
window.addEventListener('scroll', function() {
    const navbar = document.getElementById('navbar');
    const logoText = document.getElementById('logo-text');
    const logoSubtitle = document.getElementById('logo-subtitle');

    if (window.scrollY > 100) {
        navbar.classList.add('navbar-scroll');
        
        // Logo text stays white for better contrast on the gradient background (only if elements exist)
        if (logoText) {
            logoText.classList.add('text-white');
        }
        if (logoSubtitle) {
            logoSubtitle.style.color = 'var(--accent-color)';
        }

        // Nav links stay white for better contrast
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.classList.add('text-white');
            link.style.color = 'white';
        });
    } else {
        navbar.classList.remove('navbar-scroll');
        
        // Keep logo text white for consistency (only if elements exist)
        if (logoText) {
            logoText.classList.add('text-white');
        }
        if (logoSubtitle) {
            logoSubtitle.style.color = 'var(--accent-color)';
        }

        // Keep nav links white
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.classList.add('text-white');
            link.style.color = 'white';
        });
    }
});

// Active navbar link functionality
function updateActiveNavLink() {
    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('.nav-link');
    
    let currentSection = '';
    const scrollPosition = window.scrollY + 150; // Offset for better detection
    
    sections.forEach(section => {
        const sectionTop = section.offsetTop;
        const sectionHeight = section.offsetHeight;
        
        if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
            currentSection = section.getAttribute('id');
        }
    });
    
    // Remove active class from all nav links
    navLinks.forEach(link => {
        link.classList.remove('active');
    });
    
    // Add active class to current section's nav link
    if (currentSection) {
        const activeLink = document.querySelector(`.nav-link[href="#${currentSection}"]`);
        if (activeLink) {
            activeLink.classList.add('active');
        }
    }
}

// Update active nav link on scroll
window.addEventListener('scroll', updateActiveNavLink);

// Update active nav link on page load
document.addEventListener('DOMContentLoaded', updateActiveNavLink);

// Mobile menu toggle
const mobileMenuBtn = document.getElementById('mobile-menu-btn');
const mobileMenu = document.getElementById('mobile-menu');

mobileMenuBtn.addEventListener('click', function() {
    mobileMenu.classList.toggle('hidden');
});

// Contact form submission
const contactForm = document.getElementById('contact-form');
if (contactForm) {
    contactForm.addEventListener('submit', function(e) {
    e.preventDefault();

    // Get form data
    const formData = new FormData(contactForm);
    const name = formData.get('name');
    const email = formData.get('email');
    const phone = formData.get('phone');
    const subject = formData.get('subject');
    const message = formData.get('message');

    // Simple validation
    if (!name || !email || !subject || !message) {
        alert('يرجى ملء جميع الحقول المطلوبة');
        return;
    }

    // Simulate form submission
    alert('تم إرسال رسالتك بنجاح! سنتواصل معك قريباً.');
    contactForm.reset();
    });
}

// Smooth scrolling for navigation links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Optimized Intersection Observer for animations
const observerOptions = {
    threshold: 0.15,
    rootMargin: '0px 0px -30px 0px'
};

// Lightweight animation observer
const animationObserver = new IntersectionObserver(function(entries) {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('animate-in');
            // Unobserve immediately to improve performance
            animationObserver.unobserve(entry.target);
        }
    });
}, observerOptions);

// Initialize animations when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Trigger navbar animations with proper sequencing
    setTimeout(() => {
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach((item, index) => {
            // Add animate-in class with individual delays
            setTimeout(() => {
                item.classList.add('animate-in');
            }, index * 200); // 200ms between each item
        });
    }, 300); // Start after 300ms

    // Only observe elements that have animation classes
    const animatedElements = document.querySelectorAll('.section-fade-in, .card-fade-in, .text-fade-up, .icon-scale-in, .scale-in');

    animatedElements.forEach(element => {
        animationObserver.observe(element);
    });
});

// jQuery animations for administration section
$(document).ready(function() {
    // Animate counter numbers
    function animateCounters() {
        $('.counter-animation').each(function() {
            const $this = $(this);
            const countTo = parseInt($this.text().replace('+', ''));
            
            $({ countNum: 0 }).animate({
                countNum: countTo
            }, {
                duration: 2000,
                easing: 'swing',
                step: function() {
                    $this.text(Math.floor(this.countNum) + '+');
                },
                complete: function() {
                    $this.text(countTo + '+');
                }
            });
        });
    }

    // Intersection Observer for jQuery animations
    const adminObserver = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const $element = $(entry.target);
                
                if ($element.hasClass('fade-in-up')) {
                    $element.addClass('visible');
                }
                if ($element.hasClass('slide-in-left')) {
                    $element.addClass('visible');
                }
                if ($element.hasClass('slide-in-right')) {
                    $element.addClass('visible');
                }
                
                // Animate counters when they come into view
                if ($element.find('.counter-animation').length > 0) {
                    setTimeout(animateCounters, 500);
                }
            }
        });
    }, {
        threshold: 0.2,
        rootMargin: '0px 0px -50px 0px'
    });

    // Observe administration section elements
    $('.fade-in-up, .slide-in-left, .slide-in-right').each(function() {
        adminObserver.observe(this);
    });

    // Expertise cards hover effects
    $('.expertise-card').hover(
        function() {
            $(this).find('i').addClass('animate-pulse');
        },
        function() {
            $(this).find('i').removeClass('animate-pulse');
        }
    );

    // Add click effects to expertise cards
    $('.expertise-card').click(function() {
        $(this).addClass('animate-pulse');
        setTimeout(() => {
            $(this).removeClass('animate-pulse');
        }, 600);
    });

    // Smooth reveal animation for admin section cards
    $('.admin-section-card').each(function(index) {
        $(this).css('animation-delay', (index * 0.2) + 's');
    });

    // Admin cards click functionality
    $('.admin-card').click(function() {
        const section = $(this).data('section');
        showAdminDetail(section);
    });

    // Modal close functionality
    $('#close-modal').click(function(e) {
        e.preventDefault();
        e.stopPropagation();
        $('#admin-detail-modal').fadeOut(300);
    });

    // Close modal when clicking on backdrop
    $('#admin-detail-modal').click(function(e) {
        if (e.target === this) {
            $('#admin-detail-modal').fadeOut(300);
        }
    });

    // Prevent modal close when clicking inside modal content
    $(document).on('click', '.bg-white', function(e) {
        e.stopPropagation();
    });

    // Close modal with Escape key
    $(document).keydown(function(e) {
        if (e.key === "Escape") {
            $('#admin-detail-modal').fadeOut(300);
        }
    });

    // Function to show admin detail modal
    window.showAdminDetail = function(section) {
        const modalContent = $('#modal-content');
        let content = '';

        switch(section) {
            case 'team':
                content = `
                    <div class="modal-content-section grid lg:grid-cols-2 gap-8 items-start h-full">
                        <div class="space-y-6">
                            <div class="text-center lg:text-right">
                                <h3 class="text-4xl font-bold mb-4" style="color: var(--primary-color);">${getTranslation('admin_team_title')}</h3>
                                <div class="w-24 h-1 mx-auto lg:mx-0 lg:mr-auto rounded-full" style="background: var(--secondary-color);"></div>
                            </div>
                            <p class="text-lg leading-relaxed" style="color: var(--text-dark);">
                                ${getTranslation('admin_team_full_desc')}
                            </p>
                            <div class="space-y-3">
                                <div class="flex items-center space-x-3 space-x-reverse">
                                    <i class="fas fa-users text-xl" style="color: var(--secondary-color);"></i>
                                    <span class="font-medium" style="color: var(--text-dark);">${getTranslation('admin_team_specialized')}</span>
                                </div>
                                <div class="flex items-center space-x-3 space-x-reverse">
                                    <i class="fas fa-star text-xl" style="color: var(--secondary-color);"></i>
                                    <span class="font-medium" style="color: var(--text-dark);">${getTranslation('admin_team_experience')}</span>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-6">
                            <div class="text-center lg:text-right">
                                <h4 class="text-2xl font-bold mb-4" style="color: var(--primary-color);">${getTranslation('admin_team_features_title')}</h4>
                            </div>
                            <div class="space-y-3">
                                <div class="flex items-center space-x-3 space-x-reverse">
                                    <i class="fas fa-check-circle text-xl" style="color: var(--secondary-color);"></i>
                                    <span class="font-medium" style="color: var(--text-dark);">${getTranslation('admin_team_commitment')}</span>
                                </div>
                                <div class="flex items-center space-x-3 space-x-reverse">
                                    <i class="fas fa-lightbulb text-xl" style="color: var(--secondary-color);"></i>
                                    <span class="font-medium" style="color: var(--text-dark);">${getTranslation('admin_team_innovative_solutions')}</span>
                                </div>
                                <div class="flex items-center space-x-3 space-x-reverse">
                                    <i class="fas fa-handshake text-xl" style="color: var(--secondary-color);"></i>
                                    <span class="font-medium" style="color: var(--text-dark);">${getTranslation('admin_team_cooperation')}</span>
                                </div>
                                <div class="flex items-center space-x-3 space-x-reverse">
                                    <i class="fas fa-clock text-xl" style="color: var(--secondary-color);"></i>
                                    <span class="font-medium" style="color: var(--text-dark);">${getTranslation('admin_team_time_commitment')}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                break;

            case 'management':
                content = `
                    <div class="modal-content-section max-w-4xl mx-auto text-center">
                        <div class="mb-8">
                            <h3 class="text-4xl font-bold mb-4" style="color: var(--primary-color);">${getTranslation('admin_management_title')}</h3>
                            <div class="w-24 h-1 mx-auto rounded-full" style="background: var(--secondary-color);"></div>
                        </div>
                        
                        <div class="mb-8">
                            <p class="text-lg leading-relaxed max-w-3xl mx-auto" style="color: var(--text-dark);">
                                ${getTranslation('admin_management_modal_description')}
                            </p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-2xl mx-auto">
                            <div class="text-center p-6 rounded-xl" style="background: linear-gradient(135deg, var(--accent-color), rgba(233, 163, 25, 0.1));">
                                <div class="text-4xl font-bold mb-2" style="color: var(--primary-color);">25+</div>
                                <div class="text-sm font-medium" style="color: var(--text-dark);">${getTranslation('admin_stats_years_experience')}</div>
                            </div>
                            <div class="text-center p-6 rounded-xl" style="background: linear-gradient(135deg, var(--accent-color), rgba(233, 163, 25, 0.1));">
                                <div class="text-4xl font-bold mb-2" style="color: var(--primary-color);">100+</div>
                                <div class="text-sm font-medium" style="color: var(--text-dark);">${getTranslation('admin_stats_successful_projects')}</div>
                            </div>
                            <div class="text-center p-6 rounded-xl" style="background: linear-gradient(135deg, var(--accent-color), rgba(233, 163, 25, 0.1));">
                                <div class="text-4xl font-bold mb-2" style="color: var(--primary-color);">15+</div>
                                <div class="text-sm font-medium" style="color: var(--text-dark);">${getTranslation('admin_stats_partner_countries')}</div>
                            </div>
                        </div>
                    </div>
                `;
                break;

            case 'ceo':
                content = `
                    <div class="modal-content-section max-w-5xl mx-auto text-center">
                        <div class="mb-8">
                            <h3 class="text-4xl font-bold mb-4" style="color: var(--primary-color);">${getTranslation('admin_ceo_modal_title')}</h3>
                            <div class="w-24 h-1 mx-auto rounded-full" style="background: var(--secondary-color);"></div>
                        </div>

                        <div class="mb-8 space-y-6">
                            <p class="text-lg leading-relaxed max-w-4xl mx-auto" style="color: var(--text-dark);">
                                ${getTranslation('admin_ceo_modal_desc1')}
                            </p>
                            <p class="text-lg leading-relaxed max-w-4xl mx-auto" style="color: var(--text-dark);">
                                ${getTranslation('admin_ceo_modal_desc2')}
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div class="text-center p-6 rounded-2xl" style="background: linear-gradient(135deg, var(--accent-color), rgba(233, 163, 25, 0.1)); border: 2px solid rgba(168, 101, 35, 0.1);">
                                <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center" style="background: var(--primary-color);">
                                    <i class="fas fa-globe text-2xl" style="color: var(--accent-color);"></i>
                                </div>
                                <h4 class="text-lg font-bold mb-3" style="color: var(--primary-color);">${getTranslation('admin_ceo_international_trade_title')}</h4>
                                <p class="text-sm leading-relaxed" style="color: var(--text-dark);">${getTranslation('admin_ceo_international_trade_modal_desc')}</p>
                            </div>
                            
                            <div class="text-center p-6 rounded-2xl" style="background: linear-gradient(135deg, var(--accent-color), rgba(233, 163, 25, 0.1)); border: 2px solid rgba(168, 101, 35, 0.1);">
                                <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center" style="background: var(--primary-color);">
                                    <i class="fas fa-seedling text-2xl" style="color: var(--accent-color);"></i>
                                </div>
                                <h4 class="text-lg font-bold mb-3" style="color: var(--primary-color);">${getTranslation('admin_ceo_agricultural_sector_title')}</h4>
                                <p class="text-sm leading-relaxed" style="color: var(--text-dark);">${getTranslation('admin_ceo_agricultural_sector_modal_desc')}</p>
                            </div>
                            
                            <div class="text-center p-6 rounded-2xl" style="background: linear-gradient(135deg, var(--accent-color), rgba(233, 163, 25, 0.1)); border: 2px solid rgba(168, 101, 35, 0.1);">
                                <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center" style="background: var(--primary-color);">
                                    <i class="fas fa-chart-line text-2xl" style="color: var(--accent-color);"></i>
                                </div>
                                <h4 class="text-lg font-bold mb-3" style="color: var(--primary-color);">${getTranslation('admin_ceo_strategic_growth_title')}</h4>
                                <p class="text-sm leading-relaxed" style="color: var(--text-dark);">${getTranslation('admin_ceo_strategic_growth_modal_desc')}</p>
                            </div>
                            
                            <div class="text-center p-6 rounded-2xl" style="background: linear-gradient(135deg, var(--accent-color), rgba(233, 163, 25, 0.1)); border: 2px solid rgba(168, 101, 35, 0.1);">
                                <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center" style="background: var(--primary-color);">
                                    <i class="fas fa-handshake text-2xl" style="color: var(--accent-color);"></i>
                                </div>
                                <h4 class="text-lg font-bold mb-3" style="color: var(--primary-color);">${getTranslation('admin_ceo_building_partnerships_title')}</h4>
                                <p class="text-sm leading-relaxed" style="color: var(--text-dark);">${getTranslation('admin_ceo_building_partnerships_modal_desc')}</p>
                            </div>
                        </div>
                    </div>
                `;
                break;

            case 'values':
                content = `
                    <div class="modal-content-section max-w-4xl mx-auto text-center">
                        <div class="mb-8">
                            <h3 class="text-4xl font-bold mb-4" style="color: var(--primary-color);">${getTranslation('admin_values_title')}</h3>
                            <div class="w-24 h-1 mx-auto rounded-full" style="background: var(--secondary-color);"></div>
                        </div>
                        
                        <div class="mb-10">
                            <p class="text-lg leading-relaxed max-w-3xl mx-auto" style="color: var(--text-dark);">
                                ${getTranslation('admin_values_modal_description')}
                            </p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl mx-auto">
                            <div class="text-center p-8 rounded-2xl" style="background: linear-gradient(135deg, var(--accent-color), rgba(233, 163, 25, 0.1)); border: 2px solid rgba(168, 101, 35, 0.1);">
                                <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center" style="background: var(--primary-color);">
                                    <i class="fas fa-award text-2xl" style="color: var(--accent-color);"></i>
                                </div>
                                <h4 class="text-xl font-bold mb-3" style="color: var(--primary-color);">${getTranslation('admin_values_quality_title')}</h4>
                                <p class="text-sm leading-relaxed" style="color: var(--text-dark);">${getTranslation('admin_values_quality_desc')}</p>
                            </div>
                            
                            <div class="text-center p-8 rounded-2xl" style="background: linear-gradient(135deg, var(--accent-color), rgba(233, 163, 25, 0.1)); border: 2px solid rgba(168, 101, 35, 0.1);">
                                <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center" style="background: var(--primary-color);">
                                    <i class="fas fa-handshake text-2xl" style="color: var(--accent-color);"></i>
                                </div>
                                <h4 class="text-xl font-bold mb-3" style="color: var(--primary-color);">${getTranslation('admin_values_transparency_title')}</h4>
                                <p class="text-sm leading-relaxed" style="color: var(--text-dark);">${getTranslation('admin_values_transparency_desc')}</p>
                            </div>
                            
                            <div class="text-center p-8 rounded-2xl" style="background: linear-gradient(135deg, var(--accent-color), rgba(233, 163, 25, 0.1)); border: 2px solid rgba(168, 101, 35, 0.1);">
                                <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center" style="background: var(--primary-color);">
                                    <i class="fas fa-lightbulb text-2xl" style="color: var(--accent-color);"></i>
                                </div>
                                <h4 class="text-xl font-bold mb-3" style="color: var(--primary-color);">${getTranslation('admin_values_innovation_title')}</h4>
                                <p class="text-sm leading-relaxed" style="color: var(--text-dark);">${getTranslation('admin_values_innovation_desc')}</p>
                            </div>
                        </div>
                    </div>
                `;
                break;
        }

        modalContent.html(content);
        // Store the current section for language toggle updates
        $('#admin-detail-modal').data('current-section', section);
        $('#admin-detail-modal').fadeIn(300);
    }
});

// Check if Font Awesome is loaded
function checkFontAwesome() {
    // Create a test element with Font Awesome icon
    const testElement = document.createElement('i');
    testElement.className = 'fas fa-home';
    testElement.style.position = 'absolute';
    testElement.style.left = '-9999px';
    document.body.appendChild(testElement);

    // Check if the icon font is loaded
    const computedStyle = window.getComputedStyle(testElement);
    const fontFamily = computedStyle.getPropertyValue('font-family');

    document.body.removeChild(testElement);

    // If Font Awesome is not loaded, add fallback
    if (!fontFamily.includes('Font Awesome')) {
        console.warn('Font Awesome not loaded, adding fallback');
        addIconFallbacks();
    }
}

// Add fallback text for icons if Font Awesome fails
function addIconFallbacks() {
    const iconMappings = {
        'fa-bars': '☰',
        'fa-home': '🏠',
        'fa-building': '🏢',
        'fa-box': '📦',
        'fa-cogs': '⚙️',
        'fa-users': '👥',
        'fa-phone': '📞',
        'fa-envelope': '✉️',
        'fa-map-marker-alt': '📍',
        'fa-paper-plane': '✈️',
        'fa-globe': '🌍',
        'fa-award': '🏆',
        'fa-handshake': '🤝',
        'fa-lightbulb': '💡',
        'fa-check-circle': '✅',
        'fa-calculator': '🧮',
        'fa-seedling': '🌱',
        'fa-chart-line': '📈',
        'fa-rocket': '🚀',
        'fa-medal': '🏅',
        'fa-trophy': '🏆'
    };

    // Replace icons with fallback text
    Object.keys(iconMappings).forEach(iconClass => {
        const elements = document.querySelectorAll(`.${iconClass}`);
        elements.forEach(element => {
            element.textContent = iconMappings[iconClass];
            element.style.fontFamily = 'Arial, sans-serif';
        });
    });
}

// Initialize language system when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize language system
    initializeLanguage();

    // Check Font Awesome after a short delay
    setTimeout(checkFontAwesome, 1000);

    // Update mobile language toggle
    const mobileLangToggle = document.getElementById('mobile-lang-toggle');
    if (mobileLangToggle && translations[currentLanguage] && translations[currentLanguage].lang_toggle) {
        mobileLangToggle.textContent = translations[currentLanguage].lang_toggle;
    }
});
