{{-- Accounting view: explicit Blade control-flow blocks prevent ambiguous compiler output. --}}

@once

<script>
(function () {
  'use strict';
  let controller = null;
  async function loadAccountingPage(url, target, updateHistory) {
    if (!target || !url || url === '#') return;
    if (controller) controller.abort();
    controller = new AbortController();
    target.classList.add('is-loading');
    target.setAttribute('aria-busy','true');
    try {
      const response = await fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
        credentials:'same-origin', signal:controller.signal
      });
      if (!response.ok) throw new Error('HTTP ' + response.status);
      if (response.redirected && new URL(response.url).pathname !== window.location.pathname) {
        window.location.assign(response.url); return;
      }
      const html = await response.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const newList = doc.getElementById(target.id);
      if (!newList) throw new Error('Pagination target was not present in the server response');
      document.dispatchEvent(new CustomEvent('accounting:beforePageReplace', {detail:{target:target}}));
      target.replaceWith(newList);
      document.dispatchEvent(new CustomEvent('accounting:pageLoaded', {detail:{target:newList}}));
      if (updateHistory) history.pushState({accountingAjaxPage:true},'',url);
    } catch (error) {
      if (error.name !== 'AbortError') {
        console.error('Accounting AJAX pagination error',error);
        // Normal navigation fallback preserves usability if a session expired or HTML changed.
        window.location.assign(url);
      }
    } finally {
      target.classList.remove('is-loading');
      target.removeAttribute('aria-busy');
    }
  }
  window.accountingAjaxLoad = loadAccountingPage;
  document.addEventListener('click', function(event){
    const link = event.target.closest('.acc-ajax-list .acc-pagination a');
    if (!link || link.classList.contains('disabled')) return;
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
    event.preventDefault();
    loadAccountingPage(link.href,link.closest('.acc-ajax-list'),true);
  });
  window.addEventListener('popstate', function () {
    // Restore independent page state for all lists, not just the last clicked list.
    window.location.reload();
  });
})();
</script>
@endonce
