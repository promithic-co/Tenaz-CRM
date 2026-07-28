import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
    applyUrlDefaults,
} from './../../../../wayfinder';
/**
 * @see \App\Http\Controllers\CampaignController::index
 * @see app/Http/Controllers/CampaignController.php:25
 * @route '/campanhas'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});

index.definition = {
    methods: ['get', 'head'],
    url: '/campanhas',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\CampaignController::index
 * @see app/Http/Controllers/CampaignController.php:25
 * @route '/campanhas'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\CampaignController::index
 * @see app/Http/Controllers/CampaignController.php:25
 * @route '/campanhas'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\CampaignController::index
 * @see app/Http/Controllers/CampaignController.php:25
 * @route '/campanhas'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\CampaignController::index
 * @see app/Http/Controllers/CampaignController.php:25
 * @route '/campanhas'
 */
const indexForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\CampaignController::index
 * @see app/Http/Controllers/CampaignController.php:25
 * @route '/campanhas'
 */
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\CampaignController::index
 * @see app/Http/Controllers/CampaignController.php:25
 * @route '/campanhas'
 */
indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

index.form = indexForm;
/**
 * @see \App\Http\Controllers\CampaignController::create
 * @see app/Http/Controllers/CampaignController.php:41
 * @route '/campanhas/create'
 */
export const create = (
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
});

create.definition = {
    methods: ['get', 'head'],
    url: '/campanhas/create',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\CampaignController::create
 * @see app/Http/Controllers/CampaignController.php:41
 * @route '/campanhas/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\CampaignController::create
 * @see app/Http/Controllers/CampaignController.php:41
 * @route '/campanhas/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\CampaignController::create
 * @see app/Http/Controllers/CampaignController.php:41
 * @route '/campanhas/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\CampaignController::create
 * @see app/Http/Controllers/CampaignController.php:41
 * @route '/campanhas/create'
 */
const createForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\CampaignController::create
 * @see app/Http/Controllers/CampaignController.php:41
 * @route '/campanhas/create'
 */
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\CampaignController::create
 * @see app/Http/Controllers/CampaignController.php:41
 * @route '/campanhas/create'
 */
createForm.head = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: create.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

create.form = createForm;
/**
 * @see \App\Http\Controllers\CampaignController::store
 * @see app/Http/Controllers/CampaignController.php:48
 * @route '/campanhas'
 */
export const store = (
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

store.definition = {
    methods: ['post'],
    url: '/campanhas',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\CampaignController::store
 * @see app/Http/Controllers/CampaignController.php:48
 * @route '/campanhas'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\CampaignController::store
 * @see app/Http/Controllers/CampaignController.php:48
 * @route '/campanhas'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::store
 * @see app/Http/Controllers/CampaignController.php:48
 * @route '/campanhas'
 */
const storeForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::store
 * @see app/Http/Controllers/CampaignController.php:48
 * @route '/campanhas'
 */
storeForm.post = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
});

store.form = storeForm;
/**
 * @see \App\Http\Controllers\CampaignController::show
 * @see app/Http/Controllers/CampaignController.php:72
 * @route '/campanhas/{campanha}'
 */
export const show = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
});

show.definition = {
    methods: ['get', 'head'],
    url: '/campanhas/{campanha}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\CampaignController::show
 * @see app/Http/Controllers/CampaignController.php:72
 * @route '/campanhas/{campanha}'
 */
show.url = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { campanha: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { campanha: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            campanha: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        campanha:
            typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
    };

    return (
        show.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\CampaignController::show
 * @see app/Http/Controllers/CampaignController.php:72
 * @route '/campanhas/{campanha}'
 */
show.get = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\CampaignController::show
 * @see app/Http/Controllers/CampaignController.php:72
 * @route '/campanhas/{campanha}'
 */
show.head = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\CampaignController::show
 * @see app/Http/Controllers/CampaignController.php:72
 * @route '/campanhas/{campanha}'
 */
const showForm = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\CampaignController::show
 * @see app/Http/Controllers/CampaignController.php:72
 * @route '/campanhas/{campanha}'
 */
showForm.get = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\CampaignController::show
 * @see app/Http/Controllers/CampaignController.php:72
 * @route '/campanhas/{campanha}'
 */
showForm.head = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: show.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

show.form = showForm;
/**
 * @see \App\Http\Controllers\CampaignController::destroy
 * @see app/Http/Controllers/CampaignController.php:98
 * @route '/campanhas/{campanha}'
 */
export const destroy = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
});

destroy.definition = {
    methods: ['delete'],
    url: '/campanhas/{campanha}',
} satisfies RouteDefinition<['delete']>;

/**
 * @see \App\Http\Controllers\CampaignController::destroy
 * @see app/Http/Controllers/CampaignController.php:98
 * @route '/campanhas/{campanha}'
 */
destroy.url = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { campanha: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { campanha: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            campanha: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        campanha:
            typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
    };

    return (
        destroy.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\CampaignController::destroy
 * @see app/Http/Controllers/CampaignController.php:98
 * @route '/campanhas/{campanha}'
 */
destroy.delete = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
});

/**
 * @see \App\Http\Controllers\CampaignController::destroy
 * @see app/Http/Controllers/CampaignController.php:98
 * @route '/campanhas/{campanha}'
 */
const destroyForm = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::destroy
 * @see app/Http/Controllers/CampaignController.php:98
 * @route '/campanhas/{campanha}'
 */
destroyForm.delete = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

destroy.form = destroyForm;
/**
 * @see \App\Http\Controllers\CampaignController::update
 * @see app/Http/Controllers/CampaignController.php:79
 * @route '/campanhas/{campanha}'
 */
export const update = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
});

update.definition = {
    methods: ['patch'],
    url: '/campanhas/{campanha}',
} satisfies RouteDefinition<['patch']>;

/**
 * @see \App\Http\Controllers\CampaignController::update
 * @see app/Http/Controllers/CampaignController.php:79
 * @route '/campanhas/{campanha}'
 */
update.url = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { campanha: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { campanha: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            campanha: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        campanha:
            typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
    };

    return (
        update.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\CampaignController::update
 * @see app/Http/Controllers/CampaignController.php:79
 * @route '/campanhas/{campanha}'
 */
update.patch = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
});

/**
 * @see \App\Http\Controllers\CampaignController::update
 * @see app/Http/Controllers/CampaignController.php:79
 * @route '/campanhas/{campanha}'
 */
const updateForm = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::update
 * @see app/Http/Controllers/CampaignController.php:79
 * @route '/campanhas/{campanha}'
 */
updateForm.patch = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

update.form = updateForm;
/**
 * @see \App\Http\Controllers\CampaignController::start
 * @see app/Http/Controllers/CampaignController.php:112
 * @route '/campanhas/{campanha}/start'
 */
export const start = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: start.url(args, options),
    method: 'post',
});

start.definition = {
    methods: ['post'],
    url: '/campanhas/{campanha}/start',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\CampaignController::start
 * @see app/Http/Controllers/CampaignController.php:112
 * @route '/campanhas/{campanha}/start'
 */
start.url = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { campanha: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { campanha: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            campanha: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        campanha:
            typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
    };

    return (
        start.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\CampaignController::start
 * @see app/Http/Controllers/CampaignController.php:112
 * @route '/campanhas/{campanha}/start'
 */
start.post = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: start.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::start
 * @see app/Http/Controllers/CampaignController.php:112
 * @route '/campanhas/{campanha}/start'
 */
const startForm = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: start.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::start
 * @see app/Http/Controllers/CampaignController.php:112
 * @route '/campanhas/{campanha}/start'
 */
startForm.post = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: start.url(args, options),
    method: 'post',
});

start.form = startForm;
/**
 * @see \App\Http\Controllers\CampaignController::pause
 * @see app/Http/Controllers/CampaignController.php:125
 * @route '/campanhas/{campanha}/pause'
 */
export const pause = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: pause.url(args, options),
    method: 'post',
});

pause.definition = {
    methods: ['post'],
    url: '/campanhas/{campanha}/pause',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\CampaignController::pause
 * @see app/Http/Controllers/CampaignController.php:125
 * @route '/campanhas/{campanha}/pause'
 */
pause.url = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { campanha: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { campanha: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            campanha: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        campanha:
            typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
    };

    return (
        pause.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\CampaignController::pause
 * @see app/Http/Controllers/CampaignController.php:125
 * @route '/campanhas/{campanha}/pause'
 */
pause.post = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: pause.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::pause
 * @see app/Http/Controllers/CampaignController.php:125
 * @route '/campanhas/{campanha}/pause'
 */
const pauseForm = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: pause.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::pause
 * @see app/Http/Controllers/CampaignController.php:125
 * @route '/campanhas/{campanha}/pause'
 */
pauseForm.post = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: pause.url(args, options),
    method: 'post',
});

pause.form = pauseForm;
/**
 * @see \App\Http\Controllers\CampaignController::resume
 * @see app/Http/Controllers/CampaignController.php:138
 * @route '/campanhas/{campanha}/resume'
 */
export const resume = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: resume.url(args, options),
    method: 'post',
});

resume.definition = {
    methods: ['post'],
    url: '/campanhas/{campanha}/resume',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\CampaignController::resume
 * @see app/Http/Controllers/CampaignController.php:138
 * @route '/campanhas/{campanha}/resume'
 */
resume.url = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { campanha: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { campanha: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            campanha: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        campanha:
            typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
    };

    return (
        resume.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\CampaignController::resume
 * @see app/Http/Controllers/CampaignController.php:138
 * @route '/campanhas/{campanha}/resume'
 */
resume.post = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: resume.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::resume
 * @see app/Http/Controllers/CampaignController.php:138
 * @route '/campanhas/{campanha}/resume'
 */
const resumeForm = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: resume.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::resume
 * @see app/Http/Controllers/CampaignController.php:138
 * @route '/campanhas/{campanha}/resume'
 */
resumeForm.post = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: resume.url(args, options),
    method: 'post',
});

resume.form = resumeForm;
/**
 * @see \App\Http\Controllers\CampaignController::cancel
 * @see app/Http/Controllers/CampaignController.php:151
 * @route '/campanhas/{campanha}/cancel'
 */
export const cancel = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
});

cancel.definition = {
    methods: ['post'],
    url: '/campanhas/{campanha}/cancel',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\CampaignController::cancel
 * @see app/Http/Controllers/CampaignController.php:151
 * @route '/campanhas/{campanha}/cancel'
 */
cancel.url = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { campanha: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { campanha: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            campanha: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        campanha:
            typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
    };

    return (
        cancel.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\CampaignController::cancel
 * @see app/Http/Controllers/CampaignController.php:151
 * @route '/campanhas/{campanha}/cancel'
 */
cancel.post = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::cancel
 * @see app/Http/Controllers/CampaignController.php:151
 * @route '/campanhas/{campanha}/cancel'
 */
const cancelForm = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: cancel.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::cancel
 * @see app/Http/Controllers/CampaignController.php:151
 * @route '/campanhas/{campanha}/cancel'
 */
cancelForm.post = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: cancel.url(args, options),
    method: 'post',
});

cancel.form = cancelForm;
/**
 * @see \App\Http\Controllers\CampaignController::duplicate
 * @see app/Http/Controllers/CampaignController.php:164
 * @route '/campanhas/{campanha}/duplicate'
 */
export const duplicate = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: duplicate.url(args, options),
    method: 'post',
});

duplicate.definition = {
    methods: ['post'],
    url: '/campanhas/{campanha}/duplicate',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\CampaignController::duplicate
 * @see app/Http/Controllers/CampaignController.php:164
 * @route '/campanhas/{campanha}/duplicate'
 */
duplicate.url = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { campanha: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { campanha: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            campanha: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        campanha:
            typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
    };

    return (
        duplicate.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\CampaignController::duplicate
 * @see app/Http/Controllers/CampaignController.php:164
 * @route '/campanhas/{campanha}/duplicate'
 */
duplicate.post = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: duplicate.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::duplicate
 * @see app/Http/Controllers/CampaignController.php:164
 * @route '/campanhas/{campanha}/duplicate'
 */
const duplicateForm = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: duplicate.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::duplicate
 * @see app/Http/Controllers/CampaignController.php:164
 * @route '/campanhas/{campanha}/duplicate'
 */
duplicateForm.post = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: duplicate.url(args, options),
    method: 'post',
});

duplicate.form = duplicateForm;
/**
 * @see \App\Http\Controllers\CampaignController::updateThrottle
 * @see app/Http/Controllers/CampaignController.php:175
 * @route '/campanhas/{campanha}/throttle'
 */
export const updateThrottle = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: updateThrottle.url(args, options),
    method: 'patch',
});

updateThrottle.definition = {
    methods: ['patch'],
    url: '/campanhas/{campanha}/throttle',
} satisfies RouteDefinition<['patch']>;

/**
 * @see \App\Http\Controllers\CampaignController::updateThrottle
 * @see app/Http/Controllers/CampaignController.php:175
 * @route '/campanhas/{campanha}/throttle'
 */
updateThrottle.url = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { campanha: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { campanha: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            campanha: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        campanha:
            typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
    };

    return (
        updateThrottle.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\CampaignController::updateThrottle
 * @see app/Http/Controllers/CampaignController.php:175
 * @route '/campanhas/{campanha}/throttle'
 */
updateThrottle.patch = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: updateThrottle.url(args, options),
    method: 'patch',
});

/**
 * @see \App\Http\Controllers\CampaignController::updateThrottle
 * @see app/Http/Controllers/CampaignController.php:175
 * @route '/campanhas/{campanha}/throttle'
 */
const updateThrottleForm = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: updateThrottle.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::updateThrottle
 * @see app/Http/Controllers/CampaignController.php:175
 * @route '/campanhas/{campanha}/throttle'
 */
updateThrottleForm.patch = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: updateThrottle.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

updateThrottle.form = updateThrottleForm;
/**
 * @see \App\Http\Controllers\CampaignController::reprocessFailures
 * @see app/Http/Controllers/CampaignController.php:188
 * @route '/campanhas/{campanha}/reprocess-failures'
 */
export const reprocessFailures = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: reprocessFailures.url(args, options),
    method: 'post',
});

reprocessFailures.definition = {
    methods: ['post'],
    url: '/campanhas/{campanha}/reprocess-failures',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\CampaignController::reprocessFailures
 * @see app/Http/Controllers/CampaignController.php:188
 * @route '/campanhas/{campanha}/reprocess-failures'
 */
reprocessFailures.url = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { campanha: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { campanha: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            campanha: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        campanha:
            typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
    };

    return (
        reprocessFailures.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\CampaignController::reprocessFailures
 * @see app/Http/Controllers/CampaignController.php:188
 * @route '/campanhas/{campanha}/reprocess-failures'
 */
reprocessFailures.post = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: reprocessFailures.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::reprocessFailures
 * @see app/Http/Controllers/CampaignController.php:188
 * @route '/campanhas/{campanha}/reprocess-failures'
 */
const reprocessFailuresForm = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: reprocessFailures.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::reprocessFailures
 * @see app/Http/Controllers/CampaignController.php:188
 * @route '/campanhas/{campanha}/reprocess-failures'
 */
reprocessFailuresForm.post = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: reprocessFailures.url(args, options),
    method: 'post',
});

reprocessFailures.form = reprocessFailuresForm;
/**
 * @see \App\Http\Controllers\CampaignController::retryMessage
 * @see app/Http/Controllers/CampaignController.php:205
 * @route '/campanhas/{campanha}/messages/{message}/retry'
 */
export const retryMessage = (
    args:
        | {
              campanha: number | { id: number };
              message: number | { id: number };
          }
        | [campanha: number | { id: number }, message: number | { id: number }],
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: retryMessage.url(args, options),
    method: 'post',
});

retryMessage.definition = {
    methods: ['post'],
    url: '/campanhas/{campanha}/messages/{message}/retry',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\CampaignController::retryMessage
 * @see app/Http/Controllers/CampaignController.php:205
 * @route '/campanhas/{campanha}/messages/{message}/retry'
 */
retryMessage.url = (
    args:
        | {
              campanha: number | { id: number };
              message: number | { id: number };
          }
        | [campanha: number | { id: number }, message: number | { id: number }],
    options?: RouteQueryOptions,
) => {
    if (Array.isArray(args)) {
        args = {
            campanha: args[0],
            message: args[1],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        campanha:
            typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
        message:
            typeof args.message === 'object' ? args.message.id : args.message,
    };

    return (
        retryMessage.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace('{message}', parsedArgs.message.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\CampaignController::retryMessage
 * @see app/Http/Controllers/CampaignController.php:205
 * @route '/campanhas/{campanha}/messages/{message}/retry'
 */
retryMessage.post = (
    args:
        | {
              campanha: number | { id: number };
              message: number | { id: number };
          }
        | [campanha: number | { id: number }, message: number | { id: number }],
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: retryMessage.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::retryMessage
 * @see app/Http/Controllers/CampaignController.php:205
 * @route '/campanhas/{campanha}/messages/{message}/retry'
 */
const retryMessageForm = (
    args:
        | {
              campanha: number | { id: number };
              message: number | { id: number };
          }
        | [campanha: number | { id: number }, message: number | { id: number }],
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: retryMessage.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::retryMessage
 * @see app/Http/Controllers/CampaignController.php:205
 * @route '/campanhas/{campanha}/messages/{message}/retry'
 */
retryMessageForm.post = (
    args:
        | {
              campanha: number | { id: number };
              message: number | { id: number };
          }
        | [campanha: number | { id: number }, message: number | { id: number }],
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: retryMessage.url(args, options),
    method: 'post',
});

retryMessage.form = retryMessageForm;
/**
 * @see \App\Http\Controllers\CampaignController::removeRecipients
 * @see app/Http/Controllers/CampaignController.php:226
 * @route '/campanhas/{campanha}/remove-recipients'
 */
export const removeRecipients = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: removeRecipients.url(args, options),
    method: 'post',
});

removeRecipients.definition = {
    methods: ['post'],
    url: '/campanhas/{campanha}/remove-recipients',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\CampaignController::removeRecipients
 * @see app/Http/Controllers/CampaignController.php:226
 * @route '/campanhas/{campanha}/remove-recipients'
 */
removeRecipients.url = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { campanha: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { campanha: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            campanha: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        campanha:
            typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
    };

    return (
        removeRecipients.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\CampaignController::removeRecipients
 * @see app/Http/Controllers/CampaignController.php:226
 * @route '/campanhas/{campanha}/remove-recipients'
 */
removeRecipients.post = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: removeRecipients.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::removeRecipients
 * @see app/Http/Controllers/CampaignController.php:226
 * @route '/campanhas/{campanha}/remove-recipients'
 */
const removeRecipientsForm = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: removeRecipients.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::removeRecipients
 * @see app/Http/Controllers/CampaignController.php:226
 * @route '/campanhas/{campanha}/remove-recipients'
 */
removeRecipientsForm.post = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: removeRecipients.url(args, options),
    method: 'post',
});

removeRecipients.form = removeRecipientsForm;
/**
 * @see \App\Http\Controllers\CampaignController::exportMethod
 * @see app/Http/Controllers/CampaignController.php:244
 * @route '/campanhas/{campanha}/export'
 */
export const exportMethod = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: exportMethod.url(args, options),
    method: 'get',
});

exportMethod.definition = {
    methods: ['get', 'head'],
    url: '/campanhas/{campanha}/export',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\CampaignController::exportMethod
 * @see app/Http/Controllers/CampaignController.php:244
 * @route '/campanhas/{campanha}/export'
 */
exportMethod.url = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { campanha: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { campanha: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            campanha: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        campanha:
            typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
    };

    return (
        exportMethod.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\CampaignController::exportMethod
 * @see app/Http/Controllers/CampaignController.php:244
 * @route '/campanhas/{campanha}/export'
 */
exportMethod.get = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: exportMethod.url(args, options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\CampaignController::exportMethod
 * @see app/Http/Controllers/CampaignController.php:244
 * @route '/campanhas/{campanha}/export'
 */
exportMethod.head = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: exportMethod.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\CampaignController::exportMethod
 * @see app/Http/Controllers/CampaignController.php:244
 * @route '/campanhas/{campanha}/export'
 */
const exportMethodForm = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: exportMethod.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\CampaignController::exportMethod
 * @see app/Http/Controllers/CampaignController.php:244
 * @route '/campanhas/{campanha}/export'
 */
exportMethodForm.get = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: exportMethod.url(args, options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\CampaignController::exportMethod
 * @see app/Http/Controllers/CampaignController.php:244
 * @route '/campanhas/{campanha}/export'
 */
exportMethodForm.head = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: exportMethod.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

exportMethod.form = exportMethodForm;
/**
 * @see \App\Http\Controllers\CampaignController::keepPausedForQualityRisk
 * @see app/Http/Controllers/CampaignController.php:273
 * @route '/campanhas/{campanha}/quality-risk/keep-paused'
 */
export const keepPausedForQualityRisk = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: keepPausedForQualityRisk.url(args, options),
    method: 'post',
});

keepPausedForQualityRisk.definition = {
    methods: ['post'],
    url: '/campanhas/{campanha}/quality-risk/keep-paused',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\CampaignController::keepPausedForQualityRisk
 * @see app/Http/Controllers/CampaignController.php:273
 * @route '/campanhas/{campanha}/quality-risk/keep-paused'
 */
keepPausedForQualityRisk.url = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { campanha: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { campanha: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            campanha: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        campanha:
            typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
    };

    return (
        keepPausedForQualityRisk.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\CampaignController::keepPausedForQualityRisk
 * @see app/Http/Controllers/CampaignController.php:273
 * @route '/campanhas/{campanha}/quality-risk/keep-paused'
 */
keepPausedForQualityRisk.post = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: keepPausedForQualityRisk.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::keepPausedForQualityRisk
 * @see app/Http/Controllers/CampaignController.php:273
 * @route '/campanhas/{campanha}/quality-risk/keep-paused'
 */
const keepPausedForQualityRiskForm = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: keepPausedForQualityRisk.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::keepPausedForQualityRisk
 * @see app/Http/Controllers/CampaignController.php:273
 * @route '/campanhas/{campanha}/quality-risk/keep-paused'
 */
keepPausedForQualityRiskForm.post = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: keepPausedForQualityRisk.url(args, options),
    method: 'post',
});

keepPausedForQualityRisk.form = keepPausedForQualityRiskForm;
/**
 * @see \App\Http\Controllers\CampaignController::continueWithQualityRisk
 * @see app/Http/Controllers/CampaignController.php:286
 * @route '/campanhas/{campanha}/quality-risk/continue'
 */
export const continueWithQualityRisk = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: continueWithQualityRisk.url(args, options),
    method: 'post',
});

continueWithQualityRisk.definition = {
    methods: ['post'],
    url: '/campanhas/{campanha}/quality-risk/continue',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\CampaignController::continueWithQualityRisk
 * @see app/Http/Controllers/CampaignController.php:286
 * @route '/campanhas/{campanha}/quality-risk/continue'
 */
continueWithQualityRisk.url = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { campanha: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { campanha: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            campanha: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        campanha:
            typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
    };

    return (
        continueWithQualityRisk.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\CampaignController::continueWithQualityRisk
 * @see app/Http/Controllers/CampaignController.php:286
 * @route '/campanhas/{campanha}/quality-risk/continue'
 */
continueWithQualityRisk.post = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: continueWithQualityRisk.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::continueWithQualityRisk
 * @see app/Http/Controllers/CampaignController.php:286
 * @route '/campanhas/{campanha}/quality-risk/continue'
 */
const continueWithQualityRiskForm = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: continueWithQualityRisk.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\CampaignController::continueWithQualityRisk
 * @see app/Http/Controllers/CampaignController.php:286
 * @route '/campanhas/{campanha}/quality-risk/continue'
 */
continueWithQualityRiskForm.post = (
    args:
        | { campanha: number | { id: number } }
        | [campanha: number | { id: number }]
        | number
        | { id: number },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: continueWithQualityRisk.url(args, options),
    method: 'post',
});

continueWithQualityRisk.form = continueWithQualityRiskForm;
const CampaignController = {
    index,
    create,
    store,
    show,
    destroy,
    update,
    start,
    pause,
    resume,
    cancel,
    duplicate,
    updateThrottle,
    reprocessFailures,
    retryMessage,
    removeRecipients,
    exportMethod,
    keepPausedForQualityRisk,
    continueWithQualityRisk,
    export: exportMethod,
};

export default CampaignController;
