<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StaticPageCacheTest extends TestCase
{
    public function test_static_pages_are_cached(): void
    {
        Cache::flush();

        $this->get(route('pages.contact'))->assertSuccessful();
        $this->assertTrue(Cache::has('static.page.contact'));

        $this->get(route('pages.privacy-policy'))->assertSuccessful();
        $this->assertTrue(Cache::has('static.page.privacy-policy'));

        $this->get(route('pages.terms'))->assertSuccessful();
        $this->assertTrue(Cache::has('static.page.terms'));
    }
}
