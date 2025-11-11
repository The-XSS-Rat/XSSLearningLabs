// UI interactions: highlighting metadata and switching pages
document.addEventListener('DOMContentLoaded', ()=>{

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

  // tabs
  document.querySelectorAll('.nav-item').forEach(btn=>{
    btn.addEventListener('click', e=>{
      e.preventDefault();
      const page = btn.dataset.page;
      const params = new URLSearchParams(window.location.search);
      params.set('page', page);
      window.location.search = params.toString();
    });
  });

});
