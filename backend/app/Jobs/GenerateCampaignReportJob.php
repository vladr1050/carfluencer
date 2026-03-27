<?php

namespace App\Jobs;

use App\Enums\CampaignReportStatus;
use App\Models\CampaignReport;
use App\Services\Reports\CampaignReportDateSpan;
use App\Services\Reports\CampaignReportVehicleResolver;
use App\Services\Reports\Contracts\CampaignReportMetricsServiceInterface;
use App\Services\Reports\Contracts\CampaignReportPdfServiceInterface;
use App\Services\Reports\Contracts\HeatmapImageServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class GenerateCampaignReportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public int $campaignReportId
    ) {}

    public function handle(
        CampaignReportVehicleResolver $vehicleResolver,
        CampaignReportMetricsServiceInterface $metrics,
        HeatmapImageServiceInterface $heatmapImages,
        CampaignReportPdfServiceInterface $pdfService,
    ): void {
        $mem = (string) config('reports.php_memory_limit', '512M');
        if ($mem !== '' && $mem !== '0') {
            @ini_set('memory_limit', $mem);
        }

        $report = CampaignReport::query()->findOrFail($this->campaignReportId);
        $from = $report->date_from->format('Y-m-d');
        $to = $report->date_to->format('Y-m-d');

        try {
            CampaignReportDateSpan::assertWithinLimits($from, $to);
        } catch (ValidationException $e) {
            $report->update([
                'status' => CampaignReportStatus::Failed,
                'error_message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
            ]);

            return;
        }

        $report->update([
            'status' => CampaignReportStatus::Processing,
            'error_message' => null,
        ]);

        try {
            $campaign = $report->campaign()->with(['advertiser.advertiserProfile'])->firstOrFail();
            $vehicleIds = $vehicleResolver->resolveForCampaign($campaign->id);

            $kpis = $metrics->getKpis($campaign->id, $from, $to, $vehicleIds);

            $disk = Storage::disk('local');
            $baseRel = $report->storageDirectoryRelative();
            $disk->makeDirectory($baseRel);

            $baseAbs = $disk->path($baseRel);
            $drivingPath = $baseAbs.'/driving.png';
            $parkingPath = $baseAbs.'/parking.png';

            if ($report->include_driving_heatmap) {
                $heatmapImages->renderPng($campaign->id, $from, $to, $vehicleIds, 'driving', $drivingPath);
            }
            if ($report->include_parking_heatmap) {
                $heatmapImages->renderPng($campaign->id, $from, $to, $vehicleIds, 'parking', $parkingPath);
            }

            $advertiserName = $campaign->advertiser?->advertiserProfile?->company_name
                ?? $campaign->advertiser?->name
                ?? '—';

            $inputHash = hash('sha256', json_encode([
                $campaign->id,
                $from,
                $to,
                $vehicleIds,
            ], JSON_THROW_ON_ERROR));

            $snapshot = [
                'schema_version' => 1,
                'campaign_id' => $campaign->id,
                'date_from' => $from,
                'date_to' => $to,
                'vehicle_ids' => $vehicleIds,
                'input_hash' => $inputHash,
                'kpis' => $kpis,
                'campaign' => [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'advertiser_name' => $advertiserName,
                ],
                'assets' => [
                    'driving_png' => $report->include_driving_heatmap ? $baseRel.'/driving.png' : null,
                    'parking_png' => $report->include_parking_heatmap ? $baseRel.'/parking.png' : null,
                ],
                'settings' => [
                    'include_driving_heatmap' => $report->include_driving_heatmap,
                    'include_parking_heatmap' => $report->include_parking_heatmap,
                ],
            ];

            $pdfRel = $baseRel.'/report.pdf';
            $pdfAbs = $disk->path($pdfRel);

            $heatmapPaths = [];
            if ($report->include_driving_heatmap && is_file($drivingPath)) {
                $heatmapPaths['driving'] = $drivingPath;
            }
            if ($report->include_parking_heatmap && is_file($parkingPath)) {
                $heatmapPaths['parking'] = $parkingPath;
            }

            $pdfService->renderPdf($snapshot, $heatmapPaths, $pdfAbs);

            $snapshot['assets']['pdf'] = $pdfRel;

            $report->update([
                'report_data_json' => $snapshot,
                'file_path' => $pdfRel,
                'file_name' => 'campaign-'.$campaign->id.'-report-'.$report->id.'.pdf',
                'file_size' => @filesize($pdfAbs) ?: null,
                'generated_at' => now(),
                'status' => CampaignReportStatus::Done,
            ]);
        } catch (Throwable $e) {
            if ($e instanceof ValidationException) {
                $message = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            } else {
                $message = trim($e->getMessage()) !== ''
                    ? $e::class.': '.$e->getMessage()
                    : $e::class;
                Log::error('Campaign report generation failed', [
                    'campaign_report_id' => $this->campaignReportId,
                    'exception' => $e,
                ]);
            }

            $report->update([
                'status' => CampaignReportStatus::Failed,
                'error_message' => $message,
            ]);
        }
    }
}
