<?php

declare(strict_types=1);

namespace SpCompta\Tests\Unit\Front;

use SpCompta\Front\ServiceWorker;
use WP_UnitTestCase;

/**
 * Scenarios BDD documentes dans src/Front/ServiceWorker.md
 */
final class ServiceWorkerTest extends WP_UnitTestCase
{
    /** @test */
    public function it_matches_a_request_for_sw_js(): void
    {
        // Given a request for /sw.js
        $serviceWorker = new ServiceWorker();

        // When matchesRequest is called
        $result = $serviceWorker->matchesRequest('/sw.js');

        // Then it matches
        $this->assertTrue($result);
    }

    /** @test */
    public function it_matches_a_request_for_sw_js_with_a_query_string(): void
    {
        // Given a request for /sw.js with a cache-busting query string
        $serviceWorker = new ServiceWorker();

        // When matchesRequest is called
        $result = $serviceWorker->matchesRequest('/sw.js?ver=2');

        // Then it still matches (only the path is compared, not the query string)
        $this->assertTrue($result);
    }

    /** @test */
    public function it_does_not_match_an_unrelated_path(): void
    {
        // Given a request for a different page
        $serviceWorker = new ServiceWorker();

        // When matchesRequest is called
        $result = $serviceWorker->matchesRequest('/saisie-rapide/');

        // Then it does not match
        $this->assertFalse($result);
    }

    /** @test */
    public function its_url_points_to_the_site_root(): void
    {
        // Given the site's home url
        // When url is called
        $url = ServiceWorker::url();

        // Then it points to /sw.js under the site's own domain
        $this->assertSame(home_url('/sw.js'), $url);
    }

    /** @test */
    public function its_contents_register_a_fetch_listener_without_caching_anything(): void
    {
        // Given the static service worker script
        // When contents is called
        $js = ServiceWorker::contents();

        // Then it registers install/activate/fetch listeners, with no cache API usage
        $this->assertStringContainsString("addEventListener('fetch'", $js);
        $this->assertStringNotContainsString('caches.open', $js);
    }
}
