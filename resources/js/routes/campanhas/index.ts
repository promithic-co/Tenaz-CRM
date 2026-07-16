import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
import qualityRisk from './quality-risk'
/**
* @see \App\Http\Controllers\CampaignController::index
 * @see app/Http/Controllers/CampaignController.php:22
 * @route '/campanhas'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/campanhas',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CampaignController::index
 * @see app/Http/Controllers/CampaignController.php:22
 * @route '/campanhas'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CampaignController::index
 * @see app/Http/Controllers/CampaignController.php:22
 * @route '/campanhas'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\CampaignController::index
 * @see app/Http/Controllers/CampaignController.php:22
 * @route '/campanhas'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\CampaignController::index
 * @see app/Http/Controllers/CampaignController.php:22
 * @route '/campanhas'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\CampaignController::index
 * @see app/Http/Controllers/CampaignController.php:22
 * @route '/campanhas'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\CampaignController::index
 * @see app/Http/Controllers/CampaignController.php:22
 * @route '/campanhas'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
/**
* @see \App\Http\Controllers\CampaignController::create
 * @see app/Http/Controllers/CampaignController.php:38
 * @route '/campanhas/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/campanhas/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CampaignController::create
 * @see app/Http/Controllers/CampaignController.php:38
 * @route '/campanhas/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CampaignController::create
 * @see app/Http/Controllers/CampaignController.php:38
 * @route '/campanhas/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\CampaignController::create
 * @see app/Http/Controllers/CampaignController.php:38
 * @route '/campanhas/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\CampaignController::create
 * @see app/Http/Controllers/CampaignController.php:38
 * @route '/campanhas/create'
 */
    const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\CampaignController::create
 * @see app/Http/Controllers/CampaignController.php:38
 * @route '/campanhas/create'
 */
        createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\CampaignController::create
 * @see app/Http/Controllers/CampaignController.php:38
 * @route '/campanhas/create'
 */
        createForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    create.form = createForm
/**
* @see \App\Http\Controllers\CampaignController::store
 * @see app/Http/Controllers/CampaignController.php:45
 * @route '/campanhas'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/campanhas',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CampaignController::store
 * @see app/Http/Controllers/CampaignController.php:45
 * @route '/campanhas'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CampaignController::store
 * @see app/Http/Controllers/CampaignController.php:45
 * @route '/campanhas'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CampaignController::store
 * @see app/Http/Controllers/CampaignController.php:45
 * @route '/campanhas'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CampaignController::store
 * @see app/Http/Controllers/CampaignController.php:45
 * @route '/campanhas'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\CampaignController::show
 * @see app/Http/Controllers/CampaignController.php:69
 * @route '/campanhas/{campanha}'
 */
export const show = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/campanhas/{campanha}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CampaignController::show
 * @see app/Http/Controllers/CampaignController.php:69
 * @route '/campanhas/{campanha}'
 */
show.url = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { campanha: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { campanha: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    campanha: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        campanha: typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
                }

    return show.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CampaignController::show
 * @see app/Http/Controllers/CampaignController.php:69
 * @route '/campanhas/{campanha}'
 */
show.get = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\CampaignController::show
 * @see app/Http/Controllers/CampaignController.php:69
 * @route '/campanhas/{campanha}'
 */
show.head = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\CampaignController::show
 * @see app/Http/Controllers/CampaignController.php:69
 * @route '/campanhas/{campanha}'
 */
    const showForm = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\CampaignController::show
 * @see app/Http/Controllers/CampaignController.php:69
 * @route '/campanhas/{campanha}'
 */
        showForm.get = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\CampaignController::show
 * @see app/Http/Controllers/CampaignController.php:69
 * @route '/campanhas/{campanha}'
 */
        showForm.head = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
/**
* @see \App\Http\Controllers\CampaignController::destroy
 * @see app/Http/Controllers/CampaignController.php:95
 * @route '/campanhas/{campanha}'
 */
export const destroy = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/campanhas/{campanha}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\CampaignController::destroy
 * @see app/Http/Controllers/CampaignController.php:95
 * @route '/campanhas/{campanha}'
 */
destroy.url = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { campanha: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { campanha: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    campanha: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        campanha: typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
                }

    return destroy.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CampaignController::destroy
 * @see app/Http/Controllers/CampaignController.php:95
 * @route '/campanhas/{campanha}'
 */
destroy.delete = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\CampaignController::destroy
 * @see app/Http/Controllers/CampaignController.php:95
 * @route '/campanhas/{campanha}'
 */
    const destroyForm = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CampaignController::destroy
 * @see app/Http/Controllers/CampaignController.php:95
 * @route '/campanhas/{campanha}'
 */
        destroyForm.delete = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
/**
* @see \App\Http\Controllers\CampaignController::update
 * @see app/Http/Controllers/CampaignController.php:76
 * @route '/campanhas/{campanha}'
 */
export const update = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/campanhas/{campanha}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\CampaignController::update
 * @see app/Http/Controllers/CampaignController.php:76
 * @route '/campanhas/{campanha}'
 */
update.url = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { campanha: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { campanha: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    campanha: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        campanha: typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
                }

    return update.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CampaignController::update
 * @see app/Http/Controllers/CampaignController.php:76
 * @route '/campanhas/{campanha}'
 */
update.patch = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\CampaignController::update
 * @see app/Http/Controllers/CampaignController.php:76
 * @route '/campanhas/{campanha}'
 */
    const updateForm = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CampaignController::update
 * @see app/Http/Controllers/CampaignController.php:76
 * @route '/campanhas/{campanha}'
 */
        updateForm.patch = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \App\Http\Controllers\CampaignController::start
 * @see app/Http/Controllers/CampaignController.php:109
 * @route '/campanhas/{campanha}/start'
 */
export const start = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: start.url(args, options),
    method: 'post',
})

start.definition = {
    methods: ["post"],
    url: '/campanhas/{campanha}/start',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CampaignController::start
 * @see app/Http/Controllers/CampaignController.php:109
 * @route '/campanhas/{campanha}/start'
 */
start.url = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { campanha: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { campanha: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    campanha: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        campanha: typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
                }

    return start.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CampaignController::start
 * @see app/Http/Controllers/CampaignController.php:109
 * @route '/campanhas/{campanha}/start'
 */
start.post = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: start.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CampaignController::start
 * @see app/Http/Controllers/CampaignController.php:109
 * @route '/campanhas/{campanha}/start'
 */
    const startForm = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: start.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CampaignController::start
 * @see app/Http/Controllers/CampaignController.php:109
 * @route '/campanhas/{campanha}/start'
 */
        startForm.post = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: start.url(args, options),
            method: 'post',
        })
    
    start.form = startForm
/**
* @see \App\Http\Controllers\CampaignController::pause
 * @see app/Http/Controllers/CampaignController.php:122
 * @route '/campanhas/{campanha}/pause'
 */
export const pause = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pause.url(args, options),
    method: 'post',
})

pause.definition = {
    methods: ["post"],
    url: '/campanhas/{campanha}/pause',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CampaignController::pause
 * @see app/Http/Controllers/CampaignController.php:122
 * @route '/campanhas/{campanha}/pause'
 */
pause.url = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { campanha: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { campanha: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    campanha: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        campanha: typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
                }

    return pause.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CampaignController::pause
 * @see app/Http/Controllers/CampaignController.php:122
 * @route '/campanhas/{campanha}/pause'
 */
pause.post = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pause.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CampaignController::pause
 * @see app/Http/Controllers/CampaignController.php:122
 * @route '/campanhas/{campanha}/pause'
 */
    const pauseForm = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: pause.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CampaignController::pause
 * @see app/Http/Controllers/CampaignController.php:122
 * @route '/campanhas/{campanha}/pause'
 */
        pauseForm.post = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: pause.url(args, options),
            method: 'post',
        })
    
    pause.form = pauseForm
/**
* @see \App\Http\Controllers\CampaignController::resume
 * @see app/Http/Controllers/CampaignController.php:135
 * @route '/campanhas/{campanha}/resume'
 */
export const resume = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resume.url(args, options),
    method: 'post',
})

resume.definition = {
    methods: ["post"],
    url: '/campanhas/{campanha}/resume',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CampaignController::resume
 * @see app/Http/Controllers/CampaignController.php:135
 * @route '/campanhas/{campanha}/resume'
 */
resume.url = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { campanha: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { campanha: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    campanha: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        campanha: typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
                }

    return resume.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CampaignController::resume
 * @see app/Http/Controllers/CampaignController.php:135
 * @route '/campanhas/{campanha}/resume'
 */
resume.post = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resume.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CampaignController::resume
 * @see app/Http/Controllers/CampaignController.php:135
 * @route '/campanhas/{campanha}/resume'
 */
    const resumeForm = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: resume.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CampaignController::resume
 * @see app/Http/Controllers/CampaignController.php:135
 * @route '/campanhas/{campanha}/resume'
 */
        resumeForm.post = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: resume.url(args, options),
            method: 'post',
        })
    
    resume.form = resumeForm
const campanhas = {
    index: Object.assign(index, index),
create: Object.assign(create, create),
store: Object.assign(store, store),
show: Object.assign(show, show),
destroy: Object.assign(destroy, destroy),
update: Object.assign(update, update),
start: Object.assign(start, start),
pause: Object.assign(pause, pause),
resume: Object.assign(resume, resume),
qualityRisk: Object.assign(qualityRisk, qualityRisk),
}

export default campanhas