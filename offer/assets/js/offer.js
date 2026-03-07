document.addEventListener('DOMContentLoaded', () => {
    // 1. Initial Security & Disables
    const disableSelect = (e) => e.preventDefault();
    document.addEventListener('contextmenu', disableSelect);
    document.addEventListener('selectstart', disableSelect);
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && ['u', 's', 'p', 'c', 'v', 'i', 'j'].includes(e.key.toLowerCase())) {
            e.preventDefault();
        }
    });

    // Disable image dragging
    document.querySelectorAll('img').forEach(img => {
        img.addEventListener('dragstart', (e) => e.preventDefault());
    });

    // 2. Navbar Scroll Behavior
    const navbar = document.querySelector('.navbar');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    }, { passive: true });

    // 3. High-Performance Scroll Reveal (GPU Accelerated)
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
                // Optional: unobserve after reveal for better performance
                // revealObserver.unobserve(entry.target); 
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

    document.querySelectorAll('.reveal').forEach(el => {
        revealObserver.observe(el);
    });

    // Add staggered reveals to bento boxes and problem cards
    const staggerObserve = (selector, baseDelay = 100) => {
        const elements = document.querySelectorAll(selector);
        elements.forEach((el, index) => {
            el.classList.add('reveal');
            el.style.transitionDelay = `${baseDelay * (index + 1)}ms`;
            revealObserver.observe(el);
        });
    };

    staggerObserve('.problem-card', 150);
    staggerObserve('.bento-box', 100);

    // 4. FAQ Accordion
    const faqTriggers = document.querySelectorAll('.faq-trigger');
    faqTriggers.forEach(trigger => {
        trigger.addEventListener('click', () => {
            const item = trigger.closest('.faq-item');
            const isActive = item.classList.contains('active');

            // Close all
            document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('active'));

            // Toggle current
            if (!isActive) item.classList.add('active');
        });
    });

    // 5. Sticky Conversion Bar Logic
    const stickyBar = document.getElementById('stickyConversionBar');

    if (stickyBar) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 500) {
                stickyBar.classList.add('visible');
            } else {
                stickyBar.classList.remove('visible');
            }
        }, { passive: true });
    }

    // 6. Product Preview Carousel (Fade)
    const carouselBtns = document.querySelectorAll('.carousel-btn');
    const carouselSlides = document.querySelectorAll('.carousel-slide');

    if (carouselBtns.length > 0 && carouselSlides.length > 0) {
        carouselBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                carouselBtns.forEach(b => b.classList.remove('active'));
                carouselSlides.forEach(s => s.classList.remove('active-slide'));

                btn.classList.add('active');

                const slideIndex = parseInt(btn.getAttribute('data-slide'));
                carouselSlides[slideIndex].classList.add('active-slide');
            });
        });
    }

    // Toggle logic for Screenshot Gallery
    const expandGalleryBtn = document.getElementById('expandGalleryBtn');
    const expandedGallery = document.getElementById('expandedGallery');

    if (expandGalleryBtn && expandedGallery) {
        expandGalleryBtn.addEventListener('click', () => {
            expandedGallery.classList.toggle('expanded');
            const isExpanded = expandedGallery.classList.contains('expanded');
            expandGalleryBtn.innerHTML = isExpanded
                ? '<i data-lucide="chevron-up"></i> Collapse Screenshots'
                : '<i data-lucide="image"></i> Explore More Screenshots';
            if (window.lucide) lucide.createIcons();
        });
    }

    // Toggle logic for Template Screen Grid
    const expandTemplatesBtn = document.getElementById('expandTemplatesBtn');
    const templateGallery = document.getElementById('templateGallery');

    if (expandTemplatesBtn && templateGallery) {
        expandTemplatesBtn.addEventListener('click', () => {
            templateGallery.classList.toggle('expanded');
            const isExpanded = templateGallery.classList.contains('expanded');
            expandTemplatesBtn.innerHTML = isExpanded
                ? '<i data-lucide="chevron-up"></i> Show Less'
                : 'Explore More Designs <i data-lucide="chevron-down"></i>';
            if (window.lucide) lucide.createIcons();
        });
    }

    // 7. Creator Use-Case Tabs
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');

    if (tabBtns.length > 0) {
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                tabBtns.forEach(b => b.classList.remove('active'));
                tabPanes.forEach(p => p.classList.remove('active'));

                btn.classList.add('active');
                const targetId = `tab-${btn.getAttribute('data-tab')}`;
                document.getElementById(targetId).classList.add('active');
            });
        });
    }

    // 8. Live Statistics Counters
    const statNumbers = document.querySelectorAll('.stat-num');
    const animateStat = (el) => {
        const originalText = el.innerText;
        // Basic extraction: 5,000+ -> 5000, 100% -> 100, ₹0/mo -> 0, 24/7 -> 24
        const numMatch = originalText.replace(/,/g, '').match(/\d+/);
        if (!numMatch) return;

        const targetNumber = parseInt(numMatch[0]);
        const prefix = originalText.substring(0, originalText.indexOf(numMatch[0].charAt(0)) || 0); // e.g. ₹
        // The suffix logic handles commas and trailing text by splitting out the matched raw digits
        const rawDigitsStr = numMatch[0];
        let rawOriginalDigitsRegion = originalText.match(/\d+(,\d+)?/)[0]; // e.g. "5,000"
        const suffix = originalText.substring(originalText.indexOf(rawOriginalDigitsRegion) + rawOriginalDigitsRegion.length);

        let current = 0;
        const duration = 2000;
        const stepTime = Math.abs(Math.floor(duration / (targetNumber === 0 ? 1 : targetNumber)));

        // For very large numbers, we increment in chunks
        const increment = targetNumber > 1000 ? Math.ceil(targetNumber / 60) : 1;

        const timer = setInterval(() => {
            current += increment;
            if (current >= targetNumber) {
                current = targetNumber;
                clearInterval(timer);
                el.innerText = originalText; // restore exact original framing
            } else {
                // If it had a comma, add it back for the animation
                let displayNum = current;
                if (targetNumber >= 1000) displayNum = current.toLocaleString('en-US');
                el.innerText = `${prefix}${displayNum}${suffix}`;
            }
        }, stepTime < 10 ? 10 : stepTime);
    };

    const statsObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const num = entry.target.querySelector('.stat-num') || entry.target;
                if (!num.classList.contains('counted')) {
                    num.classList.add('counted');
                    animateStat(num);
                }
            }
        });
    }, { threshold: 0.5 });

    document.querySelectorAll('.stat-item').forEach(item => statsObserver.observe(item));

    // 9. Detailed Review Popup
    const popupNames = ['Rohit', 'Priya', 'Arjun', 'Neha', 'Vikram', 'Ananya', 'Siddharth'];
    const popupLocations = ['Mumbai', 'Delhi', 'Bangalore', 'Chennai', 'Pune', 'Hyderabad', 'Kolkata'];
    const popupActions = ['purchased BioScript', 'activated her license', 'downloaded BioScript'];

    const popupEl = document.getElementById('review-popup');
    const popupNameEl = document.getElementById('popup-name');
    const popupLocEl = document.getElementById('popup-location');
    const popupActionEl = document.getElementById('popup-action');
    const popupTimeEl = document.getElementById('popup-time');
    const popupAvatarEl = document.getElementById('popup-avatar');
    const popupCloseBtn = document.getElementById('popup-close');

    let popupTimer;

    const hidePopup = () => {
        if (popupEl) popupEl.classList.remove('show');
    };

    if (popupCloseBtn) {
        popupCloseBtn.addEventListener('click', () => {
            hidePopup();
            clearTimeout(popupTimer);
        });
    }

    const showDetailedPopup = () => {
        if (!popupEl) return;

        // Pick random data
        const name = popupNames[Math.floor(Math.random() * popupNames.length)];
        const loc = popupLocations[Math.floor(Math.random() * popupLocations.length)];
        const action = popupActions[Math.floor(Math.random() * popupActions.length)];
        const minutes = Math.floor(Math.random() * 15) + 1; // 1 to 15 mins ago
        const avatarId = Math.floor(Math.random() * 70) + 1; // Pravatar 1-70

        // Update DOM
        if (popupNameEl) popupNameEl.innerText = name;
        if (popupLocEl) popupLocEl.innerText = loc;
        if (popupActionEl) popupActionEl.innerText = action;
        if (popupTimeEl) popupTimeEl.innerText = `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        if (popupAvatarEl) popupAvatarEl.src = `https://i.pravatar.cc/150?img=${avatarId}`;

        // Show
        popupEl.classList.add('show');

        // Hide after 6 seconds
        clearTimeout(popupTimer);
        popupTimer = setTimeout(hidePopup, 6000);
    };

    // Initial delay then rotate every 15 seconds
    setTimeout(() => {
        showDetailedPopup();
        setInterval(() => {
            showDetailedPopup();
        }, 15000); // 15s interval
    }, 5000);
});
