// UI interactions: highlighting metadata and switching pages
document.addEventListener('DOMContentLoaded', ()=>{

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

});
