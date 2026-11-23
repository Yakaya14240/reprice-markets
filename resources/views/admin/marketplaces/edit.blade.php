@extends('layouts.admin')

@section('title', 'Éditer marketplace')

@push('styles')
<style>
    .wizard-steps {
        display: flex; gap: .5rem; flex-wrap: wrap; margin: 0 0 1rem;
    }
    .wizard-step-tab {
        flex: 1; min-width: 140px; border: 1px solid var(--border); border-radius: 10px;
        padding: .65rem .8rem; background: #111827; color: var(--muted); opacity: .55;
    }
    .wizard-step-tab.active { opacity: 1; color: var(--text); border-color: var(--accent); }
    .wizard-step-tab.done { opacity: .9; color: #86efac; }
    .wizard-step-tab .num {
        display: inline-flex; width: 1.4rem; height: 1.4rem; border-radius: 999px;
        align-items: center; justify-content: center; font-size: .8rem; font-weight: 700;
        background: #374151; color: #fff; margin-right: .4rem;
    }
    .wizard-step-tab.active .num { background: var(--accent); color: #0b1220; }
    .wizard-panel {
        border: 1px solid var(--border); border-radius: 10px; padding: .9rem;
        margin-bottom: .75rem; background: #1a2433;
    }
    .wizard-panel.is-locked {
        opacity: .45; pointer-events: none; filter: grayscale(.2);
    }
    .wizard-panel h3 { margin: 0 0 .55rem; font-size: .95rem; }
</style>
@endpush

@section('content')
    @php
        $isMirakl = $marketplace->driver === \App\Enums\MarketplaceDriver::Mirakl;
        $shopIds = $marketplace->shop_ids ?? [];
    @endphp
    <h1>Éditer {{ $marketplace->name }}</h1>
    <div class="card">
        <form method="POST" action="{{ route('admin.marketplaces.update', $marketplace) }}">
            @csrf
            @method('PUT')
            @include('admin.marketplaces._form', ['marketplace' => $marketplace])
            <div class="actions" style="margin-top:.5rem;">
                <button class="btn" type="submit">Enregistrer</button>
                <button
                    class="btn secondary"
                    type="button"
                    id="btn-test-marketplace"
                    data-url="{{ route('admin.marketplaces.test', $marketplace) }}"
                >Tester l’API</button>
                <span class="muted" id="mkp-test-result"></span>
            </div>
        </form>
        <form method="POST" action="{{ route('admin.marketplaces.destroy', $marketplace) }}" style="margin-top:.8rem;" onsubmit="return confirm('Supprimer {{ $marketplace->name }} ? Les mappings et historiques de push liés seront aussi supprimés.');">
            @csrf
            @method('DELETE')
            <button class="btn secondary" type="submit">Supprimer la marketplace</button>
        </form>
    </div>

    <div class="card" data-mirakl-panel @style(['display:none' => ! $isMirakl])>
        <h2 style="margin-top:0;">Mappings prix enregistrés</h2>
        <table>
            <thead>
            <tr><th>Shop</th><th>Channel</th><th>Colonne prix</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($marketplace->priceMappings as $mapping)
                <tr>
                    <td><code>{{ $mapping->shop_id }}</code></td>
                    <td>
                        @if ($mapping->hasChannel())
                            {{ $mapping->channelLabel() }}
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>
                        {{ $mapping->priceColumn?->name ?? '—' }}
                        <span class="muted">({{ $mapping->priceColumn?->csv_column }} / {{ $mapping->priceColumn?->feedFile?->name }})</span>
                    </td>
                    <td>
                        <form method="POST" action="{{ route('admin.marketplaces.price-mappings.destroy', [$marketplace, $mapping]) }}" onsubmit="return confirm('Supprimer ce mapping ?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn secondary" type="submit">Supprimer</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">Aucun mapping prix.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card" data-mirakl-panel @style(['display:none' => ! $isMirakl])>
        <h2 style="margin-top:0;">Ajouter un mapping</h2>
        <p class="muted" style="margin-top:0;">Suivez les 3 étapes dans l’ordre. Sans channel Mirakl : l’étape 2 se valide toute seule.</p>

        <div class="wizard-steps" id="mapping-wizard-tabs" aria-label="Étapes du mapping">
            <div class="wizard-step-tab active" data-tab="1"><span class="num">1</span> Shop</div>
            <div class="wizard-step-tab" data-tab="2"><span class="num">2</span> Channel</div>
            <div class="wizard-step-tab" data-tab="3"><span class="num">3</span> Prix</div>
        </div>

        <p class="muted" id="mirakl-channels-status"></p>

        <form method="POST" action="{{ route('admin.marketplaces.price-mappings.store', $marketplace) }}" id="form-add-price-mapping">
            @csrf
            <input type="hidden" name="channel_name" id="mirakl-channel-name" value="">

            <div class="wizard-panel" data-wizard-panel="1">
                <h3>1. Choisir le shop Mirakl</h3>
                <label for="mirakl-shop-select">Shop</label>
                <select name="shop_id" id="mirakl-shop-select" required>
                    <option value="">— Choisir un shop —</option>
                    @foreach ($shopIds as $shopId)
                        <option value="{{ $shopId }}">{{ $shopId }}</option>
                    @endforeach
                </select>
                <p class="muted" style="margin:0;">Les shops viennent du champ Shop ID(s) ci-dessus (chargez-les puis Enregistrer si besoin).</p>
            </div>

            <div class="wizard-panel is-locked" data-wizard-panel="2">
                <h3>2. Channel(s) du shop</h3>
                <label for="mirakl-channel-select">Channel</label>
                <select name="channel_code" id="mirakl-channel-select" disabled>
                    <option value="">— Choisir un shop d’abord —</option>
                </select>
            </div>

            <div class="wizard-panel is-locked" data-wizard-panel="3">
                <h3>3. Colonne prix</h3>
                <label for="mirakl-price-column">Colonne prix</label>
                <select name="price_column_id" id="mirakl-price-column" required disabled>
                    <option value="">—</option>
                    @foreach ($priceColumns as $column)
                        <option value="{{ $column->id }}">{{ $column->feedFile?->name }} — {{ $column->name }} ({{ $column->csv_column }})</option>
                    @endforeach
                </select>
                <button class="btn" type="submit" id="btn-add-price-mapping" disabled>Ajouter le mapping</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const channelsUrl = @json(route('admin.marketplaces.remote-channels', $marketplace));

    const testBtn = document.getElementById('btn-test-marketplace');
    const testResult = document.getElementById('mkp-test-result');
    testBtn?.addEventListener('click', async () => {
        if (!testResult) return;
        testResult.textContent = 'test…';
        testResult.style.color = '';
        testBtn.disabled = true;
        try {
            const res = await fetch(testBtn.dataset.url, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            });
            const data = await res.json();
            testResult.textContent = data.message || (res.ok ? 'OK' : 'Échec');
            testResult.style.color = data.ok ? '#86efac' : '#fca5a5';
        } catch (e) {
            testResult.textContent = e.message;
            testResult.style.color = '#fca5a5';
        } finally {
            testBtn.disabled = false;
        }
    });

    const loadShopsBtn = document.getElementById('btn-load-mirakl-shops');
    const shopIdsHidden = document.getElementById('mkp-shop-ids');
    const shopSelect = document.getElementById('mirakl-shop-select');
    const shopsStatus = document.getElementById('mirakl-shops-status');
    const channelSelect = document.getElementById('mirakl-channel-select');
    const channelNameInput = document.getElementById('mirakl-channel-name');
    const channelsStatus = document.getElementById('mirakl-channels-status');
    const priceColumnSelect = document.getElementById('mirakl-price-column');
    const addBtn = document.getElementById('btn-add-price-mapping');
    const mappingForm = document.getElementById('form-add-price-mapping');
    const wizardTabs = document.querySelectorAll('#mapping-wizard-tabs [data-tab]');
    const wizardPanels = document.querySelectorAll('[data-wizard-panel]');

    let channelsBlocked = false;
    let wizardStep = 1;

    function parseShopIds(value) {
        return (value || '').split(',').map(s => s.trim()).filter(Boolean);
    }

    function fillShopSelect(ids) {
        if (!shopSelect) return;
        const previous = shopSelect.value;
        shopSelect.innerHTML = '<option value="">— Choisir un shop —</option>';
        ids.forEach(id => {
            const opt = document.createElement('option');
            opt.value = id;
            opt.textContent = id;
            shopSelect.appendChild(opt);
        });
        if (previous && [...shopSelect.options].some(o => o.value === previous)) {
            shopSelect.value = previous;
        }
    }

    function setWizardStep(step) {
        wizardStep = step;
        wizardTabs.forEach(tab => {
            const n = Number(tab.getAttribute('data-tab'));
            tab.classList.toggle('active', n === step);
            tab.classList.toggle('done', n < step);
        });
        wizardPanels.forEach(panel => {
            const n = Number(panel.getAttribute('data-wizard-panel'));
            panel.classList.toggle('is-locked', n > step);
        });

        if (channelSelect) {
            channelSelect.disabled = step < 2 || channelsBlocked;
        }
        if (priceColumnSelect) {
            priceColumnSelect.disabled = step < 3;
        }
        if (addBtn) {
            addBtn.disabled = step < 3 || channelsBlocked;
        }
    }

    function resetFromStep1() {
        channelsBlocked = false;
        if (channelSelect) {
            channelSelect.innerHTML = '<option value="">— Choisir un shop d’abord —</option>';
            channelSelect.required = false;
            channelSelect.disabled = true;
        }
        if (channelNameInput) channelNameInput.value = '';
        if (priceColumnSelect) {
            priceColumnSelect.value = '';
            priceColumnSelect.disabled = true;
        }
        if (channelsStatus) {
            channelsStatus.textContent = '';
            channelsStatus.style.color = '';
        }
        setWizardStep(1);
    }

    function setChannelOptions(channels, shopId) {
        if (!channelSelect) return;
        channelSelect.innerHTML = '';
        if (!channels.length) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = 'Aucun channel — mapping shop + prix';
            channelSelect.appendChild(opt);
            channelSelect.required = false;
            if (channelNameInput) channelNameInput.value = '';
            return;
        }

        channelSelect.required = true;
        if (channels.length > 1) {
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = '— Choisir un channel —';
            channelSelect.appendChild(placeholder);
        }
        channels.forEach(ch => {
            const opt = document.createElement('option');
            opt.value = ch.code;
            opt.dataset.label = ch.label || ch.code;
            opt.textContent = ch.display || `${shopId} — ${ch.label || ch.code} (${ch.code})`;
            channelSelect.appendChild(opt);
        });
        if (channels.length === 1) {
            channelSelect.value = channels[0].code;
        }
        syncChannelName();
    }

    function syncChannelName() {
        if (!channelSelect || !channelNameInput) return;
        const opt = channelSelect.selectedOptions[0];
        channelNameInput.value = opt?.dataset?.label || '';
    }

    function maybeUnlockPriceStep() {
        if (channelsBlocked) {
            setWizardStep(2);
            return;
        }
        if (!channelSelect) {
            setWizardStep(3);
            return;
        }
        if (channelSelect.required && !channelSelect.value) {
            setWizardStep(2);
            return;
        }
        setWizardStep(3);
    }

    fillShopSelect(parseShopIds(shopIdsHidden?.value || ''));
    setWizardStep(1);

    document.addEventListener('mkp-shop-ids-changed', (e) => {
        const ids = parseShopIds(e.detail || '');
        fillShopSelect(ids);
        if (shopSelect && shopSelect.value && !ids.includes(shopSelect.value)) {
            shopSelect.value = '';
            resetFromStep1();
        }
    });

    loadShopsBtn?.addEventListener('click', async () => {
        if (shopsStatus) {
            shopsStatus.textContent = 'Chargement des shops…';
            shopsStatus.style.color = '';
        }
        loadShopsBtn.disabled = true;
        try {
            const res = await fetch(loadShopsBtn.dataset.url, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            });
            const data = await res.json();
            if (!data.ok) {
                throw new Error(data.message || 'Échec du chargement');
            }
            const ids = (data.shops || []).map(s => String(s.id));
            if (typeof window.mkpSetShopIds === 'function') {
                window.mkpSetShopIds(ids);
            } else if (shopIdsHidden) {
                shopIdsHidden.value = ids.join(',');
            }
            fillShopSelect(ids);
            if (shopsStatus) {
                shopsStatus.textContent = (data.message || 'Shops chargés.') + ' — pensez à Enregistrer.';
                shopsStatus.style.color = '#86efac';
            }
        } catch (e) {
            if (shopsStatus) {
                shopsStatus.textContent = e.message;
                shopsStatus.style.color = '#fca5a5';
            }
        } finally {
            loadShopsBtn.disabled = false;
        }
    });

    channelSelect?.addEventListener('change', () => {
        syncChannelName();
        maybeUnlockPriceStep();
    });

    shopSelect?.addEventListener('change', async () => {
        const shopId = shopSelect.value;
        if (!shopId) {
            resetFromStep1();
            return;
        }

        channelsBlocked = false;
        setWizardStep(2);
        if (channelsStatus) {
            channelsStatus.textContent = 'Chargement des channels…';
            channelsStatus.style.color = '';
        }
        if (channelSelect) {
            channelSelect.disabled = true;
            channelSelect.innerHTML = '<option value="">Chargement…</option>';
        }

        try {
            const url = new URL(channelsUrl, window.location.origin);
            url.searchParams.set('shop_id', shopId);
            const res = await fetch(url.toString(), {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            });
            const data = await res.json();
            if (!data.ok) {
                throw new Error(data.message || 'Échec du chargement des channels');
            }
            setChannelOptions(data.channels || [], shopId);
            channelSelect.disabled = false;
            const count = (data.channels || []).length;
            if (channelsStatus) {
                channelsStatus.textContent = data.message || (count
                    ? `${count} channel(s) — choisissez-en un pour continuer.`
                    : 'Aucun channel — passez à la colonne prix.');
                channelsStatus.style.color = '#86efac';
            }
            maybeUnlockPriceStep();
        } catch (e) {
            channelsBlocked = true;
            if (channelSelect) {
                channelSelect.innerHTML = '<option value="">— Channels indisponibles —</option>';
                channelSelect.required = false;
                channelSelect.disabled = true;
            }
            if (channelsStatus) {
                channelsStatus.textContent = e.message + ' — mapping bloqué pour éviter un mauvais prix.';
                channelsStatus.style.color = '#fca5a5';
            }
            setWizardStep(2);
        }
    });

    mappingForm?.addEventListener('submit', (e) => {
        if (channelsBlocked) {
            e.preventDefault();
            if (channelsStatus) {
                channelsStatus.textContent = 'Impossible d’enregistrer : channels indisponibles pour ce shop.';
                channelsStatus.style.color = '#fca5a5';
            }
            return;
        }
        if (wizardStep < 3) {
            e.preventDefault();
            if (channelsStatus) {
                channelsStatus.textContent = 'Terminez les étapes Shop → Channel → Prix avant d’ajouter.';
                channelsStatus.style.color = '#fca5a5';
            }
            return;
        }
        if (channelSelect?.required && !channelSelect.value) {
            e.preventDefault();
            if (channelsStatus) {
                channelsStatus.textContent = 'Sélectionnez un channel.';
                channelsStatus.style.color = '#fca5a5';
            }
        }
    });
})();
</script>
@endpush
