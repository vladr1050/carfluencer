<?php

namespace App\Services\Telemetry;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches advertiser dashboard metrics from a remote HTTP JSON API when configured.
 */
class HttpDashboardMetricsService implements DashboardMetricsServiceInterface
{
    public function __construct(
        private readonly MockDashboardMetricsService $fallback,
    ) {}

    public function advertiserSummary(User $user): array
    {
        $url = config('telemetry.metrics_url');

        if (! is_string($url) || $url === '') {
            return $this->withSource($this->fallback->advertiserSummary($user), 'mock');
        }

        try {
            $request = Http::timeout(8)->acceptJson();

            $token = config('telemetry.metrics_token');
            if (is_string($token) && $token !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->get($url, [
                'advertiser_id' => $user->id,
            ]);

            if (! $response->successful()) {
                Log::warning('Telemetry metrics HTTP non-success', ['status' => $response->status()]);

                return $this->withSource($this->fallback->advertiserSummary($user), 'mock_fallback');
            }

            $json = $response->json();
            if (! is_array($json)) {
                return $this->withSource($this->fallback->advertiserSummary($user), 'mock_fallback');
            }

            $campaigns = $user->campaignsAsAdvertiser()->get();
            $active = $campaigns->where('status', 'active')->count();

            return [
                'active_campaigns_count' => $active,
                'impressions' => (int) ($json['impressions'] ?? $json['impressions_total'] ?? 0),
                'driving_distance_km' => (float) ($json['driving_distance_km'] ?? 0),
                'driving_time_hours' => (float) ($json['driving_time_hours'] ?? 0),
                'parking_time_hours' => (float) ($json['parking_time_hours'] ?? 0),
                'note' => $json['note'] ?? null,
                'source' => 'http',
            ];
        } catch (\Throwable $e) {
            Log::warning('Telemetry metrics HTTP failed', ['error' => $e->getMessage()]);

            return $this->withSource($this->fallback->advertiserSummary($user), 'mock_fallback');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withSource(array $data, string $source): array
    {
        $data['source'] = $source;

        return $data;
    }
}
