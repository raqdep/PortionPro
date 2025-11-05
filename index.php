<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PortionPro - Food Costing Calculator</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
body.dashboard {
    position: relative;
    overflow-x: hidden;
    background-image: url('bg/bgopav.png');
    background-size: cover;
    background-attachment: fixed;
    background-repeat: no-repeat;
    background-position: center;
    min-height: 100vh;
}

.content-panel {
    background: rgba(255, 255, 255, 0.65);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 40px;
    margin: 20px auto;
    max-width: 1800px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.hero-title { 
    color: #2c3e50; 
    font-size: 2.8rem; 
    margin: 0; 
    font-weight: 800;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    animation: fadeInUp 1s ease;
    background: linear-gradient(135deg, #2c3e50 0%, #16a085 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-sub { 
    color: #3d4849ff; 
    margin: 12px 0 0; 
    font-size: 1.2rem;
    text-shadow: none;
    animation: fadeInUp 1s ease 0.2s both;
}

.hero-actions { 
    margin-top: 24px; 
    display: flex; 
    gap: 16px; 
    animation: fadeInUp 1s ease 0.4s both;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.features-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:16px; }
.feature-card { background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.2); border-radius: 10px; padding:16px; color:#fff; }
.feature-card i { margin-right:8px; }

.navbar-brand {
    display: flex;
    align-items: center;
    text-decoration: none;
    color: #2c3e50;
    font-weight: bold;
    font-size: 1.5rem;
    transition: all 0.3s ease;
}

.navbar-brand:hover {
    color: #16a085;
    transform: scale(1.05);
}

.navbar-brand i {
    font-size: 2rem;
    margin-right: 10px;
    color: #16a085;
}

.logo-image {
    height: 40px;
    width: auto;
    margin-right: 10px;
    transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
    animation: logoFloat 3s ease-in-out infinite;
}

@keyframes logoFloat {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-5px); }
}

.logo-image:hover {
    transform: scale(1.15) rotate(5deg);
    filter: drop-shadow(0 6px 12px rgba(22, 160, 133, 0.4));
    animation: none;
}

.logo-text {
    font-size: 1.5rem;
    font-weight: bold;
    color: #fff;
    margin: 0;
}

.hero-logo {
    text-align: center;
    margin-bottom: 30px;
}

.hero-logo .logo-text {
    font-size: 3.5rem;
    font-weight: bold;
    color: #2c3e50;
    margin: 0;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.05);
}

.hero-logo .logo-icon {
    font-size: 4rem;
    color: #16a085;
    margin-bottom: 10px;
}

.hero-logo .logo-tagline {
    color: #95a5a6;
    font-size: 1.1rem;
    margin-top: 10px;
    font-style: italic;
}

.hero-logo-image {
    height: 100px;
    width: auto;
    margin-bottom: 20px;
    transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    filter: drop-shadow(0 8px 16px rgba(0,0,0,0.2));
    animation: heroLogoFloat 4s ease-in-out infinite, logoGlow 2s ease-in-out infinite alternate;
}

@keyframes heroLogoFloat {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-10px) rotate(2deg); }
}

@keyframes logoGlow {
    from { filter: drop-shadow(0 8px 16px rgba(22, 160, 133, 0.3)); }
    to { filter: drop-shadow(0 8px 24px rgba(22, 160, 133, 0.6)); }
}

.hero-logo-image:hover {
    transform: scale(1.1) rotate(5deg);
    filter: drop-shadow(0 12px 24px rgba(22, 160, 133, 0.8));
    animation: none;
}

/* Enhanced Features Grid */
.features-enhanced-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 20px;
    padding: 20px 0;
}

.feature-card-enhanced {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 20px;
    padding: 28px;
    transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    cursor: pointer;
}

.feature-card-enhanced::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(45deg, transparent, rgba(22, 160, 133, 0.1), transparent);
    transform: rotate(45deg);
    transition: all 0.6s ease;
    opacity: 0;
}

.feature-card-enhanced:hover::after {
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); opacity: 0; }
    50% { opacity: 1; }
    100% { transform: translateX(100%) translateY(100%) rotate(45deg); opacity: 0; }
}

.feature-card-enhanced::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: #16a085;
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.feature-card-enhanced:hover {
    transform: translateY(-12px) scale(1.02);
    box-shadow: 0 20px 40px rgba(22, 160, 133, 0.3), 0 0 0 1px rgba(22, 160, 133, 0.5);
    border-color: #16a085;
    background: rgba(255, 255, 255, 1);
}

.feature-card-enhanced:hover::before {
    transform: scaleX(1);
}

.feature-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, #16a085 0%, #f39c12 100%);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    position: relative;
    overflow: hidden;
}

.feature-icon::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s ease, height 0.6s ease;
}

.feature-card-enhanced:hover .feature-icon::before {
    width: 200px;
    height: 200px;
}

.feature-icon i {
    font-size: 28px;
    color: white;
    transition: all 0.5s ease;
    position: relative;
    z-index: 1;
}

.feature-card-enhanced:hover .feature-icon i {
    transform: scale(1.2);
    filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.8));
}

.feature-card-enhanced:hover .feature-icon {
    transform: scale(1.15) rotate(360deg);
    box-shadow: 0 12px 24px rgba(22, 160, 133, 0.5);
}

.feature-content {
    position: relative;
}

.feature-title {
    font-size: 1.3rem;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 12px 0;
    transition: color 0.3s ease;
}

.feature-card-enhanced:hover .feature-title {
    color: #16a085;
}

.feature-description {
    color: #7f8c8d;
    line-height: 1.6;
    margin: 0 0 16px 0;
    font-size: 0.95rem;
}

.feature-badge {
    display: inline-block;
    background: #f39c12;
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
}

.feature-card-enhanced:hover .feature-badge {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(243, 156, 18, 0.3);
}

/* Enhanced Button */
.btn-large {
    padding: 16px 36px;
    font-size: 1.15rem;
    font-weight: 700;
    border-radius: 50px;
    transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    text-transform: none;
    letter-spacing: 0.5px;
    position: relative;
    overflow: hidden;
    z-index: 1;
}

.btn-large::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s ease, height 0.6s ease;
    z-index: -1;
}

.btn-large:hover::before {
    width: 300px;
    height: 300px;
}

.btn-large:hover {
    transform: translateY(-4px) scale(1.05);
    box-shadow: 0 12px 24px rgba(22, 160, 133, 0.4);
}

.btn-large:active {
    transform: translateY(-2px) scale(1.02);
}

@media (max-width: 768px) {
    .features-enhanced-grid {
        grid-template-columns: 1fr;
        gap: 16px;
        padding: 16px 0;
    }
    
    .feature-card-enhanced {
        padding: 20px;
    }
    
    .feature-icon {
        width: 50px;
        height: 50px;
    }
    
    .feature-icon i {
        font-size: 20px;
    }
    
    .feature-title {
        font-size: 1.2rem;
    }
}
    </style>
</head>
<body class="dashboard">
    <nav class="navbar">
        <div class="navbar-content">
            <a href="#" class="navbar-brand">
                <img src="logo/PortionPro-fill.png" alt="PortionPro Logo" class="logo-image">
                <span class="logo-text">PortionPro</span>
            </a>
            <div class="navbar-menu">
                <a href="#features"><i class="fas fa-star"></i> Features</a>
                <a href="login.php"><i class="fas fa-right-to-bracket"></i> Login</a>
            </div>
            <div class="user-menu">
                <a class="btn btn-primary" href="login.php"> Get Started</a>
            </div>
        </div>
    </nav>
    <div class="main-content" style="position: relative; z-index: 10;">
        <div class="content-panel">
        <div class="page-header">
            <div class="hero-logo">
                <img src="logo/PortionPro-fill.png" alt="PortionPro Logo" class="hero-logo-image">
                <h1 class="logo-text">PortionPro</h1>
                <p class="logo-tagline">Food Costing Calculator</p>
            </div>
            
            <h1 class="page-title hero-title">Master Your Food Business Costs & Profits</h1>
            <p class="page-subtitle hero-sub">Calculate accurate costs, set profitable prices, and make data‑driven decisions with ease.</p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="login.php"><i class="fas fa-arrow-right"></i> Get Started</a>
                <a class="btn btn-secondary" href="#features"><i class="fas fa-star"></i> See Features</a>
            </div>
        </div>

        <div class="stats-grid" style="margin-top: 10px;">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-value">Recipe Costing</div>
                <div class="stat-label">Build recipes with smart unit conversions</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon secondary">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stat-value">Profit Analysis</div>
                <div class="stat-label">See profit per serving and total profit</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-file-excel"></i>
                </div>
                <div class="stat-value">Export Reports</div>
                <div class="stat-label">Download clean spreadsheets anytime</div>
            </div>
        </div>

        <div class="card" id="features" style="margin-top: 20px;">
            <div class="card-header">
                <h2 class="card-title">Everything You Need to Price Right</h2>
                <p style="color: #7f8c8d; margin: 8px 0 0; font-size: 1.1rem;">Powerful tools designed for food business success</p>
            </div>
            
            <!-- Enhanced Features Grid -->
            <div class="features-enhanced-grid">
                <div class="feature-card-enhanced">
                    <div class="feature-icon">
                        <i class="fas fa-scale-balanced"></i>
                    </div>
                    <div class="feature-content">
                        <h3 class="feature-title">Smart Unit Conversions</h3>
                        <p class="feature-description">Mix grams, cups, and pieces—costs are converted automatically with precision.</p>
                        <div class="feature-badge">Auto-Convert</div>
                    </div>
                </div>
                
                <div class="feature-card-enhanced">
                    <div class="feature-icon">
                        <i class="fas fa-basket-shopping"></i>
                    </div>
                    <div class="feature-content">
                        <h3 class="feature-title">Ingredient Management</h3>
                        <p class="feature-description">Track units, prices, and categories with quick search and smart filters.</p>
                        <div class="feature-badge">Organized</div>
                    </div>
                </div>
                
                <div class="feature-card-enhanced">
                    <div class="feature-icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="feature-content">
                        <h3 class="feature-title">Recipe Costing</h3>
                        <p class="feature-description">See total and per‑serving cost with margin‑based price suggestions.</p>
                        <div class="feature-badge">Accurate</div>
                    </div>
                </div>
                
                <div class="feature-card-enhanced">
                    <div class="feature-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="feature-content">
                        <h3 class="feature-title">Profit Analysis</h3>
                        <p class="feature-description">Identify your most profitable recipes and opportunities to improve margins.</p>
                        <div class="feature-badge">Insights</div>
                    </div>
                </div>
                
                <div class="feature-card-enhanced">
                    <div class="feature-icon">
                        <i class="fas fa-file-excel"></i>
                    </div>
                    <div class="feature-content">
                        <h3 class="feature-title">Exportable Reports</h3>
                        <p class="feature-description">Download comprehensive spreadsheets for ingredients and profitability analysis.</p>
                        <div class="feature-badge">Professional</div>
                    </div>
                </div>
                
                <div class="feature-card-enhanced">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="feature-content">
                        <h3 class="feature-title">Business Analytics</h3>
                        <p class="feature-description">Visual charts and insights to make data-driven business decisions.</p>
                        <div class="feature-badge">Analytics</div>
                    </div>
                </div>
            </div>
            
            <div style="display:flex; justify-content:center; padding: 20px; margin-top: 10px;">
                <a class="btn btn-primary btn-large" href="login.php">
                    </i> Get Started Now
                </a>
            </div>
        </div>
        </div>
    </div>
    <script>
    (function(){
        const featureCards = document.querySelectorAll('.feature-card-enhanced');
        const io = new IntersectionObserver((entries)=>{
            entries.forEach((entry, index)=>{
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }, index * 150);
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });
        featureCards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(50px)';
            card.style.transition = 'all 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
            io.observe(card);
        });
    })();

    (function(){
        const featureCards = document.querySelectorAll('.feature-card-enhanced');
        
        featureCards.forEach(card => {
            card.addEventListener('mousemove', function(e){
                const rect = this.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const rotateX = (y - centerY) / 10;
                const rotateY = (centerX - x) / 10;
                
                this.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-12px) scale(1.02)`;
            });
            
            card.addEventListener('mouseleave', function(){
                this.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) translateY(0) scale(1)';
            });
        });
    })();

    (function(){
        const navLinks = document.querySelectorAll('a[href^="#"]');
        navLinks.forEach(link => {
            link.addEventListener('click', function(e){
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
    })();

    (function(){
        const hero = document.querySelector('.page-header');
        if (!hero) return;
        
        window.addEventListener('scroll', function(){
            const scrolled = window.pageYOffset;
            const rate = scrolled * -0.3;
            hero.style.transform = `translateY(${rate}px)`;
        });
    })();

    (function(){
        const buttons = document.querySelectorAll('.btn-primary, .btn-large');
        
        buttons.forEach(button => {
            button.addEventListener('mousemove', function(e){
                const rect = this.getBoundingClientRect();
                const x = e.clientX - rect.left - rect.width / 2;
                const y = e.clientY - rect.top - rect.height / 2;
                
                this.style.transform = `translate(${x * 0.2}px, ${y * 0.2}px) scale(1.05)`;
            });
            
            button.addEventListener('mouseleave', function(){
                this.style.transform = 'translate(0, 0) scale(1)';
            });
        });
    })();

    (function(){
        const statCards = document.querySelectorAll('.stat-card');
        const io = new IntersectionObserver((entries)=>{
            entries.forEach((entry, index)=>{
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0) scale(1)';
                    }, index * 100);
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.2 });
        
        statCards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px) scale(0.9)';
            card.style.transition = 'all 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
            io.observe(card);
        });
    })();

    (function(){
        const buttons = document.querySelectorAll('.btn-primary, .btn-secondary, .btn-large');
        
        buttons.forEach(button => {
            button.addEventListener('click', function(e){
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.background = 'rgba(255, 255, 255, 0.6)';
                ripple.style.transform = 'scale(0)';
                ripple.style.animation = 'ripple 0.6s ease-out';
                ripple.style.pointerEvents = 'none';
                
                this.appendChild(ripple);
                
                setTimeout(() => ripple.remove(), 600);
            });
        });
        
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(2);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    })();
    </script>
</body>
</html>
