/**
 * Baracca - Landing Page Scripts
 * Gère les animations, les compteurs, le menu mobile et les interactions
 */

// ============================================
// 1. DOM Content Loaded - Initialisation principale
// ============================================
document.addEventListener('DOMContentLoaded', () => {
  initSmoothScroll();
  initMobileMenu();
  initCounters();
  initScrollAnimations();
  initScrollHeader();
  initCurrentYear();
  initHashScroll();
  
  // Add loaded class to body for any CSS transitions
  document.body.classList.add('loaded');
});

// ============================================
// 2. Scroll Animations (Fade In)
// ============================================
function initScrollAnimations() {
  const animatedElements = document.querySelectorAll('.fade-in');
  
  if (!animatedElements.length) return;
  
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('animate');
        observer.unobserve(entry.target);
      }
    });
  }, { 
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  });
  
  animatedElements.forEach(el => {
    // Reset opacity for scroll observation
    el.style.opacity = '0';
    observer.observe(el);
  });
}

// ============================================
// 3. Compteurs animés (Counter Animation)
// ============================================
function animateCounter(element, target, duration = 2000) {
  const start = 0;
  const startTime = performance.now();
  
  function updateCounter(currentTime) {
    const elapsed = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);
    
    // Easing function (ease-out)
    const easeOut = 1 - Math.pow(1 - progress, 3);
    const current = start + (target - start) * easeOut;
    
    element.textContent = Math.floor(current).toLocaleString('fr-FR');
    
    if (progress < 1) {
      requestAnimationFrame(updateCounter);
    } else {
      element.textContent = target.toLocaleString('fr-FR');
    }
  }
  
  requestAnimationFrame(updateCounter);
}

function initCounters() {
  const counters = document.querySelectorAll('.stat-number[data-target]');
  
  if (!counters.length) return;
  
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const target = parseInt(entry.target.dataset.target, 10);
        if (!isNaN(target)) {
          animateCounter(entry.target, target);
        }
        observer.unobserve(entry.target);
      }
    });
  }, { 
    threshold: 0.5,
    rootMargin: '0px 0px -100px 0px'
  });
  
  counters.forEach(counter => observer.observe(counter));
}

// ============================================
// 4. Menu Mobile (Mobile Navigation)
// ============================================
function initMobileMenu() {
  const mobileMenuBtn = document.getElementById('mobile-menu-btn');
  const mobileMenu = document.getElementById('mobile-menu');
  
  if (!mobileMenuBtn || !mobileMenu) return;
  
  // Toggle menu
  mobileMenuBtn.addEventListener('click', () => {
    mobileMenu.classList.toggle('hidden');
    
    // Toggle button icon (hamburger to close)
    const icon = mobileMenuBtn.querySelector('svg');
    if (mobileMenu.classList.contains('hidden')) {
      // Show hamburger icon
      icon.innerHTML = `
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      `;
    } else {
      // Show close icon
      icon.innerHTML = `
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
      `;
    }
  });
  
  // Close menu when clicking on a link
  const menuLinks = mobileMenu.querySelectorAll('a');
  menuLinks.forEach(link => {
    link.addEventListener('click', () => {
      mobileMenu.classList.add('hidden');
      const icon = mobileMenuBtn.querySelector('svg');
      icon.innerHTML = `
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      `;
    });
  });
  
  // Close menu when clicking outside
  document.addEventListener('click', (e) => {
    // composedPath() includes the button even if the icon swap detached e.target during this click
    const path = e.composedPath();
    if (!mobileMenu.classList.contains('hidden') && 
        !path.includes(mobileMenuBtn) && 
        !path.includes(mobileMenu)) {
      mobileMenu.classList.add('hidden');
      const icon = mobileMenuBtn.querySelector('svg');
      icon.innerHTML = `
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      `;
    }
  });
}

// ============================================
// 5. Header Scroll Effect - Hide on scroll down, show on scroll up
// ============================================
function initScrollHeader() {
  const header = document.querySelector('.header');
  
  if (!header) return;
  
  let lastScroll = 0;
  
  window.addEventListener('scroll', () => {
    const currentScroll = window.pageYOffset;
    
    // Add/remove scrolled class based on scroll position
    if (currentScroll > 50) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }
    
    // Hide header when scrolling down, show when scrolling up
    if (currentScroll > 100) {
      if (currentScroll > lastScroll) {
        header.classList.add('header-hidden');
      } else {
        header.classList.remove('header-hidden');
      }
    } else {
      header.classList.remove('header-hidden');
    }
    
    lastScroll = currentScroll;
  });
}

// ============================================
// 6. Smooth Scroll
// ============================================
function initSmoothScroll() {
  // Handle anchor links
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
      const href = this.getAttribute('href');
      
      // Skip if it's just '#'
      if (href === '#') return;
      
      const target = document.querySelector(href);
      
      if (target) {
        e.preventDefault();
        
        // Get header height for offset
        const header = document.querySelector('.header');
        const headerHeight = header ? header.offsetHeight : 80;
        
        const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - headerHeight;
        
        window.scrollTo({
          top: targetPosition,
          behavior: 'smooth'
        });
        
        // Update URL hash without jumping
        history.pushState(null, null, href);
      }
    });
  });
}

// ============================================
// 7. Hash Scroll on Page Load
// ============================================
function initHashScroll() {
  const hash = window.location.hash;
  
  if (hash && hash !== '#') {
    const target = document.querySelector(hash);
    
    if (target) {
      const header = document.querySelector('.header');
      const headerHeight = header ? header.offsetHeight : 80;
      
      const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - headerHeight;
      
      setTimeout(() => {
        window.scrollTo({
          top: targetPosition,
          behavior: 'smooth'
        });
      }, 100);
    }
  }
}

// ============================================
// 8. Current Year in Footer
// ============================================
function initCurrentYear() {
  const yearElement = document.getElementById('current-year');
  
  if (yearElement) {
    yearElement.textContent = new Date().getFullYear();
  }
}

// ============================================
// 9. Window Load - Additional initializations
// ============================================
window.addEventListener('load', () => {
  // Add any post-load initializations here
  // For example, if you need to wait for images to load
});

// ============================================
// 10. Window Scroll - Active Navigation
// ============================================
window.addEventListener('scroll', () => {
  const sections = document.querySelectorAll('section[id]');
  const navLinks = document.querySelectorAll('.nav-links a[href^="#"]');
  
  if (!sections.length || !navLinks.length) return;
  
  let current = '';
  const headerHeight = 80; // Approximate header height
  
  sections.forEach(section => {
    const sectionTop = section.offsetTop - headerHeight - 100;
    const sectionHeight = section.offsetHeight;
    
    if (window.pageYOffset >= sectionTop && window.pageYOffset < sectionTop + sectionHeight) {
      current = section.getAttribute('id');
    }
  });
  
  navLinks.forEach(link => {
    link.classList.remove('active');
    const href = link.getAttribute('href').substring(1);
    if (href === current) {
      link.classList.add('active');
    }
  });
});

// ============================================
// 11. Parallax Effect (Optional - Hero Image)
// ============================================
function initParallax() {
  const heroImage = document.querySelector('.hero-image');
  
  if (!heroImage) return;
  
  window.addEventListener('scroll', () => {
    const scrolled = window.pageYOffset;
    const rate = scrolled * 0.3;
    
    heroImage.style.transform = `translateY(-${rate}px)`;
  });
}

// Initialize parallax (uncomment to enable)
// initParallax();

// ============================================
// 12. (Supprimé — remplacé par initContactPageForm en section 19)
// ============================================

// ============================================
// 13. FAQ Accordion
// ============================================
function initFAQ() {
  const faqQuestions = document.querySelectorAll('.faq-question');
  
  faqQuestions.forEach(question => {
    question.addEventListener('click', () => {
      const faqItem = question.closest('.faq-item');
      const answer = faqItem.querySelector('.faq-answer');
      const isActive = faqItem.classList.contains('active');
      
      // Close all FAQ items
      document.querySelectorAll('.faq-item').forEach(item => {
        item.classList.remove('active');
        const ans = item.querySelector('.faq-answer');
        if (ans) ans.style.maxHeight = null;
      });
      
      // Open clicked item if it was closed
      if (!isActive) {
        faqItem.classList.add('active');
        if (answer) {
          answer.style.maxHeight = answer.scrollHeight + 'px';
        }
      }
    });
  });
}

// Initialize FAQ
initFAQ();

// ============================================
// 14. Back to Top Button
// ============================================
function initBackToTop() {
  const backToTopBtn = document.getElementById('back-to-top');
  
  if (!backToTopBtn) return;
  
  window.addEventListener('scroll', () => {
    if (window.pageYOffset > 300) {
      backToTopBtn.classList.remove('hidden');
    } else {
      backToTopBtn.classList.add('hidden');
    }
  });
  
  backToTopBtn.addEventListener('click', (e) => {
    e.preventDefault();
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  });
}

// Initialize back to top (if button exists in HTML)
// Uncomment if you add a back-to-top button
// initBackToTop();

// ============================================
// 15. Typing Animation (Optional)
// ============================================
function initTypingAnimation() {
  const typingElement = document.getElementById('typing-text');
  
  if (!typingElement) return;
  
  const text = typingElement.dataset.text || '';
  const speed = typingElement.dataset.speed ? parseInt(typingElement.dataset.speed, 10) : 100;
  let index = 0;
  
  function type() {
    if (index < text.length) {
      typingElement.textContent += text.charAt(index);
      index++;
      setTimeout(type, speed);
    }
  }
  
  // Start typing after a delay
  setTimeout(() => {
    typingElement.textContent = '';
    type();
  }, 1000);
}

// Initialize typing animation
// initTypingAnimation();

// ============================================
// 16. Lazy Loading Images
// ============================================
function initLazyLoading() {
  if ('IntersectionObserver' in window) {
    const lazyImages = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const img = entry.target;
          img.src = img.dataset.src;
          img.removeAttribute('data-src');
          imageObserver.unobserve(img);
        }
      });
    });
    
    lazyImages.forEach(img => imageObserver.observe(img));
  }
}

// Initialize lazy loading
initLazyLoading();

// ============================================
// 17. Console Message (Easter Egg)
// ============================================
console.log('%c Baracca ', 'background: #4A9B8E; color: white; font-size: 20px; padding: 5px;');
console.log('%c L\'habitat mobile, biosourcé et réversible ', 'color: #4A9B8E; font-size: 14px;');
console.log('%c Rejoignez la révolution de l\'habitat ! ', 'color: #6B7280; font-size: 12px;');

// ============================================
// 18. Cookie Consent Banner (RGPD)
// ============================================
function initCookieBanner() {
  const banner = document.getElementById('cookie-banner');
  const acceptBtn = document.getElementById('cookie-accept');
  const refuseBtn = document.getElementById('cookie-refuse');

  if (!banner || !acceptBtn || !refuseBtn) return;

  const COOKIE_KEY = 'baracca_cookie_consent';
  const storedConsent = localStorage.getItem(COOKIE_KEY);

  // If user has already made a choice, don't show the banner
  if (storedConsent !== null) {
    banner.classList.add('hidden');
    return;
  }

  // Show the banner after a short delay (let the page render first)
  setTimeout(() => {
    banner.classList.remove('hidden');
  }, 500);

  // Accept all cookies
  acceptBtn.addEventListener('click', () => {
    localStorage.setItem(COOKIE_KEY, 'accepted');
    hideCookieBanner();
    // Here you would initialize analytics/tracking scripts if needed
    // Example: loadAnalytics();
  });

  // Refuse non-essential cookies
  refuseBtn.addEventListener('click', () => {
    localStorage.setItem(COOKIE_KEY, 'refused');
    hideCookieBanner();
    // Only strictly necessary cookies remain active
  });

  function hideCookieBanner() {
    banner.classList.add('hidden');
    // Remove from DOM after transition completes
    setTimeout(() => {
      if (banner.parentNode) {
        banner.parentNode.removeChild(banner);
      }
    }, 500);
  }

  // "Gérer les cookies" link in footer — reset consent and reload
  const cookiePrefsLink = document.getElementById('cookie-preferences-link');
  if (cookiePrefsLink) {
    cookiePrefsLink.addEventListener('click', (e) => {
      e.preventDefault();
      localStorage.removeItem(COOKIE_KEY);
      location.reload();
    });
  }
}

// Initialize cookie banner
initCookieBanner();

// ============================================
// 19. Contact Form (anti-spam timestamp + UX)
// ============================================
function initContactPageForm() {
  const form = document.getElementById('contact-form');
  if (!form) return;

  // Set timestamp when form loads (anti-spam: server checks elapsed time)
  const timestampField = document.getElementById('form_timestamp');
  if (timestampField) {
    timestampField.value = Math.floor(Date.now() / 1000);
  }

  // Disable submit button on submit to prevent double-sending
  const submitBtn = document.getElementById('contact-submit');
  form.addEventListener('submit', () => {
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Envoi en cours...';
    }
  });
}

// Initialize contact form
initContactPageForm();
