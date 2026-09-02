<style>
    .inventory-list-toolbar {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:16px;
        flex-wrap:wrap;
    }
    .inventory-result-copy { display:flex; align-items:center; gap:10px; }
    .inventory-search-wrap {
        position:relative;
        width:min(100%,360px);
        margin-left:auto;
    }
    .inventory-search-input {
        width:100%;
        padding-left:40px !important;
        padding-right:38px !important;
    }
    .inventory-search-icon {
        position:absolute;
        left:14px;
        top:50%;
        transform:translateY(-50%);
        color:#6c757d;
        pointer-events:none;
        z-index:2;
    }
    .inventory-search-clear {
        position:absolute;
        right:9px;
        top:50%;
        transform:translateY(-50%);
        border:0;
        background:transparent;
        color:#6c757d;
        width:28px;
        height:28px;
        display:none;
        align-items:center;
        justify-content:center;
        border-radius:50%;
        padding:0;
    }
    .inventory-search-clear:hover { background:rgba(108,117,125,.12); }
    #inventoryListContent { position:relative; min-height:150px; }
    #inventoryListContent.is-loading { opacity:.55; pointer-events:none; }
    .inventory-pagination-footer {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:16px;
        padding:14px 16px;
        border-top:1px solid var(--progga-border-light);
        flex-wrap:wrap;
    }
    .progga-pagination-wrap { display:flex; justify-content:flex-end; }
    .progga-pagination { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
    .progga-page-btn,
    .progga-page-num {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:5px;
        min-height:34px;
        padding:7px 11px;
        border:1px solid var(--progga-border-light);
        border-radius:8px;
        background:#fff;
        color:var(--progga-text);
        font-size:12px;
        font-weight:800;
        text-decoration:none;
        transition:all .15s ease;
    }
    .progga-page-num { min-width:34px; padding-left:9px; padding-right:9px; }
    .progga-page-btn:hover,
    .progga-page-num:hover {
        border-color:var(--progga-primary);
        color:var(--progga-primary);
        background:rgba(33,53,42,.05);
    }
    .progga-page-num.active {
        background:var(--progga-primary);
        border-color:var(--progga-primary);
        color:#fff;
        cursor:default;
    }
    .progga-page-btn.disabled {
        opacity:.45;
        pointer-events:none;
        cursor:not-allowed;
        background:#f8f9fa;
    }
    .progga-page-ellipsis { padding:0 4px; color:var(--progga-text-muted); font-weight:800; }
    .inventory-filter-card .progga-form-control { min-height:42px; }
    @media (max-width:767.98px) {
        .inventory-search-wrap { width:100%; }
        .inventory-pagination-footer { justify-content:center; text-align:center; }
        .progga-pagination { justify-content:center; }
    }
</style>
<script>
(() => {
    const inventoryIndexUrl = {{ \Illuminate\Support\Js::from($indexUrl) }};
    let searchTimer = null;
    let requestController = null;

    const getSearchInput = () => document.getElementById('inventorySearch');
    const getSearchClear = () => document.getElementById('inventorySearchClear');
    const getListContent = () => document.getElementById('inventoryListContent');
    const getFilterForm = () => document.getElementById('inventoryFilterForm');

    function updateSearchClear(){
        const input = getSearchInput();
        const clear = getSearchClear();
        if(input && clear) clear.style.display = input.value.length ? 'inline-flex' : 'none';
    }

    function buildUrl(page = 1){
        const url = new URL(inventoryIndexUrl, window.location.origin);
        const form = getFilterForm();
        if(form){
            const formData = new FormData(form);
            formData.forEach((value, key) => {
                const normalized = typeof value === 'string' ? value.trim() : value;
                if(normalized !== '') url.searchParams.set(key, normalized);
            });
        }

        const search = getSearchInput()?.value.trim() || '';
        if(search) url.searchParams.set('search', search);
        if(page > 1) url.searchParams.set('page', String(page));
        return url;
    }

    async function loadList(page = 1){
        const content = getListContent();
        if(!content) return;
        const url = buildUrl(page);

        if(requestController) requestController.abort();
        const activeController = new AbortController();
        requestController = activeController;
        content.classList.add('is-loading');

        try{
            const response = await fetch(url.toString(), {
                method:'GET',
                headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html'},
                signal:activeController.signal
            });
            if(!response.ok) throw new Error('Unable to load inventory list.');

            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const nextContent = doc.getElementById('inventoryListContent');
            if(!nextContent) throw new Error('Inventory list container was not found.');

            content.innerHTML = nextContent.innerHTML;
            const nextCount = doc.getElementById('inventoryResultCount');
            const count = document.getElementById('inventoryResultCount');
            if(nextCount && count) count.textContent = nextCount.textContent;
            history.replaceState({}, '', url.pathname + url.search);
        }catch(error){
            if(error.name !== 'AbortError') console.error(error);
        }finally{
            if(requestController === activeController) content.classList.remove('is-loading');
        }
    }

    function init(){
        const input = getSearchInput();
        const clear = getSearchClear();
        const content = getListContent();
        const form = getFilterForm();

        updateSearchClear();

        input?.addEventListener('input', () => {
            updateSearchClear();
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadList(1), 300);
        });

        clear?.addEventListener('click', () => {
            input.value = '';
            updateSearchClear();
            input.focus();
            loadList(1);
        });

        form?.addEventListener('submit', event => {
            event.preventDefault();
            loadList(1);
        });

        content?.addEventListener('click', event => {
            const link = event.target.closest('a[data-inventory-page]');
            if(!link || link.classList.contains('disabled')) return;
            event.preventDefault();
            loadList(Number(link.dataset.inventoryPage || 1));
        });
    }

    window.inventoryConfirmDelete = function(button){
        const form = button.closest('form');
        const itemName = button.dataset.deleteName || 'this record';
        Swal.fire({
            title:'Delete this record?',
            text:`Delete ${itemName}? This action cannot be undone.`,
            icon:'warning',
            showCancelButton:true,
            confirmButtonColor:'#dc3545',
            cancelButtonColor:'#6c757d',
            confirmButtonText:'Yes, delete it!',
            cancelButtonText:'Cancel',
            allowOutsideClick:false
        }).then(result => {
            if(result.isConfirmed) form.submit();
        });
    };

    if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
</script>
