<?php

namespace App\Filament\Resources\CampaignReports\Pages;

use App\Enums\CampaignReportStatus;
use App\Filament\Resources\CampaignReports\CampaignReportResource;
use App\Jobs\GenerateCampaignReportJob;
use App\Models\Campaign;
use App\Services\Reports\CampaignReportDateSpan;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateCampaignReport extends CreateRecord
{
    protected static string $resource = CampaignReportResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $campaign = Campaign::query()->findOrFail($data['campaign_id']);
        $data['advertiser_id'] = $campaign->advertiser_id;
        $data['created_by'] = auth()->id();
        $data['status'] = CampaignReportStatus::Queued;
        $data['report_type'] = 'single_period';

        $from = Carbon::parse($data['date_from'])->format('Y-m-d');
        $to = Carbon::parse($data['date_to'])->format('Y-m-d');
        try {
            CampaignReportDateSpan::assertWithinLimits($from, $to);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([
                'date_to' => $e->errors()['date_to'] ?? [__('Invalid date range.')],
            ]);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        dispatch(new GenerateCampaignReportJob($this->record->getKey()));
    }
}
