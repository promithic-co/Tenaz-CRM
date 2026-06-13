<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\ContactListEntry;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;

class CampaignReplyDetector
{
    /**
     * Detect if this incoming message is a reply from a campaign recipient.
     * If so, links the lead to the campaign and returns the matched campaign.
     * Returns null if no active campaign is found for this phone/tenant combination.
     */
    public function detect(Lead $lead, string $phone, string $tenantId): ?Campaign
    {
        // Already linked — check the existing campaign is still relevant
        if ($lead->campaign_id) {
            $campaign = Campaign::find($lead->campaign_id);

            if ($campaign && in_array($campaign->status, ['sending', 'paused'])) {
                return $campaign;
            }
        }

        // Look for a ContactListEntry with this phone in an active campaign's contact list
        $entry = ContactListEntry::where('phone', $phone)
            ->whereHas('contactList.campaigns', function ($query) use ($tenantId): void {
                $query->where('tenant_id', $tenantId)
                    ->whereIn('status', ['sending', 'paused']);
            })
            ->first();

        if (! $entry) {
            return null;
        }

        // Get the most recent active campaign using this contact list
        $campaign = Campaign::where('tenant_id', $tenantId)
            ->where('contact_list_id', $entry->contact_list_id)
            ->whereIn('status', ['sending', 'paused'])
            ->latest()
            ->first();

        if (! $campaign) {
            return null;
        }

        // Link lead to campaign
        if ($lead->campaign_id !== $campaign->id) {
            $lead->update(['campaign_id' => $campaign->id]);

            Log::info('CampaignReplyDetector: linked lead to campaign', [
                'lead_id' => $lead->id,
                'campaign_id' => $campaign->id,
                'phone' => $phone,
            ]);
        }

        return $campaign;
    }
}
