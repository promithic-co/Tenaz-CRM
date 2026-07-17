<?php

it('uses the backend field contract in the contact list creation form', function () {
    $component = file_get_contents(resource_path('js/pages/listas-contato/Create.vue'));

    expect($component)
        ->toContain("name: ''")
        ->toContain("description: ''")
        ->toContain('v-model="form.name"')
        ->toContain('v-model="form.description"')
        ->toContain(':aria-invalid="!!form.errors.name"')
        ->toContain('v-if="form.errors.name"')
        ->toContain('{{ form.errors.name }}')
        ->toContain('is_dynamic: false as boolean')
        ->toContain("filters_json: { version: 1, match: 'all', rules: [] } as FiltersJson")
        ->toContain('form.post(store.url())')
        ->not->toContain("nome: ''")
        ->not->toContain("descricao: ''");
});
