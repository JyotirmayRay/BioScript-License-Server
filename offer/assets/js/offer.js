document.addEventListener('DOMContentLoaded', () => {
    // 1. Initial State & Security
    const disableSelect = (e) => e.preventDefault();
    document.addEventListener('contextmenu', disableSelect);
    document.addEventListener('selectstart', disableSelect);
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && ['u', 's', 'p', 'c', 'v', 'i', 'j'].includes(e.key.toLowerCase())) {
            e.preventDefault();
        }
    });

    // 2. Scroll Reveal Engine
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -50px 0px' });

    document.querySelectorAll('.reveal, .glass-card, .feature-card, .gallery-item, .pricing-card').forEach(el => {
        if (!el.classList.contains('reveal')) el.classList.add('reveal');
        revealObserver.observe(el);
    });

    // 3. Interactive Savings Calculator
    const calcInput = document.getElementById('monthlyPlan');
    const save3yr = document.getElementById('save3yr');
    const save5yr = document.getElementById('save5yr');
    const save10yr = document.getElementById('save10yr');
    const savingsHighlight = document.getElementById('savingsHighlight');

    const animateValue = (obj, start, end, duration) => {
        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            const current = Math.floor(progress * (end - start) + start);
            obj.innerHTML = '₹' + current.toLocaleString();
            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        };
        window.requestAnimationFrame(step);
    };

    const updateCalculations = () => {
        const monthly = parseInt(calcInput.value) || 0;
        const bioScriptPrice = 999;

        const v3yr = monthly * 12 * 3;
        const v5yr = monthly * 12 * 5;
        const v10yr = monthly * 12 * 10;
        const totalSaved = v5yr - bioScriptPrice;

        animateValue(save3yr, 0, v3yr, 800);
        animateValue(save5yr, 0, v5yr, 800);
        animateValue(save10yr, 0, v10yr, 800);

        if (savingsHighlight) {
            savingsHighlight.innerHTML = `You save ₹${totalSaved.toLocaleString()} over 5 years.`;
        }
    };

    if (calcInput) {
        calcInput.addEventListener('input', updateCalculations);
        // Initial run
        setTimeout(updateCalculations, 1000);
    }

    // 4. FAQ Accordion
    document.querySelectorAll('.faq-item').forEach(item => {
        item.addEventListener('click', () => {
            const isActive = item.classList.contains('active');
            document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('active'));
            if (!isActive) item.classList.add('active');
        });
    });

    // 5. Floating Revenue Notifications (Social Proof)
    const names = ['Aarav', 'Ishaan', 'Sanya', 'Vikram', 'Ananya', 'Rohan', 'Kavya'];
    const locations = ['Mumbai', 'Delhi', 'Bangalore', 'Chennai', 'Pune', 'Hyderabad'];

    const showNotification = () => {
        const name = names[Math.floor(Math.random() * names.length)];
        const loc = locations[Math.floor(Math.random() * locations.length)];
        const notification = document.createElement('div');
        notification.className = 'glass-card floating-notif';
        notification.innerHTML = `
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:10px; height:10px; border-radius:50%; background:var(--accent); box-shadow:0 0 10px var(--accent);"></div>
                <p style="font-size:0.85rem; font-weight:700;">${name} from ${loc} just secured a lifetime license!</p>
            </div>
        `;

        Object.assign(notification.style, {
            position: 'fixed',
            bottom: '30px',
            left: '30px',
            padding: '1rem 1.5rem',
            zIndex: '2000',
            transform: 'translateY(100px)',
            opacity: '0',
            transition: 'all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275)'
        });

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.transform = 'translateY(0)';
            notification.style.opacity = '1';
        }, 100);

        setTimeout(() => {
            notification.style.transform = 'translateY(100px)';
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 500);
        }, 5000);
    };

    // Show notification every 15-25 seconds
    setInterval(() => {
        if (Math.random() > 0.5) showNotification();
    }, 20000);

    // 6. Sticky CTA visibility
    const finalCta = document.getElementById('pricing');
    const stickyBar = document.getElementById('mobileStickyBar');

    if (stickyBar && finalCta) {
        window.addEventListener('scroll', () => {
            const ctaPos = finalCta.getBoundingClientRect().top;
            if (ctaPos < window.innerHeight && ctaPos > 0) {
                stickyBar.style.transform = 'translateY(100%)';
            } else if (ctaPos <= 0) {
                stickyBar.style.transform = 'translateY(100%)';
            } else {
                stickyBar.style.transform = 'translateY(0)';
            }
        });
    }

    // 7. Image Security (Drag disable)
    document.querySelectorAll('img').forEach(img => {
        img.addEventListener('dragstart', (e) => e.preventDefault());
    });
});
