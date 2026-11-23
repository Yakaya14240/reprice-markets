@php
    $m = $marketplace ?? null;
    $driverValue = old('driver', $m?->driver?->value ?? \App\Enums\MarketplaceDriver::Mirakl->value);
    $shopIdsValue = old('shop_ids', $m ? implode(',', $m->shop_ids ?? []) : '');
    $shopIdsList = collect(explode(',', $shopIdsValue))->map(fn ($id) => trim($id))->filter()->values()->all();
@endphp

@once
@push('styles')
<style>
    .shop-chips {
        display: flex; flex-wrap: wrap; gap: .4rem; align-items: center;
        min-height: 2.4rem; padding: .4rem; margin-bottom: .35rem;
        border: 1px solid var(--border); border-radius: 8px; background: #111827;
    }
    .shop-chip {
        display: inline-flex; align-items: center; gap: .35rem;
        background: #0c4a6e; color: #e0f2fe; border-radius: 999px;
        padding: .25rem .55rem; font-size: .9rem; font-weight: 600;
    }
    .shop-chip button {
        border: 0; background: transparent; color: #7dd3fc; cursor: pointer;
        font-size: 1rem; line-height: 1; padding: 0; width: auto; margin: 0;
    }
    .shop-chips-input {
        flex: 1; min-width: 120px; border: 0 !important; background: transparent !important;
        margin: 0 !important; padding: .35rem .4rem !important; color: var(--text);
    }
</style>
@endpush
@endonce

<div id="mkp-form" data-initial-driver="{{ $driverValue }}">
    <label>Nom</label>
    <input name="name" value="{{ old('name', $m?->name) }}" required>

    <label>Type d’API</label>
    <select name="driver" id="mkp-driver" required>
        @foreach ($drivers as $driver)
            <option value="{{ $driver->value }}" @selected($driverValue === $driver->value)>{{ $driver->label() }}</option>
        @endforeach
    </select>

    <label>API URL</label>
    <input name="api_url" value="{{ old('api_url', $m?->api_url) }}" required placeholder="https://…">

    <div data-show-when-driver="mirakl">
        <x-secret-field name="api_key" label="API key Mirakl" :keep-on-blank="(bool) $m" />

        <label>Shop ID(s)</label>
        <div class="actions" style="gap:.5rem;align-items:flex-start;margin-bottom:.35rem;">
            <div style="flex:1;min-width:220px;">
                <div class="shop-chips" id="mkp-shop-chips" aria-label="Liste des shop IDs">
                    @foreach ($shopIdsList as $shopId)
                        <span class="shop-chip" data-shop-id="{{ $shopId }}">
                            {{ $shopId }}
                            <button type="button" data-remove-shop="{{ $shopId }}" aria-label="Retirer {{ $shopId }}">×</button>
                        </span>
                    @endforeach
                    <input
                        type="text"
                        id="mkp-shop-chip-input"
                        class="shop-chips-input"
                        placeholder="Ajouter un shop ID…"
                        autocomplete="off"
                    >
                </div>
            </div>
            @if ($m)
                <button
                    class="btn secondary"
                    type="button"
                    id="btn-load-mirakl-shops"
                    data-url="{{ route('admin.marketplaces.remote-shops', $m) }}"
                >Charger les shops</button>
            @endif
        </div>
        <p class="muted" style="margin-top:-.2rem;" id="mirakl-shops-status">
            Entrée ou virgule pour ajouter. Persistés au « Enregistrer ».
        </p>
    </div>

    <div data-show-when-driver="kaufland">
        <x-secret-field name="client_key" label="Client key" :keep-on-blank="(bool) $m" />
        <x-secret-field name="client_secret" label="Client secret" :keep-on-blank="(bool) $m" />
    </div>

    <div data-show-when-driver="other">
        <p class="muted">Driver « Autre » : URL libre. Aucun push automatisé pour l’instant.</p>
    </div>

    <input type="hidden" name="shop_ids" id="mkp-shop-ids" value="{{ $shopIdsValue }}">

    <label><input type="checkbox" name="is_active" value="1" style="width:auto;" @checked(old('is_active', $m?->is_active ?? true))> Actif</label>
</div>

@once
@push('scripts')
<script>
(() => {
    const driverEl = document.getElementById('mkp-driver');
    const shopIdsEl = document.getElementById('mkp-shop-ids');
    const chipsEl = document.getElementById('mkp-shop-chips');
    const chipInput = document.getElementById('mkp-shop-chip-input');
    if (!driverEl) return;

    function currentShopIds() {
        if (!chipsEl) return [];
        return [...chipsEl.querySelectorAll('.shop-chip')].map(el => el.getAttribute('data-shop-id')).filter(Boolean);
    }

    function syncShopIdsHidden() {
        const ids = currentShopIds();
        const csv = ids.join(',');
        if (shopIdsEl) shopIdsEl.value = csv;
        document.dispatchEvent(new CustomEvent('mkp-shop-ids-changed', { detail: csv }));
    }

    function renderChips(ids) {
        if (!chipsEl || !chipInput) return;
        [...chipsEl.querySelectorAll('.shop-chip')].forEach(el => el.remove());
        const unique = [...new Set((ids || []).map(String).map(s => s.trim()).filter(Boolean))];
        unique.forEach(id => {
            const chip = document.createElement('span');
            chip.className = 'shop-chip';
            chip.setAttribute('data-shop-id', id);
            chip.innerHTML = `${id} <button type="button" data-remove-shop="${id}" aria-label="Retirer ${id}">×</button>`;
            chipsEl.insertBefore(chip, chipInput);
        });
        syncShopIdsHidden();
    }

    function addShopId(raw) {
        const parts = String(raw || '').split(/[,\s]+/).map(s => s.trim()).filter(Boolean);
        if (!parts.length) return;
        renderChips([...currentShopIds(), ...parts]);
        if (chipInput) chipInput.value = '';
    }

    window.mkpSetShopIds = function (ids) {
        renderChips(ids);
    };

    function syncVisibility() {
        const driver = driverEl.value || 'mirakl';
        document.querySelectorAll('[data-show-when-driver]').forEach(el => {
            el.style.display = el.getAttribute('data-show-when-driver') === driver ? '' : 'none';
        });
        document.querySelectorAll('[data-mirakl-panel]').forEach(el => {
            el.style.display = driver === 'mirakl' ? '' : 'none';
        });
        if (driver !== 'mirakl' && shopIdsEl) {
            shopIdsEl.value = '';
        } else {
            syncShopIdsHidden();
        }
    }

    chipsEl?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-remove-shop]');
        if (!btn) return;
        const id = btn.getAttribute('data-remove-shop');
        renderChips(currentShopIds().filter(x => x !== id));
    });

    chipInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addShopId(chipInput.value.replace(/,/g, ' '));
        }
        if (e.key === 'Backspace' && !chipInput.value) {
            const ids = currentShopIds();
            if (ids.length) renderChips(ids.slice(0, -1));
        }
    });
    chipInput?.addEventListener('blur', () => addShopId(chipInput.value));

    driverEl.addEventListener('change', syncVisibility);
    document.querySelector('#mkp-form')?.closest('form')?.addEventListener('submit', () => {
        addShopId(chipInput?.value || '');
        syncVisibility();
    });
    syncVisibility();
})();
</script>
@endpush
@endonce
