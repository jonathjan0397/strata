<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Product;
use App\Models\Setting;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    /** Public home: hero + featured products + latest KB + announcements */
    public function home(): \Illuminate\Http\RedirectResponse|Response
    {
        if (auth()->check()) {
            return auth()->user()->isAdmin()
                ? redirect()->route('admin.dashboard')
                : redirect()->route('client.dashboard');
        }

        $products = Product::where('hidden', false)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(6)
            ->get(['id', 'name', 'description', 'type', 'price', 'setup_fee', 'billing_cycle']);

        $announcements = Announcement::where('published', true)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get(['id', 'title', 'body', 'published_at']);

        $categories = KbCategory::active()
            ->with(['publishedArticles' => fn ($q) => $q->select('id', 'kb_category_id', 'title', 'slug', 'views')->limit(3)])
            ->limit(4)
            ->get(['id', 'name', 'slug', 'description']);

        return Inertia::render('Portal/Home', [
            'products'      => $products,
            'announcements' => $announcements,
            'categories'    => $categories,
            'siteName'      => Setting::get('company_name', config('app.name')),
            'tagline'       => Setting::get('tagline', 'Professional hosting & services'),
        ]);
    }

    /** Public product catalog */
    public function products(): Response
    {
        $products = Product::where('hidden', false)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'type', 'price', 'setup_fee', 'billing_cycle']);

        return Inertia::render('Portal/Products', [
            'products' => $products,
            'siteName' => Setting::get('company_name', config('app.name')),
        ]);
    }

    /** Public knowledge base index */
    public function kb(): Response
    {
        $categories = KbCategory::active()
            ->with(['publishedArticles' => fn ($q) => $q->select('id', 'kb_category_id', 'title', 'slug', 'views')->orderBy('sort_order')])
            ->get(['id', 'name', 'slug', 'description']);

        return Inertia::render('Portal/KB/Index', [
            'categories' => $categories,
            'siteName'   => Setting::get('company_name', config('app.name')),
        ]);
    }

    /** Single KB article (public) */
    public function kbArticle(string $slug): Response
    {
        $article = KbArticle::where('slug', $slug)
            ->where('published', true)
            ->with(['category:id,name,slug', 'author:id,name'])
            ->firstOrFail();

        $article->incrementViews();

        $related = KbArticle::where('kb_category_id', $article->kb_category_id)
            ->where('id', '!=', $article->id)
            ->where('published', true)
            ->limit(4)
            ->get(['id', 'title', 'slug']);

        return Inertia::render('Portal/KB/Show', [
            'article'  => $article,
            'related'  => $related,
            'siteName' => Setting::get('company_name', config('app.name')),
        ]);
    }

    /** Public announcements */
    public function announcements(): Response
    {
        $announcements = Announcement::where('published', true)
            ->orderByDesc('published_at')
            ->paginate(10);

        return Inertia::render('Portal/Announcements', [
            'announcements' => $announcements,
            'siteName'      => Setting::get('company_name', config('app.name')),
        ]);
    }
}
