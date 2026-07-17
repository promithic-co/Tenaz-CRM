<?php

it('resolves the Portuguese paginator labels globally', function () {
    app()->setLocale('pt_BR');

    expect(__('pagination.previous'))->toBe('Anterior')
        ->and(__('pagination.next'))->toBe('Próximo');
});
