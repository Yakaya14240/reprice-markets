@extends('layouts.admin')

@section('title', 'Mapping fiche')

@push('styles')
<style>
    .image-zone {
        border: 1px dashed var(--border);
        border-radius: 12px;
        padding: .9rem 1rem 1rem;
        background: #1a2436;
        margin-bottom: 1rem;
    }
    .image-zone-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: .75rem;
        flex-wrap: wrap;
        margin-bottom: .75rem;
    }
    .image-zone-head p { margin: .2rem 0 0; }
    .image-rows { display: flex; flex-direction: column; gap: .55rem; }
    .image-row {
        display: grid;
        grid-template-columns: 2rem 1fr auto;
        gap: .5rem;
        align-items: center;
    }
    .image-row .slot {
        width: 2rem; height: 2rem; border-radius: 8px;
        display: inline-flex; align-items: center; justify-content: center;
        background: #111827; border: 1px solid var(--border);
        color: var(--muted); font-size: .75rem; font-weight: 700;
    }
    .image-row select { margin: 0; }
    .image-row .btn-remove {
        border: 1px solid var(--border); background: #111827; color: #fca5a5;
        border-radius: 8px; width: 2.2rem; height: 2.2rem; cursor: pointer;
        font-size: 1.1rem; line-height: 1; font-weight: 700;
    }
    .image-row .btn-remove:hover { border-color: #f87171; color: #fecaca; }
    .image-zone-empty {
        color: var(--muted); font-size: .9rem; padding: .35rem 0 .55rem;
    }
    .btn-add-image {
        display: inline-flex; align-items: center; gap: .35rem;
        border: 1px dashed var(--accent); background: transparent; color: var(--accent);
        border-radius: 8px; padding: .45rem .75rem; font-weight: 700; cursor: pointer;
        margin-top: .65rem;
    }
    .btn-add-image:hover { background: rgba(56, 189, 248, .08); }
    .btn-add-image:disabled {
        opacity: .45; cursor: not-allowed; border-color: var(--border); color: var(--muted);
    }
</style>
@endpush

@section('content')
    <h1>Mapping fiche produit</h1>
    <p class="muted">
        Définissez description et images (URLs) pour la fiche Front. Le titre réutilise la colonne nom du flux
        (<code>name_column</code>).
    </p>

    <div class="card">
        <form method="GET" class="actions" style="align-items:end;">
            <div style="flex:1;min-width:220px;">
                <label>Flux</label>
                <select name="feed_file_id" onchange="this.form.submit()">
                    @forelse ($feeds as $feed)
                        <option value="{{ $feed->id }}" @selected($selectedFeedId === $feed->id)>{{ $feed->name }}</option>
                    @empty
                        <option value="">Aucun flux</option>
                    @endforelse
                </select>
            </div>
        </form>
    </div>

    @if ($selectedFeed)
        <div class="card">
            <h2 style="margin-top:0;">{{ $selectedFeed->name }}</h2>
            <p class="muted">
                Titre fiche :
                <strong>{{ $selectedFeed->name_column ?: 'Titre (défaut)' }}</strong>
                — modifiable dans
                <a href="{{ route('admin.feeds.edit', $selectedFeed) }}">Édition du flux</a>.
            </p>

            @if (count($availableHeaders) === 0)
                <p class="muted">Aucune colonne CSV en mémoire. Rechargez le flux depuis Mapping prix ou Flux.</p>
            @else
                @php($selectedImages = array_values(array_filter(
                    old('image_columns', $selectedFeed->image_columns ?? []),
                    static fn ($c) => is_string($c) && $c !== ''
                )))

                <form method="POST" action="{{ route('admin.product-sheet.update', $selectedFeed) }}">
                    @csrf
                    @method('PUT')

                    <label for="description_column">Colonne description</label>
                    <select id="description_column" name="description_column">
                        <option value="">— aucune —</option>
                        @foreach ($availableHeaders as $header)
                            <option value="{{ $header }}" @selected(old('description_column', $selectedFeed->description_column) === $header)>
                                {{ $header }}
                            </option>
                        @endforeach
                    </select>

                    <div class="image-zone" id="image-zone" data-max="10">
                        <div class="image-zone-head">
                            <div>
                                <strong>Zone images</strong>
                                <p class="muted">Colonnes URL (séparateurs | , ;). Max 10.</p>
                            </div>
                            <span class="muted" id="image-count">{{ count($selectedImages) }} / 10</span>
                        </div>

                        <div class="image-rows" id="image-rows">
                            @forelse ($selectedImages as $index => $column)
                                <div class="image-row" data-image-row>
                                    <span class="slot">{{ $index + 1 }}</span>
                                    <select name="image_columns[]" required>
                                        <option value="{{ $column }}" selected>{{ $column }}</option>
                                    </select>
                                    <button type="button" class="btn-remove" data-remove-image title="Retirer" aria-label="Retirer">×</button>
                                </div>
                            @empty
                                <div class="image-zone-empty" id="image-empty">Aucune colonne image. Cliquez sur + pour en ajouter.</div>
                            @endforelse
                        </div>

                        <button type="button" class="btn-add-image" id="btn-add-image" @disabled(count($selectedImages) >= 10 || count($selectedImages) >= count($availableHeaders))>
                            + Ajouter une image
                        </button>
                    </div>

                    <button class="btn" type="submit">Enregistrer</button>
                </form>
            @endif
        </div>
    @endif
@endsection

@if ($selectedFeed && count($availableHeaders) > 0)
@push('scripts')
<script>
(() => {
    const headers = @json(array_values($availableHeaders));
    const zone = document.getElementById('image-zone');
    const rowsEl = document.getElementById('image-rows');
    const addBtn = document.getElementById('btn-add-image');
    const countEl = document.getElementById('image-count');
    const max = Number(zone?.dataset.max || 10);

    function selectedValues(exceptSelect = null) {
        return [...rowsEl.querySelectorAll('select')]
            .filter(sel => sel !== exceptSelect)
            .map(sel => sel.value)
            .filter(Boolean);
    }

    function rebuildOptions(select) {
        const current = select.value;
        const taken = new Set(selectedValues(select));
        const options = headers.filter(h => h === current || !taken.has(h));
        select.innerHTML = options.map(h =>
            `<option value="${escapeHtml(h)}" ${h === current ? 'selected' : ''}>${escapeHtml(h)}</option>`
        ).join('');
        if (!select.value && options.length) {
            select.value = options[0];
        }
    }

    function refreshAllSelects() {
        [...rowsEl.querySelectorAll('select')].forEach(rebuildOptions);
        renumber();
        syncAddButton();
    }

    function renumber() {
        [...rowsEl.querySelectorAll('[data-image-row]')].forEach((row, i) => {
            const slot = row.querySelector('.slot');
            if (slot) slot.textContent = String(i + 1);
        });
        const n = rowsEl.querySelectorAll('[data-image-row]').length;
        if (countEl) countEl.textContent = `${n} / ${max}`;
        const empty = document.getElementById('image-empty');
        if (empty) empty.style.display = n ? 'none' : '';
    }

    function syncAddButton() {
        const n = rowsEl.querySelectorAll('[data-image-row]').length;
        const taken = selectedValues();
        const remaining = headers.filter(h => !taken.includes(h)).length;
        addBtn.disabled = n >= max || remaining === 0;
    }

    function addRow(preferred = null) {
        const taken = selectedValues();
        const available = headers.filter(h => !taken.includes(h));
        if (!available.length || rowsEl.querySelectorAll('[data-image-row]').length >= max) return;

        document.getElementById('image-empty')?.remove();

        const value = preferred && available.includes(preferred) ? preferred : available[0];
        const row = document.createElement('div');
        row.className = 'image-row';
        row.dataset.imageRow = '';
        row.innerHTML = `
            <span class="slot"></span>
            <select name="image_columns[]" required></select>
            <button type="button" class="btn-remove" data-remove-image title="Retirer" aria-label="Retirer">×</button>
        `;
        const select = row.querySelector('select');
        select.innerHTML = available.map(h =>
            `<option value="${escapeHtml(h)}" ${h === value ? 'selected' : ''}>${escapeHtml(h)}</option>`
        ).join('');
        select.addEventListener('change', refreshAllSelects);
        rowsEl.appendChild(row);
        refreshAllSelects();
    }

    function escapeHtml(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[c]));
    }

    addBtn?.addEventListener('click', () => addRow());
    rowsEl?.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-remove-image]');
        if (!btn) return;
        btn.closest('[data-image-row]')?.remove();
        if (!rowsEl.querySelector('[data-image-row]')) {
            const empty = document.createElement('div');
            empty.className = 'image-zone-empty';
            empty.id = 'image-empty';
            empty.textContent = 'Aucune colonne image. Cliquez sur + pour en ajouter.';
            rowsEl.appendChild(empty);
        }
        refreshAllSelects();
    });

    [...rowsEl.querySelectorAll('select')].forEach(sel => {
        sel.addEventListener('change', refreshAllSelects);
    });
    refreshAllSelects();
})();
</script>
@endpush
@endif
