@extends('layouts.app')

@section('title', 'Prix')

@push('styles')
<style>
    .prices-layout { display:grid; grid-template-columns: 1fr; gap:1rem; }
    .prices-layout.is-detail { grid-template-columns: minmax(200px, 28%) 1fr; }
    @media (max-width: 960px) {
        .prices-layout.is-detail { grid-template-columns: 1fr; }
    }
    .toolbar { display:flex; gap:.6rem; flex-wrap:wrap; align-items:end; margin-bottom:.8rem; }
    .toolbar label { margin:0; font-weight:600; font-size:.85rem; color:var(--muted); }
    .toolbar input, .toolbar select { margin:0; padding:.55rem .65rem; border:1px solid var(--border); border-radius:8px; }
    #product-list { max-height:62vh; overflow:auto; border:1px solid var(--border); border-radius:10px; }
    .prices-layout.is-detail #product-list { max-height:70vh; }
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
    .pending-summary:hover, .pending-summary:focus-visible, .pending-summary.is-open {
        background:var(--accent-soft); outline:none;
    }
    .pending-summary strong { white-space:nowrap; }
    .pending-items {
        display:flex; gap:.4rem; flex-wrap:wrap; align-items:center;
    }
    .pending-chip {
        display:inline-flex; align-items:center; gap:.3rem;
        background:#f8fafc; border:1px solid var(--border); border-radius:999px;
        padding:.2rem .65rem; font-size:.82rem; white-space:nowrap;
    }
    .pending-chip-more {
        color:var(--muted); font-weight:700;
    }
    .pending-queue {
        display:none; position:fixed; inset:0; z-index:1150;
    }
    .pending-queue.open { display:block; }
    .pending-queue-scrim {
        position:absolute; inset:0; background:rgba(15,23,32,.4);
    }
    .pending-queue-panel {
        position:absolute; top:.75rem; right:.75rem; bottom:.75rem;
        width:min(380px, calc(100vw - 1.5rem));
        background:#fff; border:1px solid var(--border); border-radius:12px;
        padding:1rem 1.1rem; overflow:auto;
        box-shadow:0 20px 50px rgba(0,0,0,.18);
        display:flex; flex-direction:column; gap:.85rem;
    }
    .pending-queue-head {
        display:flex; justify-content:space-between; align-items:center; gap:.5rem;
    }
    .pending-queue-head h2 { margin:0; font-size:1.1rem; }
    .pending-queue-list {
        list-style:none; margin:0; padding:0;
        border:1px solid var(--border); border-radius:10px; overflow:hidden;
    }
    .pending-queue-list li {
        display:flex; justify-content:space-between; gap:.5rem; align-items:center;
        padding:.7rem .8rem; border-bottom:1px solid var(--border); font-size:.9rem;
    }
    .pending-queue-list li:last-child { border-bottom:0; }
    .pending-queue-list .btn-remove {
        border:0; background:transparent; color:var(--danger); font-weight:700;
        font-size:1.15rem; line-height:1; cursor:pointer; padding:0 .2rem;
    }
    .pending-queue-section-title { font-weight:700; margin:0 0 .25rem; }
    .pending-queue-hint { margin:0 0 .55rem; font-size:.85rem; }
    .pending-mkp {
        display:grid; grid-template-columns:1fr 1fr; gap:.4rem .75rem; margin-bottom:.55rem;
    }
    .pending-mkp label {
        display:flex; gap:.4rem; align-items:center; margin:0; font-weight:500; font-size:.9rem;
    }
    .pending-mkp-actions { display:flex; gap:.4rem; flex-wrap:wrap; margin-bottom:.35rem; }
    .pending-sync { margin:0; font-size:.82rem; }
    .pending-queue-publish { width:100%; }
    .detail-empty { color:var(--muted); padding:1rem 0; }
    .detail-panel { display:none; }
    .prices-layout.is-detail .detail-panel { display:block; }
    .prices-layout.is-detail .list-only-hint { display:none; }
    .identity-card {
        display:grid; grid-template-columns: minmax(180px, 260px) 1fr; gap:1rem;
        margin-bottom:1rem; padding:1rem; border:1px solid var(--border); border-radius:12px; background:#f8fafc;
    }
    @media (max-width: 700px) { .identity-card { grid-template-columns: 1fr; } }
    .carousel { position:relative; background:#fff; border:1px solid var(--border); border-radius:10px; overflow:hidden; aspect-ratio:1; }
    .carousel img { width:100%; height:100%; object-fit:contain; display:block; background:#fff; }
    .carousel .ph {
        display:flex; align-items:center; justify-content:center; height:100%;
        color:var(--muted); font-size:.9rem; padding:1rem; text-align:center;
    }
    .carousel-nav {
        position:absolute; inset:auto 0 0 0; display:flex; justify-content:space-between; align-items:center;
        padding:.4rem .5rem; background:linear-gradient(transparent, rgba(15,23,32,.35));
    }
    .carousel-nav button {
        border:0; background:rgba(255,255,255,.9); color:var(--text); border-radius:999px;
        width:1.8rem; height:1.8rem; cursor:pointer; font-weight:700;
    }
    .carousel-dots { display:flex; gap:.3rem; justify-content:center; flex:1; }
    .carousel-dots span {
        width:.4rem; height:.4rem; border-radius:999px; background:rgba(255,255,255,.55);
    }
    .carousel-dots span.on { background:#fff; }
    .identity-copy h2 { margin:0 0 .35rem; font-size:1.25rem; }
    .identity-copy .sku-line { color:var(--muted); font-size:.9rem; margin-bottom:.65rem; }
    .identity-copy .desc {
        color:var(--text); font-size:.95rem; line-height:1.45; max-height:9.5rem; overflow:auto;
        white-space:pre-wrap;
    }
    .price-table-wrap { overflow-x:auto; margin:.35rem 0 .75rem; border:1px solid var(--border); border-radius:10px; }
    .price-table { width:100%; border-collapse:collapse; font-size:.92rem; min-width:420px; }
    .price-table th {
        text-align:left; font-size:.75rem; text-transform:uppercase; letter-spacing:.03em;
        color:var(--muted); font-weight:700; padding:.55rem .75rem; background:#f8fafc;
        border-bottom:1px solid var(--border);
    }
    .price-table td { padding:.55rem .75rem; border-bottom:1px solid var(--border); vertical-align:middle; }
    .price-table tr:last-child td { border-bottom:0; }
    .price-table tr.is-pending { background:#f0fdf8; }
    .price-table tr.is-dirty { background:#fffbeb; }
    .price-table .market-name { font-weight:700; }
    .price-table .market-meta { font-size:.78rem; color:var(--muted); font-weight:500; }
    .price-table .num { font-variant-numeric:tabular-nums; white-space:nowrap; }
    .price-table .current { color:var(--muted); }
    .price-table .edit-cell { display:flex; align-items:center; gap:.4rem; min-width:140px; }
    .price-table .edit-cell input[type="number"] {
        width:7.5rem; margin:0; padding:.4rem .5rem; font-variant-numeric:tabular-nums;
    }
    .price-table .currency-affix { color:var(--muted); font-size:.85rem; font-weight:600; }
    .price-table .row-error { display:none; color:var(--danger); font-size:.8rem; margin-top:.25rem; }
    .badge-pending {
        display:inline-block; margin-left:.35rem; padding:.1rem .4rem; border-radius:999px;
        background:var(--accent-soft); color:#03543f; font-size:.7rem; font-weight:700;
        vertical-align:middle;
    }
    .badge-colleague {
        display:inline-block; margin-left:.35rem; padding:.1rem .4rem; border-radius:999px;
        background:#fff7ed; color:#9a3412; font-size:.7rem; font-weight:700;
        vertical-align:middle;
    }
    .colleague-hint {
        margin:.35rem 0 0; font-size:.8rem; color:#9a3412; line-height:1.35;
    }
    .price-table tr.has-colleague { background:#fff7ed; }
    .pending-others {
        margin-top:.85rem; padding-top:.75rem; border-top:1px solid var(--border);
    }
    .pending-others-list {
        list-style:none; margin:.35rem 0 0; padding:0; max-height:180px; overflow:auto;
    }
    .pending-others-list li {
        padding:.45rem 0; border-bottom:1px solid var(--border); font-size:.88rem;
        display:flex; justify-content:space-between; gap:.5rem; align-items:flex-start;
    }
    .pending-others-list li:last-child { border-bottom:0; }
    .pending-chip-others {
        background:#fff7ed; color:#9a3412; border:1px solid #fdba74;
    }
    .detail-actions { display:flex; gap:.5rem; flex-wrap:wrap; justify-content:flex-end; margin:.35rem 0 1rem; }
    .history-head { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin:1rem 0 .35rem; }
    .history-head h3 { margin:0; }
    .btn-chart {
        border:1px solid var(--border); background:#fff; color:var(--accent);
        border-radius:8px; width:2rem; height:2rem; padding:0;
        display:inline-flex; align-items:center; justify-content:center; cursor:pointer;
    }
    .btn-chart:hover { background:var(--accent-soft); }
    .btn-chart:disabled { opacity:.45; cursor:not-allowed; }
    .btn-chart svg { width:1.05rem; height:1.05rem; display:block; }
    .history { max-height:220px; overflow:auto; margin-top:.4rem; }
    .history table { width:100%; border-collapse:collapse; font-size:.9rem; }
    .history th, .history td { border-bottom:1px solid var(--border); padding:.4rem; text-align:left; }
    #price-chart-modal, #publish-modal, #conflict-modal {
        display:none; position:fixed; inset:0; z-index:1200;
        background:rgba(15,23,32,.45); align-items:center; justify-content:center; padding:1rem;
    }
    #price-chart-modal.open, #publish-modal.open, #conflict-modal.open { display:flex; }
    #price-chart-modal .panel, #publish-modal .panel, #conflict-modal .panel {
        background:#fff; border-radius:12px; max-height:90vh; overflow:auto;
        padding:1.1rem 1.25rem 1.25rem; box-shadow:0 20px 50px rgba(0,0,0,.2);
    }
    #price-chart-modal .panel { width:min(720px,96vw); }
    #publish-modal .panel, #conflict-modal .panel { width:min(520px,96vw); }
    #conflict-modal .conflict-list {
        list-style:none; margin:.75rem 0 0; padding:0; max-height:240px; overflow:auto;
        border:1px solid var(--border); border-radius:8px;
    }
    #conflict-modal .conflict-list li {
        padding:.55rem .75rem; border-bottom:1px solid var(--border); font-size:.9rem;
    }
    #conflict-modal .conflict-list li:last-child { border-bottom:0; }
    #price-chart-modal .panel-head {
        display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:.85rem;
    }
    #price-chart-modal .panel-head h3 { margin:0; }
    #price-chart-modal .chart-toolbar { display:flex; gap:.6rem; flex-wrap:wrap; align-items:end; margin-bottom:.75rem; }
    #price-chart-modal .chart-toolbar label { margin:0; font-size:.8rem; color:var(--muted); }
    #price-chart-modal .chart-toolbar select { margin:0; min-width:180px; }
    #price-chart-svg-wrap {
        border:1px solid var(--border); border-radius:10px; background:#f8fafc; padding:.75rem; min-height:240px;
    }
    #price-chart-svg-wrap svg { width:100%; height:240px; display:block; }
    #price-chart-empty { display:none; color:var(--muted); padding:2rem 1rem; text-align:center; }
    #publish-modal .panel h3, #conflict-modal .panel h3 { margin:0 0 .35rem; }
    #publish-modal textarea {
        width:100%; min-height:88px; resize:vertical; margin:0;
        font:inherit; padding:.65rem .75rem; border:1px solid var(--border); border-radius:8px;
    }
    #publish-modal .panel-actions, #conflict-modal .panel-actions {
        display:flex; gap:.5rem; justify-content:flex-end; flex-wrap:wrap; margin-top:1rem;
    }
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

        <div class="pending-bar" id="pending-bar">
            <button type="button" class="pending-summary" id="pending-summary" aria-expanded="false" aria-controls="pending-queue">
                <strong><span id="pending-count">{{ $pending->count() }}</span> en attente</strong>
                <span class="pending-items" id="pending-chips">
                    @forelse ($pending->take(2) as $change)
                        @php($label = $priceColumnLabels[$change->csv_column] ?? $change->csv_column)
                        <span class="pending-chip">{{ $change->sku }} · {{ $label }} → {{ $change->new_price }}</span>
                    @empty
                        <span class="muted" id="pending-empty">Aucune modification.</span>
                    @endforelse
                    @if ($pending->count() > 2)
                        <span class="pending-chip pending-chip-more">+{{ $pending->count() - 2 }}</span>
                    @endif
                </span>
            </button>
            <button class="btn" type="button" id="btn-publish" @disabled(! $canWrite || $pending->isEmpty())>Publier</button>
        </div>

        <div class="pending-queue" id="pending-queue">
            <div class="pending-queue-scrim" id="pending-queue-scrim"></div>
            <aside class="pending-queue-panel" role="dialog" aria-modal="true" aria-labelledby="pending-queue-title">
                <div class="pending-queue-head">
                    <h2 id="pending-queue-title">File d’attente</h2>
                    <button class="btn ghost" type="button" id="pending-queue-close">Fermer</button>
                </div>

                <ul class="pending-queue-list" id="pending-list">
                    @forelse ($pending as $change)
                        @php($label = $priceColumnLabels[$change->csv_column] ?? $change->csv_column)
                        <li data-id="{{ $change->id }}">
                            <span>
                                <strong>{{ $change->sku }}</strong>
                                · {{ $label }}
                                · {{ $change->old_price }} → <strong>{{ $change->new_price }}</strong>
                            </span>
                            <button type="button" class="btn-remove" data-pending-id="{{ $change->id }}" title="Retirer" aria-label="Retirer">×</button>
                        </li>
                    @empty
                        <li class="muted" id="pending-list-empty">Aucune modification.</li>
                    @endforelse
                </ul>

                <div class="pending-others" id="pending-others" hidden>
                    <div class="pending-queue-section-title">Chez les collègues</div>
                    <p class="muted pending-queue-hint">Lecture seule — déjà en file chez d’autres opérateurs.</p>
                    <ul class="pending-others-list" id="pending-others-list"></ul>
                </div>

                <div>
                    <div class="pending-queue-section-title">Marketplaces</div>
                    <p class="muted pending-queue-hint">Aucune cochée = sync CSV uniquement.</p>
                    <div class="pending-mkp">
                        @foreach ($marketplaces as $marketplace)
                            <label>
                                <input type="checkbox" class="mkp-check" value="{{ $marketplace->id }}" checked style="width:auto;margin:0;">
                                {{ $marketplace->name }}
                            </label>
                        @endforeach
                    </div>
                    <div class="pending-mkp-actions">
                        <button class="btn ghost" type="button" id="mkp-all">Tout</button>
                        <button class="btn ghost" type="button" id="mkp-none">Aucun</button>
                    </div>
                </div>

                <p class="muted pending-sync" id="write-destination">
                    Sync : <strong>{{ $writeDestination }}</strong>
                    @unless($canWrite)
                        <span style="color:var(--danger);"> — non configurable</span>
                    @endunless
                </p>

                <button class="btn pending-queue-publish" type="button" id="btn-publish-panel" @disabled(! $canWrite || $pending->isEmpty())>
                    Publier {{ $pending->count() }} modification{{ $pending->count() > 1 ? 's' : '' }}
                </button>
            </aside>
        </div>

        <div class="prices-layout" id="prices-layout">
            <div class="card" id="list-card">
                <div class="toolbar">
                    <div style="flex:1;min-width:180px;">
                        <label for="search">Recherche SKU / nom</label>
                        <input id="search" type="search" placeholder="Ex. SKU ou titre…" style="width:100%;">
                    </div>
                </div>
                <div id="product-list"></div>
                <div class="muted" id="list-meta" style="margin-top:.5rem;"></div>
                <p class="muted list-only-hint" style="margin:.75rem 0 0;">Sélectionnez un produit pour ouvrir sa fiche.</p>
            </div>

            <div class="detail-panel">
                <div class="card" id="detail-card">
                    <div class="detail-empty">Sélectionnez un produit.</div>
                </div>
            </div>
        </div>

        <div id="publish-modal" role="dialog" aria-modal="true" aria-labelledby="publish-modal-title">
            <div class="panel">
                <h3 id="publish-modal-title">Confirmer la publication</h3>
                <p class="muted" id="publish-modal-summary" style="margin:0 0 .9rem;"></p>
                <label for="publish-note">Note (optionnel)</label>
                <textarea id="publish-note" maxlength="500" placeholder="Ex. alignement promo / correction erreur…"></textarea>
                <p class="muted" style="margin:.35rem 0 0;font-size:.8rem;">Visible ensuite sur Suivi au survol de la ligne de push.</p>
                <div class="panel-actions">
                    <button class="btn secondary" type="button" id="publish-modal-cancel">Annuler</button>
                    <button class="btn" type="button" id="publish-modal-confirm">Publier</button>
                </div>
            </div>
        </div>

        <div id="conflict-modal" role="dialog" aria-modal="true" aria-labelledby="conflict-modal-title">
            <div class="panel">
                <h3 id="conflict-modal-title">Conflits avec d’autres opérateurs</h3>
                <p class="muted" id="conflict-modal-hint" style="margin:0;">Des collègues ont aussi des modifications en attente sur les mêmes produits.</p>
                <ul class="conflict-list" id="conflict-modal-list"></ul>
                <div class="panel-actions">
                    <button class="btn secondary" type="button" id="conflict-modal-cancel">Annuler</button>
                    <button class="btn" type="button" id="conflict-modal-confirm">Continuer quand même</button>
                </div>
            </div>
        </div>

        <div id="price-chart-modal" role="dialog" aria-modal="true" aria-labelledby="price-chart-title">
            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h3 id="price-chart-title">Évolution du prix</h3>
                        <p class="muted" id="price-chart-subtitle" style="margin:.25rem 0 0;">50 derniers mouvements</p>
                    </div>
                    <button class="btn ghost" type="button" id="price-chart-close" aria-label="Fermer">Fermer</button>
                </div>
                <div class="chart-toolbar">
                    <div>
                        <label for="price-chart-column">Colonne</label>
                        <select id="price-chart-column"></select>
                    </div>
                </div>
                <div id="price-chart-svg-wrap">
                    <div id="price-chart-empty">Aucun mouvement pour cette colonne.</div>
                    <svg id="price-chart-svg" viewBox="0 0 640 240" preserveAspectRatio="none" aria-hidden="true"></svg>
                </div>
            </div>
        </div>
    @endif
@endsection

@if($feed)
@push('scripts')
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const bootRefresh = @json($bootRefresh);
    const endpoints = {
        status: @json(route('api.feeds.status', $feed)),
        refresh: @json(route('api.feeds.refresh', $feed)),
        products: @json(route('api.products.index', $feed)),
        product: (rowId) => @json(route('api.products.show', [$feed, 0])).replace(/\/0$/, '/' + encodeURIComponent(rowId)),
        pricesBatch: (rowId) => @json(route('api.products.prices.batch', [$feed, 0])).replace(/\/0\/prices\/batch$/, '/' + encodeURIComponent(rowId) + '/prices/batch'),
        pending: @json(route('api.pending.index', $feed)),
        pendingDestroy: (id) => @json(route('api.pending.destroy', [$feed, 0])).replace(/\/0$/, '/' + encodeURIComponent(id)),
        publish: @json(route('api.publish.store', $feed)),
        publishStatus: @json(route('api.publish.status')),
    };

    const layoutEl = document.getElementById('prices-layout');
    const listEl = document.getElementById('product-list');
    const metaEl = document.getElementById('list-meta');
    const detailEl = document.getElementById('detail-card');
    const pendingList = document.getElementById('pending-list');
    const pendingChips = document.getElementById('pending-chips');
    const pendingCount = document.getElementById('pending-count');
    const pendingSummary = document.getElementById('pending-summary');
    const pendingQueue = document.getElementById('pending-queue');
    const btnPublish = document.getElementById('btn-publish');
    const btnPublishPanel = document.getElementById('btn-publish-panel');
    const loadBar = document.getElementById('load-bar');
    const loadLabel = document.getElementById('load-label');
    const lock = document.getElementById('ui-lock');
    const lockLabel = document.getElementById('ui-lock-label');
    const lockBar = document.getElementById('ui-lock-bar');
    const chartModal = document.getElementById('price-chart-modal');
    const chartColumnSelect = document.getElementById('price-chart-column');
    const chartSvg = document.getElementById('price-chart-svg');
    const chartEmpty = document.getElementById('price-chart-empty');
    const chartSubtitle = document.getElementById('price-chart-subtitle');
    const publishModal = document.getElementById('publish-modal');
    const publishNoteInput = document.getElementById('publish-note');
    const publishModalSummary = document.getElementById('publish-modal-summary');
    const conflictModal = document.getElementById('conflict-modal');
    const conflictModalTitle = document.getElementById('conflict-modal-title');
    const conflictModalHint = document.getElementById('conflict-modal-hint');
    const conflictModalList = document.getElementById('conflict-modal-list');
    const conflictModalConfirm = document.getElementById('conflict-modal-confirm');
    const pendingOthersWrap = document.getElementById('pending-others');
    const pendingOthersList = document.getElementById('pending-others-list');
    const canWrite = @json($canWrite);

    let offset = 0;
    let loadingList = false;
    let hasMore = true;
    let activeRowId = null;
    let activeProductData = null;
    let carouselIndex = 0;
    let searchTimer = null;
    let pendingPublishNote = '';
    let conflictMode = 'publish';
    let pendingSaveContext = null;
    let colleaguePollTimer = null;
    let colleaguePollInFlight = false;
    let lastOthersFingerprint = '';
    const COLLEAGUE_POLL_MS = 10000;

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
            const err = new Error(data.message || data.error || `Erreur application HTTP ${r.status} (${url})`);
            err.status = r.status;
            err.payload = data;
            throw err;
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
            if (s.status === 'failed') return false;
            await new Promise(r => setTimeout(r, 1000));
        }
        return false;
    }

    function setDetailMode(on) {
        layoutEl.classList.toggle('is-detail', !!on);
        if (!on) {
            activeRowId = null;
            activeProductData = null;
            detailEl.innerHTML = '<div class="detail-empty">Sélectionnez un produit.</div>';
            [...listEl.querySelectorAll('.product-row')].forEach(el => el.classList.remove('active'));
        }
    }

    function renderProducts(products, append) {
        if (!append) listEl.innerHTML = '';
        products.forEach(p => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'product-row' + (String(activeRowId) === String(p.id) ? ' active' : '');
            btn.dataset.rowId = p.id;
            btn.innerHTML = `<div class="sku">${escapeHtml(p.sku)}</div><div class="muted">${escapeHtml(p.name || '')}</div>`;
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
        await refreshPending();
    }

    async function openProduct(rowId) {
        activeRowId = rowId;
        carouselIndex = 0;
        setDetailMode(true);
        [...listEl.querySelectorAll('.product-row')].forEach(el => {
            el.classList.toggle('active', el.dataset.rowId === String(rowId));
        });
        detailEl.innerHTML = '<div class="muted">Chargement…</div>';
        try {
            const data = await api(endpoints.product(rowId));
            activeProductData = data;
            const prices = data.prices || [];
            const images = data.images || [];
            const hasHistory = (data.history || []).length > 0;

            detailEl.innerHTML = `
                <div class="identity-card">
                    <div class="carousel" id="product-carousel">
                        ${images.length
                            ? `<img id="carousel-img" src="${escapeHtml(images[0])}" alt="">`
                            : `<div class="ph">Aucune image mappée</div>`}
                        ${images.length > 1 ? `
                            <div class="carousel-nav">
                                <button type="button" data-carousel="-1" aria-label="Précédente">‹</button>
                                <div class="carousel-dots" id="carousel-dots"></div>
                                <button type="button" data-carousel="1" aria-label="Suivante">›</button>
                            </div>` : ''}
                    </div>
                    <div class="identity-copy">
                        <h2>${escapeHtml(data.name || 'Sans titre')}</h2>
                        <div class="sku-line">${escapeHtml(data.sku || ('#' + data.id))}</div>
                        <div class="desc">${escapeHtml(data.description || 'Pas de description mappée.')}</div>
                    </div>
                </div>
                <div class="price-table-wrap">
                    <table class="price-table">
                        <thead>
                            <tr><th>Marché</th><th>Actuel</th><th>Nouveau</th></tr>
                        </thead>
                        <tbody id="price-grid"></tbody>
                    </table>
                </div>
                <div class="detail-actions">
                    <button class="btn secondary" type="button" id="btn-close-detail">Retour liste</button>
                    <button class="btn" type="button" id="btn-validate-prices" disabled>Valider les prix</button>
                </div>
                <div class="history-head">
                    <h3>Historique</h3>
                    <button class="btn-chart" type="button" id="btn-price-chart" ${hasHistory ? '' : 'disabled'} title="Évolution du prix" aria-label="Évolution du prix">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 6-8"/>
                        </svg>
                    </button>
                </div>
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

            if (images.length > 1) {
                renderCarouselDots(images.length);
                detailEl.querySelectorAll('[data-carousel]').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const dir = Number(btn.getAttribute('data-carousel'));
                        carouselIndex = (carouselIndex + dir + images.length) % images.length;
                        const img = document.getElementById('carousel-img');
                        if (img) img.src = images[carouselIndex];
                        renderCarouselDots(images.length);
                    });
                });
            }

            const grid = document.getElementById('price-grid');
            const validateBtn = document.getElementById('btn-validate-prices');
            if (!prices.length) {
                grid.innerHTML = '<tr><td colspan="3" class="muted">Aucune colonne prix active.</td></tr>';
            }

            const syncValidateState = () => {
                const dirty = [...grid.querySelectorAll('tr.is-dirty')].length > 0;
                validateBtn.disabled = !dirty;
            };

            prices.forEach(price => {
                const hasPending = price.pending_price !== null && price.pending_price !== undefined && price.pending_price !== '';
                const others = Array.isArray(price.others_pending) ? price.others_pending : [];
                const hasColleague = others.length > 0;
                const baseline = hasPending ? price.pending_price : price.current_price;
                const baselineStr = baseline === null || baseline === undefined ? '' : String(baseline);
                const symbol = price.currency_symbol || price.currency_code || '';
                const marketLabel = displayMarketName(price.name);
                const tip = [price.csv_column, price.currency_code].filter(Boolean).join(' · ');

                const row = document.createElement('tr');
                if (hasPending) row.classList.add('is-pending');
                if (hasColleague) row.classList.add('has-colleague');
                row.dataset.csvColumn = price.csv_column;
                row.innerHTML = `
                    <td>
                        <div class="market-name" title="${escapeHtml(tip)}">${escapeHtml(marketLabel)}${hasPending ? '<span class="badge-pending">en attente</span>' : ''}</div>
                        ${price.alias ? `<div class="market-meta">${escapeHtml(price.alias)}</div>` : ''}
                    </td>
                    <td class="num current">${escapeHtml(formatPrice(price.current_price, symbol))}</td>
                    <td>
                        <div class="edit-cell">
                            <input type="number" step="0.01" min="0.01" value="${escapeHtml(baselineStr)}" data-baseline="${escapeHtml(baselineStr)}" aria-label="Nouveau prix ${escapeHtml(marketLabel)}">
                            ${symbol ? `<span class="currency-affix">${escapeHtml(symbol)}</span>` : ''}
                        </div>
                        <div class="colleague-hint" hidden></div>
                        <div class="row-error" data-price-error></div>
                    </td>
                `;
                applyColleaguePendingToRow(row, others);
                const input = row.querySelector('input');
                const errorEl = row.querySelector('[data-price-error]');
                const syncDirty = () => {
                    const dirty = String(input.value ?? '').trim() !== String(input.dataset.baseline ?? '');
                    row.classList.toggle('is-dirty', dirty);
                    if (dirty) {
                        errorEl.style.display = 'none';
                        errorEl.textContent = '';
                        input.style.borderColor = '';
                    }
                    syncValidateState();
                };
                input.addEventListener('input', syncDirty);
                grid.appendChild(row);
                syncDirty();
            });

            document.getElementById('btn-close-detail')?.addEventListener('click', () => setDetailMode(false));
            document.getElementById('btn-price-chart')?.addEventListener('click', () => openPriceChartModal());
            validateBtn?.addEventListener('click', () => validateAllPrices(rowId, grid, validateBtn));
        } catch (e) {
            detailEl.innerHTML = `<div class="muted">${escapeHtml(e.message)}</div>`;
            showToast(e.message, 'error');
        }
    }

    function renderCarouselDots(total) {
        const dots = document.getElementById('carousel-dots');
        if (!dots) return;
        dots.innerHTML = Array.from({ length: total }, (_, i) => `<span class="${i === carouselIndex ? 'on' : ''}"></span>`).join('');
    }

    async function validateAllPrices(rowId, grid, validateBtn, confirmConflicts = false) {
        const dirtyRows = [...grid.querySelectorAll('tr.is-dirty')];
        if (!dirtyRows.length && !confirmConflicts) return;

        let prices = [];
        if (confirmConflicts && pendingSaveContext?.prices) {
            prices = pendingSaveContext.prices;
            rowId = pendingSaveContext.rowId;
            grid = pendingSaveContext.grid || grid;
            validateBtn = pendingSaveContext.validateBtn || validateBtn;
        } else {
            for (const row of dirtyRows) {
                const input = row.querySelector('input');
                const errorEl = row.querySelector('[data-price-error]');
                const raw = String(input.value ?? '').trim();
                const parsed = Number(raw);
                if (raw === '' || Number.isNaN(parsed) || parsed < 0.01) {
                    errorEl.textContent = 'Indiquez un prix ≥ 0,01.';
                    errorEl.style.display = 'block';
                    input.style.borderColor = 'var(--danger)';
                    input.focus();
                    return;
                }
                prices.push({ csv_column: row.dataset.csvColumn, new_price: raw });
            }
        }

        if (!prices.length) return;

        validateBtn.disabled = true;
        const previous = validateBtn.textContent;
        validateBtn.textContent = 'Validation…';
        try {
            const payload = { prices };
            if (confirmConflicts) payload.confirm_conflicts = true;
            await api(endpoints.pricesBatch(rowId), {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            pendingSaveContext = null;
            await refreshPending();
            showToast(`${prices.length} prix mis en attente.`, 'success');
            setDetailMode(false);
        } catch (e) {
            if (e.payload?.code === 'pending_conflicts') {
                pendingSaveContext = { rowId, grid, validateBtn, prices };
                validateBtn.disabled = false;
                validateBtn.textContent = previous;
                openConflictModal(e.payload.conflicts || [], 'save');
                return;
            }
            showToast(e.message, 'error');
            validateBtn.disabled = false;
        } finally {
            if (validateBtn.textContent === 'Validation…') {
                validateBtn.textContent = previous;
            }
        }
    }

    function marketLabel(item) {
        return item.market_label || displayMarketName(item.csv_column) || item.csv_column;
    }

    function syncPublishButtons(count) {
        const disabled = !canWrite || count < 1;
        if (btnPublish) {
            btnPublish.disabled = disabled;
        }
        if (btnPublishPanel) {
            btnPublishPanel.disabled = disabled;
            btnPublishPanel.textContent = count < 1
                ? 'Publier'
                : `Publier ${count} modification${count > 1 ? 's' : ''}`;
        }
    }

    function renderPendingChips(items, othersCount = 0) {
        if (!items.length && othersCount < 1) {
            pendingChips.innerHTML = '<span class="muted" id="pending-empty">Aucune modification.</span>';
            return;
        }
        const visible = items.slice(0, 2).map(i => `
            <span class="pending-chip">${escapeHtml(i.sku)} · ${escapeHtml(marketLabel(i))} → ${escapeHtml(i.new_price)}</span>
        `).join('');
        const more = items.length > 2
            ? `<span class="pending-chip pending-chip-more">+${items.length - 2}</span>`
            : '';
        const othersChip = othersCount > 0
            ? `<span class="pending-chip pending-chip-others">${othersCount} chez collègues</span>`
            : '';
        const emptyMine = !items.length
            ? '<span class="muted" id="pending-empty">Aucune modification.</span>'
            : '';
        pendingChips.innerHTML = emptyMine + visible + more + othersChip;
    }

    function renderPendingList(items) {
        if (!items.length) {
            pendingList.innerHTML = '<li class="muted" id="pending-list-empty">Aucune modification.</li>';
            return;
        }
        pendingList.innerHTML = items.map(i => `
            <li data-id="${escapeHtml(i.id)}">
                <span>
                    <strong>${escapeHtml(i.sku)}</strong>
                    · ${escapeHtml(marketLabel(i))}
                    · ${escapeHtml(i.old_price ?? '—')} → <strong>${escapeHtml(i.new_price)}</strong>
                </span>
                <button type="button" class="btn-remove" data-pending-id="${escapeHtml(i.id)}" title="Retirer" aria-label="Retirer">×</button>
            </li>
        `).join('');
    }

    function renderOthersPending(items) {
        if (!pendingOthersWrap || !pendingOthersList) return;
        if (!items.length) {
            pendingOthersWrap.hidden = true;
            pendingOthersList.innerHTML = '';
            return;
        }
        pendingOthersWrap.hidden = false;
        pendingOthersList.innerHTML = items.map(i => `
            <li>
                <span>
                    <strong>${escapeHtml(i.sku)}</strong>
                    · ${escapeHtml(marketLabel(i))}
                    · ${escapeHtml(i.old_price ?? '—')} → <strong>${escapeHtml(i.new_price)}</strong>
                    <div class="muted" style="font-size:.8rem;">${escapeHtml(i.user_name)}</div>
                </span>
            </li>
        `).join('');
    }

    function othersFingerprint(others) {
        return (others || [])
            .map(o => `${o.id}:${o.user_id}:${o.csv_column}:${o.new_price}:${o.updated_at || ''}`)
            .sort()
            .join('|');
    }

    function applyColleaguePendingToRow(row, others) {
        const list = Array.isArray(others) ? others : [];
        const hasColleague = list.length > 0;
        row.classList.toggle('has-colleague', hasColleague);

        const nameEl = row.querySelector('.market-name');
        if (nameEl) {
            nameEl.querySelectorAll('.badge-colleague').forEach(el => el.remove());
            list.forEach(o => {
                const badge = document.createElement('span');
                badge.className = 'badge-colleague';
                badge.title = `En attente chez ${o.user_name}`;
                badge.textContent = `${o.user_name} → ${o.new_price}`;
                nameEl.appendChild(badge);
            });
        }

        const hintEl = row.querySelector('.colleague-hint');
        if (hintEl) {
            if (!hasColleague) {
                hintEl.hidden = true;
                hintEl.textContent = '';
            } else {
                hintEl.hidden = false;
                hintEl.textContent = list.map(o => `${o.user_name} a ${o.new_price} en attente`).join(' · ');
            }
        }
    }

    async function patchOpenProductColleagues() {
        if (activeRowId === null) return;
        const grid = document.getElementById('price-grid');
        if (!grid) return;
        try {
            const data = await api(endpoints.product(activeRowId));
            const prices = data.prices || [];
            prices.forEach(price => {
                const row = [...grid.querySelectorAll('tr[data-csv-column]')]
                    .find(tr => tr.dataset.csvColumn === price.csv_column);
                if (!row) return;
                applyColleaguePendingToRow(row, price.others_pending || []);
            });
        } catch (_) {
            // Ignore transient product poll errors.
        }
    }

    async function refreshPending({ quiet = false, syncOpenProduct = true } = {}) {
        try {
            const data = await api(endpoints.pending);
            const items = data.pending || [];
            const others = data.others || [];
            const fingerprint = othersFingerprint(others);
            const othersChanged = fingerprint !== lastOthersFingerprint;
            lastOthersFingerprint = fingerprint;

            pendingCount.textContent = String(items.length);
            renderPendingChips(items, others.length);
            renderPendingList(items);
            renderOthersPending(others);
            syncPublishButtons(items.length);
            if (!items.length && !others.length) {
                closePendingQueue();
            }
            if (syncOpenProduct && othersChanged && activeRowId !== null) {
                await patchOpenProductColleagues();
            }
            return data;
        } catch (e) {
            if (!quiet) throw e;
            return null;
        }
    }

    function shouldPauseColleaguePolling() {
        return document.hidden
            || lock?.classList.contains('active')
            || conflictModal?.classList.contains('open')
            || publishModal?.classList.contains('open');
    }

    async function pollColleagueAwareness() {
        if (colleaguePollInFlight || shouldPauseColleaguePolling()) return;
        colleaguePollInFlight = true;
        try {
            await refreshPending({ quiet: true, syncOpenProduct: true });
        } finally {
            colleaguePollInFlight = false;
        }
    }

    function startColleaguePolling() {
        stopColleaguePolling();
        colleaguePollTimer = window.setInterval(() => {
            pollColleagueAwareness();
        }, COLLEAGUE_POLL_MS);
    }

    function stopColleaguePolling() {
        if (colleaguePollTimer !== null) {
            clearInterval(colleaguePollTimer);
            colleaguePollTimer = null;
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
            if (activeRowId !== null) await openProduct(activeRowId);
            showToast('Modification retirée.', 'success');
        } catch (e) {
            showToast(e.message, 'error');
        }
    }

    function displayMarketName(name) {
        const raw = String(name || '').trim();
        return raw.replace(/^prix\s+/i, '') || raw;
    }

    function formatPrice(value, symbol) {
        if (value === null || value === undefined || value === '') return '—';
        const text = String(value);
        return symbol ? `${text} ${symbol}` : text;
    }

    function openPriceChartModal() {
        if (!activeProductData || !chartModal) return;
        const prices = activeProductData.prices || [];
        const history = activeProductData.history || [];
        const columnsFromHistory = [...new Set(history.map(h => h.csv_column).filter(Boolean))];
        const options = prices.length
            ? prices.map(p => ({ value: p.csv_column, label: displayMarketName(p.name) || p.csv_column }))
            : columnsFromHistory.map(col => ({ value: col, label: col }));
        if (!options.length) {
            showToast('Aucune colonne prix à afficher.', 'warn');
            return;
        }
        const preferred = activeProductData.chart_default_csv_column || options[0].value;
        chartColumnSelect.innerHTML = options.map(opt => `
            <option value="${escapeHtml(opt.value)}" ${opt.value === preferred ? 'selected' : ''}>${escapeHtml(opt.label)}</option>
        `).join('');
        chartSubtitle.textContent = `${activeProductData.sku || ''} — 50 derniers mouvements`;
        chartModal.classList.add('open');
        renderPriceChart(chartColumnSelect.value);
    }

    function closePriceChartModal() {
        chartModal?.classList.remove('open');
    }

    function renderPriceChart(csvColumn) {
        const history = (activeProductData?.history || [])
            .filter(h => h.csv_column === csvColumn)
            .slice()
            .reverse();
        const points = history
            .map(h => ({ at: h.at ? new Date(h.at) : null, price: Number(h.new_price) }))
            .filter(p => p.at && !Number.isNaN(p.price));
        if (!points.length) {
            chartSvg.innerHTML = '';
            chartSvg.style.display = 'none';
            chartEmpty.style.display = 'block';
            return;
        }
        chartEmpty.style.display = 'none';
        chartSvg.style.display = 'block';
        const width = 640, height = 240;
        const pad = { top: 18, right: 16, bottom: 36, left: 48 };
        const innerW = width - pad.left - pad.right;
        const innerH = height - pad.top - pad.bottom;
        const values = points.map(p => p.price);
        let min = Math.min(...values), max = Math.max(...values);
        if (min === max) { min -= 1; max += 1; }
        const xAt = (i) => pad.left + (points.length === 1 ? innerW / 2 : (i / (points.length - 1)) * innerW);
        const yAt = (price) => pad.top + (1 - ((price - min) / (max - min))) * innerH;
        const path = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${xAt(i).toFixed(1)} ${yAt(p.price).toFixed(1)}`).join(' ');
        const dots = points.map((p, i) => `<circle cx="${xAt(i).toFixed(1)}" cy="${yAt(p.price).toFixed(1)}" r="3.5" fill="#0f6e56"><title>${escapeHtml(formatDate(p.at.toISOString()))} · ${escapeHtml(String(p.price))}</title></circle>`).join('');
        const yTicks = [min, (min + max) / 2, max].map(v => {
            const y = yAt(v);
            return `<line x1="${pad.left}" y1="${y}" x2="${width - pad.right}" y2="${y}" stroke="#e2e8f0" /><text x="${pad.left - 8}" y="${y + 4}" text-anchor="end" fill="#61707d" font-size="11">${v.toFixed(2)}</text>`;
        }).join('');
        const firstLabel = formatDate(points[0].at.toISOString()).split(' ')[0] || '';
        const lastLabel = formatDate(points[points.length - 1].at.toISOString()).split(' ')[0] || '';
        chartSvg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        chartSvg.innerHTML = `${yTicks}<path d="${path}" fill="none" stroke="#0f6e56" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />${dots}<text x="${pad.left}" y="${height - 10}" fill="#61707d" font-size="11">${escapeHtml(firstLabel)}</text><text x="${width - pad.right}" y="${height - 10}" text-anchor="end" fill="#61707d" font-size="11">${escapeHtml(lastLabel)}</text>`;
    }

    function selectedMarketplaces() {
        return [...document.querySelectorAll('.mkp-check:checked')].map(el => Number(el.value));
    }

    function setUiLock(active, label = '', progress = 0) {
        lock.classList.toggle('active', active);
        lockLabel.textContent = label || 'Traitement…';
        lockBar.style.width = progress + '%';
        document.querySelectorAll('button, input, select, textarea').forEach(el => {
            if (el.closest('#ui-lock')) return;
            el.disabled = active;
        });
    }

    function openPublishModal() {
        const pendingCountValue = Number(pendingCount?.textContent || 0);
        if (!pendingCountValue) {
            showToast('Aucune modification en attente.', 'warn');
            return;
        }
        closePendingQueue();
        const marketplaceIds = selectedMarketplaces();
        const mkpLabel = marketplaceIds.length
            ? `${marketplaceIds.length} marketplace(s)`
            : 'sync CSV uniquement (pas d’API)';
        publishModalSummary.textContent = `${pendingCountValue} modification(s) · ${mkpLabel}`;
        if (publishNoteInput) publishNoteInput.value = '';
        publishModal.classList.add('open');
        publishNoteInput?.focus();
    }

    function closePublishModal() {
        publishModal?.classList.remove('open');
    }

    function openConflictModal(conflicts, mode = 'publish') {
        if (!conflictModalList) return;
        conflictMode = mode;
        if (conflictModalTitle) {
            conflictModalTitle.textContent = mode === 'save'
                ? 'Modifications déjà en attente chez un collègue'
                : 'Conflits avec d’autres opérateurs';
        }
        if (conflictModalHint) {
            conflictModalHint.textContent = mode === 'save'
                ? 'Un collègue a déjà ces prix en file. Continuer ajoutera aussi vos valeurs dans votre panier (last-write-wins à la publication).'
                : 'Des collègues ont aussi des modifications en attente sur les mêmes produits. Publier écrasera leur prix sur le flux pour ces lignes.';
        }
        if (conflictModalConfirm) {
            conflictModalConfirm.textContent = mode === 'save' ? 'Mettre en attente quand même' : 'Publier quand même';
        }
        const rows = (conflicts || []).map((c) => {
            const label = escapeHtml(c.csv_column);
            const who = escapeHtml(c.other_user_name || 'Collègue');
            const sku = escapeHtml(c.sku);
            const mine = escapeHtml(c.my_new_price);
            const theirs = escapeHtml(c.other_new_price);
            return `<li><strong>${sku}</strong> · ${label}<br><span class="muted">${who} → ${theirs} · vous → ${mine}</span></li>`;
        });
        conflictModalList.innerHTML = rows.join('') || '<li class="muted">Conflits détectés.</li>';
        conflictModal?.classList.add('open');
    }

    function closeConflictModal() {
        conflictModal?.classList.remove('open');
        if (conflictMode === 'save' && pendingSaveContext?.validateBtn) {
            pendingSaveContext.validateBtn.disabled = false;
        }
    }

    async function publish(note = null, confirmConflicts = false) {
        const marketplaceIds = selectedMarketplaces();
        closePublishModal();
        closeConflictModal();
        pendingPublishNote = String(note ?? '');
        setUiLock(true, marketplaceIds.length ? 'Démarrage sync flux…' : 'Sync CSV uniquement…', 2);
        try {
            const payload = { marketplace_ids: marketplaceIds };
            const trimmed = pendingPublishNote.trim();
            if (trimmed !== '') payload.note = trimmed.slice(0, 500);
            if (confirmConflicts) payload.confirm_conflicts = true;
            const result = await api(endpoints.publish, { method: 'POST', body: JSON.stringify(payload) });
            const status = result.status || await api(endpoints.publishStatus);
            const label = status.label || result.message || 'Terminé';
            setUiLock(true, label, status.progress || 100);
            await refreshPending();
            if (activeRowId !== null) await openProduct(activeRowId);
            const delay = status.status === 'partial' ? 1800 : 700;
            setTimeout(() => setUiLock(false), delay);
        } catch (e) {
            const code = e.payload?.code;
            if (code === 'pending_conflicts') {
                setUiLock(false);
                openConflictModal(e.payload.conflicts || [], 'publish');
                return;
            }
            setUiLock(true, e.message, 100);
            setTimeout(() => setUiLock(false), 1800);
            if (code === 'feed_publish_lock') {
                showToast(e.message || 'Une publication est déjà en cours sur ce flux.', 'warn');
            }
        }
    }

    function escapeHtml(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function formatDate(iso) {
        if (!iso) return '';
        return new Date(iso).toLocaleString('fr-FR');
    }

    listEl.addEventListener('scroll', () => {
        if (listEl.scrollTop + listEl.clientHeight >= listEl.scrollHeight - 40) loadMore(false);
    });
    document.getElementById('search').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadMore(true), 250);
    });
    document.getElementById('btn-refresh').addEventListener('click', refreshFeed);
    btnPublish?.addEventListener('click', openPublishModal);
    btnPublishPanel?.addEventListener('click', openPublishModal);
    pendingSummary?.addEventListener('click', () => {
        if (pendingQueue.classList.contains('open')) closePendingQueue();
        else openPendingQueue();
    });
    document.getElementById('pending-queue-close')?.addEventListener('click', closePendingQueue);
    document.getElementById('pending-queue-scrim')?.addEventListener('click', closePendingQueue);
    document.getElementById('publish-modal-cancel')?.addEventListener('click', closePublishModal);
    document.getElementById('publish-modal-confirm')?.addEventListener('click', () => publish(publishNoteInput?.value || ''));
    publishModal?.addEventListener('click', (event) => { if (event.target === publishModal) closePublishModal(); });
    document.getElementById('conflict-modal-cancel')?.addEventListener('click', closeConflictModal);
    conflictModalConfirm?.addEventListener('click', () => {
        closeConflictModal();
        if (conflictMode === 'save') {
            const ctx = pendingSaveContext;
            if (!ctx) return;
            validateAllPrices(ctx.rowId, ctx.grid, ctx.validateBtn, true);
            return;
        }
        publish(pendingPublishNote, true);
    });
    conflictModal?.addEventListener('click', (event) => { if (event.target === conflictModal) closeConflictModal(); });
    document.getElementById('mkp-all').addEventListener('click', () => {
        document.querySelectorAll('.mkp-check').forEach(el => el.checked = true);
    });
    document.getElementById('mkp-none').addEventListener('click', () => {
        document.querySelectorAll('.mkp-check').forEach(el => el.checked = false);
    });
    pendingList.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-pending-id]');
        if (!btn) return;
        removePending(btn.getAttribute('data-pending-id'));
    });
    chartColumnSelect?.addEventListener('change', () => renderPriceChart(chartColumnSelect.value));
    document.getElementById('price-chart-close')?.addEventListener('click', closePriceChartModal);
    chartModal?.addEventListener('click', (event) => { if (event.target === chartModal) closePriceChartModal(); });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (conflictModal?.classList.contains('open')) { closeConflictModal(); return; }
        if (publishModal?.classList.contains('open')) { closePublishModal(); return; }
        if (chartModal?.classList.contains('open')) { closePriceChartModal(); return; }
        if (pendingQueue?.classList.contains('open')) { closePendingQueue(); return; }
        if (layoutEl.classList.contains('is-detail')) setDetailMode(false);
    });
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) pollColleagueAwareness();
    });

    (async () => {
        try {
            if (bootRefresh) {
                setLoadProgress(5, 'Mise à jour des infos à la connexion…');
                const ready = await waitUntilReady();
                if (!ready) await refreshFeed();
                else await resetAndLoad();
            } else {
                const s = await pollFeedStatus();
                if (s.status !== 'ready') await refreshFeed();
                else await resetAndLoad();
            }
        } finally {
            startColleaguePolling();
        }
    })();
})();
</script>
@endpush
@endif
