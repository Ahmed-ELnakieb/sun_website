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

// Language Functions
function initializeLanguage() {
    // Check if translations are loaded from translations.js
    if (!checkTranslations()) {
        console.error('Cannot initialize language system - translations not loaded');
        return;
    }

    // Check localStorage first, then browser language
    const savedLang = localStorage.getItem('preferred-language');
    const browserLang = navigator.language || navigator.userLanguage;

    if (savedLang) {
        currentLanguage = savedLang;
    } else if (browserLang.startsWith('ar')) {
        currentLanguage = 'ar';
    } else {
        currentLanguage = 'en';
    }

    setLanguage(currentLanguage);
}

function toggleLanguage() {
    currentLanguage = currentLanguage === 'ar' ? 'en' : 'ar';
    setLanguage(currentLanguage);
    localStorage.setItem('preferred-language', currentLanguage);
}

// Force update translations
function forceUpdateTranslations() {
    console.log('Manually updating translations...');
    updateTranslations(currentLanguage);
}

function setLanguage(lang) {
    currentLanguage = lang;
    const isRTL = lang === 'ar';

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
        // Logo text stays white for better contrast on the gradient background
        logoText.classList.add('text-white');
        logoSubtitle.style.color = 'var(--accent-color)';

        // Nav links stay white for better contrast
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.classList.add('text-white');
            link.style.color = 'white';
        });
    } else {
        navbar.classList.remove('navbar-scroll');
        // Keep logo text white for consistency
        logoText.classList.add('text-white');
        logoSubtitle.style.color = 'var(--accent-color)';

        // Keep nav links white
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.classList.add('text-white');
            link.style.color = 'white';
        });
    }
});

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
    function showAdminDetail(section) {
        const modalContent = $('#modal-content');
        let content = '';

        switch(section) {
            case 'team':
                content = `
                    <div class="modal-content-section grid lg:grid-cols-2 gap-12 items-center">
                        <div class="order-2 lg:order-1">
                            <img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80"
                                 alt="فريق العمل"
                                 class="detail-image rounded-xl shadow-2xl w-full">
                        </div>
                        <div class="order-1 lg:order-2">
                            <h3 class="text-3xl font-bold text-gray-800 mb-6">فريق العمل</h3>
                            <p class="text-lg text-gray-600 mb-6 leading-relaxed">
                                يضم فريق عمل شركة صن مجموعة من المتخصصين ذوي الخبرة العالية في مجال الاستيراد والتصدير. نحن نؤمن بأن القوة الحقيقية للشركة تكمن في فريق العمل المتفاني والمتخصص الذي يعمل بروح الفريق الواحد لتحقيق أهداف الشركة وتلبية احتياجات عملائنا.
                            </p>
                            <div class="space-y-4">
                                <div class="flex items-center space-x-3 space-x-reverse">
                                    <i class="fas fa-check-circle" style="color: var(--secondary-color);"></i>
                                    <span style="color: var(--text-dark);">فريق متخصص ومتفاني يعمل بأعلى معايير الجودة</span>
                                </div>
                                <div class="flex items-center space-x-3 space-x-reverse">
                                    <i class="fas fa-check-circle" style="color: var(--secondary-color);"></i>
                                    <span style="color: var(--text-dark);">خبرة واسعة في مجال الاستيراد والتصدير والتجارة الدولية</span>
                                </div>
                                <div class="flex items-center space-x-3 space-x-reverse">
                                    <i class="fas fa-check-circle" style="color: var(--secondary-color);"></i>
                                    <span style="color: var(--text-dark);">التزام بالتميز ونسعى دائماً لتقديم أفضل الخدمات</span>
                                </div>
                                <div class="flex items-center space-x-3 space-x-reverse">
                                    <i class="fas fa-check-circle" style="color: var(--secondary-color);"></i>
                                    <span style="color: var(--text-dark);">حلول مبتكرة ومتخصصة لجميع احتياجات عملائنا</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                break;

            case 'management':
                content = `
                    <div class="modal-content-section grid lg:grid-cols-2 gap-12 items-center">
                        <div class="order-2 lg:order-1">
                            <img src="https://images.unsplash.com/photo-1560472354-b33ff0c44a43?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80"
                                 alt="إدارة الشركة"
                                 class="detail-image rounded-xl shadow-2xl w-full">
                        </div>
                        <div class="order-1 lg:order-2">
                            <h3 class="text-3xl font-bold text-gray-800 mb-6">إدارة الشركة</h3>
                            <p class="text-lg text-gray-600 mb-6 leading-relaxed">
                                في شركة صن، يقودنا فريق إداري متميز ذو خبرة طويلة في صناعة الاستيراد والتصدير، حيث نؤمن أن القيادة الحكيمة والتخطيط الاستراتيجي هما أساس نجاحنا المستمر. يتمتع كل عضو في فريق الإدارة بمعرفة واسعة بمتطلبات السوق الزراعي، مما يعزز قدرتنا على تقديم حلول مبتكرة تلبي احتياجات عملائنا.
                            </p>
                            <div class="grid grid-cols-3 gap-6 mb-6">
                                <div class="text-center">
                                    <div class="text-3xl font-bold mb-2" style="color: var(--secondary-color);">25+</div>
                                    <div class="text-sm" style="color: var(--text-dark);">سنة خبرة</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-3xl font-bold mb-2" style="color: var(--secondary-color);">100+</div>
                                    <div class="text-sm" style="color: var(--text-dark);">مشروع ناجح</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-3xl font-bold mb-2" style="color: var(--secondary-color);">15+</div>
                                    <div class="text-sm" style="color: var(--text-dark);">دولة شريكة</div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                break;

            case 'ceo':
                content = `
                    <div class="modal-content-section max-w-4xl mx-auto">
                        <div class="text-center mb-8">
                            <h3 class="text-4xl font-bold mb-4" style="color: var(--primary-color);">المدير العام</h3>
                            <div class="w-24 h-1 mx-auto rounded-full" style="background: var(--secondary-color);"></div>
                        </div>

                        <div class="bg-gradient-to-r from-purple-50 to-indigo-50 rounded-2xl p-8 mb-8">
                            <p class="text-xl text-gray-700 leading-relaxed text-center mb-6">
                                بخبرة تمتد لأكثر من <span class="font-bold text-purple-600">12 سنة</span> في مجال التجارة الدولية والقطاع الزراعي، يقود <span class="font-bold text-purple-600">[اسم المدير العام]</span> شركة <span class="font-bold text-purple-600">[اسم الشركة]</span> برؤية استراتيجية طموحة تهدف إلى تحقيق النمو المستدام والريادة في الأسواق العالمية.
                            </p>
                            <p class="text-lg text-gray-600 leading-relaxed text-center">
                                من خلال قيادته الحكيمة ومهاراته الاستراتيجية المتقدمة، نجح في بناء شبكة واسعة من الشراكات الدولية وتعزيز مكانة الشركة كرائدة في مجال استيراد وتصدير المنتجات الزراعية عالية الجودة.
                            </p>
                        </div>

                        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                            <div class="bg-white rounded-xl p-6 text-center shadow-lg hover:shadow-xl transition-shadow duration-300">
                                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background: var(--accent-color);">
                                    <i class="fas fa-globe text-2xl" style="color: var(--primary-color);"></i>
                                </div>
                                <h4 class="font-bold mb-2" style="color: var(--primary-color);">التجارة الدولية</h4>
                                <p class="text-sm" style="color: var(--text-dark);">خبرة واسعة في الأسواق العالمية والتجارة الخارجية</p>
                            </div>
                            <div class="bg-white rounded-xl p-6 text-center shadow-lg hover:shadow-xl transition-shadow duration-300">
                                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-seedling text-green-600 text-2xl"></i>
                                </div>
                                <h4 class="font-bold text-gray-800 mb-2">القطاع الزراعي</h4>
                                <p class="text-sm text-gray-600">تخصص عميق في المنتجات الزراعية والحبوب</p>
                            </div>
                            <div class="bg-white rounded-xl p-6 text-center shadow-lg hover:shadow-xl transition-shadow duration-300">
                                <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-chart-line text-purple-600 text-2xl"></i>
                                </div>
                                <h4 class="font-bold text-gray-800 mb-2">النمو الاستراتيجي</h4>
                                <p class="text-sm text-gray-600">رؤية طموحة للتوسع والنمو المستدام</p>
                            </div>
                            <div class="bg-white rounded-xl p-6 text-center shadow-lg hover:shadow-xl transition-shadow duration-300">
                                <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-handshake text-orange-600 text-2xl"></i>
                                </div>
                                <h4 class="font-bold text-gray-800 mb-2">بناء الشراكات</h4>
                                <p class="text-sm text-gray-600">علاقات قوية مع العملاء والشركاء الدوليين</p>
                            </div>
                        </div>

                        <div class="text-center mt-8">
                            <div class="flex flex-wrap justify-center gap-3">
                                <span class="inline-flex items-center px-6 py-3 rounded-full text-sm font-medium bg-purple-100 text-purple-800">
                                    <i class="fas fa-medal text-xs ml-2"></i>
                                    12+ سنة خبرة
                                </span>
                                <span class="inline-flex items-center px-6 py-3 rounded-full text-sm font-medium" style="background: var(--accent-color); color: var(--primary-color);">
                                    <i class="fas fa-trophy text-xs ml-2"></i>
                                    قائد متميز
                                </span>
                                <span class="inline-flex items-center px-6 py-3 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                    <i class="fas fa-rocket text-xs ml-2"></i>
                                    رؤية استراتيجية
                                </span>
                                <span class="inline-flex items-center px-6 py-3 rounded-full text-sm font-medium bg-orange-100 text-orange-800">
                                    <i class="fas fa-globe text-xs ml-2"></i>
                                    خبرة دولية
                                </span>
                            </div>
                        </div>
                    </div>
                `;
                break;

            case 'values':
                content = `
                    <div class="modal-content-section grid lg:grid-cols-2 gap-12 items-center">
                        <div class="order-2 lg:order-1">
                            <img src="https://images.unsplash.com/photo-1559136555-9303baea8ebd?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80"
                                 alt="قيمنا"
                                 class="detail-image rounded-xl shadow-2xl w-full">
                        </div>
                        <div class="order-1 lg:order-2">
                            <h3 class="text-3xl font-bold text-gray-800 mb-6">قيمنا</h3>
                            <p class="text-lg text-gray-600 mb-8 leading-relaxed">
                                تقوم شركة صن على مجموعة من القيم الأساسية التي توجه عملنا وتحدد هويتنا في السوق. هذه القيم ليست مجرد كلمات، بل هي المبادئ التي نعيش بها ونطبقها في كل جانب من جوانب عملنا.
                            </p>
                            <div class="space-y-6">
                                <div class="flex items-start space-x-4 space-x-reverse">
                                    <div class="flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center" style="background: var(--accent-color);">
                                        <i class="fas fa-award" style="color: var(--primary-color);"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-xl font-bold mb-2" style="color: var(--primary-color);">الجودة</h4>
                                        <p style="color: var(--text-dark);">التزامنا بمعايير الجودة والتميز في كل خطوة</p>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-4 space-x-reverse">
                                    <div class="flex-shrink-0 w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-handshake text-green-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-xl font-bold text-gray-800 mb-2">الشفافية</h4>
                                        <p class="text-gray-600">نؤمن بالتعامل بشفافية مع عملائنا وشركائنا</p>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-4 space-x-reverse">
                                    <div class="flex-shrink-0 w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-lightbulb text-purple-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-xl font-bold text-gray-800 mb-2">الابتكار</h4>
                                        <p class="text-gray-600">نسعى دائماً لتقديم حلول جديدة ومبتكرة</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                break;
        }

        modalContent.html(content);
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
