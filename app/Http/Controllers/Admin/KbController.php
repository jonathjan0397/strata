<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KbArticle;
use App\Models\KbCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class KbController extends Controller
{
    // ── Categories ──────────────────────────────────────────────────────────

    public function categories(): Response
    {
        return Inertia::render('Admin/KB/Categories', [
            'categories' => KbCategory::withCount('articles')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order'  => ['integer', 'min:0'],
        ]);

        $data['slug']   = Str::slug($data['name']);
        $data['active'] = true;

        KbCategory::create($data);

        return back()->with('flash', ['success' => 'Category created.']);
    }

    public function updateCategory(Request $request, KbCategory $kbCategory): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order'  => ['integer', 'min:0'],
            'active'      => ['boolean'],
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $kbCategory->update($data);

        return back()->with('flash', ['success' => 'Category updated.']);
    }

    public function destroyCategory(KbCategory $kbCategory): RedirectResponse
    {
        $kbCategory->delete();

        return back()->with('flash', ['success' => 'Category deleted.']);
    }

    // ── Articles ─────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $articles = KbArticle::with(['category', 'author'])
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->when($request->category, fn ($q, $c) => $q->where('kb_category_id', $c))
            ->when($request->has('published'), fn ($q) => $q->where('published', $request->boolean('published')))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/KB/Index', [
            'articles'   => $articles,
            'categories' => KbCategory::orderBy('name')->get(['id', 'name']),
            'filters'    => $request->only('search', 'category', 'published'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/KB/Edit', [
            'article'    => null,
            'categories' => KbCategory::active()->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kb_category_id' => ['required', 'exists:kb_categories,id'],
            'title'          => ['required', 'string', 'max:255'],
            'body'           => ['required', 'string'],
            'published'      => ['boolean'],
            'sort_order'     => ['integer', 'min:0'],
        ]);

        $data['slug']      = $this->uniqueSlug(Str::slug($data['title']));
        $data['author_id'] = $request->user()->id;

        $article = KbArticle::create($data);

        return redirect()->route('admin.kb.edit', $article)
            ->with('flash', ['success' => 'Article created.']);
    }

    public function edit(KbArticle $kbArticle): Response
    {
        return Inertia::render('Admin/KB/Edit', [
            'article'    => $kbArticle->load('category'),
            'categories' => KbCategory::active()->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, KbArticle $kbArticle): RedirectResponse
    {
        $data = $request->validate([
            'kb_category_id' => ['required', 'exists:kb_categories,id'],
            'title'          => ['required', 'string', 'max:255'],
            'body'           => ['required', 'string'],
            'published'      => ['boolean'],
            'sort_order'     => ['integer', 'min:0'],
        ]);

        if ($kbArticle->title !== $data['title']) {
            $data['slug'] = $this->uniqueSlug(Str::slug($data['title']), $kbArticle->id);
        }

        $kbArticle->update($data);

        return back()->with('flash', ['success' => 'Article saved.']);
    }

    public function destroy(KbArticle $kbArticle): RedirectResponse
    {
        $kbArticle->delete();

        return redirect()->route('admin.kb.index')
            ->with('flash', ['success' => 'Article deleted.']);
    }

    private function uniqueSlug(string $base, ?int $excludeId = null): string
    {
        $slug    = $base;
        $counter = 2;

        while (
            KbArticle::where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'],
        ]);

        $path = $request->file('image')->store('kb-images', 'public');

        return response()->json(['url' => Storage::disk('public')->url($path)]);
    }
}
