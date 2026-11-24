<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductSheetGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductSheetGroupController extends Controller
{
    public function index(): View
    {
        return view('admin.product-sheet-groups.index', [
            'groups' => ProductSheetGroup::query()->orderBy('sort_order')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.product-sheet-groups.create');
    }

    public function store(Request $request): RedirectResponse
    {
        ProductSheetGroup::query()->create($this->validated($request));

        return redirect()->route('admin.product-sheet-groups.index')->with('status', 'Zone fiche créée.');
    }

    public function edit(ProductSheetGroup $productSheetGroup): View
    {
        return view('admin.product-sheet-groups.edit', [
            'group' => $productSheetGroup,
        ]);
    }

    public function update(Request $request, ProductSheetGroup $productSheetGroup): RedirectResponse
    {
        $productSheetGroup->update($this->validated($request, $productSheetGroup));

        return redirect()->route('admin.product-sheet-groups.index')->with('status', 'Zone fiche mise à jour.');
    }

    public function destroy(ProductSheetGroup $productSheetGroup): RedirectResponse
    {
        if ($productSheetGroup->fields()->exists()) {
            return redirect()->route('admin.product-sheet-groups.index')
                ->with('status', "Impossible de supprimer « {$productSheetGroup->name} » : utilisée par au moins un champ fiche.");
        }

        $name = $productSheetGroup->name;
        $productSheetGroup->delete();

        return redirect()->route('admin.product-sheet-groups.index')
            ->with('status', "Zone « {$name} » supprimée.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ProductSheetGroup $group = null): array
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_sheet_groups', 'name')->ignore($group?->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return [
            'name' => trim($data['name']),
            'sort_order' => (int) ($data['sort_order'] ?? 100),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
