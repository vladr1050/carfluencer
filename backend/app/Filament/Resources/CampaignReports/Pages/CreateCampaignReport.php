<?php

namespace App\Filament\Resources\CampaignReports\Pages;

use App\Enums\CampaignReportStatus;
use App\Filament\Resources\CampaignReports\CampaignReportResource;
use App\Jobs\GenerateCampaignReportJob;
use App\Models\Campaign;
use Filament\Resources\Pages\CreateRecord;

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

        return $data;
    }

    protected function afterCreate(): void
    {
        dispatch(new GenerateCampaignReportJob($this->record->getKey()));
    }
}
