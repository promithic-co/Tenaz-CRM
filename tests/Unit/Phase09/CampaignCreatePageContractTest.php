<?php

test('campaign wizard filters and clears templates by exact selected instance', function () {
    $component = file_get_contents(resource_path('js/pages/campanhas/Create.vue'));

    expect(preg_match(
        '/const filteredTemplates = computed\(\(\) => \{.*?if \(!form\.whatsapp_instance_id.*?return \[\];/s',
        $component,
    ))->toBe(1);

    expect($component)->toContain('t.whatsapp_instance_id === selectedInstanceId');

    expect(preg_match(
        '/selectedTemplate\.value\.whatsapp_instance_id\s*!==\s*selectedInstanceId/',
        $component,
    ))->toBe(1);
});
