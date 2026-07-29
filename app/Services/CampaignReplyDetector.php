<?php

namespace App\Services;

use App\Console\Commands\BackfillCampaignReplyLinksCommand;
use App\Models\Campaign;
use App\Models\ContactListEntry;
use App\Models\Lead;
use App\Services\WhatsApp\PhoneNumberValidator;
use Illuminate\Support\Facades\Log;

class CampaignReplyDetector
{
    /**
     * Campaigns a live inbound message may be attributed to. A reply belongs to the run
     * that is still going; attributing it to a campaign that finished long ago would
     * reopen closed reporting.
     *
     * @var list<string>
     */
    public const LIVE_STATUSES = ['sending', 'paused'];

    /**
     * Every status the historical repair may attribute to.
     *
     * {@see BackfillCampaignReplyLinksCommand} passes this instead of
     * LIVE_STATUSES: a reply that arrived while the campaign was still sending is a reply to
     * it regardless of the status the campaign carries by the time the repair runs.
     *
     * @var list<string>
     */
    public const HISTORICAL_STATUSES = ['sending', 'paused', 'completed', 'failed'];

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

            if ($campaign && in_array($campaign->status, self::LIVE_STATUSES)) {
                return $campaign;
            }
        }

        $campaign = $this->resolveCampaign($phone, $tenantId);

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

    /**
     * The campaign that reached this phone, without writing anything.
     *
     * Split out of detect() so the historical repair resolves through the identical rules
     * and can still report a --dry-run. Widening $statuses is the only difference between
     * the live path and the repair.
     *
     * @param  list<string>  $statuses
     */
    public function resolveCampaign(string $phone, string $tenantId, array $statuses = self::LIVE_STATUSES): ?Campaign
    {
        // Look for a ContactListEntry with this phone in a matching campaign's contact list.
        // Matched across every 9th-digit form: the lead's phone comes from the inbound
        // webhook and the entry from a CSV import, and the two routinely disagree about it
        // — an exact match silently drops the reply, leaving the recipient unlinked from
        // the campaign that reached them. Same reconciliation CampaignConversationTimelineWriter
        // already applies to the outbound mirror.
        $entry = ContactListEntry::whereIn('phone', PhoneNumberValidator::variants($phone))
            ->whereHas('contactList.campaigns', function ($query) use ($tenantId, $statuses): void {
                $query->where('tenant_id', $tenantId)
                    ->whereIn('status', $statuses);
            })
            ->first();

        if (! $entry) {
            return null;
        }

        // Get the most recent matching campaign using this contact list
        return Campaign::where('tenant_id', $tenantId)
            ->where('contact_list_id', $entry->contact_list_id)
            ->whereIn('status', $statuses)
            ->latest()
            ->first();
    }
}
