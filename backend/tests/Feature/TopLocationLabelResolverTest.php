<?php

namespace Tests\Feature;

use App\Services\Analytics\Contracts\LocationLabelProviderInterface;
use App\Services\Analytics\TopLocationLabelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TopLocationLabelResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_nominatim_result_is_cached_and_second_call_skips_http(): void
    {
        Config::set('reports.location_labels.provider', 'nominatim');
        Config::set('reports.location_labels.inter_request_delay_ms', 0);
        Config::set('reports.location_labels.cache_ttl_days', 90);

        Http::fake([
            'nominatim.openstreetmap.org/reverse*' => Http::response([
                'type' => 'way',
                'address' => [
                    'city' => 'Riga',
                    'road' => 'Test Street',
                ],
            ], 200),
        ]);

        $this->app->forgetInstance(TopLocationLabelResolver::class);
        $this->app->forgetInstance(LocationLabelProviderInterface::class);

        $resolver = $this->app->make(TopLocationLabelResolver::class);

        $a = $resolver->resolveForCoordinates(56.95, 24.1);
        $b = $resolver->resolveForCoordinates(56.95, 24.1);

        $this->assertSame('Riga / Test Street area', $a);
        $this->assertSame($a, $b);
        Http::assertSentCount(1);
    }

    public function test_none_provider_returns_null_without_http(): void
    {
        Config::set('reports.location_labels.provider', 'none');

        Http::fake();

        $this->app->forgetInstance(TopLocationLabelResolver::class);
        $this->app->forgetInstance(LocationLabelProviderInterface::class);

        $resolver = $this->app->make(TopLocationLabelResolver::class);
        $this->assertNull($resolver->resolveForCoordinates(56.95, 24.1));
        Http::assertNothingSent();
    }
}
