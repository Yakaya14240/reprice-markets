<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LanguageController extends Controller
{
    public function index(): View
    {
        return view('admin.languages.index', [
            'languages' => Language::query()->orderBy('sort_order')->orderBy('code')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.languages.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Language::query()->create($this->validated($request));

        return redirect()->route('admin.languages.index')->with('status', 'Langue créée.');
    }

    public function edit(Language $language): View
    {
        return view('admin.languages.edit', compact('language'));
    }

    public function update(Request $request, Language $language): RedirectResponse
    {
        $language->update($this->validated($request, $language));

        return redirect()->route('admin.languages.index')->with('status', 'Langue mise à jour.');
    }

    public function destroy(Language $language): RedirectResponse
    {
        if ($language->productSheetFields()->exists()) {
            return redirect()->route('admin.languages.index')
                ->with('status', "Impossible de supprimer « {$language->code} » : utilisée par au moins un champ fiche.");
        }

        $code = $language->code;
        $language->delete();

        return redirect()->route('admin.languages.index')
            ->with('status', "Langue « {$code} » supprimée.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Language $language = null): array
    {
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:5',
                Rule::unique('languages', 'code')->ignore($language?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return [
            'code' => strtoupper(trim($data['code'])),
            'name' => $data['name'],
            'sort_order' => (int) ($data['sort_order'] ?? 100),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
