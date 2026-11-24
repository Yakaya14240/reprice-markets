@extends('layouts.app')

@section('title', 'Fiches')

@push('styles')
<style>
    .shell { max-width: min(1400px, 100%); }
    .sheets-layout { display:grid; grid-template-columns: 1fr; gap:1rem; }
    .sheets-layout.is-detail #list-card { display:none; }
    .sheets-layout.is-detail #detail-card { display:block; }
    #detail-card { display:none; }
    .toolbar { display:flex; gap:.6rem; flex-wrap:wrap; align-items:end; margin-bottom:.8rem; }
    .toolbar label { margin:0; font-weight:600; font-size:.85rem; color:var(--muted); }
    .toolbar input, .toolbar select { margin:0; padding:.55rem .65rem; border:1px solid var(--border); border-radius:8px; }
    #product-list { max-height:70vh; overflow:auto; border:1px solid var(--border); border-radius:10px; }
    .product-row {
        display:block; width:100%; text-align:left; border:0; border-bottom:1px solid var(--border);
        background:#fff; padding:.75rem .85rem; cursor:pointer;
    }
    .product-row:hover, .product-row.active { background:#eefbf5; }
    .product-row .sku { font-weight:700; }
    .pending-bar {
        display:flex; gap:.65rem; flex-wrap:wrap; align-items:center;
        border:1px solid var(--border); border-radius:12px; padding:.55rem .7rem; background:#fff;
        margin-bottom:1rem;
    }
    .pending-summary {
        flex:1; min-width:220px; display:flex; gap:.5rem; align-items:center; flex-wrap:wrap;
        border:0; background:transparent; color:inherit; font:inherit; text-align:left;
        cursor:pointer; padding:.25rem .35rem; border-radius:8px;
    }
    .pending-summary:hover, .pending-summary.is-open { background:var(--accent-soft); }
    .pending-chip {
        display:inline-flex; align-items:center; gap:.3rem;
        background:#f8fafc; border:1px solid var(--border); border-radius:999px;
        padding:.2rem .65rem; font-size:.82rem; white-space:nowrap;
    }
    .pending-chip-others { background:#fff7ed; color:#9a3412; border-color:#fdba74; }
    .pending-queue { display:none; position:fixed; inset:0; z-index:1150; }
    .pending-queue.open { display:block; }
    .pending-queue-scrim { position:absolute; inset:0; background:rgba(15,23,32,.4); }
    .pending-queue-panel {
        position:absolute; top:.75rem; right:.75rem; bottom:.75rem;
        width:min(400px, calc(100vw - 1.5rem));
        background:#fff; border:1px solid var(--border); border-radius:12px;
        padding:1rem 1.1rem; overflow:auto; box-shadow:0 20px 50px rgba(0,0,0,.18);
        display:flex; flex-direction:column; gap:.85rem;
    }
    .pending-queue-list { list-style:none; margin:0; padding:0; border:1px solid var(--border); border-radius:10px; overflow:hidden; }
    .pending-queue-list li {
        display:flex; justify-content:space-between; gap:.5rem; align-items:center;
        padding:.7rem .8rem; border-bottom:1px solid var(--border); font-size:.9rem;
    }
    .pending-queue-list li:last-child { border-bottom:0; }
    .pending-queue-list .btn-remove {
        border:0; background:transparent; color:var(--danger); font-weight:700;
        font-size:1.15rem; line-height:1; cursor:pointer;
    }
    .sheet-head {
        display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; flex-wrap:wrap;
        margin-bottom:1rem;
    }
    .sheet-head h2 { margin:0 0 .25rem; }
    .sheet-lang { min-width:140px; margin:0; }
    .sheet-section {
        border:1px solid var(--border); border-radius:12px; padding:1rem 1rem .35rem;
        margin-bottom:1rem; background:#fff;
    }
    .sheet-section-title {
        margin:0 0 .85rem; font-size:1rem; font-weight:800; color:var(--text);
        padding-bottom:.55rem; border-bottom:1px solid var(--border);
    }
    .sheet-field {
        border:1px solid var(--border); border-radius:10px; padding:.85rem; margin-bottom:.75rem; background:#f8fafc;
    }
    .sheet-section .sheet-field:last-child { margin-bottom:.85rem; }
    .sheet-field.is-pending { background:#f0fdf8; }
    .sheet-field.has-colleague { background:#fff7ed; }
    .sheet-field label {
        margin-bottom:.2rem; display:flex; align-items:center; gap:.4rem; flex-wrap:wrap;
    }
    .sheet-field .help-tip {
        position:relative; display:inline-flex; align-items:center; justify-content:center;
        width:1.15rem; height:1.15rem; border-radius:999px; border:1px solid var(--border);
        background:#f8fafc; color:var(--muted); font-size:.7rem; font-weight:800;
        cursor:help; padding:0; line-height:1; flex:0 0 auto;
    }
    .sheet-field .help-tip:hover, .sheet-field .help-tip:focus-visible {
        color:var(--accent); border-color:var(--accent); background:var(--accent-soft); outline:none;
    }
    .sheet-field .help-tip::after {
        content:attr(data-tip);
        position:absolute; left:50%; bottom:calc(100% + .45rem); transform:translateX(-50%);
        width:min(280px, 70vw); padding:.55rem .7rem; border-radius:8px;
        background:#1f2933; color:#fff; font-size:.78rem; font-weight:500; line-height:1.35;
        text-align:left; white-space:pre-wrap; box-shadow:0 10px 24px rgba(15,23,32,.2);
        opacity:0; visibility:hidden; pointer-events:none; z-index:20;
    }
    .sheet-field .help-tip::before {
        content:""; position:absolute; left:50%; bottom:calc(100% + .2rem); transform:translateX(-50%);
        border:6px solid transparent; border-top-color:#1f2933;
        opacity:0; visibility:hidden; pointer-events:none; z-index:20;
    }
    .sheet-field .help-tip:hover::after, .sheet-field .help-tip:focus-visible::after,
    .sheet-field .help-tip:hover::before, .sheet-field .help-tip:focus-visible::before {
        opacity:1; visibility:visible;
    }
    .sheet-field .readonly {
        white-space:pre-wrap; line-height:1.45; padding:.55rem .65rem;
        background:#f8fafc; border:1px solid var(--border); border-radius:8px;
    }
    .sheet-field .readonly.html { white-space:normal; }
    .sheet-field textarea { min-height:110px; margin-bottom:.35rem; }
    .sheet-field .html-editor-host { margin-bottom:.35rem; }
    .sheet-field .html-editor-host textarea { min-height:280px; }
    .sheet-field .char-counter {
        display:flex; justify-content:flex-end; margin:.25rem 0 .15rem;
        font-size:.8rem; color:var(--muted); font-variant-numeric:tabular-nums;
    }
    .sheet-field .char-counter.is-near { color:var(--warn); font-weight:600; }
    .sheet-field .char-counter.is-over { color:var(--danger); font-weight:700; }
    .badge-pending {
        display:inline-block; margin-left:.35rem; padding:.1rem .4rem; border-radius:999px;
        background:var(--accent-soft); color:#03543f; font-size:.7rem; font-weight:700;
    }
    .badge-colleague {
        display:inline-block; margin-left:.35rem; padding:.1rem .4rem; border-radius:999px;
        background:#fff7ed; color:#9a3412; font-size:.7rem; font-weight:700;
    }
    .colleague-hint { margin:.35rem 0 0; font-size:.8rem; color:#9a3412; }
    .sheet-images { display:flex; flex-wrap:wrap; gap:.5rem; margin-top:.35rem; }
    .sheet-images img {
        width:72px; height:72px; object-fit:contain; border:1px solid var(--border);
        border-radius:8px; background:#fff;
    }
    .detail-actions { display:flex; gap:.5rem; flex-wrap:wrap; justify-content:flex-end; margin:1rem 0 0; }
    #publish-modal, #conflict-modal {
        display:none; position:fixed; inset:0; z-index:1200;
        background:rgba(15,23,32,.45); align-items:center; justify-content:center; padding:1rem;
    }
    #publish-modal.open, #conflict-modal.open { display:flex; }
    #publish-modal .panel, #conflict-modal .panel {
        background:#fff; border-radius:12px; width:min(520px,96vw); max-height:90vh; overflow:auto;
        padding:1.1rem 1.25rem; box-shadow:0 20px 50px rgba(0,0,0,.2);
    }
    #conflict-modal .conflict-list {
        list-style:none; margin:.75rem 0 0; padding:0; max-height:240px; overflow:auto;
        border:1px solid var(--border); border-radius:8px;
    }
    #conflict-modal .conflict-list li { padding:.55rem .75rem; border-bottom:1px solid var(--border); font-size:.9rem; }
    #conflict-modal .conflict-list li:last-child { border-bottom:0; }
    .panel-actions { display:flex; gap:.5rem; justify-content:flex-end; flex-wrap:wrap; margin-top:1rem; }
    #publish-modal textarea {
        width:100%; min-height:88px; resize:vertical; margin:0;
        font:inherit; padding:.65rem .75rem; border:1px solid var(--border); border-radius:8px;
    }
</style>
@endpush

@section('content')
    <h1>Fiches</h1>
    <p class="muted">Recherchez un produit, éditez les champs configurés par langue, puis publiez vers le flux.</p>

    @if(! $feed)
        <div class="card">Aucun flux actif. Configurez-en un dans l’admin.</div>
    @else
        <div class="card" id="load-card">
            <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;align-items:center;">
                <div>
                    <strong>Flux :</strong> {{ $feed->name }}
                    <div class="muted" id="load-label">En attente du chargement…</div>
                </div>
                <button class="btn ghost" type="button" id="btn-refresh">Rafraîchir</button>
            </div>
            <div class="progress" style="margin-top:.75rem;"><span id="load-bar"></span></div>
        </div>

        <div class="pending-bar" id="pending-bar">
            <button type="button" class="pending-summary" id="pending-summary" aria-expanded="false" aria-controls="pending-queue">
                <strong><span id="pending-count">{{ $pending->count() }}</span> en attente</strong>
                <span class="pending-items" id="pending-chips">
                    @forelse ($pending->take(2) as $change)
                        <span class="pending-chip">{{ $change->sku }} · {{ $change->field?->label ?? 'Champ' }}</span>
                    @empty
                        <span class="muted" id="pending-empty">Aucune modification.</span>
                    @endforelse
                    @if ($pending->count() > 2)
                        <span class="pending-chip">+{{ $pending->count() - 2 }}</span>
                    @endif
                </span>
            </button>
            <button class="btn" type="button" id="btn-publish" @disabled(! $canWrite || $pending->isEmpty())>Publier</button>
        </div>

        <div class="pending-queue" id="pending-queue">
            <div class="pending-queue-scrim" id="pending-queue-scrim"></div>
            <aside class="pending-queue-panel" role="dialog" aria-modal="true" aria-labelledby="pending-queue-title">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;">
                    <h2 id="pending-queue-title" style="margin:0;font-size:1.1rem;">File d’attente fiche</h2>
                    <button class="btn ghost" type="button" id="pending-queue-close">Fermer</button>
                </div>
                <ul class="pending-queue-list" id="pending-list">
                    @forelse ($pending as $change)
                        <li data-id="{{ $change->id }}">
                            <span>
                                <strong>{{ $change->sku }}</strong>
                                · {{ $change->field?->label ?? 'Champ' }}
                            </span>
                            <button type="button" class="btn-remove" data-pending-id="{{ $change->id }}" title="Retirer" aria-label="Retirer">×</button>
                        </li>
                    @empty
                        <li class="muted" id="pending-list-empty">Aucune modification.</li>
                    @endforelse
                </ul>
                <div id="pending-others" hidden>
                    <div style="font-weight:700;margin:0 0 .25rem;">Chez les collègues</div>
                    <ul class="pending-queue-list" id="pending-others-list"></ul>
                </div>
                <p class="muted" style="margin:0;font-size:.82rem;">
                    Sync : <strong>{{ $writeDestination }}</strong>
                    @unless($canWrite)
                        <span style="color:var(--danger);"> — non configurable</span>
                    @endunless
                </p>
                <p class="muted" style="margin:0;font-size:.82rem;">Publication fiche = sync CSV (sans push marketplace).</p>
                <button class="btn" type="button" id="btn-publish-panel" style="width:100%;" @disabled(! $canWrite || $pending->isEmpty())>
                    Publier
                </button>
            </aside>
        </div>

        <div class="sheets-layout" id="sheets-layout">
            <div class="card" id="list-card">
                <div class="toolbar">
                    <div style="flex:1;min-width:180px;">
                        <label for="search">Recherche SKU / nom</label>
                        <input id="search" type="search" placeholder="Ex. SKU ou titre…" style="width:100%;">
                    </div>
                </div>
                <div id="product-list"></div>
                <div class="muted" id="list-meta" style="margin-top:.5rem;"></div>
            </div>

            <div class="card" id="detail-card">
                <div class="muted">Sélectionnez un produit.</div>
            </div>
        </div>

        <div id="publish-modal" role="dialog" aria-modal="true">
            <div class="panel">
                <h3 style="margin:0 0 .35rem;">Confirmer la publication</h3>
                <p class="muted" id="publish-modal-summary" style="margin:0 0 .9rem;"></p>
                <label for="publish-note">Note (optionnel)</label>
                <textarea id="publish-note" maxlength="500" placeholder="Ex. maj description IT…"></textarea>
                <div class="panel-actions">
                    <button class="btn secondary" type="button" id="publish-modal-cancel">Annuler</button>
                    <button class="btn" type="button" id="publish-modal-confirm">Publier</button>
                </div>
            </div>
        </div>

        <div id="conflict-modal" role="dialog" aria-modal="true">
            <div class="panel">
                <h3 style="margin:0 0 .35rem;">Conflits avec d’autres opérateurs</h3>
                <p class="muted" id="conflict-modal-hint" style="margin:0;">Des collègues ont aussi des modifications en attente.</p>
                <ul class="conflict-list" id="conflict-modal-list"></ul>
                <div class="panel-actions">
                    <button class="btn secondary" type="button" id="conflict-modal-cancel">Annuler</button>
                    <button class="btn" type="button" id="conflict-modal-confirm">Continuer quand même</button>
                </div>
            </div>
        </div>
    @endif
@endsection

@if($feed)
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tinymce@7.6.1/tinymce.min.js" referrerpolicy="origin"></script>
<script>
(() => {
    const feedId = {{ $feed->id }};
    const bootRefresh = @json($bootRefresh);
    const canWrite = @json($canWrite);
    const defaultLang = @json($languages->first()?->code ?? 'FR');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const endpoints = {
        status: @json(route('api.feeds.status', $feed)),
        refresh: @json(route('api.feeds.refresh', $feed)),
        products: @json(route('api.products.index', $feed)),
        sheet: (row, lang) => @json(url('/api/feeds/'.$feed->id.'/sheet')).replace(/\/$/, '') + '/' + row + '?lang=' + encodeURIComponent(lang),
        fields: (row) => @json(url('/api/feeds/'.$feed->id.'/sheet')).replace(/\/$/, '') + '/' + row + '/fields',
        pending: @json(route('api.pending.index', $feed)),
        pendingDestroy: (id) => @json(url('/api/feeds/'.$feed->id.'/pending-sheet')).replace(/\/$/, '') + '/' + id,
        publish: @json(route('api.publish.store', $feed)),
        publishStatus: @json(route('api.publish.status')),
    };

    const layout = document.getElementById('sheets-layout');
    const listEl = document.getElementById('product-list');
    const listMeta = document.getElementById('list-meta');
    const detailCard = document.getElementById('detail-card');
    const searchInput = document.getElementById('search');
    const loadLabel = document.getElementById('load-label');
    const loadBar = document.getElementById('load-bar');
    const pendingCount = document.getElementById('pending-count');
    const pendingChips = document.getElementById('pending-chips');
    const pendingList = document.getElementById('pending-list');
    const pendingOthers = document.getElementById('pending-others');
    const pendingOthersList = document.getElementById('pending-others-list');
    const pendingSummary = document.getElementById('pending-summary');
    const pendingQueue = document.getElementById('pending-queue');
    const btnPublish = document.getElementById('btn-publish');
    const btnPublishPanel = document.getElementById('btn-publish-panel');
    const publishModal = document.getElementById('publish-modal');
    const publishModalSummary = document.getElementById('publish-modal-summary');
    const publishNoteInput = document.getElementById('publish-note');
    const conflictModal = document.getElementById('conflict-modal');
    const conflictModalList = document.getElementById('conflict-modal-list');
    const conflictModalHint = document.getElementById('conflict-modal-hint');
    const lock = document.getElementById('ui-lock');
    const lockLabel = document.getElementById('ui-lock-label');
    const lockBar = document.getElementById('ui-lock-bar');

    let activeRowId = null;
    let activeLang = defaultLang;
    let htmlEditors = {};
    let offset = 0;
    let hasMore = false;
    let loadingList = false;
    let conflictMode = null;
    let conflictPayload = null;
    let colleaguePollTimer = null;
    let colleaguePollInFlight = false;
    let lastOthersFingerprint = '';
    const COLLEAGUE_POLL_MS = 10000;

    async function api(url, options = {}) {
        const res = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            ...options,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            const err = new Error(data.message || 'Erreur');
            err.status = res.status;
            err.data = data;
            throw err;
        }
        return data;
    }

    function escapeHtml(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[c]));
    }

    function showToast(message, type = 'error') {
        window.showToast?.(message, type);
    }

    function setLoadProgress(progress, label) {
        loadBar.style.width = Math.max(0, Math.min(100, Number(progress) || 0)) + '%';
        loadLabel.textContent = label || '';
    }

    async function waitReady() {
        for (let i = 0; i < 120; i++) {
            const data = await api(endpoints.status);
            setLoadProgress(data.progress || 0, data.label || data.status || '');
            if (data.status === 'ready') return true;
            if (data.status === 'failed') {
                showToast(data.label || 'Échec du chargement du flux.', 'error');
                return false;
            }
            await new Promise(r => setTimeout(r, 800));
        }
        return false;
    }

    async function loadProducts({ reset = false } = {}) {
        if (loadingList) return;
        loadingList = true;
        if (reset) {
            offset = 0;
            listEl.innerHTML = '';
        }
        try {
            const q = searchInput.value.trim();
            const url = new URL(endpoints.products, window.location.origin);
            url.searchParams.set('offset', String(offset));
            url.searchParams.set('limit', '40');
            if (q) url.searchParams.set('q', q);
            const data = await api(url.toString());
            hasMore = !!data.has_more;
            const products = data.products || [];
            products.forEach(p => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'product-row' + (activeRowId === p.id ? ' active' : '');
                btn.dataset.id = String(p.id);
                btn.innerHTML = `<div class="sku">${escapeHtml(p.sku || '—')}</div><div class="muted">${escapeHtml(p.name || '')}</div>`;
                btn.addEventListener('click', () => openProduct(p.id));
                listEl.appendChild(btn);
            });
            offset += products.length;
            listMeta.textContent = `${data.total || 0} produit(s)` + (hasMore ? ' — faites défiler pour charger plus' : '');
        } catch (e) {
            showToast(e.message, 'error');
        } finally {
            loadingList = false;
        }
    }

    listEl?.addEventListener('scroll', () => {
        if (!hasMore || loadingList) return;
        if (listEl.scrollTop + listEl.clientHeight >= listEl.scrollHeight - 80) {
            loadProducts();
        }
    });

    let searchTimer = null;
    searchInput?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadProducts({ reset: true }), 250);
    });

    function destroyHtmlEditors() {
        Object.keys(htmlEditors).forEach((fieldId) => {
            const editorId = `html-editor-${fieldId}`;
            const editor = window.tinymce?.get(editorId);
            if (editor) {
                editor.remove();
            }
        });
        htmlEditors = {};
    }

    function closeDetail() {
        activeRowId = null;
        destroyHtmlEditors();
        layout.classList.remove('is-detail');
        detailCard.innerHTML = '<div class="muted">Sélectionnez un produit.</div>';
        listEl.querySelectorAll('.product-row.active').forEach(el => el.classList.remove('active'));
    }

    function fieldDisplayValue(field) {
        return field.pending_value !== null && field.pending_value !== undefined
            ? String(field.pending_value)
            : String(field.current_value ?? '');
    }

    function renderImagesPreview(value) {
        const urls = String(value || '').split(/[|;,]/).map(s => s.trim()).filter(Boolean);
        if (!urls.length) return '<span class="muted">Aucune image</span>';
        return `<div class="sheet-images">${urls.map(u => `<img src="${escapeHtml(u)}" alt="" loading="lazy" onerror="this.style.display='none'">`).join('')}</div>`;
    }

    function charCount(value) {
        return Array.from(String(value ?? '')).length;
    }

    function charCounterMarkup(fieldId, maxLength, value = '') {
        if (!maxLength) return '';
        const current = charCount(value);
        return `<div class="char-counter" data-char-counter="${fieldId}" data-max="${Number(maxLength)}">${current} / ${Number(maxLength)}</div>`;
    }

    function updateCharCounter(fieldId, value) {
        const el = detailCard.querySelector(`[data-char-counter="${fieldId}"]`);
        if (!el) return;
        const max = Number(el.dataset.max || 0);
        if (!max) return;
        const current = charCount(value);
        el.textContent = `${current} / ${max}`;
        el.classList.toggle('is-near', current >= Math.floor(max * 0.9) && current <= max);
        el.classList.toggle('is-over', current > max);
    }

    function bindCharCounters() {
        detailCard.querySelectorAll('[data-field-input]').forEach((input) => {
            const fieldId = input.getAttribute('data-field-input');
            const counter = detailCard.querySelector(`[data-char-counter="${fieldId}"]`);
            if (!counter) return;
            const refresh = () => updateCharCounter(fieldId, input.value);
            if (input.dataset.charCounterBound !== '1') {
                input.dataset.charCounterBound = '1';
                input.addEventListener('input', refresh);
                input.addEventListener('change', refresh);
            }
            refresh();
        });
    }

    function renderGroupedFields(fields, groups) {
        const sections = Array.isArray(groups) && groups.length
            ? groups
            : [{
                name: 'Autres',
                fields: fields || [],
            }];

        return sections.map((section) => {
            const sectionFields = section.fields || [];
            if (!sectionFields.length) return '';
            const body = sectionFields.map(renderField).join('');
            return `<section class="sheet-section">
                <h3 class="sheet-section-title">${escapeHtml(section.name || 'Autres')}</h3>
                ${body}
            </section>`;
        }).join('') || '<p class="muted">Aucun champ configuré pour cette langue.</p>';
    }

    function renderField(field) {
        const value = fieldDisplayValue(field);
        const badges = [
            field.pending_value !== null && field.pending_value !== undefined ? '<span class="badge-pending">en attente</span>' : '',
            (field.others_pending || []).length ? '<span class="badge-colleague">collègue</span>' : '',
        ].join('');
        const helpTip = field.help_text
            ? `<button type="button" class="help-tip" aria-label="Aide mémoire" data-tip="${escapeHtml(field.help_text)}">?</button>`
            : '';
        const colleague = (field.others_pending || []).length
            ? `<p class="colleague-hint">${escapeHtml((field.others_pending || []).map(o => `${o.user_name}: ${String(o.new_value || '').slice(0, 80)}`).join(' · '))}</p>`
            : '';
        const counter = charCounterMarkup(field.id, field.max_length, value);
        const classes = ['sheet-field'];
        if (field.pending_value !== null && field.pending_value !== undefined) classes.push('is-pending');
        if ((field.others_pending || []).length) classes.push('has-colleague');

        if (!field.is_editable) {
            if (field.type === 'text_html') {
                return `<div class="${classes.join(' ')}" data-field-id="${field.id}">
                    <label><span>${escapeHtml(field.label)} <span class="muted">(${escapeHtml(field.type_label)})</span></span>${helpTip}${badges}</label>
                    <div class="readonly html">${value}</div>
                    ${counter}
                    ${colleague}
                </div>`;
            }
            if (field.type === 'images') {
                return `<div class="${classes.join(' ')}" data-field-id="${field.id}">
                    <label><span>${escapeHtml(field.label)}</span>${helpTip}${badges}</label>
                    ${renderImagesPreview(value)}
                    ${colleague}
                </div>`;
            }
            if (field.type === 'http_link' && value) {
                return `<div class="${classes.join(' ')}" data-field-id="${field.id}">
                    <label><span>${escapeHtml(field.label)}</span>${helpTip}${badges}</label>
                    <div class="readonly"><a href="${escapeHtml(value)}" target="_blank" rel="noopener">${escapeHtml(value)}</a></div>
                    ${colleague}
                </div>`;
            }
            return `<div class="${classes.join(' ')}" data-field-id="${field.id}">
                <label><span>${escapeHtml(field.label)}</span>${helpTip}${badges}</label>
                <div class="readonly">${escapeHtml(value)}</div>
                ${counter}
                ${colleague}
            </div>`;
        }

        if (field.type === 'text_html') {
            return `<div class="${classes.join(' ')}" data-field-id="${field.id}">
                <label><span>${escapeHtml(field.label)}</span>${helpTip}${badges}</label>
                <div class="html-editor-host">
                    <textarea
                        id="html-editor-${field.id}"
                        data-field-input="${field.id}"
                        data-baseline="${escapeHtml(value)}"
                        data-max-length="${field.max_length ? Number(field.max_length) : ''}"
                    >${escapeHtml(value)}</textarea>
                </div>
                ${counter}
                ${colleague}
            </div>`;
        }

        if (field.type === 'images') {
            return `<div class="${classes.join(' ')}" data-field-id="${field.id}">
                <label><span>${escapeHtml(field.label)}</span>${helpTip}${badges}</label>
                <textarea data-field-input="${field.id}" data-baseline="${escapeHtml(value)}" placeholder="URLs séparées par | , ou ;">${escapeHtml(value)}</textarea>
                ${renderImagesPreview(value)}
                ${colleague}
            </div>`;
        }

        if (field.type === 'http_link') {
            return `<div class="${classes.join(' ')}" data-field-id="${field.id}">
                <label><span>${escapeHtml(field.label)}</span>${helpTip}${badges}</label>
                <input type="url" data-field-input="${field.id}" data-baseline="${escapeHtml(value)}" value="${escapeHtml(value)}" placeholder="https://…">
                ${colleague}
            </div>`;
        }

        const maxAttr = field.max_length ? ` maxlength="${Number(field.max_length)}"` : '';
        if (field.type === 'text_line') {
            return `<div class="${classes.join(' ')}" data-field-id="${field.id}">
                <label><span>${escapeHtml(field.label)}</span>${helpTip}${badges}</label>
                <input type="text" data-field-input="${field.id}" data-baseline="${escapeHtml(value)}" value="${escapeHtml(value)}"${maxAttr}>
                ${counter}
                ${colleague}
            </div>`;
        }

        return `<div class="${classes.join(' ')}" data-field-id="${field.id}">
            <label><span>${escapeHtml(field.label)}</span>${helpTip}${badges}</label>
            <textarea data-field-input="${field.id}" data-baseline="${escapeHtml(value)}"${maxAttr}>${escapeHtml(value)}</textarea>
            ${counter}
            ${colleague}
        </div>`;
    }

    function getHtmlFieldValue(fieldId) {
        const editorId = `html-editor-${fieldId}`;
        const editor = window.tinymce?.get(editorId);
        if (editor) {
            return editor.getContent({ format: 'html' });
        }
        return detailCard.querySelector(`[data-field-input="${fieldId}"]`)?.value ?? '';
    }

    function mountHtmlEditors(fields) {
        destroyHtmlEditors();
        if (typeof window.tinymce === 'undefined') {
            showToast('Éditeur HTML indisponible (TinyMCE non chargé).', 'error');
            return;
        }

        (fields || []).filter(f => f.is_editable && f.type === 'text_html').forEach(field => {
            const editorId = `html-editor-${field.id}`;
            const textarea = document.getElementById(editorId);
            if (!textarea) return;

            const raw = fieldDisplayValue(field);
            textarea.value = raw;

            window.tinymce.init({
                selector: `#${editorId}`,
                base_url: 'https://cdn.jsdelivr.net/npm/tinymce@7.6.1',
                suffix: '.min',
                promotion: false,
                branding: false,
                menubar: 'edit view insert format table tools',
                plugins: 'code lists link table image media fullscreen searchreplace visualblocks autoresize',
                toolbar: 'undo redo | styles | bold italic underline strikethrough | alignleft aligncenter alignright | bullist numlist outdent indent | link table image | code fullscreen',
                height: 420,
                min_height: 280,
                resize: true,
                // Conserve le HTML marketplace (tables, div, attributs inline, etc.).
                valid_elements: '*[*]',
                extended_valid_elements: '*[*]',
                invalid_elements: '',
                valid_children: '+body[style],+div[*],+span[*],+td[*],+th[*],+tr[*],+table[*]',
                verify_html: false,
                cleanup: false,
                convert_urls: false,
                relative_urls: false,
                remove_script_host: false,
                entity_encoding: 'raw',
                remove_trailing_brs: false,
                indent: false,
                content_style: 'body { font-family: Segoe UI, Tahoma, sans-serif; font-size: 14px; } table { border-collapse: collapse; width: 100%; } td, th { border: 1px solid #ccc; padding: 4px 6px; }',
                setup: (editor) => {
                    htmlEditors[field.id] = editorId;
                    const sync = () => {
                        const html = editor.getContent({ format: 'html' });
                        textarea.value = html;
                        updateCharCounter(field.id, html);
                    };
                    editor.on('init', () => {
                        editor.setContent(raw, { format: 'raw' });
                        sync();
                    });
                    editor.on('change keyup SetContent Undo Redo', sync);
                },
            });
        });
    }

    async function openProduct(rowId, lang = activeLang) {
        activeRowId = rowId;
        activeLang = lang || activeLang;
        layout.classList.add('is-detail');
        listEl.querySelectorAll('.product-row').forEach(el => {
            el.classList.toggle('active', Number(el.dataset.id) === Number(rowId));
        });
        destroyHtmlEditors();
        detailCard.innerHTML = '<div class="muted">Chargement…</div>';
        try {
            const data = await api(endpoints.sheet(rowId, activeLang));
            const langs = data.languages || [];
            const langOptions = langs.map(l =>
                `<option value="${escapeHtml(l.code)}" ${l.code === (data.language?.code || activeLang) ? 'selected' : ''}>${escapeHtml(l.code)} — ${escapeHtml(l.name)}</option>`
            ).join('');
            const allFields = data.fields || [];
            const fieldsHtml = renderGroupedFields(allFields, data.groups || []);
            const editableCount = allFields.filter(f => f.is_editable).length;
            destroyHtmlEditors();
            detailCard.innerHTML = `
                <div class="sheet-head">
                    <div>
                        <button class="btn ghost" type="button" id="btn-back">← Retour</button>
                        <h2>${escapeHtml(data.name || 'Produit')}</h2>
                        <div class="muted">${escapeHtml(data.sku || '')}</div>
                    </div>
                    <div>
                        <label for="sheet-lang" style="margin:0 0 .25rem;">Langue</label>
                        <select id="sheet-lang" class="sheet-lang">${langOptions}</select>
                    </div>
                </div>
                ${fieldsHtml}
                <div class="detail-actions">
                    <button class="btn" type="button" id="btn-validate-fields" ${editableCount ? '' : 'disabled'}>Valider les champs</button>
                </div>
            `;
            document.getElementById('btn-back')?.addEventListener('click', closeDetail);
            document.getElementById('sheet-lang')?.addEventListener('change', (e) => {
                openProduct(rowId, e.target.value);
            });
            document.getElementById('btn-validate-fields')?.addEventListener('click', () => saveFields(false));
            bindCharCounters();
            mountHtmlEditors(allFields);
            // Resync counters after layout (évite 0/N si la valeur n’était pas encore lue).
            requestAnimationFrame(() => bindCharCounters());
        } catch (e) {
            detailCard.innerHTML = `<div class="error">${escapeHtml(e.message)}</div>`;
        }
    }

    function collectDirtyFields() {
        const items = [];
        detailCard.querySelectorAll('[data-field-input]').forEach(input => {
            const id = Number(input.dataset.fieldId || input.getAttribute('data-field-input'));
            const value = htmlEditors[id] ? getHtmlFieldValue(id) : input.value;
            if (htmlEditors[id]) {
                input.value = value;
            }
            const baseline = input.dataset.baseline ?? '';
            if (String(value) !== String(baseline)) {
                items.push({ product_sheet_field_id: id, new_value: value });
            }
        });
        return items;
    }

    async function saveFields(confirmConflicts) {
        if (activeRowId === null) return;
        const fields = collectDirtyFields();
        if (!fields.length) {
            showToast('Aucune modification.', 'warn');
            return;
        }
        try {
            await api(endpoints.fields(activeRowId), {
                method: 'POST',
                body: JSON.stringify({ fields, confirm_conflicts: !!confirmConflicts }),
            });
            showToast(`${fields.length} champ(s) mis en attente.`, 'success');
            await refreshPending();
            await openProduct(activeRowId, activeLang);
        } catch (e) {
            if (e.status === 409 && e.data?.code === 'pending_conflicts') {
                openConflictModal('save', e.data);
                return;
            }
            showToast(e.message, 'error');
        }
    }

    function renderPendingChips(items, othersCount) {
        if (!items.length && !othersCount) {
            pendingChips.innerHTML = '<span class="muted" id="pending-empty">Aucune modification.</span>';
            return;
        }
        const chips = items.slice(0, 2).map(item =>
            `<span class="pending-chip">${escapeHtml(item.sku)} · ${escapeHtml(item.field_label || 'Champ')}</span>`
        );
        if (items.length > 2) chips.push(`<span class="pending-chip">+${items.length - 2}</span>`);
        if (othersCount) chips.push(`<span class="pending-chip pending-chip-others">${othersCount} chez collègues</span>`);
        pendingChips.innerHTML = chips.join('');
    }

    function renderPendingList(items) {
        if (!items.length) {
            pendingList.innerHTML = '<li class="muted" id="pending-list-empty">Aucune modification.</li>';
            return;
        }
        pendingList.innerHTML = items.map(item => `
            <li data-id="${item.id}">
                <span><strong>${escapeHtml(item.sku)}</strong> · ${escapeHtml(item.field_label || 'Champ')}</span>
                <button type="button" class="btn-remove" data-pending-id="${item.id}" title="Retirer" aria-label="Retirer">×</button>
            </li>
        `).join('');
    }

    function renderOthersPending(others) {
        if (!others.length) {
            pendingOthers.hidden = true;
            pendingOthersList.innerHTML = '';
            return;
        }
        pendingOthers.hidden = false;
        pendingOthersList.innerHTML = others.map(item => `
            <li>
                <span><strong>${escapeHtml(item.user_name)}</strong> · ${escapeHtml(item.sku)} · ${escapeHtml(item.field_label || 'Champ')}</span>
            </li>
        `).join('');
    }

    function syncPublishButtons(count) {
        const disabled = !canWrite || count === 0;
        btnPublish.disabled = disabled;
        btnPublishPanel.disabled = disabled;
        btnPublishPanel.textContent = `Publier ${count} modification${count > 1 ? 's' : ''}`;
    }

    function othersFingerprint(others) {
        return (others || []).map(o => `${o.id}:${o.updated_at || ''}`).join('|');
    }

    async function refreshPending({ quiet = false } = {}) {
        try {
            const data = await api(endpoints.pending);
            const items = data.pending_sheet || [];
            const others = data.others_sheet || [];
            lastOthersFingerprint = othersFingerprint(others);
            pendingCount.textContent = String(items.length);
            renderPendingChips(items, others.length);
            renderPendingList(items);
            renderOthersPending(others);
            syncPublishButtons(items.length);
            return data;
        } catch (e) {
            if (!quiet) throw e;
            return null;
        }
    }

    function openPendingQueue() {
        pendingQueue.classList.add('open');
        pendingSummary.classList.add('is-open');
        pendingSummary.setAttribute('aria-expanded', 'true');
    }
    function closePendingQueue() {
        pendingQueue.classList.remove('open');
        pendingSummary.classList.remove('is-open');
        pendingSummary.setAttribute('aria-expanded', 'false');
    }

    async function removePending(id) {
        try {
            await api(endpoints.pendingDestroy(id), { method: 'DELETE', body: '{}' });
            await refreshPending();
            if (activeRowId !== null) await openProduct(activeRowId, activeLang);
            showToast('Modification retirée.', 'success');
        } catch (e) {
            showToast(e.message, 'error');
        }
    }

    function openConflictModal(mode, data) {
        conflictMode = mode;
        conflictPayload = data;
        const conflicts = [...(data.conflicts || []), ...(data.sheet_conflicts || [])];
        conflictModalHint.textContent = data.message || 'Conflits détectés.';
        conflictModalList.innerHTML = conflicts.map(c => {
            if (c.product_sheet_field_id) {
                return `<li><strong>${escapeHtml(c.sku)}</strong> · ${escapeHtml(c.field_label || 'Champ')} — ${escapeHtml(c.other_user_name)}</li>`;
            }
            return `<li><strong>${escapeHtml(c.sku)}</strong> · ${escapeHtml(c.csv_column || '')} — ${escapeHtml(c.other_user_name)}</li>`;
        }).join('') || '<li class="muted">Aucun détail.</li>';
        conflictModal.classList.add('open');
    }

    function closeConflictModal() {
        conflictModal.classList.remove('open');
        conflictMode = null;
        conflictPayload = null;
    }

    function openPublishModal() {
        const count = Number(pendingCount.textContent || 0);
        if (!count) {
            showToast('Aucune modification en attente.', 'warn');
            return;
        }
        closePendingQueue();
        publishModalSummary.textContent = `${count} modification(s) fiche · sync CSV uniquement`;
        if (publishNoteInput) publishNoteInput.value = '';
        publishModal.classList.add('open');
    }

    function setUiLock(active, label = '', progress = 0) {
        lock.classList.toggle('active', active);
        lockLabel.textContent = label || 'Traitement…';
        lockBar.style.width = progress + '%';
    }

    async function pollPublishStatus() {
        for (let i = 0; i < 180; i++) {
            const status = await api(endpoints.publishStatus);
            setUiLock(true, status.label || 'Publication…', status.progress || 0);
            if (status.status === 'done' || status.status === 'partial') {
                setUiLock(false);
                return status;
            }
            if (status.status === 'failed') {
                setUiLock(false);
                throw new Error(status.label || 'Publication échouée');
            }
            await new Promise(r => setTimeout(r, 700));
        }
        setUiLock(false);
        throw new Error('Publication trop longue.');
    }

    async function doPublish(confirmConflicts = false) {
        publishModal.classList.remove('open');
        setUiLock(true, 'Démarrage…', 1);
        try {
            await api(endpoints.publish, {
                method: 'POST',
                body: JSON.stringify({
                    marketplace_ids: [],
                    note: publishNoteInput?.value || null,
                    confirm_conflicts: !!confirmConflicts,
                }),
            });
            const status = await pollPublishStatus();
            showToast(status.label || 'Publication terminée.', 'success');
            await refreshPending();
            if (activeRowId !== null) await openProduct(activeRowId, activeLang);
        } catch (e) {
            setUiLock(false);
            if (e.status === 409 && e.data?.code === 'pending_conflicts') {
                openConflictModal('publish', e.data);
                return;
            }
            showToast(e.message, 'error');
        }
    }

    pendingSummary?.addEventListener('click', () => {
        if (pendingQueue.classList.contains('open')) closePendingQueue();
        else openPendingQueue();
    });
    document.getElementById('pending-queue-close')?.addEventListener('click', closePendingQueue);
    document.getElementById('pending-queue-scrim')?.addEventListener('click', closePendingQueue);
    pendingList?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-pending-id]');
        if (btn) removePending(btn.getAttribute('data-pending-id'));
    });
    btnPublish?.addEventListener('click', openPublishModal);
    btnPublishPanel?.addEventListener('click', openPublishModal);
    document.getElementById('publish-modal-cancel')?.addEventListener('click', () => publishModal.classList.remove('open'));
    document.getElementById('publish-modal-confirm')?.addEventListener('click', () => doPublish(false));
    document.getElementById('conflict-modal-cancel')?.addEventListener('click', closeConflictModal);
    document.getElementById('conflict-modal-confirm')?.addEventListener('click', async () => {
        const mode = conflictMode;
        closeConflictModal();
        if (mode === 'save') await saveFields(true);
        if (mode === 'publish') await doPublish(true);
    });
    document.getElementById('btn-refresh')?.addEventListener('click', async () => {
        try {
            await api(endpoints.refresh, { method: 'POST', body: '{}' });
            const ok = await waitReady();
            if (ok) await loadProducts({ reset: true });
        } catch (e) {
            showToast(e.message, 'error');
        }
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && layout.classList.contains('is-detail')) closeDetail();
    });

    async function pollColleagueAwareness() {
        if (colleaguePollInFlight || document.hidden || lock?.classList.contains('active')) return;
        colleaguePollInFlight = true;
        try {
            await refreshPending({ quiet: true });
        } finally {
            colleaguePollInFlight = false;
        }
    }

    function startColleaguePolling() {
        if (colleaguePollTimer !== null) clearInterval(colleaguePollTimer);
        colleaguePollTimer = window.setInterval(pollColleagueAwareness, COLLEAGUE_POLL_MS);
    }

    (async () => {
        try {
            if (bootRefresh) {
                await api(endpoints.refresh, { method: 'POST', body: '{}' });
            }
            const ok = await waitReady();
            if (!ok) return;
            await loadProducts({ reset: true });
            await refreshPending({ quiet: true });
            startColleaguePolling();
        } catch (e) {
            showToast(e.message, 'error');
        }
    })();
})();
</script>
@endpush
@endif
