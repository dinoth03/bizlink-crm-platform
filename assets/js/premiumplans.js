/*
BizLink Premium Plans - Interactive Features
Handles billing toggle, FAQ accordion, and pricing updates
 */

document.addEventListener('DOMContentLoaded', function() {
  initBillingToggle();
  initFAQ();
  initNavbar();
});

/**
 * Billing Toggle - Switch between monthly and annual pricing
 */
function initBillingToggle() {
  const toggle = document.getElementById('billingToggle');
  const prices = document.querySelectorAll('.monthly-price');
  let isMonthly = true;

  if (toggle) {
    toggle.addEventListener('click', function() {
      toggle.classList.toggle('active');
      isMonthly = !isMonthly;

      prices.forEach(priceEl => {
        const monthlyPrice = priceEl.dataset.monthly;
        const annualPrice = priceEl.dataset.annual;
        const newPrice = isMonthly ? monthlyPrice : annualPrice;
        const billingNote = priceEl.nextElementSibling;

        // Update price
        priceEl.textContent = `Rs. ${parseInt(newPrice).toLocaleString()}`;

        // Update billing note
        if (billingNote && billingNote.classList.contains('price-note')) {
          if (isMonthly) {
            billingNote.textContent = 'Billed monthly';
          } else {
            const monthlyVal = parseInt(monthlyPrice);
            const annualVal = parseInt(annualPrice);
            const monthlyCost = Math.round(annualVal / 12);
            billingNote.textContent = `Billed annually (Rs. ${monthlyCost}/month)`;
          }
        }
      });
    });
  }
}

/**
 * FAQ Accordion - Toggle FAQ item visibility
 */
function toggleFAQ(faqItem) {
  faqItem.classList.toggle('active');
  
  // Smooth max-height animation
  const answer = faqItem.querySelector('.faq-answer');
  if (faqItem.classList.contains('active')) {
    answer.style.maxHeight = answer.scrollHeight + 'px';
  } else {
    answer.style.maxHeight = '0';
  }
}

function initFAQ() {
  const faqItems = document.querySelectorAll('.faq-item');
  faqItems.forEach(item => {
    item.addEventListener('click', function(e) {
      // Prevent triggering on nested links
      if (e.target.tagName !== 'A') {
        toggleFAQ(this);
      }
    });
  });
}

/**
 * Navbar Interactivity
 */
function initNavbar() {
  const hamburger = document.getElementById('hamburger');
  const navbar = document.getElementById('navbar');

  if (hamburger) {
    hamburger.addEventListener('click', function() {
      const navLinks = navbar.querySelector('.nav-links');
      navLinks.style.display = navLinks.style.display === 'flex' ? 'none' : 'flex';
    });
  }

  // Close menu when link is clicked
  const navLinks = document.querySelectorAll('.nav-links a');
  navLinks.forEach(link => {
    link.addEventListener('click', function() {
      const navLinksContainer = document.querySelector('.nav-links');
      navLinksContainer.style.display = 'none';
    });
  });
}

/**
 * Smooth scroll for anchor links
 */
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function(e) {
    e.preventDefault();
    const target = document.querySelector(this.getAttribute('href'));
    if (target) {
      target.scrollIntoView({ behavior: 'smooth' });
    }
  });
});

/**
 * Animation on scroll
 */
const observerOptions = {
  threshold: 0.1,
  rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver(function(entries) {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('in-view');
      observer.unobserve(entry.target);
    }
  });
}, observerOptions);

document.querySelectorAll('.pricing-card, .faq-item, .comparison-table').forEach(el => {
  observer.observe(el);
});

/**
 * Track plan selection
 */
document.querySelectorAll('.btn-plan').forEach(btn => {
  btn.addEventListener('click', function(e) {
    const planName = this.closest('.pricing-card').querySelector('.plan-name').textContent;
    const price = this.closest('.pricing-card').querySelector('.price').textContent;
    
    // Log for analytics or store selection
    console.log(`User selected: ${planName} - ${price}`);
  });
});
