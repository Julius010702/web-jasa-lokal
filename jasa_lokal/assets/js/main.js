class JasaLokal {
    constructor() {
        this.init();
        this.bindEvents();
        this.loadUserPreferences();
    }

    init() {
        // Initialize tooltips
        this.initTooltips();
        
        // Initialize lazy loading
        this.initLazyLoading();
        
        // Initialize search functionality
        this.initSearch();
        
        // Initialize notifications
        this.initNotifications();
        
        console.log('JasaLokal initialized');
    }

    bindEvents() {
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', this.handleSmoothScroll);
        });

        // Auto-hide navbar on scroll
        window.addEventListener('scroll', this.handleNavbarScroll);

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', this.handleFormValidation);
        });

        // Image error handling
        document.querySelectorAll('img').forEach(img => {
            img.addEventListener('error', this.handleImageError);
        });

        // WhatsApp click tracking
        document.querySelectorAll('a[href*="wa.me"]').forEach(link => {
            link.addEventListener('click', this.trackWhatsAppClick);
        });
    }

    initTooltips() {
        if (typeof bootstrap !== 'undefined') {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    }

    initLazyLoading() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    }

    initSearch() {
        const searchInput = document.querySelector('input[name="keyword"]');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.performSearch(e.target.value);
                }, 300);
            });
        }
    }

    initNotifications() {
        // Check for notifications from PHP session
        if (window.notifications && window.notifications.length > 0) {
            window.notifications.forEach(notification => {
                this.showNotification(notification.message, notification.type);
            });
        }

        // Request notification permission
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }

    handleSmoothScroll(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    }

    handleNavbarScroll() {
        let lastScrollTop = 0;
        const navbar = document.querySelector('.navbar');
        
        return function() {
            let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                // Scrolling down
                navbar.style.transform = 'translateY(-100%)';
            } else {
                // Scrolling up
                navbar.style.transform = 'translateY(0)';
            }
            
            lastScrollTop = scrollTop;
        };
    }

    handleFormValidation(e) {
        const form = e.target;
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
            } else {
                field.classList.remove('is-invalid');
            }
        });

        // Email validation
        const emailFields = form.querySelectorAll('input[type="email"]');
        emailFields.forEach(field => {
            if (field.value && !this.isValidEmail(field.value)) {
                isValid = false;
                field.classList.add('is-invalid');
            }
        });

        // Phone validation
        const phoneFields = form.querySelectorAll('input[name*="telepon"], input[name*="whatsapp"]');
        phoneFields.forEach(field => {
            if (field.value && !this.isValidPhone(field.value)) {
                isValid = false;
                field.classList.add('is-invalid');
            }
        });

        if (!isValid) {
            e.preventDefault();
            this.showNotification('Mohon periksa kembali data yang Anda masukkan', 'error');
        }
    }

    handleImageError(e) {
        e.target.src = '../../assets/img/default-avatar.png';
        e.target.alt = 'Foto tidak tersedia';
    }

    trackWhatsAppClick(e) {
        // Analytics tracking
        if (typeof gtag !== 'undefined') {
            gtag('event', 'whatsapp_contact', {
                'event_category': 'engagement',
                'event_label': 'worker_contact'
            });
        }
        
        console.log('WhatsApp contact clicked');
    }

    performSearch(query) {
        if (query.length < 2) return;

        // Show loading state
        const searchResults = document.getElementById('searchResults');
        if (searchResults) {
            searchResults.innerHTML = '<div class="text-center py-3"><div class="spinner"></div></div>';
        }

        // Debounced search
        fetch(`api/search.php?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                this.displaySearchResults(data);
            })
            .catch(error => {
                console.error('Search error:', error);
            });
    }

    displaySearchResults(results) {
        const container = document.getElementById('searchResults');
        if (!container) return;

        if (results.length === 0) {
            container.innerHTML = '<div class="text-center py-3 text-muted">Tidak ada hasil ditemukan</div>';
            return;
        }

        let html = '';
        results.forEach(result => {
            html += `
                <div class="search-result-item p-3 border-bottom">
                    <div class="d-flex">
                        <img src="${result.foto || '../../assets/img/default-avatar.png'}" 
                             class="rounded-circle me-3" width="50" height="50">
                        <div>
                            <h6 class="mb-1">${result.nama}</h6>
                            <small class="text-muted">${result.kategori} - ${result.kota}</small>
                            <div class="rating">
                                ${this.generateStarRating(result.rating)}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    generateStarRating(rating) {
        let stars = '';
        for (let i = 1; i <= 5; i++) {
            if (i <= rating) {
                stars += '<i class="fas fa-star"></i>';
            } else {
                stars += '<i class="fas fa-star text-muted"></i>';
            }
        }
        return stars;
    }

    showNotification(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} notification animate-fade-up`;
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-${this.getIconByType(type)} me-2"></i>
                <span>${message}</span>
                <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;

        document.body.appendChild(notification);

        // Auto remove
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, duration);
    }

    getIconByType(type) {
        const icons = {
            'success': 'check-circle',
            'error': 'exclamation-triangle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    isValidEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

    isValidPhone(phone) {
        const regex = /^[\+]?[0-9\-\(\)\s]{10,}$/;
        return regex.test(phone);
    }

    loadUserPreferences() {
        // Load dark mode preference
        const darkMode = localStorage.getItem('darkMode') === 'true';
        if (darkMode) {
            document.body.classList.add('dark-mode');
        }

        // Load language preference
        const language = localStorage.getItem('language') || 'id';
        document.documentElement.lang = language;
    }

    saveUserPreference(key, value) {
        localStorage.setItem(key, value);
    }

    toggleDarkMode() {
        const isDark = document.body.classList.toggle('dark-mode');
        this.saveUserPreference('darkMode', isDark);
    }

    // Utility functions
    formatRupiah(number) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        }).format(number);
    }

    formatDate(date) {
        return new Intl.DateTimeFormat('id-ID', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        }).format(new Date(date));
    }

    copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                this.showNotification('Teks berhasil disalin!', 'success');
            });
        } else {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            this.showNotification('Teks berhasil disalin!', 'success');
        }
    }

    shareProfile(profileUrl, workerName) {
        if (navigator.share) {
            navigator.share({
                title: `Profil ${workerName} - JasaLokal`,
                text: `Lihat profil pekerja ${workerName} di JasaLokal`,
                url: profileUrl
            });
        } else {
            this.copyToClipboard(profileUrl);
        }
    }

    // PWA functions
    initPWA() {
        // Register service worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(registration => {
                    console.log('SW registered: ', registration);
                })
                .catch(registrationError => {
                    console.log('SW registration failed: ', registrationError);
                });
        }

        // Handle install prompt
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            this.showInstallButton();
        });
    }

    showInstallButton() {
        const installButton = document.getElementById('installApp');
        if (installButton) {
            installButton.style.display = 'block';
            installButton.addEventListener('click', () => {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then((choiceResult) => {
                        if (choiceResult.outcome === 'accepted') {
                            console.log('User accepted the A2HS prompt');
                        }
                        deferredPrompt = null;
                    });
                }
            });
        }
    }

    // Analytics tracking
    trackEvent(action, category, label) {
        if (typeof gtag !== 'undefined') {
            gtag('event', action, {
                'event_category': category,
                'event_label': label
            });
        }
    }

    trackPageView(page) {
        if (typeof gtag !== 'undefined') {
            gtag('config', 'GA_TRACKING_ID', {
                page_title: document.title,
                page_location: window.location.href,
                page_path: page
            });
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.JasaLokal = new JasaLokal();
});

// Additional utility functions
function showLoading(element) {
    element.classList.add('loading');
    element.style.pointerEvents = 'none';
}

function hideLoading(element) {
    element.classList.remove('loading');
    element.style.pointerEvents = 'auto';
}

function confirmDelete(message = 'Apakah Anda yakin ingin menghapus item ini?') {
    return confirm(message);
}

function openWhatsApp(number, message = '') {
    const url = `https://wa.me/${number}${message ? '?text=' + encodeURIComponent(message) : ''}`;
    window.open(url, '_blank');
}

function callPhone(number) {
    window.location.href = `tel:${number}`;
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = JasaLokal;
}