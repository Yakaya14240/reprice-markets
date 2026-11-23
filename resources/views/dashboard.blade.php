@extends('layouts.app')

@section('title', 'Suivi')

@push('styles')
<style>
    .suivi-kpis { display:grid; gap:1rem; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); margin-bottom:1rem; }
    .suivi-kpi .label { color:var(--muted); font-size:.85rem; font-weight:600; }
    .suivi-kpi .value { font-size:1.75rem; font-weight:800; margin-top:.2rem; font-variant-numeric:tabular-nums; }
    .suivi-kpi.warn .value { color:var(--danger); }
    .suivi-toolbar {
        display:flex; gap:.65rem; flex-wrap:wrap; align-items:flex-start;
        padding:.85rem 1rem; margin-bottom:1rem;
        background:#fff; border:1px solid var(--border); border-radius:12px;
    }
    .suivi-field {
        display:flex; flex-direction:column; gap:.3rem; min-width:0;
    }
    .suivi-toolbar .suivi-field > label,
    .suivi-toolbar .suivi-field-label {
        display:block; height:1rem; line-height:1rem; margin:0;
        font-size:.72rem; color:var(--muted); font-weight:700;
        text-transform:uppercase; letter-spacing:.03em; white-space:nowrap;
    }
    .suivi-toolbar .suivi-field > input,
    .suivi-toolbar .suivi-field > select {
        width:auto; min-width:148px; height:2.5rem; margin:0; box-sizing:border-box;
        padding:0 .7rem; border:1px solid var(--border); border-radius:8px;
        background:#fff; color:var(--text); font:inherit; line-height:1.2;
    }
    .suivi-toolbar input[type="date"] { min-width:158px; }
    .suivi-toolbar .actions {
        display:flex; flex-direction:column; gap:.3rem; justify-content:flex-start;
    }
    .suivi-toolbar .actions .btn {
        height:2.5rem; margin:0; display:inline-flex; align-items:center; justify-content:center;
        box-sizing:border-box; padding:0 .95rem; white-space:nowrap;
    }
    .suivi-card h2 { margin:0 0 .75rem; font-size:1.1rem; }
    .suivi-table-wrap { overflow-x:auto; }
    .suivi-table { width:100%; border-collapse:collapse; font-size:.92rem; min-width:560px; }
    .suivi-table th {
        text-align:left; font-size:.72rem; text-transform:uppercase; letter-spacing:.03em;
        color:var(--muted); font-weight:700; padding:.55rem .65rem; background:#f8fafc;
        border-bottom:1px solid var(--border); white-space:nowrap;
    }
    .suivi-table td {
        padding:.55rem .65rem; border-bottom:1px solid var(--border); vertical-align:middle;
    }
    .suivi-table tbody tr:nth-child(even) { background:#fafbfc; }
    .suivi-table tbody tr:hover { background:#eefbf5; }
    .suivi-table .num { font-variant-numeric:tabular-nums; white-space:nowrap; font-weight:700; }
    .suivi-table .sku { font-weight:700; }
    .suivi-table .muted-cell { color:var(--muted); }
    .suivi-table .col-meta { display:block; font-size:.75rem; color:var(--muted); font-weight:500; }
    .badge {
        display:inline-block; padding:.15rem .5rem; border-radius:999px;
        font-size:.75rem; font-weight:700; white-space:nowrap;
    }
    .badge-ok { background:#d8f3ea; color:#03543f; }
    .badge-danger { background:#fde8e8; color:#9b1c1c; }
    .badge-warn { background:#fff3cd; color:#8a6d3b; }
    .badge-muted { background:#e2e8f0; color:#475569; }
    .msg-cell { max-width:320px; }
    .msg-toggle {
        display:block; width:100%; text-align:left; border:0; background:transparent;
        padding:0; cursor:pointer; color:inherit; font:inherit;
    }
    .msg-toggle .preview {
        display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
        overflow:hidden; color:var(--muted); font-size:.88rem; line-height:1.35;
    }
    .msg-toggle.is-open .preview {
        display:block; -webkit-line-clamp:unset; color:var(--text); white-space:pre-wrap;
    }
    .msg-toggle .hint { display:inline-block; margin-top:.2rem; font-size:.72rem; color:var(--accent); font-weight:700; }
    .msg-toggle.is-open .hint { display:none; }
    .note-pill {
        display:inline-flex; align-items:center; justify-content:center;
        width:1.35rem; height:1.35rem; margin-left:.35rem; border-radius:999px;
        background:#e0f2fe; color:#0369a1; cursor:help; vertical-align:middle;
        border:0; padding:0;
    }
    .note-pill:hover { background:#bae6fd; }
    .note-pill svg { width:.85rem; height:.85rem; display:block; }
    .mkp-cell { display:flex; align-items:center; gap:.15rem; flex-wrap:wrap; }
</style>
@endpush

@section('content')
    <h1>Suivi</h1>
    <p class="muted">Activité du jour et historique des publications.</p>

    <div class="suivi-kpis">
        <div class="card suivi-kpi">
            <div class="label">Modifs aujourd’hui</div>
            <div class="value">{{ $changesToday }}</div>
        </div>
        <div class="card suivi-kpi">
            <div class="label">Push aujourd’hui</div>
            <div class="value">{{ $pushesToday }}</div>
        </div>
        <div class="card suivi-kpi {{ $pushesFailedToday > 0 ? 'warn' : '' }}">
            <div class="label">Échecs aujourd’hui</div>
            <div class="value">{{ $pushesFailedToday }}</div>
        </div>
        <div class="card suivi-kpi">
            <div class="label">Marketplaces actives</div>
            <div class="value">{{ $activeMarketplaces }}</div>
        </div>
    </div>

    <form method="GET" action="{{ route('dashboard') }}" class="suivi-toolbar" aria-label="Filtres du suivi">
        <div class="suivi-field">
            <label for="filter-user">Opérateur</label>
            <select id="filter-user" name="user_id">
                <option value="">Tous</option>
                @foreach ($filterUsers as $user)
                    <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="suivi-field">
            <label for="filter-marketplace">Marketplace</label>
            <select id="filter-marketplace" name="marketplace_id">
                <option value="">Toutes</option>
                @foreach ($filterMarketplaces as $marketplace)
                    <option value="{{ $marketplace->id }}" @selected((string) ($filters['marketplace_id'] ?? '') === (string) $marketplace->id)>{{ $marketplace->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="suivi-field">
            <label for="filter-status">Statut push</label>
            <select id="filter-status" name="status">
                <option value="">Tous</option>
                @foreach ($filterStatuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="suivi-field">
            <label for="filter-from">Du</label>
            <input id="filter-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
        </div>
        <div class="suivi-field">
            <label for="filter-to">Au</label>
            <input id="filter-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
        </div>
        <div class="actions">
            <span class="suivi-field-label" aria-hidden="true">&nbsp;</span>
            <div style="display:flex;gap:.45rem;align-items:center;">
                <button class="btn" type="submit">Filtrer</button>
                @if ($filtersActive)
                    <a class="btn secondary" href="{{ route('dashboard') }}">Réinitialiser</a>
                @endif
            </div>
        </div>
    </form>

    <div class="card suivi-card">
        <h2>Modifications publiées</h2>
        <div class="suivi-table-wrap">
            <table class="suivi-table">
                <thead>
                <tr>
                    <th>Quand</th>
                    <th>Opérateur</th>
                    <th>SKU</th>
                    <th>Marché</th>
                    <th>Nouveau</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($recentChanges as $change)
                    @php
                        $labelKey = $change->feed_file_id.'|'.$change->csv_column;
                        $marketLabel = $columnLabels[$labelKey] ?? $change->csv_column;
                        $marketLabel = preg_replace('/^prix\s+/iu', '', (string) $marketLabel) ?: $marketLabel;
                    @endphp
                    <tr>
                        <td class="muted-cell">{{ $change->created_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $change->user?->name }}</td>
                        <td class="sku">{{ $change->sku }}</td>
                        <td>
                            {{ $marketLabel }}
                            @if ($marketLabel !== $change->csv_column)
                                <span class="col-meta" title="{{ $change->csv_column }}">{{ $change->csv_column }}</span>
                            @endif
                        </td>
                        <td class="num">{{ number_format((float) $change->new_price, 2, ',', ' ') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted-cell">Aucune modification publiée.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $recentChanges->links() }}
    </div>

    <div class="card suivi-card">
        <h2>Push marketplaces</h2>
        <div class="suivi-table-wrap">
            <table class="suivi-table">
                <thead>
                <tr>
                    <th>Quand</th>
                    <th>Marketplace</th>
                    <th>Statut</th>
                    <th>Opérateur</th>
                    <th>Message</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($recentPushes as $batch)
                    <tr>
                        <td class="muted-cell">{{ $batch->created_at?->format('d/m/Y H:i') }}</td>
                        <td>
                            <div class="mkp-cell">
                                <span>{{ $batch->marketplaceDisplayName() }}</span>
                                @if (filled($batch->note))
                                    <button
                                        type="button"
                                        class="note-pill"
                                        title="{{ $batch->note }}"
                                        aria-label="Note de publication : {{ $batch->note }}"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/>
                                            <path d="M14 3v6h6"/>
                                            <path d="M8 13h8M8 17h5"/>
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                        <td><span class="badge badge-{{ $batch->status->badgeTone() }}">{{ $batch->status->label() }}</span></td>
                        <td>{{ $batch->user?->name }}</td>
                        <td class="msg-cell">
                            @if (filled($batch->result_message))
                                <button type="button" class="msg-toggle" data-msg-toggle title="Afficher le détail">
                                    <span class="preview">{{ $batch->result_message }}</span>
                                    <span class="hint">Voir plus</span>
                                </button>
                            @else
                                <span class="muted-cell">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted-cell">Aucun push.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $recentPushes->links() }}
    </div>
@endsection

@push('scripts')
<script>
(() => {
    document.querySelectorAll('[data-msg-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            btn.classList.toggle('is-open');
            btn.title = btn.classList.contains('is-open') ? 'Réduire' : 'Afficher le détail';
        });
    });
})();
</script>
@endpush
