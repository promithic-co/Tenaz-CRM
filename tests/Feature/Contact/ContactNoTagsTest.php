<?php

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Contact has no tag surface (D1)', function () {
    test('POST /contacts/{id}/tags returns 404 because the route is gone', function () {
        $user = User::factory()->create();
        $contact = Contact::factory()->forTenant((string) $user->tenantId)->create();

        $this->actingAs($user)
            ->postJson('/contacts/'.$contact->id.'/tags', [
                'tag_names' => ['VIP'],
            ])
            ->assertNotFound();
    });

    test('Contact model no longer exposes the tags relation via HasTags', function () {
        $contact = new Contact;

        expect(method_exists($contact, 'tags'))->toBeFalse();
        expect(method_exists($contact, 'attachTag'))->toBeFalse();
    });
});
