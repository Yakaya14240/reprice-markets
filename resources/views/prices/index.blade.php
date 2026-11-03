@extends('layouts.app')

@section('title', 'Prix')

@push('styles')
<style>
    .prices-layout { display:grid; grid-template-columns: 1.1fr .9fr; gap:1rem; }
    @media (max-width: 960px) { .prices-layout { grid-template-columns: 1fr; } }
    .toolbar { display:flex; gap:.6rem; flex-wrap:wrap; align-items:end; margin-bottom:.8rem; }
    .toolbar label { margin:0; font-weight:600; font-size:.85rem; color:var(--muted); }
    .toolbar input, .toolbar select { margin:0; padding:.55rem .65rem; border:1px solid var(--border); border-radius:8px; }
    #product-list { max-height:62vh; overflow:auto; border:1px solid var(--border); border-radius:10px; }
    .product-row {
        display:block; width:100%; text-align:left; border:0; border-bottom:1px solid var(--border);
        background:#fff; padding:.75rem .85rem; cursor:pointer;
    }
    .product-row:hover, .product-row.active { background:#eefbf5; }
    .product-row .sku { font-weight:700; }
    .pending-box { border:1px dashed #94a3b8; border-radius:10px; padding:.8rem; background:#f8fafc; }
    .pending-item { display:flex; justify-content:space-between; gap:.5rem; padding:.35rem 0; border-bottom:1px solid #e2e8f0; font-size:.92rem; }
    .mkp-list label { font-weight:500; margin:.25rem 0; }
    .detail-empty { color:var(--muted); padding:1rem 0; }
    .price-grid { display:grid; gap:.65rem; }
    .price-row { display:grid; grid-template-columns: 1fr 110px 110px auto; gap:.5rem; align-items:center; }
    @media (max-width: 700px) { .price-row { grid-template-columns: 1fr; } }
    .history { max-height:220px; overflow:auto; margin-top:.8rem; }
    .history table { width:100%; border-collapse:collapse; font-size:.9rem; }
    .history th, .history td { border-bottom:1px solid var(--border); padding:.4rem; text-align:left; }
</style>
@endpush

@section('content')
    <h1>Prix</h1>
    <p class="muted">Recherchez un produit, éditez les prix, puis publiez les modifications en attente.</p>

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

        <div class="prices-layout">
            <div class="card">
                <div class="toolbar">
                    <div style="flex:1;min-width:220px;">
                        <label for="search">Recherche SKU / nom</label>
                        <input id="search" type="search" placeholder="Ex. SKU ou titre…" style="width:100%;">
                    </div>
                </div>
                <div id="product-list"></div>
                <div class="muted" id="list-meta" style="margin-top:.5rem;"></div>
            </div>

            <div>
                <div class="card" id="detail-card">
                    <div class="detail-empty">Sélectionnez un produit pour voir / éditer ses prix.</div>
                </div>

                <div class="card pending-box">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap;">
                        <strong>Modifications en attente</strong>
                        <span class="muted" id="pending-count">{{ $pending->count() }}</span>
                    </div>
                    <div id="pending-list" style="margin:.6rem 0;">
                        @forelse ($pending as $change)
                            <div class="pending-item" data-id="{{ $change->id }}">
                                <span>{{ $change->sku }} · {{ $change->csv_column }} : {{ $change->old_price }} → <strong>{{ $change->new_price }}</strong></span>
                            </div>
                        @empty
                            <div class="muted">Aucune modification.</div>
                        @endforelse
                    </div>

                    <div class="mkp-list">
                        <div class="actions" style="margin-bottom:.4rem;">
                            <strong>Marketplaces</strong>
                            <button class="btn ghost" type="button" id="mkp-all">Tout cocher</button>
                            <button class="btn ghost" type="button" id="mkp-none">Tout décocher</button>
                        </div>
                        @foreach ($marketplaces as $marketplace)
                            <label>
                                <input type="checkbox" class="mkp-check" value="{{ $marketplace->id }}" checked style="width:auto;margin-right:.35rem;">
                                {{ $marketplace->name }}
                            </label>
                        @endforeach
                    </div>

                    <button class="btn" type="button" id="btn-publish" style="margin-top:.9rem;width:100%;">
                        Publier (sync flux + API marketplaces)
                    </button>
                </div>
            </div>
        </div>
    @endif
@endsection

@if($feed)
@push('scripts')
<script>
(() => {
    const feedId = {{ $feed->id }};
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const bootRefresh = @json($bootRefresh);
    const endpoints = {
        status: @json(route('api.feeds.status', $feed)),
        refresh: @json(route('api.feeds.refresh', $feed)),
        products: @json(route('api.products.index', $feed)),
        product: (rowId) => @json(route('api.products.show', [$feed, 0])).replace(/\/0$/, '/' + encodeURIComponent(rowId)),
        price: (rowId) => @json(route('api.products.prices', [$feed, 0])).replace(/\/0\/prices$/, '/' + encodeURIComponent(rowId) + '/prices'),
        pending: @json(route('api.pending.index', $feed)),
        publish: @json(route('api.publish.store', $feed)),
        publishStatus: @json(route('api.publish.status')),
    };

    const listEl = document.getElementById('product-list');
    const metaEl = document.getElementById('list-meta');
    const detailEl = document.getElementById('detail-card');
    const pendingList = document.getElementById('pending-list');
    const pendingCount = document.getElementById('pending-count');
    const loadBar = document.getElementById('load-bar');
    const loadLabel = document.getElementById('load-label');
    const lock = document.getElementById('ui-lock');
    const lockLabel = document.getElementById('ui-lock-label');
    const lockBar = document.getElementById('ui-lock-bar');

    let offset = 0;
    let loadingList = false;
    let hasMore = true;
    let activeRowId = null;
    let searchTimer = null;

    const api = (url, options = {}) => fetch(url, {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
            ...(options.body instanceof FormData ? {} : {'Content-Type': 'application/json'}),
        },
        ...options,
    }).then(async r => {
        const data = await r.json().catch(() => ({}));
        if (!r.ok) {
            throw new Error(data.message || data.error || `Erreur application HTTP ${r.status} (${url})`);
        }
        return data;
    });

    function setLoadProgress(pct, label) {
        loadBar.style.width = Math.max(0, Math.min(100, pct)) + '%';
        if (label) loadLabel.textContent = label;
    }

    async function pollFeedStatus() {
        const data = await api(endpoints.status);
        setLoadProgress(data.progress || 0, data.label || data.status);
        return data;
    }

    async function refreshFeed() {
        setLoadProgress(5, 'Téléchargement du flux distant…');
        document.getElementById('btn-refresh').disabled = true;
        try {
            const result = await api(endpoints.refresh, { method: 'POST', body: '{}' });
            setLoadProgress(result.progress || 100, result.label || 'Flux chargé');
            await resetAndLoad();
        } catch (e) {
            setLoadProgress(100, e.message);
        } finally {
            document.getElementById('btn-refresh').disabled = false;
        }
    }

    async function waitUntilReady() {
        for (let i = 0; i < 60; i++) {
            const s = await pollFeedStatus();
            if (s.status === 'ready') return true;
            if (s.status === 'failed') {
                setLoadProgress(100, s.error || 'Échec chargement');
                return false;
            }
            await new Promise(r => setTimeout(r, 1000));
        }
        return false;
    }

    function renderProducts(products, append = false) {
        if (!append) listEl.innerHTML = '';
        products.forEach(p => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'product-row' + (String(p.id) === String(activeRowId) ? ' active' : '');
            btn.dataset.rowId = String(p.id);
            btn.innerHTML = `<div class="sku">${escapeHtml(p.sku || ('#' + p.id))}</div><div class="muted">${escapeHtml(p.name || '')}</div>`;
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                openProduct(p.id);
            });
            listEl.appendChild(btn);
        });
    }

    async function loadMore(reset = false) {
        if (loadingList) return;
        if (reset) { offset = 0; hasMore = true; }
        if (!hasMore) return;
        loadingList = true;
        const q = document.getElementById('search').value.trim();
        try {
            const data = await api(`${endpoints.products}?offset=${offset}&limit=40&q=${encodeURIComponent(q)}`);
            if (data.status !== 'ready') {
                metaEl.textContent = data.label || 'Chargement du flux…';
                return;
            }
            renderProducts(data.products || [], !reset);
            offset += (data.products || []).length;
            hasMore = !!data.has_more;
            metaEl.textContent = `${data.total} produit(s)` + (hasMore ? ' — scroll pour charger plus' : '');
        } catch (e) {
            metaEl.textContent = e.message;
        } finally {
            loadingList = false;
        }
    }

    async function resetAndLoad() {
        await pollFeedStatus();
        await loadMore(true);
    }

    async function openProduct(rowId) {
        activeRowId = rowId;
        [...listEl.querySelectorAll('.product-row')].forEach(el => {
            el.classList.toggle('active', el.dataset.rowId === String(rowId));
        });
        detailEl.innerHTML = '<div class="muted">Chargement…</div>';
        try {
            const data = await api(endpoints.product(rowId));
            detailEl.innerHTML = `
                <h2 style="margin-top:0;">${escapeHtml(data.sku || ('#' + data.id))}</h2>
                <p class="muted">${escapeHtml(data.name || '')}</p>
                <div class="price-grid" id="price-grid"></div>
                <h3>Historique</h3>
                <div class="history">
                    <table>
                        <thead><tr><th>Quand</th><th>Colonne</th><th>Ancien</th><th>Nouveau</th><th>Qui</th></tr></thead>
                        <tbody>
                            ${(data.history || []).map(h => `
                                <tr>
                                    <td>${escapeHtml(formatDate(h.at))}</td>
                                    <td>${escapeHtml(h.csv_column)}</td>
                                    <td>${escapeHtml(h.old_price ?? '')}</td>
                                    <td>${escapeHtml(h.new_price ?? '')}</td>
                                    <td>${escapeHtml(h.user || '')}</td>
                                </tr>
                            `).join('') || '<tr><td colspan="5" class="muted">Aucun historique.</td></tr>'}
                        </tbody>
                    </table>
                </div>
            `;
            const grid = document.getElementById('price-grid');
            (data.prices || []).forEach(price => {
                const row = document.createElement('div');
                row.className = 'price-row';
                const value = price.pending_price ?? price.current_price ?? '';
                const currency = [price.currency_code, price.currency_symbol].filter(Boolean).join(' ');
                row.innerHTML = `
                    <div><strong>${escapeHtml(price.name)}</strong><div class="muted">${escapeHtml(price.csv_column)}${currency ? ' · ' + escapeHtml(currency) : ''}</div></div>
                    <div class="muted">Actuel: ${escapeHtml(price.current_price ?? '')}${price.currency_symbol ? ' ' + escapeHtml(price.currency_symbol) : ''}</div>
                    <input type="number" step="0.01" min="0.01" value="${escapeHtml(value)}" data-column="${escapeHtml(price.csv_column)}">
                    <button class="btn ghost" type="button">OK</button>
                `;
                row.querySelector('button').addEventListener('click', async () => {
                    const input = row.querySelector('input');
                    try {
                        await api(endpoints.price(rowId), {
                            method: 'POST',
                            body: JSON.stringify({ csv_column: price.csv_column, new_price: input.value }),
                        });
                        await refreshPending();
                    } catch (e) {
                        alert(e.message);
                    }
                });
                grid.appendChild(row);
            });
        } catch (e) {
            detailEl.innerHTML = `<div class="muted">${escapeHtml(e.message)}</div>`;
        }
    }

    async function refreshPending() {
        const data = await api(endpoints.pending);
        const items = data.pending || [];
        pendingCount.textContent = items.length;
        if (!items.length) {
            pendingList.innerHTML = '<div class="muted">Aucune modification.</div>';
            return;
        }
        pendingList.innerHTML = items.map(i => `
            <div class="pending-item">
                <span>${escapeHtml(i.sku)} · ${escapeHtml(i.csv_column)} : ${escapeHtml(i.old_price ?? '')} → <strong>${escapeHtml(i.new_price)}</strong></span>
            </div>
        `).join('');
    }

    function selectedMarketplaces() {
        return [...document.querySelectorAll('.mkp-check:checked')].map(el => Number(el.value));
    }

    function setUiLock(active, label = '', progress = 0) {
        lock.classList.toggle('active', active);
        lockLabel.textContent = label || 'Traitement…';
        lockBar.style.width = progress + '%';
        document.querySelectorAll('button, input, select').forEach(el => {
            if (el.closest('#ui-lock')) return;
            el.disabled = active;
        });
    }

    async function publish() {
        const marketplaceIds = selectedMarketplaces();
        if (!marketplaceIds.length) {
            alert('Cochez au moins une marketplace.');
            return;
        }
        setUiLock(true, 'Démarrage…', 2);
        try {
            await api(endpoints.publish, {
                method: 'POST',
                body: JSON.stringify({ marketplace_ids: marketplaceIds }),
            });
            const status = await api(endpoints.publishStatus);
            setUiLock(true, status.label || 'Terminé', status.progress || 100);
            await refreshPending();
            if (activeRowId !== null) await openProduct(activeRowId);
            setTimeout(() => setUiLock(false), 600);
        } catch (e) {
            setUiLock(true, e.message, 100);
            setTimeout(() => setUiLock(false), 1200);
        }
    }

    function escapeHtml(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function formatDate(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        return d.toLocaleString('fr-FR');
    }

    listEl.addEventListener('scroll', () => {
        if (listEl.scrollTop + listEl.clientHeight >= listEl.scrollHeight - 40) {
            loadMore(false);
        }
    });
    document.getElementById('search').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadMore(true), 250);
    });
    document.getElementById('btn-refresh').addEventListener('click', refreshFeed);
    document.getElementById('btn-publish').addEventListener('click', publish);
    document.getElementById('mkp-all').addEventListener('click', () => {
        document.querySelectorAll('.mkp-check').forEach(el => el.checked = true);
    });
    document.getElementById('mkp-none').addEventListener('click', () => {
        document.querySelectorAll('.mkp-check').forEach(el => el.checked = false);
    });

    (async () => {
        if (bootRefresh) {
            setLoadProgress(5, 'Mise à jour des infos à la connexion…');
            // Jobs already dispatched at login; wait for ready then load.
            const ready = await waitUntilReady();
            if (!ready) {
                // Fallback sync refresh if async worker is not running.
                await refreshFeed();
                return;
            }
            await resetAndLoad();
            return;
        }
        const s = await pollFeedStatus();
        if (s.status !== 'ready') {
            await refreshFeed();
        } else {
            await resetAndLoad();
        }
    })();
})();
</script>
@endpush
@endif
