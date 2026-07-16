<?php

use App\Models\ContactList;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->ownerTenantId = (int) $this->owner->tenantId;
    $this->foreignOwner = User::factory()->create();
    $this->foreignTenantId = (int) $this->foreignOwner->tenantId;

    $this->instance = WhatsappInstance::factory()->create([
        'user_id' => $this->owner->id,
        'tenant_id' => $this->ownerTenantId,
    ]);
    $this->contactList = ContactList::factory()->create([
        'tenant_id' => $this->ownerTenantId,
    ]);
    $this->foreignContactList = ContactList::factory()->create([
        'tenant_id' => $this->foreignTenantId,
    ]);
    $this->template = WhatsappTemplate::factory()->create([
        'tenant_id' => $this->ownerTenantId,
    ]);
    $this->foreignTemplate = WhatsappTemplate::factory()->create([
        'tenant_id' => $this->foreignTenantId,
    ]);
});

it('allows a campaign to reference a contact list and template from the same tenant', function () {
    DB::table('campaigns')->insert(campaignIntegrityAttributes(
        tenantId: $this->ownerTenantId,
        contactListId: $this->contactList->id,
        templateId: $this->template->id,
        instanceId: $this->instance->id,
    ));

    expect(DB::table('campaigns')->count())->toBe(1);
});

it('rejects a campaign contact list from another tenant at the database boundary', function () {
    expect(fn () => DB::table('campaigns')->insert(campaignIntegrityAttributes(
        tenantId: $this->ownerTenantId,
        contactListId: $this->foreignContactList->id,
        templateId: $this->template->id,
        instanceId: $this->instance->id,
    )))->toThrow(QueryException::class);
});

it('rejects a campaign template from another tenant at the database boundary', function () {
    expect(fn () => DB::table('campaigns')->insert(campaignIntegrityAttributes(
        tenantId: $this->ownerTenantId,
        contactListId: $this->contactList->id,
        templateId: $this->foreignTemplate->id,
        instanceId: $this->instance->id,
    )))->toThrow(QueryException::class);
});

/**
 * @return array<string, mixed>
 */
function campaignIntegrityAttributes(int $tenantId, int $contactListId, int $templateId, int $instanceId): array
{
    return [
        'tenant_id' => $tenantId,
        'whatsapp_instance_id' => $instanceId,
        'contact_list_id' => $contactListId,
        'whatsapp_template_id' => $templateId,
        'name' => 'Integrity test campaign',
        'created_at' => now(),
        'updated_at' => now(),
    ];
}
