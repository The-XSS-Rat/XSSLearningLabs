// UI interactions: highlighting metadata and switching pages
document.addEventListener('DOMContentLoaded', ()=>{

  const goToPage = (page)=>{
    const params = new URLSearchParams(window.location.search);
    params.set('page', page);
    window.location.search = params.toString();
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

});
