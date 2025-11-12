// UI interactions: highlighting metadata and switching pages
document.addEventListener('DOMContentLoaded', ()=>{

  const bodyEl = document.body;
  const getToastStack = () => {
    let stack = document.querySelector('.easter-toast-stack');
    if (!stack){
      stack = document.createElement('div');
      stack.className = 'easter-toast-stack';
      document.body.appendChild(stack);
    }
    return stack;
  };

  const showToast = (message) => {
    if (!message){
      return;
    }
    const stack = getToastStack();
    const toast = document.createElement('div');
    toast.className = 'easter-toast';
    toast.textContent = message;
    stack.appendChild(toast);
    requestAnimationFrame(()=> toast.classList.add('is-visible'));
    setTimeout(()=>{
      toast.classList.remove('is-visible');
      setTimeout(()=>{
        toast.remove();
        if (!stack.children.length){
          stack.remove();
        }
      }, 260);
    }, 2200);
  };

  const goToPage = (page)=>{
    const params = new URLSearchParams(window.location.search);
    params.set('page', page);
    const next = params.toString();
    window.setTimeout(()=>{
      window.location.search = next;
    }, 110);
  };

  // highlight corresponding metadata when focusing inputs
  document.querySelectorAll('[data-field]').forEach(el=>{
    el.addEventListener('focus', ()=> {
      const name = el.getAttribute('data-field');
      document.querySelectorAll('.meta-item').forEach(mi=>{
        mi.classList.toggle('field-highlight', mi.dataset.field === name);
      });
    });
    el.addEventListener('blur', ()=> {
      document.querySelectorAll('.meta-item').forEach(mi=>{
        mi.classList.remove('field-highlight');
      });
    });
  });

  // navigation buttons in sidebar and home cards
  document.querySelectorAll('[data-page]').forEach(btn=>{
    btn.addEventListener('click', e=>{
      if (btn.tagName === 'SELECT') return;
      e.preventDefault();
      const page = btn.dataset.page;
      if (page){
        goToPage(page);
      }
    });
  });

  // fancy ripple feedback for all buttons
  document.querySelectorAll('.button').forEach(btn=>{
    btn.addEventListener('click', e=>{
      const rect = btn.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height);
      const ripple = document.createElement('span');
      ripple.className = 'button-ripple';
      const clientX = e.clientX || (rect.left + rect.width / 2);
      const clientY = e.clientY || (rect.top + rect.height / 2);
      ripple.style.width = ripple.style.height = `${size}px`;
      ripple.style.left = `${clientX - rect.left - size / 2}px`;
      ripple.style.top = `${clientY - rect.top - size / 2}px`;
      btn.appendChild(ripple);
      requestAnimationFrame(()=> ripple.classList.add('is-active'));
      setTimeout(()=> ripple.remove(), 450);
    }, {passive:true});
  });

  // XP tracker (localStorage with cookie fallback)
  const xpPerLevel = 100;
  const storageKey = 'xsslab_xp';
  const cookieKey = 'xsslab_xp';

  const readCookie = () => {
    const cookies = document.cookie ? document.cookie.split(';') : [];
    for (const raw of cookies){
      const [name, ...rest] = raw.trim().split('=');
      if (name === cookieKey){
        try {
          return decodeURIComponent(rest.join('='));
        } catch (err) {
          return null;
        }
      }
    }
    return null;
  };

  const writeCookie = (value) => {
    const encoded = encodeURIComponent(value);
    const maxAge = 60 * 60 * 24 * 365; // one year
    document.cookie = `${cookieKey}=${encoded};path=/;max-age=${maxAge}`;
  };

  const xpStore = {
    data: { total: 0, completed: {} },
    load(){
      let raw = null;
      try {
        raw = window.localStorage.getItem(storageKey);
      } catch (err) {
        raw = null;
      }
      if (!raw){
        raw = readCookie();
      }
      if (raw){
        try {
          const parsed = JSON.parse(raw);
          if (typeof parsed === 'object' && parsed){
            this.data.total = Number.isFinite(parsed.total) ? parsed.total : 0;
            this.data.completed = parsed.completed && typeof parsed.completed === 'object' ? parsed.completed : {};
          }
        } catch (err) {
          this.data = { total: 0, completed: {} };
        }
      }
    },
    save(){
      const payload = JSON.stringify(this.data);
      try {
        window.localStorage.setItem(storageKey, payload);
      } catch (err) {
        // ignore storage quota errors
      }
      writeCookie(payload);
    },
    award(id, amount){
      if (!id || this.data.completed[id]){
        return false;
      }
      const numericAmount = Number.isFinite(amount) ? amount : 0;
      if (numericAmount <= 0){
        return false;
      }
      this.data.total += numericAmount;
      this.data.completed[id] = {
        amount: numericAmount,
        awardedAt: new Date().toISOString()
      };
      this.save();
      return true;
    },
    reset(){
      this.data = { total: 0, completed: {} };
      this.save();
    }
  };

  xpStore.load();

  const levelEl = document.getElementById('xp-level');
  const totalEl = document.getElementById('xp-total');
  const progressFill = document.getElementById('xp-progress-fill');
  const resetBtn = document.getElementById('xp-reset');
  const xpHudEl = document.querySelector('[data-xp-hud]');

  const showXpPop = (amount) => {
    if (!xpHudEl || !Number.isFinite(amount) || amount <= 0){
      return;
    }
    const pop = document.createElement('div');
    pop.className = 'xp-pop';
    pop.textContent = `+${amount} XP`;
    xpHudEl.appendChild(pop);
    requestAnimationFrame(()=>{
      pop.classList.add('is-visible');
    });
    setTimeout(()=>{
      pop.classList.add('is-leaving');
    }, 1400);
    setTimeout(()=>{
      pop.remove();
    }, 1900);
  };

  const updateHud = () => {
    if (!levelEl || !totalEl || !progressFill){
      return;
    }
    const total = Math.max(0, Math.round(xpStore.data.total));
    const level = Math.floor(total / xpPerLevel) + 1;
    const progress = (total % xpPerLevel) / xpPerLevel;
    levelEl.textContent = `Level ${level}`;
    totalEl.textContent = `${total} XP`;
    progressFill.style.width = `${Math.min(100, Math.max(0, progress * 100))}%`;
    progressFill.setAttribute('aria-valuenow', String(Math.round(progress * 100)));
    progressFill.setAttribute('aria-label', `Progress to next level: ${Math.round(progress * 100)}%`);
  };

  updateHud();

  const konamiSequence = ['ArrowUp','ArrowUp','ArrowDown','ArrowDown','ArrowLeft','ArrowRight','ArrowLeft','ArrowRight','b','a'];
  const konamiBuffer = [];
  let konamiUnlocked = false;

  window.addEventListener('keydown', (event)=>{
    konamiBuffer.push(event.key);
    if (konamiBuffer.length > konamiSequence.length){
      konamiBuffer.shift();
    }
    const matched = konamiSequence.every((codeKey, index)=>{
      const buffered = konamiBuffer[index];
      return (buffered || '').toLowerCase() === codeKey.toLowerCase();
    });
    if (matched){
      if (!konamiUnlocked){
        konamiUnlocked = true;
        if (bodyEl){
          bodyEl.classList.add('is-hacker');
        }
        if (xpStore.award('konami-easter-egg', 42)){
          showXpPop(42);
          updateHud();
        }
        showToast('Konami unlocked! neon mode engaged');
      } else {
        showToast('Konami encore! keep hacking');
      }
    }
  });

  const logo = document.querySelector('.logo');
  let logoClicks = 0;
  let logoTimer = null;

  if (logo){
    logo.addEventListener('click', ()=>{
      logoClicks += 1;
      if (logoTimer){
        clearTimeout(logoTimer);
      }
      logoTimer = setTimeout(()=>{
        logoClicks = 0;
      }, 650);
      if (logoClicks >= 5){
        logoClicks = 0;
        if (bodyEl){
          const nowActive = bodyEl.classList.toggle('is-rat');
          if (nowActive && xpStore.award('rat-mode-easter-egg', 13)){
            showXpPop(13);
            updateHud();
          }
          showToast(nowActive ? 'Rat mode activated! squeak squeak' : 'Rat mode disengaged. back to work');
        }
      }
    });
  }

  if (resetBtn){
    resetBtn.addEventListener('click', ()=>{
      if (window.confirm('Reset XP progress? This will clear all recorded completions.')){
        xpStore.reset();
        updateHud();
        document.querySelectorAll('.xp-marker').forEach(marker => {
          marker.classList.remove('is-complete');
          const button = marker.querySelector('.xp-button');
          const status = marker.querySelector('.xp-marker-status');
          if (button){
            button.disabled = false;
            const award = marker.dataset.xpAward ? parseInt(marker.dataset.xpAward, 10) : 0;
            button.textContent = `Mark complete (+${award} XP)`;
          }
          if (status){
            status.textContent = '';
          }
        });
      }
    });
  }

  const activateMarker = (marker) => {
    const button = marker.querySelector('.xp-button');
    const status = marker.querySelector('.xp-marker-status');
    const award = marker.dataset.xpAward ? parseInt(marker.dataset.xpAward, 10) : 0;
    const id = marker.dataset.xpId;
    if (xpStore.data.completed[id]){
      marker.classList.add('is-complete');
      if (button){
        button.disabled = true;
        button.textContent = 'Completed';
      }
      if (status){
        status.textContent = `Awarded +${award} XP`;
      }
    } else {
      marker.classList.remove('is-complete');
      if (button){
        button.disabled = false;
        button.textContent = `Mark complete (+${award} XP)`;
      }
      if (status){
        status.textContent = '';
      }
    }
  };

  document.querySelectorAll('.xp-marker').forEach(marker => {
    activateMarker(marker);
    const button = marker.querySelector('.xp-button');
    if (!button){
      return;
    }
    button.addEventListener('click', ()=>{
      const award = marker.dataset.xpAward ? parseInt(marker.dataset.xpAward, 10) : 0;
      const id = marker.dataset.xpId;
      if (xpStore.award(id, award)){
        activateMarker(marker);
        updateHud();
        showXpPop(award);
      }
    });
  });

  const escapeSelector = (value) => {
    if (window.CSS && typeof window.CSS.escape === 'function'){
      return window.CSS.escape(value);
    }
    return value.replace(/(["'\\\[\]\.:#])/g, '\\$1');
  };

  document.addEventListener('lab:solved', (event)=>{
    if (!event || !event.detail || !event.detail.id){
      return;
    }
    const id = String(event.detail.id);
    const marker = document.querySelector(`.xp-marker[data-xp-id="${escapeSelector(id)}"]`);
    const award = Number.isFinite(event.detail.amount) ? event.detail.amount : (marker ? parseInt(marker.dataset.xpAward || '0', 10) : 0);
    if (xpStore.award(id, award)){
      if (marker){
        activateMarker(marker);
      }
      updateHud();
      showXpPop(award);
    }
  });

  document.querySelectorAll('[data-fundamentals-playground]').forEach(playground => {
    const input = playground.querySelector('[data-fundamentals-input]');
    const renderBtn = playground.querySelector('[data-fundamentals-render]');
    const resetBtnEl = playground.querySelector('[data-fundamentals-reset]');
    const preview = playground.querySelector('[data-fundamentals-preview]');
    const escaped = playground.querySelector('[data-fundamentals-escaped]');
    const tip = playground.querySelector('[data-fundamentals-tip]');
    if (!input || !preview || !escaped){
      return;
    }
    const defaultValue = input.value;
    let solved = false;
    const syncPreview = (value) => {
      preview.innerHTML = value;
      escaped.textContent = value;
    };
    const markSolved = () => {
      if (solved){
        return;
      }
      const value = input.value;
      if (!value.trim().length){
        return;
      }
      solved = true;
      if (tip){
        tip.textContent = 'Nice! You just rendered live DOM and saw the escaped version.';
        tip.classList.add('is-success');
      }
      document.dispatchEvent(new CustomEvent('lab:solved', { detail: { id: 'fundamentals-overview' } }));
    };

    syncPreview(defaultValue);

    renderBtn?.addEventListener('click', () => {
      syncPreview(input.value);
      markSolved();
    });
    input.addEventListener('keydown', (event)=>{
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'enter'){
        event.preventDefault();
        syncPreview(input.value);
        markSolved();
      }
    });
    input.addEventListener('input', () => {
      syncPreview(input.value);
      if (input.value !== defaultValue){
        markSolved();
      }
    });
    resetBtnEl?.addEventListener('click', ()=>{
      input.value = defaultValue;
      syncPreview(defaultValue);
      solved = false;
      if (tip){
        tip.textContent = '✅ Keep experimenting until the rendered DOM matches what you expect.';
        tip.classList.remove('is-success');
      }
    });
  });

});
