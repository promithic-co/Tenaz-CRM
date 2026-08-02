<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderCustomFieldsRequest;
use App\Http\Requests\StoreCustomFieldRequest;
use App\Http\Requests\UpdateCustomFieldRequest;
use App\Models\CustomField;
use App\Services\CustomFieldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administration of the tenant's extra lead fields.
 *
 * Every endpoint is behind the `role:owner,administrator` middleware applied in
 * routes/web.php. Operators consume these definitions from the conversation panel
 * (ConversasController::updateCustomFields) but never define them.
 */
class CustomFieldController extends Controller
{
    public function __construct(
        private readonly CustomFieldService $service,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('settings/campos/Index', [
            'fields' => $this->service->definitionsPayload((string) $request->user()->tenantId),
            'types' => CustomFieldService::TYPES,
            'max_fields' => CustomFieldService::MAX_FIELDS,
        ]);
    }

    public function store(StoreCustomFieldRequest $request): RedirectResponse
    {
        $this->service->create((string) $request->user()->tenantId, $request->validated());

        return back()->with('flash', 'Campo criado.');
    }

    public function update(UpdateCustomFieldRequest $request, CustomField $customField): RedirectResponse
    {
        $this->assertBelongsToTenant($request, $customField);

        $this->service->update($customField, $request->validated());

        return back()->with('flash', 'Campo atualizado.');
    }

    public function destroy(Request $request, CustomField $customField): RedirectResponse
    {
        $this->assertBelongsToTenant($request, $customField);

        $this->service->delete($customField);

        return back()->with('flash', 'Campo removido.');
    }

    public function reorder(ReorderCustomFieldsRequest $request): RedirectResponse
    {
        $this->service->reorder((string) $request->user()->tenantId, $request->validated('ids'));

        return back();
    }

    /**
     * `custom_fields` carries no global tenant scope, so route-model binding will
     * happily hand over another tenant's row. A 404 keeps the answer the same
     * whether the id is foreign or simply gone.
     */
    private function assertBelongsToTenant(Request $request, CustomField $field): void
    {
        abort_unless(
            (string) $field->tenant_id === (string) $request->user()->tenantId
                && $field->entity_type === CustomFieldService::ENTITY_LEAD,
            404,
        );
    }
}
