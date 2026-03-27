<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\KbArticle;
use App\Models\KbCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KbController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->search;

        if ($search) {
            $articles = KbArticle::with('category')
                ->published()
                ->where(fn ($q) =>
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('body', 'like', "%{$search}%")
                )
                ->orderBy('views', 'desc')
                ->limit(20)
                ->get();

            return Inertia::render('Client/KB/Index', [
                'categories' => [],
                'articles'   => $articles,
                'search'     => $search,
            ]);
        }

        $categories = KbCategory::active()
            ->with(['publishedArticles' => fn ($q) => $q->orderBy('sort_order')->orderBy('title')->limit(5)])
            ->get();

        return Inertia::render('Client/KB/Index', [
            'categories' => $categories,
            'articles'   => [],
            'search'     => '',
        ]);
    }

    public function show(KbArticle $kbArticle): Response
    {
        abort_unless($kbArticle->published, 404);

        $kbArticle->incrementViews();
        $kbArticle->load('category');

        $related = KbArticle::published()
            ->where('kb_category_id', $kbArticle->kb_category_id)
            ->where('id', '!=', $kbArticle->id)
            ->orderBy('views', 'desc')
            ->limit(4)
            ->get(['id', 'title', 'slug']);

        return Inertia::render('Client/KB/Show', [
            'article' => $kbArticle,
            'related' => $related,
        ]);
    }
}
