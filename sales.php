<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BioScript Creator Edition — Own Your Brand</title>
    <!-- FontAwesome for essential icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <style>
        :root {
            /* Strict Design Tokens */
            --bg-primary: #0a0c11;
            --bg-surface: #111827;
            --bg-elevated: #151a23;

            --accent-primary: #8b5cf6;
            --accent-blue: #3b82f6;
            --success: #10b981;
            --danger: #ef4444;

            --text-primary: #f8fafc;
            --text-muted: #94a3b8;
            --text-dark: #475569;

            --border-subtle: rgba(255, 255, 255, 0.05);
            --border-hover: rgba(255, 255, 255, 0.12);

            --font-display: 'Plus Jakarta Sans', sans-serif;
            --font-body: 'Plus Jakarta Sans', sans-serif;

            --transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* --- Global --- */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background-color: var(--bg-primary);
            background-image:
                radial-gradient(circle at 15% 50%, rgba(139, 92, 246, 0.08), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(59, 130, 246, 0.08), transparent 25%);
            background-attachment: fixed;
            color: var(--text-primary);
            font-family: var(--font-body);
            line-height: 1.6;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* --- Typography Hierarchy --- */
        h1,
        h2,
        h3,
        h4 {
            font-family: var(--font-display);
            line-height: 1.1;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            letter-spacing: -0.02em;
        }

        h1 {
            font-size: clamp(3.5rem, 8vw, 6.3rem);
            /* +15% scale */
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        h2 {
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        h3 {
            font-size: 1.75rem;
            font-weight: 600;
        }

        p {
            color: var(--text-muted);
            font-size: 1.125rem;
            margin-bottom: 1.5rem;
            font-weight: 400;
        }

        p.lead {
            font-size: clamp(1.25rem, 2vw, 1.5rem);
            color: #cbd5e1;
            max-width: 800px;
            font-weight: 400;
        }

        .text-center {
            text-align: center;
        }

        .mx-auto {
            margin-left: auto;
            margin-right: auto;
        }

        .mb-2 {
            margin-bottom: 0.5rem;
        }

        .mb-4 {
            margin-bottom: 1rem;
        }

        .mb-8 {
            margin-bottom: 2rem;
        }

        .mb-12 {
            margin-bottom: 3rem;
        }

        .mb-16 {
            margin-bottom: 4rem;
        }

        .mt-8 {
            margin-top: 2rem;
        }

        .gradient-text {
            background: linear-gradient(135deg, #fff 0%, var(--accent-primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;
        }

        .highlight-text {
            color: var(--text-primary);
            font-weight: 600;
        }

        /* Animated Highlight Gradient */
        .animated-gradient-text {
            background: linear-gradient(270deg, var(--accent-primary), var(--accent-blue), #ec4899, var(--accent-primary));
            background-size: 300% 300%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: gradientMove 6s ease infinite;
            font-weight: 900;
        }

        .subtle-gradient-word {
            background: linear-gradient(270deg, var(--accent-primary), var(--accent-blue), var(--accent-primary));
            background-size: 200% 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: gradientMove 6s ease infinite;
        }

        .animated-underline {
            position: relative;
            display: inline-block;
        }

        .animated-underline::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -5px;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-primary), transparent);
            border-radius: 2px;
            animation: pulseWidth 3s ease-in-out infinite;
        }

        @keyframes pulseWidth {

            0%,
            100% {
                width: 40%;
                opacity: 0.5;
            }

            50% {
                width: 100%;
                opacity: 1;
            }
        }

        @keyframes gradientMove {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        @keyframes borderSlide {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }


        /* --- Scroll Reveal Animations (Restrained Motion) --- */
        .reveal-up {
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.4s cubic-bezier(0.22, 1, 0.36, 1), transform 0.4s cubic-bezier(0.22, 1, 0.36, 1);
            will-change: opacity, transform;
        }

        .reveal-up.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        .delay-100 {
            transition-delay: 100ms;
        }

        .delay-200 {
            transition-delay: 200ms;
        }

        /* --- Layout --- */
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .section {
            padding: 10rem 0;
            /* +25% spacing */
            position: relative;
            z-index: 2;
        }

        /* Grounding Alternate Sections */
        .bg-surface {
            background-color: var(--bg-surface);
            border-top: 1px solid var(--border-subtle);
            border-bottom: 1px solid var(--border-subtle);
        }

        /* --- Buttons --- */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 1rem 2.5rem;
            border-radius: 8px;
            /* Slightly softer than hard cuts for human trust */
            font-family: var(--font-body);
            font-size: 1.125rem;
            font-weight: 700;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            border: 1px solid transparent;
        }

        .btn-primary {
            background: var(--text-primary);
            color: var(--bg-primary);
        }

        .btn-primary:hover {
            background: var(--accent-primary);
            color: #fff;
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.4);
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border-color: var(--border-hover);
        }

        .btn-outline:hover {
            border-color: var(--text-primary);
            background: rgba(255, 255, 255, 0.05);
            transform: translateY(-2px);
        }

        .urgency-badge {
            display: inline-block;
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 700;
            margin-top: 1rem;
        }

        /* --- 1. HERO --- */
        .hero {
            min-height: 90vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
            padding-top: 4rem;
        }

        .hero-mesh {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 50% 0%, rgba(139, 92, 246, 0.1), transparent 40%);
            z-index: 1;
            pointer-events: none;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        /* --- 4. Cost Shock Board --- */
        .tracker-board {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1px;
            background: var(--border-subtle);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            overflow: hidden;
            margin-top: 3rem;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }

        @media(min-width: 768px) {
            .tracker-board {
                grid-template-columns: 1fr 1fr;
            }
        }

        .tracker-cell {
            background: var(--bg-surface);
            padding: 4rem 2rem;
            text-align: center;
        }

        .tracker-label {
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: var(--text-muted);
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .tracker-value {
            font-family: var(--font-display);
            font-size: clamp(3rem, 5vw, 4.5rem);
            font-weight: 800;
            line-height: 1;
        }

        .savings-number {
            color: var(--success);
            text-shadow: 0 0 40px rgba(16, 185, 129, 0.3);
            font-size: clamp(4rem, 6vw, 6rem);
        }

        .calc-input-wrapper {
            margin-top: 3rem;
            display: inline-flex;
            align-items: center;
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
        }

        .calc-input {
            background: transparent;
            border: none;
            color: var(--text-primary);
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 700;
            width: 90px;
            text-align: center;
            outline: none;
        }

        /* --- 5. Problem Solution & 9. Features --- */
        .human-list {
            list-style: none;
            max-width: 600px;
            margin: 0 auto;
            text-align: left;
        }

        .human-list li {
            padding: 1rem 0;
            font-size: 1.25rem;
            display: flex;
            align-items: flex-start;
            border-bottom: 1px solid var(--border-subtle);
        }

        .human-list li:last-child {
            border-bottom: none;
        }

        .human-list i {
            color: var(--success);
            margin-top: 0.3rem;
            margin-right: 1.5rem;
            font-size: 1.5rem;
        }

        /* Feature blocks */
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 3rem;
            max-width: 1000px;
            margin: 0 auto;
        }

        .feature-item {
            text-align: left;
            background: rgba(21, 26, 35, 0.4);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 2rem;
            transition: var(--transition);
        }

        .feature-item:hover {
            border-color: rgba(139, 92, 246, 0.3);
            box-shadow: 0 10px 40px -10px rgba(139, 92, 246, 0.1);
        }

        .feature-item h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        /* --- 6. Comparison Table --- */
        .comparison-table-wrapper {
            max-width: 900px;
            margin: 0 auto;
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            overflow: hidden;
        }

        .compare-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            border-bottom: 1px solid var(--border-subtle);
        }

        .compare-row:last-child {
            border-bottom: none;
        }

        .compare-header {
            background: rgba(0, 0, 0, 0.2);
            font-weight: 700;
            font-size: 1.125rem;
        }

        .compare-cell {
            padding: 1.5rem;
            display: flex;
            align-items: center;
        }

        .compare-cell:not(:first-child) {
            justify-content: center;
            text-align: center;
            border-left: 1px solid var(--border-subtle);
        }

        .cell-bioscript {
            background: rgba(139, 92, 246, 0.05);
        }

        .cell-bioscript.compare-header {
            color: var(--accent-primary);
        }

        .icon-yes {
            color: var(--success);
            font-size: 1.25rem;
        }

        .icon-no {
            color: var(--danger);
            font-size: 1.25rem;
        }

        .text-yes {
            color: var(--success);
            font-weight: 600;
        }

        /* --- 7. Theme Showcase --- */
        .theme-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 2rem;
            margin-top: 4rem;
        }

        .mockup-plate {
            aspect-ratio: 9/16;
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: var(--transition);
        }

        .mockup-plate::after {
            content: 'Theme Preview';
            color: var(--text-dark);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* --- 10. Pricing --- */
        .pricing-box {
            max-width: 500px;
            margin: 0 auto;
            background: rgba(10, 12, 17, 0.6);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 80px -20px rgba(139, 92, 246, 0.15);
            border-radius: 16px;
            padding: 4rem 3rem;
            text-align: center;
            position: relative;
        }

        .pricing-box::before {
            content: '';
            position: absolute;
            top: -1px;
            left: -1px;
            right: -1px;
            bottom: -1px;
            background: linear-gradient(90deg, rgba(139, 92, 246, 0.6), transparent, rgba(255, 255, 255, 0.1), transparent);
            background-size: 300% 100%;
            z-index: -1;
            border-radius: 17px;
            animation: borderSlide 8s linear infinite;
        }

        .price-massive {
            font-family: var(--font-display);
            font-size: 8.4rem;
            /* 1.4x scale */
            font-weight: 900;
            line-height: 1;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .pricing-list {
            list-style: none;
            text-align: left;
            margin: 2rem auto 3rem auto;
            max-width: 300px;
        }

        .pricing-list li {
            margin-bottom: 1rem;
            font-size: 1.125rem;
            color: var(--text-primary);
            font-weight: 500;
        }

        .pricing-list i {
            color: var(--success);
            margin-right: 0.75rem;
        }

        /* --- 11. FAQ --- */
        .faq-container {
            max-width: 700px;
            margin: 0 auto;
            border-top: 1px solid var(--border-subtle);
        }

        .faq-row {
            border-bottom: 1px solid var(--border-subtle);
            padding: 2rem 0;
            cursor: pointer;
        }

        .faq-question {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .faq-icon {
            color: var(--text-muted);
            transition: transform 0.3s ease;
        }

        .faq-row.is-open .faq-icon {
            transform: rotate(45deg);
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease, opacity 0.4s ease;
            opacity: 0;
        }

        .faq-row.is-open .faq-answer {
            max-height: 200px;
            opacity: 1;
        }

        .faq-answer-inner {
            padding-top: 1rem;
            color: var(--text-muted);
        }

        /* --- 6.5. Showcase Gallery --- */
        .showcase-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 4rem;
            margin-top: 4rem;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .showcase-item {
            position: relative;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            overflow: hidden;
            background: var(--bg-surface);
            aspect-ratio: 1080 / 720;
            transition: transform 0.4s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .showcase-item:hover {
            transform: translateY(-6px);
        }

        .showcase-item img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            pointer-events: none;
            user-select: none;
            -webkit-user-drag: none;
            user-drag: none;
        }
    </style>
</head>

<body>

    <!-- 1️⃣ HERO -->
    <header class="hero">
        <div class="hero-mesh"></div>
        <div class="container hero-content text-center">
            <div class="reveal-up">
                <h1>Stop Paying Every Month.<br>
                    <span class="animated-gradient-text">Own Your Bio Page.</span>
                </h1>
            </div>

            <p class="lead mx-auto mt-8 mb-12 reveal-up delay-100">
                Most creators rent their brand.<br>
                You don’t have to.<br>
                BioScript gives you a premium bio page that you fully own.
            </p>

            <div class="reveal-up delay-200">
                <h3 class="mb-8" style="font-weight: 500;">₹999 — One Time. Lifetime Access.</h3>

                <div class="flex justify-center flex-wrap gap-4"
                    style="display: flex; justify-content: center; gap: 1rem;">
                    <a href="#pricing" class="btn btn-primary" style="padding: 1rem 2rem; font-size: 1.125rem;">Get
                        Lifetime Access – ₹999</a>
                </div>

                <div class="urgency-badge">Founder pricing. Limited release.</div>
            </div>
        </div>
    </header>

    <!-- 2️⃣ THE REALITY -->
    <section class="section bg-surface">
        <div class="container text-center reveal-up">
            <h2>Most Creators Are Renting Their Brand</h2>
            <p class="lead mx-auto mb-12" style="max-width: 600px;">
                If you're using a monthly bio tool, you're paying ₹300–₹800 every month.
            </p>

            <p class="text-muted"
                style="text-transform: uppercase; letter-spacing: 0.1em; font-weight: 600; margin-bottom: 1rem;">That
                means:</p>
            <div style="font-size: 2.5rem; font-weight: 800; font-family: var(--font-display); margin-bottom: 2rem;">
                ₹499 × 24 months = <span style="color: var(--danger);">₹11,976</span>
            </div>

            <p class="lead mx-auto mb-12" style="max-width: 600px;">
                And after all that?<br>
                You still don’t own anything.
            </p>

            <ul class="human-list"
                style="margin-bottom: 3rem; text-align: center; border: 1px solid var(--border-subtle); padding: 2rem; border-radius: 12px; background: var(--bg-primary);">
                <li style="justify-content: center; border: none; padding: 0.5rem;"><i class="fas fa-times"
                        style="color: var(--danger);"></i> If they increase prices,</li>
                <li style="justify-content: center; border: none; padding: 0.5rem;"><i class="fas fa-times"
                        style="color: var(--danger);"></i> If they remove features,</li>
                <li style="justify-content: center; border: none; padding: 0.5rem;"><i class="fas fa-times"
                        style="color: var(--danger);"></i> If your account gets suspended,</li>
            </ul>

            <h3>You lose control.</h3>
            <p class="text-muted">That’s not ownership.<br>That’s dependency.</p>
        </div>
    </section>

    <!-- 3️⃣ THE SHIFT -->
    <section class="section">
        <div class="container text-center reveal-up">
            <h2>BioScript Changes That.</h2>
            <p class="lead mx-auto mb-16">
                You install it. You host it. You control it.
            </p>

            <div class="feature-grid grid-4 text-center" style="max-width: 1000px; gap: 2rem;">
                <div class="feature-item"
                    style="text-align: center; border: none; background: transparent; padding: 1rem;">
                    <i class="fas fa-ban text-muted mb-4" style="font-size: 2rem;"></i>
                    <h3 style="font-size: 1.25rem;">No subscription.</h3>
                </div>
                <div class="feature-item"
                    style="text-align: center; border: none; background: transparent; padding: 1rem;">
                    <i class="fas fa-ban text-muted mb-4" style="font-size: 2rem;"></i>
                    <h3 style="font-size: 1.25rem;">No branding forced on you.</h3>
                </div>
                <div class="feature-item"
                    style="text-align: center; border: none; background: transparent; padding: 1rem;">
                    <i class="fas fa-ban text-muted mb-4" style="font-size: 2rem;"></i>
                    <h3 style="font-size: 1.25rem;">No locked features.</h3>
                </div>
                <div class="feature-item"
                    style="text-align: center; border: none; background: transparent; padding: 1rem;">
                    <i class="fas fa-ban text-muted mb-4" style="font-size: 2rem;"></i>
                    <h3 style="font-size: 1.25rem;">No monthly reminders.</h3>
                </div>
            </div>

            <div class="mt-8 pt-12">
                <h3 style="font-size: 2.5rem; font-weight: 900;">Build it once.<br><span class="gradient-text">Keep it
                        forever.</span></h3>
            </div>

            <div class="mt-12">
                <a href="#pricing" class="btn btn-primary">Get Lifetime Access – ₹999</a>
            </div>
        </div>
    </section>

    <!-- 4️⃣ COST IMPACT -->
    <section class="section bg-surface">
        <div class="container text-center reveal-up">
            <h2>One Decision. Big Difference.</h2>

            <div class="tracker-board text-center">
                <div class="tracker-cell"
                    style="border-right: 1px solid var(--border-subtle); border-bottom: 1px solid var(--border-subtle);">
                    <div class="tracker-label">Monthly Tool (2 Years)</div>
                    <div class="tracker-value" id="val-rent">₹11,976</div>
                </div>

                <div class="tracker-cell" style="border-bottom: 1px solid var(--border-subtle);">
                    <div class="tracker-label">BioScript</div>
                    <div class="tracker-value">₹999</div>
                </div>

                <div class="tracker-cell"
                    style="grid-column: 1 / -1; padding: 5rem 2rem; background: var(--bg-primary);">
                    <div class="tracker-label">You save</div>
                    <div class="tracker-value savings-number" id="val-savings">₹10,977</div>
                    <p class="mt-8 text-muted">But more importantly —<br>you stop depending on someone else.</p>
                    <h3 class="mt-4" style="color: var(--text-primary); font-size: 2rem;">That’s freedom.</h3>
                </div>
            </div>
        </div>
    </section>

    <!-- 5️⃣ WHAT MAKES IT SERIOUS -->
    <section class="section">
        <div class="container reveal-up text-center">
            <h2 class="mb-12">This Isn’t Just a Link Page</h2>
            <h3 class="mb-12" style="font-weight: 500;">It’s a personal brand engine.</h3>

            <div class="feature-grid"
                style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); max-width: 900px; margin: 0 auto; gap: 1.5rem;">
                <div class="feature-item"
                    style="padding: 1.5rem; display: flex; align-items: center; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: 8px;">
                    <i class="fas fa-check-circle"
                        style="color: var(--success); font-size: 1.5rem; margin-right: 1rem;"></i>
                    <h3 style="font-size: 1.125rem; margin: 0;">Smart link expiry</h3>
                </div>
                <div class="feature-item"
                    style="padding: 1.5rem; display: flex; align-items: center; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: 8px;">
                    <i class="fas fa-check-circle"
                        style="color: var(--success); font-size: 1.5rem; margin-right: 1rem;"></i>
                    <h3 style="font-size: 1.125rem; margin: 0;">Time-based visibility</h3>
                </div>
                <div class="feature-item"
                    style="padding: 1.5rem; display: flex; align-items: center; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: 8px;">
                    <i class="fas fa-check-circle"
                        style="color: var(--success); font-size: 1.5rem; margin-right: 1rem;"></i>
                    <h3 style="font-size: 1.125rem; margin: 0;">Scarcity countdown</h3>
                </div>
                <div class="feature-item"
                    style="padding: 1.5rem; display: flex; align-items: center; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: 8px;">
                    <i class="fas fa-check-circle"
                        style="color: var(--success); font-size: 1.5rem; margin-right: 1rem;"></i>
                    <h3 style="font-size: 1.125rem; margin: 0;">Lead capture</h3>
                </div>
                <div class="feature-item"
                    style="padding: 1.5rem; display: flex; align-items: center; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: 8px;">
                    <i class="fas fa-check-circle"
                        style="color: var(--success); font-size: 1.5rem; margin-right: 1rem;"></i>
                    <h3 style="font-size: 1.125rem; margin: 0;">WhatsApp integration</h3>
                </div>
                <div class="feature-item"
                    style="padding: 1.5rem; display: flex; align-items: center; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: 8px;">
                    <i class="fas fa-check-circle"
                        style="color: var(--success); font-size: 1.5rem; margin-right: 1rem;"></i>
                    <h3 style="font-size: 1.125rem; margin: 0;">Premium dark themes</h3>
                </div>
                <div class="feature-item"
                    style="padding: 1.5rem; display: flex; align-items: center; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: 8px;">
                    <i class="fas fa-check-circle"
                        style="color: var(--success); font-size: 1.5rem; margin-right: 1rem;"></i>
                    <h3 style="font-size: 1.125rem; margin: 0;">Full control</h3>
                </div>
            </div>

            <h3 class="mt-12 pt-8" style="margin-top: 3rem; margin-bottom: 0.5rem;">Everything included.</h3>
            <p class="text-muted" style="font-size: 1.5rem;">No upgrades required.</p>

            <div class="text-center mt-12 pb-8">
                <a href="#pricing" class="btn btn-primary">Get <span class="subtle-gradient-word"
                        style="margin-left: 0.25rem;">Lifetime</span> Access – ₹999</a>
            </div>
        </div>
    </section>

    <!-- 6️⃣ DESIGN AUTHORITY -->
    <section class="section bg-surface">
        <div class="container reveal-up text-center">
            <h2 class="mb-12">Looks Like a Personal Website</h2>

            <p class="lead mx-auto mb-12" style="font-weight: 600;">Your bio becomes:</p>

            <ul class="human-list"
                style="margin-bottom: 3rem; text-align: left; display: inline-block; max-width: 400px;">
                <li style="border: none; padding: 0.5rem;"><i class="fas fa-circle"
                        style="color: var(--text-primary); font-size: 0.5rem;"></i> Clean</li>
                <li style="border: none; padding: 0.5rem;"><i class="fas fa-circle"
                        style="color: var(--text-primary); font-size: 0.5rem;"></i> Modern</li>
                <li style="border: none; padding: 0.5rem;"><i class="fas fa-circle"
                        style="color: var(--text-primary); font-size: 0.5rem;"></i> Premium</li>
                <li style="border: none; padding: 0.5rem;"><i class="fas fa-circle"
                        style="color: var(--text-primary); font-size: 0.5rem;"></i> Professional</li>
            </ul>

            <h3 class="mt-8" style="font-size: 1.75rem; font-weight: 500;">Not a generic list of links.</h3>

            <h3 class="mt-8" style="font-size: 2rem;">You look serious.<br>Because you are.</h3>
        </div>
    </section>

    <!-- 6.5️⃣ SEE IT IN ACTION -->
    <section class="section" style="padding-top: 80px; padding-bottom: 80px;">
        <div class="container text-center reveal-up">
            <h2 class="mb-4">See It In Action</h2>
            <p class="lead mx-auto mb-12" style="max-width: 600px;">
                Real creator layouts. Real premium design.
            </p>

            <div class="showcase-grid">
                <?php
$showcase_images = [
    'http://bioscript.link/assets/1.png',
    'http://bioscript.link/assets/2.png',
    'http://bioscript.link/assets/3.png',
    'http://bioscript.link/assets/4.png',
    'http://bioscript.link/assets/5.png',
    'http://bioscript.link/assets/6.png',
    'http://bioscript.link/assets/7.png',
    'http://bioscript.link/assets/8.png',
    'http://bioscript.link/assets/9.png',
    'http://bioscript.link/assets/10.png',
    'http://bioscript.link/assets/11.png',
    'http://bioscript.link/assets/12.png',
    'http://bioscript.link/assets/ph1.png',
    'http://bioscript.link/assets/ph2.png'
];
foreach ($showcase_images as $index => $src):
    $delay = ($index % 3) * 100;
    $delay_class = $delay > 0 ? " delay-" . (string)$delay : "";
?>
                <div class="showcase-item reveal-up<?php echo $delay_class; ?>">
                    <img src="<?php echo $src; ?>" alt="Showcase layout" loading="lazy" decoding="async" width="1080"
                        height="720" oncontextmenu="return false" draggable="false">
                </div>
                <?php
endforeach; ?>
            </div>
        </div>
    </section>

    <!-- 7️⃣ WHO IT'S FOR -->
    <section class="section">
        <div class="container reveal-up text-center">
            <h2 class="mb-12">Built for Creators Who Want Control</h2>

            <ul class="human-list"
                style="margin-bottom: 3rem; text-align: left; display: inline-block; max-width: 400px;">
                <li style="border: none; padding: 0.5rem;"><i class="fas fa-check text-muted"
                        style="color: var(--accent-primary);"></i> Instagram creators</li>
                <li style="border: none; padding: 0.5rem;"><i class="fas fa-check text-muted"
                        style="color: var(--accent-primary);"></i> Coaches</li>
                <li style="border: none; padding: 0.5rem;"><i class="fas fa-check text-muted"
                        style="color: var(--accent-primary);"></i> YouTubers</li>
                <li style="border: none; padding: 0.5rem;"><i class="fas fa-check text-muted"
                        style="color: var(--accent-primary);"></i> Freelancers</li>
                <li style="border: none; padding: 0.5rem;"><i class="fas fa-check text-muted"
                        style="color: var(--accent-primary);"></i> Digital sellers</li>
                <li style="border: none; padding: 0.5rem;"><i class="fas fa-check text-muted"
                        style="color: var(--accent-primary);"></i> Agencies</li>
            </ul>

            <h3 class="text-muted" style="font-weight: 400;">If your link matters — this matters.</h3>
        </div>
    </section>

    <!-- 8️⃣ COMPARISON TABLE -->
    <section class="section bg-surface">
        <div class="container reveal-up">
            <h2 class="text-center mb-16">Keep Paying… Or Own It.</h2>

            <div class="comparison-table-wrapper">
                <div class="compare-row compare-header">
                    <div class="compare-cell"></div>
                    <div class="compare-cell">Monthly Tools</div>
                    <div class="compare-cell cell-bioscript">BioScript</div>
                </div>

                <div class="compare-row">
                    <div class="compare-cell">Monthly Fees</div>
                    <div class="compare-cell"><i class="fas fa-check text-muted icon-yes"></i> Yes</div>
                    <div class="compare-cell cell-bioscript"><i class="fas fa-times icon-yes"></i> <span
                            class="text-yes">No</span></div>
                </div>

                <div class="compare-row">
                    <div class="compare-cell">Ownership</div>
                    <div class="compare-cell"><i class="fas fa-times icon-no"></i> No</div>
                    <div class="compare-cell cell-bioscript"><i class="fas fa-check icon-yes"></i> <span
                            class="text-yes">Yes</span></div>
                </div>

                <div class="compare-row">
                    <div class="compare-cell">Branding Removal</div>
                    <div class="compare-cell text-muted">Paid</div>
                    <div class="compare-cell cell-bioscript"><i class="fas fa-check icon-yes"></i> <span
                            class="text-yes">Included</span></div>
                </div>

                <div class="compare-row">
                    <div class="compare-cell">Smart Features</div>
                    <div class="compare-cell text-muted">Limited</div>
                    <div class="compare-cell cell-bioscript"><i class="fas fa-check icon-yes"></i> <span
                            class="text-yes">Included</span></div>
                </div>

                <div class="compare-row">
                    <div class="compare-cell">Lifetime Access</div>
                    <div class="compare-cell"><i class="fas fa-times icon-no"></i> No</div>
                    <div class="compare-cell cell-bioscript"><i class="fas fa-check icon-yes"></i> <span
                            class="text-yes">Yes</span></div>
                </div>
            </div>

            <p class="text-center mt-12" style="font-weight: 700; color: var(--danger); font-size: 1.25rem;">Every month
                you delay costs you more.</p>

            <p class="text-center mt-8 text-muted">No tricks. No hidden pricing. No feature locks.</p>
        </div>
    </section>

    <!-- 🔟 PRICING SECTION (DOMINANT) -->
    <section class="section bg-surface" id="pricing">
        <div class="container reveal-up">
            <div class="pricing-box">
                <h2 class="mb-2">Creator Edition</h2>
                <p class="text-muted mb-8">One-Time Payment</p>

                <div class="price-massive">₹999</div>

                <div class="text-left" style="margin: 0 auto; max-width: 250px;">
                    <p style="font-weight: 700; color: var(--text-primary); margin-bottom: 1rem;">Includes:</p>
                    <ul class="pricing-list" style="margin-top: 0; margin-bottom: 2rem;">
                        <li><i class="fas fa-check"></i> 10 Premium Themes</li>
                        <li><i class="fas fa-check"></i> Conversion Features</li>
                        <li><i class="fas fa-check"></i> Smart Expiry</li>
                        <li><i class="fas fa-check"></i> Lead Capture</li>
                        <li><i class="fas fa-check"></i> WhatsApp Integration</li>
                        <li><i class="fas fa-check"></i> Lifetime Access</li>
                        <li><i class="fas fa-check"></i> <b>Full Ownership</b></li>
                    </ul>
                </div>

                <p style="font-weight: bold; font-size: 1.25rem; margin-bottom: 2rem; color: var(--text-primary);">No
                    subscriptions. Ever.</p>

                <button class="btn btn-primary" style="width: 100%; font-size: 1.25rem;">Get Lifetime Access –
                    ₹999</button>

                <div class="urgency-badge">Founder pricing may increase soon.</div>
            </div>
        </div>
    </section>

    <!-- 1️⃣1️⃣ FAQ -->
    <section class="section">
        <div class="container reveal-up">
            <h2 class="text-center mb-16">FAQ</h2>

            <div class="faq-container">
                <div class="faq-row" onclick="this.classList.toggle('is-open')">
                    <div class="faq-question">Is this a subscription? <i class="fas fa-plus faq-icon"></i></div>
                    <div class="faq-answer">
                        <div class="faq-answer-inner pl-4 pr-12">No. You pay once.</div>
                    </div>
                </div>

                <div class="faq-row" onclick="this.classList.toggle('is-open')">
                    <div class="faq-question">Do I need hosting? <i class="fas fa-plus faq-icon"></i></div>
                    <div class="faq-answer">
                        <div class="faq-answer-inner pl-4 pr-12">Yes. Any basic hosting works.</div>
                    </div>
                </div>

                <div class="faq-row" onclick="this.classList.toggle('is-open')">
                    <div class="faq-question">Can I remove BioScript branding? <i class="fas fa-plus faq-icon"></i>
                    </div>
                    <div class="faq-answer">
                        <div class="faq-answer-inner pl-4 pr-12">Yes.</div>
                    </div>
                </div>

                <div class="faq-row" onclick="this.classList.toggle('is-open')">
                    <div class="faq-question">Is it beginner friendly? <i class="fas fa-plus faq-icon"></i></div>
                    <div class="faq-answer">
                        <div class="faq-answer-inner pl-4 pr-12">Yes. Installation guide included.</div>
                    </div>
                </div>

                <div class="faq-row" onclick="this.classList.toggle('is-open')">
                    <div class="faq-question">Can I customize themes? <i class="fas fa-plus faq-icon"></i></div>
                    <div class="faq-answer">
                        <div class="faq-answer-inner pl-4 pr-12">Yes.</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 1️⃣2️⃣ FINAL CLOSE -->
    <section class="section text-center bg-surface">
        <div class="container reveal-up">
            <h2 style="font-size: clamp(3rem, 7vw, 6rem); margin-bottom: 1rem;">Build Once.</h2>
            <h2 style="font-size: clamp(3rem, 7vw, 6rem); margin-bottom: 3rem;" class="animated-gradient-text">Own
                Forever.</h2>

            <h3 class="mb-12">₹999.</h3>

            <a href="#pricing" class="btn btn-primary" style="padding: 1.5rem 4rem; font-size: 1.25rem;">Get Lifetime
                Access Now</a>
        </div>
    </section>

    <!-- UI Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {

            // Intersection Observer for scroll fades (Transform & Opacity only)
            const revealOptions = { root: null, rootMargin: '0px', threshold: 0.15 };
            const revealObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, revealOptions);

            document.querySelectorAll('.reveal-up').forEach(el => revealObserver.observe(el));

            // Smooth Calculator Mathematics
            const input = document.getElementById('calc-input');
            const elRent = document.getElementById('val-rent');
            const elSavings = document.getElementById('val-savings');
            const SCRIPT_COST = 999;

            let displayRent = 0;
            let displaySavings = 0;
            let targetRent = 0;
            let targetSavings = 0;
            let animationFrame;

            function formatINR(num) {
                return '₹' + Math.floor(num).toLocaleString('en-IN');
            }

            function lerp(start, end, amt) {
                return (1 - amt) * start + amt * end;
            }

            function animateNumbers() {
                displayRent = lerp(displayRent, targetRent, 0.1);
                displaySavings = lerp(displaySavings, targetSavings, 0.1);

                if (Math.abs(displayRent - targetRent) < 1) displayRent = targetRent;
                if (Math.abs(displaySavings - targetSavings) < 1) displaySavings = targetSavings;

                if (elRent) elRent.textContent = '₹499 × 24 months = ' + formatINR(displayRent);
                if (elSavings) elSavings.textContent = formatINR(displaySavings);

                if (displayRent !== targetRent || displaySavings !== targetSavings) {
                    animationFrame = requestAnimationFrame(animateNumbers);
                }
            }

            function updateMath() {
                if (!input) return;
                const val = parseInt(input.value) || 0;
                targetRent = val * 24;
                targetSavings = Math.max(0, targetRent - SCRIPT_COST);

                cancelAnimationFrame(animationFrame);
                animationFrame = requestAnimationFrame(animateNumbers);
            }

            if (input) {
                input.addEventListener('input', updateMath);
                updateMath(); // Initialize
            }
        });
    </script>
</body>

</html>