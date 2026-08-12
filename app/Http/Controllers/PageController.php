<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;

class PageController extends Controller
{
    private const CACHE_TTL_SECONDS = 86400;

    public function contact()
    {
        return $this->cachedView('pages.contact', 'static.page.contact');
    }

    public function privacyPolicy()
    {
        return $this->cachedView('pages.privacy-policy', 'static.page.privacy-policy');
    }

    public function terms()
    {
        return $this->cachedView('pages.terms', 'static.page.terms');
    }

    private function cachedView(string $view, string $cacheKey)
    {
        $html = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, fn () => view($view)->render());

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
