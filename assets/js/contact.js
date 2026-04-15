 (function() {
    // navbar scroll
    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
      navbar.classList.toggle('scrolled', window.scrollY > 60);
    });

    // hamburger
    const hamburger = document.getElementById('hamburger');
    const navLinks = document.querySelector('.nav-links');
    const navCta = document.querySelector('.nav-cta');
    if (hamburger) {
      hamburger.addEventListener('click', () => {
        navLinks.classList.toggle('open');
        navCta.classList.toggle('open');
      });
    }

    // send message – changes colour based on role selection and submits to backend
    const sendBtn = document.getElementById('sendMsgBtn');
    const roleSelect = document.getElementById('roleSelect');
    const nameInput = document.getElementById('contactName');
    const emailInput = document.getElementById('contactEmail');
    const messageInput = document.getElementById('contactMessage');
    const statusEl = document.getElementById('contactFormStatus');

    const setStatus = (message, tone) => {
      if (!statusEl) return;
      statusEl.textContent = message || '';
      if (tone === 'success') {
        statusEl.style.color = '#50C878';
      } else if (tone === 'error') {
        statusEl.style.color = '#ff6b6b';
      } else {
        statusEl.style.color = 'var(--text-muted)';
      }
    };

    const isValidEmail = (email) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || '').trim());

    const submitContactForm = async () => {
      const role = roleSelect ? roleSelect.value : 'customer';
      const fullName = nameInput ? nameInput.value.trim() : '';
      const email = emailInput ? emailInput.value.trim().toLowerCase() : '';
      const message = messageInput ? messageInput.value.trim() : '';

      if (fullName.length < 2) {
        setStatus('Please enter your full name.', 'error');
        return;
      }
      if (!isValidEmail(email)) {
        setStatus('Please enter a valid email address.', 'error');
        return;
      }
      if (message.length < 10) {
        setStatus('Please enter at least 10 characters in your message.', 'error');
        return;
      }

      if (sendBtn) {
        sendBtn.disabled = true;
        sendBtn.textContent = 'Sending...';
      }
      setStatus('Sending your message...', 'info');

      try {
        // Fetch CSRF token before submitting form
        const csrfToken = await ensureCsrfToken();
        if (!csrfToken) {
          throw new Error('Security token unavailable. Please refresh and try again.');
        }

        const response = await fetch('../api/contact_submit.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            role,
            name: fullName,
            email,
            message,
            csrf_token: csrfToken,
            source_page: '/pages/contact.html'
          })
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error((result && result.message) || 'Unable to submit your message.');
        }

        setStatus(`Message sent successfully. Ticket #${result.data.inquiry_id}`, 'success');
        if (messageInput) messageInput.value = '';
      } catch (error) {
        console.error('Contact form submit failed:', error);
        setStatus(error.message || 'Submission failed. Please try again.', 'error');
      } finally {
        if (sendBtn) {
          sendBtn.disabled = false;
          sendBtn.textContent = 'Send message';
        }
      }
    };

    if (sendBtn && roleSelect) {
      const updateBtnStyle = () => {
        const role = roleSelect.value;
        if (role === 'admin') {
          sendBtn.style.background = 'linear-gradient(135deg, #000080, #000066)';
          sendBtn.style.boxShadow = '0 8px 20px rgba(0,0,128,0.4)';
        } else if (role === 'vendor') {
          sendBtn.style.background = 'linear-gradient(135deg, #50C878, #3d8c5f)';
          sendBtn.style.boxShadow = '0 8px 20px rgba(80,200,120,0.4)';
        } else {
          sendBtn.style.background = 'linear-gradient(135deg, #FF8C00, #e6a800)';
          sendBtn.style.boxShadow = '0 8px 20px rgba(255,140,0,0.4)';
        }
      };
      roleSelect.addEventListener('change', updateBtnStyle);
      updateBtnStyle(); // initial

      sendBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        await submitContactForm();
      });
    }

    // close mobile nav if link clicked
    document.querySelectorAll('.nav-links a').forEach(a => a.addEventListener('click', ()=>{
      if (navLinks) navLinks.classList.remove('open');
      if (navCta) navCta.classList.remove('open');
    }));

    console.log('BizLink contact — admin #000080 · vendor #50C878 · customer #FF8C00');
  })();