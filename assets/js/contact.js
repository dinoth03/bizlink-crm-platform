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

    // send message demo – changes colour based on role selection
    const sendBtn = document.getElementById('sendMsgBtn');
    const roleSelect = document.getElementById('roleSelect');
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

      sendBtn.addEventListener('click', (e) => {
        e.preventDefault();
        alert(`📨 Demo: message sent to ${roleSelect.value} team. (real form would submit)`);
      });
    }

    // close mobile nav if link clicked
    document.querySelectorAll('.nav-links a').forEach(a => a.addEventListener('click', ()=>{
      if (navLinks) navLinks.classList.remove('open');
      if (navCta) navCta.classList.remove('open');
    }));

    console.log('BizLink contact — admin #000080 · vendor #50C878 · customer #FF8C00');
  })();