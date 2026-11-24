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
        Configurez la carte identité Prix (description / images) et les champs multi-langues de l’écran Fiches.
        Le titre réutilise la colonne nom du flux (<code>name_column</code>).
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

                    <button class="btn" type="submit">Enregistrer identité Prix</button>
                </form>
            @endif
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Champs fiche (multi-langues)</h2>
            <p class="muted">Ces champs alimentent l’écran FO « Fiches ». Un champ éditable pourra être mis en attente puis publié dans le flux CSV.</p>

            @if ($languages->isEmpty())
                <p class="muted">Aucune langue active. Créez-en dans <a href="{{ route('admin.languages.index') }}">Langues</a>.</p>
            @elseif (count($availableHeaders) === 0)
                <p class="muted">Aucune colonne CSV en mémoire. Rechargez le flux.</p>
            @else
                <form method="POST" action="{{ route('admin.product-sheet-fields.store') }}">
                    @csrf
                    <input type="hidden" name="feed_file_id" value="{{ $selectedFeed->id }}">
                    <label>Label</label>
                    <input name="label" required placeholder="Description FR">
                    <label>Zone</label>
                    @if (($sheetGroups ?? collect())->isEmpty())
                        <p class="muted">Aucune zone active. Créez-en dans <a href="{{ route('admin.product-sheet-groups.index') }}">Zones fiche</a>.</p>
                    @else
                        <select name="product_sheet_group_id">
                            <option value="">— aucune (Autres) —</option>
                            @foreach ($sheetGroups as $group)
                                <option value="{{ $group->id }}" @selected(old('product_sheet_group_id') == $group->id)>{{ $group->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    <p class="muted" style="margin-top:-.45rem;margin-bottom:.9rem;">Administrable dans <a href="{{ route('admin.product-sheet-groups.index') }}">Zones fiche</a>.</p>
                    <label>Langue</label>
                    <select name="language_id" required>
                        <option value="">— choisir —</option>
                        @foreach ($languages as $language)
                            <option value="{{ $language->id }}" @selected(old('language_id') == $language->id)>{{ $language->label() }}</option>
                        @endforeach
                    </select>
                    <label>Type</label>
                    <select name="type" required id="psf-type">
                        @foreach ($fieldTypes as $type)
                            <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    <label>Colonne CSV</label>
                    <x-csv-column-select :headers="$availableHeaders" name="csv_column" />
                    <div id="psf-create-max-length" data-psf-for="text_line,text_plain,text_html" hidden>
                        <label for="psf-max-length">Limite de caractères</label>
                        <input id="psf-max-length" name="max_length" type="number" min="1" max="100000" value="{{ old('max_length') }}" placeholder="ex. 5000">
                    </div>
                    <div id="psf-create-max-images" data-psf-for="images" hidden>
                        <label for="psf-max-images">Max images</label>
                        <input id="psf-max-images" name="max_images" type="number" min="1" max="20" value="{{ old('max_images', 10) }}">
                    </div>
                    <label>Aide mémoire</label>
                    <input name="help_text" value="{{ old('help_text') }}" maxlength="500" placeholder="Conditions / consignes du champ">
                    <label>Ordre</label>
                    <input name="sort_order" type="number" min="0" value="{{ old('sort_order', 100) }}">
                    <label style="margin-top:.35rem;"><input type="checkbox" name="is_editable" value="1" style="width:auto;" @checked(old('is_editable'))> Éditable en FO</label>
                    <button class="btn" type="submit">Ajouter le champ</button>
                </form>
            @endif
        </div>

        <div class="card">
            <table>
                <thead>
                <tr>
                    <th>Label</th><th>Zone</th><th>Langue</th><th>Type</th><th>Colonne</th><th>Éditable</th><th>Limite</th><th>Aide</th><th>Ordre</th><th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($fields as $field)
                    <tr data-psf-row="{{ $field->id }}">
                        <td>
                            <strong class="psf-view">{{ $field->label }}</strong>
                            <input class="psf-edit" name="label" form="psf-form-{{ $field->id }}" value="{{ $field->label }}" style="display:none;margin:0;" required>
                        </td>
                        <td>
                            <span class="psf-view">{{ $field->group?->name ?: '—' }}</span>
                            <select class="psf-edit" name="product_sheet_group_id" form="psf-form-{{ $field->id }}" style="display:none;margin:0;max-width:140px;">
                                <option value="">— aucune —</option>
                                @foreach ($sheetGroups as $group)
                                    <option value="{{ $group->id }}" @selected((int) $field->product_sheet_group_id === $group->id)>{{ $group->name }}</option>
                                @endforeach
                                @if($field->product_sheet_group_id && ! $sheetGroups->contains('id', $field->product_sheet_group_id) && $field->group)
                                    <option value="{{ $field->group->id }}" selected>{{ $field->group->name }} (inactive)</option>
                                @endif
                            </select>
                        </td>
                        <td>
                            <span class="psf-view">{{ $field->language?->code ?? '—' }}</span>
                            <select class="psf-edit" name="language_id" form="psf-form-{{ $field->id }}" required style="display:none;margin:0;max-width:120px;">
                                @foreach ($languages as $language)
                                    <option value="{{ $language->id }}" @selected((int) $field->language_id === $language->id)>{{ $language->code }}</option>
                                @endforeach
                                @if($field->language_id && ! $languages->contains('id', $field->language_id) && $field->language)
                                    <option value="{{ $field->language->id }}" selected>{{ $field->language->code }} (inactive)</option>
                                @endif
                            </select>
                        </td>
                        <td>
                            <span class="psf-view">{{ $field->type->label() }}</span>
                            <select class="psf-edit" name="type" form="psf-form-{{ $field->id }}" required style="display:none;margin:0;max-width:140px;">
                                @foreach ($fieldTypes as $type)
                                    <option value="{{ $type->value }}" @selected($field->type === $type)>{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <code class="psf-view">{{ $field->csv_column }}</code>
                            <div class="psf-edit" style="display:none;min-width:180px;">
                                <x-csv-column-select
                                    :headers="$availableHeaders"
                                    name="csv_column"
                                    :value="$field->csv_column"
                                    :form="'psf-form-'.$field->id"
                                    style="margin-bottom:0;"
                                />
                            </div>
                        </td>
                        <td>
                            <span class="psf-view">{{ $field->is_editable ? 'oui' : 'non' }}</span>
                            <label class="psf-edit" style="display:none;margin:0;">
                                <input type="checkbox" name="is_editable" value="1" form="psf-form-{{ $field->id }}" style="width:auto;" @checked($field->is_editable)> éditable
                            </label>
                        </td>
                        <td>
                            <span class="psf-view">
                                @if ($field->type === \App\Enums\ProductSheetFieldType::Images)
                                    {{ $field->settings['max_images'] ?? 10 }} img
                                @else
                                    {{ $field->max_length ?? '—' }}
                                @endif
                            </span>
                            <div class="psf-edit" data-psf-row-max-length style="display:none;">
                                <input name="max_length" type="number" min="1" form="psf-form-{{ $field->id }}" value="{{ $field->max_length }}" style="margin:0;max-width:90px;" placeholder="caractères">
                            </div>
                            <div class="psf-edit" data-psf-row-max-images style="display:none;">
                                <input name="max_images" type="number" min="1" max="20" form="psf-form-{{ $field->id }}" value="{{ $field->settings['max_images'] ?? 10 }}" style="margin:0;max-width:90px;" placeholder="images">
                            </div>
                        </td>
                        <td>
                            <span class="psf-view" title="{{ $field->help_text }}">{{ \Illuminate\Support\Str::limit($field->help_text ?? '—', 40) }}</span>
                            <input class="psf-edit" name="help_text" form="psf-form-{{ $field->id }}" value="{{ $field->help_text }}" style="display:none;margin:0;" maxlength="500">
                        </td>
                        <td>
                            <span class="psf-view">{{ $field->sort_order }}</span>
                            <input class="psf-edit" name="sort_order" type="number" min="0" form="psf-form-{{ $field->id }}" value="{{ $field->sort_order }}" style="display:none;margin:0;max-width:70px;">
                        </td>
                        <td>
                            <form id="psf-form-{{ $field->id }}" method="POST" action="{{ route('admin.product-sheet-fields.update', $field) }}">
                                @csrf
                                @method('PUT')
                            </form>
                            <div class="actions psf-view">
                                <button class="btn secondary" type="button" data-psf-edit="{{ $field->id }}">Éditer</button>
                                <form method="POST" action="{{ route('admin.product-sheet-fields.destroy', $field) }}" onsubmit="return confirm('Supprimer ce champ ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn secondary" type="submit">Supprimer</button>
                                </form>
                            </div>
                            <div class="actions psf-edit" style="display:none;">
                                <button class="btn" type="submit" form="psf-form-{{ $field->id }}">Enregistrer</button>
                                <button class="btn secondary" type="button" data-psf-cancel="{{ $field->id }}">Annuler</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="muted">Aucun champ fiche pour ce flux.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $fields->links() }}
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

@push('scripts')
<script>
(() => {
    const createType = document.getElementById('psf-type');

    function syncCreateTypeFields() {
        if (!createType) return;
        const type = createType.value;
        document.querySelectorAll('#psf-create-max-length, #psf-create-max-images').forEach(block => {
            const allowed = (block.dataset.psfFor || '').split(',').map(s => s.trim()).filter(Boolean);
            const show = allowed.includes(type);
            block.hidden = !show;
            block.querySelectorAll('input').forEach(input => {
                input.disabled = !show;
            });
        });
    }

    createType?.addEventListener('change', syncCreateTypeFields);
    syncCreateTypeFields();

    function syncRowTypeFields(row) {
        const typeSelect = row.querySelector('select[name="type"]');
        const type = typeSelect?.value || '';
        const isText = type === 'text_line' || type === 'text_plain' || type === 'text_html';
        const isImages = type === 'images';
        const maxLength = row.querySelector('[data-psf-row-max-length]');
        const maxImages = row.querySelector('[data-psf-row-max-images]');
        if (maxLength) {
            maxLength.style.display = isText ? '' : 'none';
            maxLength.querySelectorAll('input').forEach(input => { input.disabled = !isText; });
        }
        if (maxImages) {
            maxImages.style.display = isImages ? '' : 'none';
            maxImages.querySelectorAll('input').forEach(input => { input.disabled = !isImages; });
        }
    }

    function setRowMode(row, editing) {
        row.querySelectorAll('.psf-view').forEach(el => {
            el.style.display = editing ? 'none' : '';
        });
        row.querySelectorAll('.psf-edit').forEach(el => {
            if (el.matches('[data-psf-row-max-length], [data-psf-row-max-images]')) {
                return;
            }
            if (el.tagName === 'LABEL' || el.classList.contains('actions') || el.tagName === 'DIV') {
                el.style.display = editing ? (el.classList.contains('actions') ? 'flex' : '') : 'none';
            } else if (el.type === 'hidden') {
                el.style.display = 'none';
            } else {
                el.style.display = editing ? '' : 'none';
            }
        });
        if (editing) {
            syncRowTypeFields(row);
        } else {
            row.querySelectorAll('[data-psf-row-max-length], [data-psf-row-max-images]').forEach(el => {
                el.style.display = 'none';
            });
        }
    }

    document.querySelectorAll('[data-psf-edit]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-psf-edit');
            const row = document.querySelector(`[data-psf-row="${id}"]`);
            if (row) setRowMode(row, true);
        });
    });

    document.querySelectorAll('[data-psf-cancel]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-psf-cancel');
            const row = document.querySelector(`[data-psf-row="${id}"]`);
            if (row) setRowMode(row, false);
        });
    });

    document.querySelectorAll('[data-psf-row] select[name="type"]').forEach(select => {
        select.addEventListener('change', () => {
            const row = select.closest('[data-psf-row]');
            if (row) syncRowTypeFields(row);
        });
    });
})();
</script>
@endpush
