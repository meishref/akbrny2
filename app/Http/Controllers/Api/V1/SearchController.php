<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SiteSearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        private readonly SiteSearchService $search,
    ) {}

    /**
     * GET /api/v1/search?query=
     */
    public function index(Request $request)
    {
        return response()->json($this->search->searchJson((string) $request->query('query', '')));
    }
}
