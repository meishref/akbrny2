<?php

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use App\Services\SiteSearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        private readonly SiteSearchService $search,
    ) {}

    public function siteSearch(Request $request)
    {
        return response()->json($this->search->searchHtml((string) $request->get('query', '')));
    }
}
