import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\VoiceCampaignController::index
 * @see app/Http/Controllers/VoiceCampaignController.php:16
 * @route '/campanhas-voz'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/campanhas-voz',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\VoiceCampaignController::index
 * @see app/Http/Controllers/VoiceCampaignController.php:16
 * @route '/campanhas-voz'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\VoiceCampaignController::index
 * @see app/Http/Controllers/VoiceCampaignController.php:16
 * @route '/campanhas-voz'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\VoiceCampaignController::index
 * @see app/Http/Controllers/VoiceCampaignController.php:16
 * @route '/campanhas-voz'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\VoiceCampaignController::index
 * @see app/Http/Controllers/VoiceCampaignController.php:16
 * @route '/campanhas-voz'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\VoiceCampaignController::index
 * @see app/Http/Controllers/VoiceCampaignController.php:16
 * @route '/campanhas-voz'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\VoiceCampaignController::index
 * @see app/Http/Controllers/VoiceCampaignController.php:16
 * @route '/campanhas-voz'
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
* @see \App\Http\Controllers\VoiceCampaignController::create
 * @see app/Http/Controllers/VoiceCampaignController.php:36
 * @route '/campanhas-voz/criar'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/campanhas-voz/criar',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\VoiceCampaignController::create
 * @see app/Http/Controllers/VoiceCampaignController.php:36
 * @route '/campanhas-voz/criar'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\VoiceCampaignController::create
 * @see app/Http/Controllers/VoiceCampaignController.php:36
 * @route '/campanhas-voz/criar'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\VoiceCampaignController::create
 * @see app/Http/Controllers/VoiceCampaignController.php:36
 * @route '/campanhas-voz/criar'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\VoiceCampaignController::create
 * @see app/Http/Controllers/VoiceCampaignController.php:36
 * @route '/campanhas-voz/criar'
 */
    const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\VoiceCampaignController::create
 * @see app/Http/Controllers/VoiceCampaignController.php:36
 * @route '/campanhas-voz/criar'
 */
        createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\VoiceCampaignController::create
 * @see app/Http/Controllers/VoiceCampaignController.php:36
 * @route '/campanhas-voz/criar'
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
* @see \App\Http\Controllers\VoiceCampaignController::store
 * @see app/Http/Controllers/VoiceCampaignController.php:49
 * @route '/campanhas-voz'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/campanhas-voz',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\VoiceCampaignController::store
 * @see app/Http/Controllers/VoiceCampaignController.php:49
 * @route '/campanhas-voz'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\VoiceCampaignController::store
 * @see app/Http/Controllers/VoiceCampaignController.php:49
 * @route '/campanhas-voz'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\VoiceCampaignController::store
 * @see app/Http/Controllers/VoiceCampaignController.php:49
 * @route '/campanhas-voz'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\VoiceCampaignController::store
 * @see app/Http/Controllers/VoiceCampaignController.php:49
 * @route '/campanhas-voz'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\VoiceCampaignController::show
 * @see app/Http/Controllers/VoiceCampaignController.php:61
 * @route '/campanhas-voz/{voiceCampaign}'
 */
export const show = (args: { voiceCampaign: number | { id: number } } | [voiceCampaign: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/campanhas-voz/{voiceCampaign}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\VoiceCampaignController::show
 * @see app/Http/Controllers/VoiceCampaignController.php:61
 * @route '/campanhas-voz/{voiceCampaign}'
 */
show.url = (args: { voiceCampaign: number | { id: number } } | [voiceCampaign: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { voiceCampaign: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { voiceCampaign: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    voiceCampaign: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        voiceCampaign: typeof args.voiceCampaign === 'object'
                ? args.voiceCampaign.id
                : args.voiceCampaign,
                }

    return show.definition.url
            .replace('{voiceCampaign}', parsedArgs.voiceCampaign.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\VoiceCampaignController::show
 * @see app/Http/Controllers/VoiceCampaignController.php:61
 * @route '/campanhas-voz/{voiceCampaign}'
 */
show.get = (args: { voiceCampaign: number | { id: number } } | [voiceCampaign: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\VoiceCampaignController::show
 * @see app/Http/Controllers/VoiceCampaignController.php:61
 * @route '/campanhas-voz/{voiceCampaign}'
 */
show.head = (args: { voiceCampaign: number | { id: number } } | [voiceCampaign: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\VoiceCampaignController::show
 * @see app/Http/Controllers/VoiceCampaignController.php:61
 * @route '/campanhas-voz/{voiceCampaign}'
 */
    const showForm = (args: { voiceCampaign: number | { id: number } } | [voiceCampaign: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\VoiceCampaignController::show
 * @see app/Http/Controllers/VoiceCampaignController.php:61
 * @route '/campanhas-voz/{voiceCampaign}'
 */
        showForm.get = (args: { voiceCampaign: number | { id: number } } | [voiceCampaign: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\VoiceCampaignController::show
 * @see app/Http/Controllers/VoiceCampaignController.php:61
 * @route '/campanhas-voz/{voiceCampaign}'
 */
        showForm.head = (args: { voiceCampaign: number | { id: number } } | [voiceCampaign: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\VoiceCampaignController::start
 * @see app/Http/Controllers/VoiceCampaignController.php:88
 * @route '/campanhas-voz/{voiceCampaign}/start'
 */
export const start = (args: { voiceCampaign: number | { id: number } } | [voiceCampaign: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: start.url(args, options),
    method: 'post',
})

start.definition = {
    methods: ["post"],
    url: '/campanhas-voz/{voiceCampaign}/start',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\VoiceCampaignController::start
 * @see app/Http/Controllers/VoiceCampaignController.php:88
 * @route '/campanhas-voz/{voiceCampaign}/start'
 */
start.url = (args: { voiceCampaign: number | { id: number } } | [voiceCampaign: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { voiceCampaign: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { voiceCampaign: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    voiceCampaign: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        voiceCampaign: typeof args.voiceCampaign === 'object'
                ? args.voiceCampaign.id
                : args.voiceCampaign,
                }

    return start.definition.url
            .replace('{voiceCampaign}', parsedArgs.voiceCampaign.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\VoiceCampaignController::start
 * @see app/Http/Controllers/VoiceCampaignController.php:88
 * @route '/campanhas-voz/{voiceCampaign}/start'
 */
start.post = (args: { voiceCampaign: number | { id: number } } | [voiceCampaign: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: start.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\VoiceCampaignController::start
 * @see app/Http/Controllers/VoiceCampaignController.php:88
 * @route '/campanhas-voz/{voiceCampaign}/start'
 */
    const startForm = (args: { voiceCampaign: number | { id: number } } | [voiceCampaign: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: start.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\VoiceCampaignController::start
 * @see app/Http/Controllers/VoiceCampaignController.php:88
 * @route '/campanhas-voz/{voiceCampaign}/start'
 */
        startForm.post = (args: { voiceCampaign: number | { id: number } } | [voiceCampaign: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: start.url(args, options),
            method: 'post',
        })
    
    start.form = startForm
/**
* @see \App\Http\Controllers\VoiceCampaignController::pause
 * @see app/Http/Controllers/VoiceCampaignController.php:101
 * @route '/campanhas-voz/{voiceCampaign}/pause'
 */
export const pause = (args: { voiceCampaign: number | { id: number } } | [voiceCampaign: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pause.url(args, options),
    method: 'post',
})

pause.definition = {
    methods: ["post"],
    url: '/campanhas-voz/{voiceCampaign}/pause',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\VoiceCampaignController::pause
 * @see app/Http/Controllers/VoiceCampaignController.php:101
 * @route '/campanhas-voz/{voiceCampaign}/pause'
 */
pause.url = (args: { voiceCampaign: number | { id: number } } | [voiceCampaign: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { voiceCampaign: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { voiceCampaign: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    voiceCampaign: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        voiceCampaign: typeof args.voiceCampaign === 'object'
                ? args.voiceCampaign.id
                : args.voiceCampaign,
                }

    return pause.definition.url
            .replace('{voiceCampaign}', parsedArgs.voiceCampaign.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\VoiceCampaignController::pause
 * @see app/Http/Controllers/VoiceCampaignController.php:101
 * @route '/campanhas-voz/{voiceCampaign}/pause'
 */
pause.post = (args: { voiceCampaign: number | { id: number } } | [voiceCampaign: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pause.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\VoiceCampaignController::pause
 * @see app/Http/Controllers/VoiceCampaignController.php:101
 * @route '/campanhas-voz/{voiceCampaign}/pause'
 */
    const pauseForm = (args: { voiceCampaign: number | { id: number } } | [voiceCampaign: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: pause.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\VoiceCampaignController::pause
 * @see app/Http/Controllers/VoiceCampaignController.php:101
 * @route '/campanhas-voz/{voiceCampaign}/pause'
 */
        pauseForm.post = (args: { voiceCampaign: number | { id: number } } | [voiceCampaign: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: pause.url(args, options),
            method: 'post',
        })
    
    pause.form = pauseForm
/**
* @see \App\Http\Controllers\VoiceCampaignController::resume
 * @see app/Http/Controllers/VoiceCampaignController.php:114
 * @route '/campanhas-voz/{voiceCampaign}/resume'
 */
export const resume = (args: { voiceCampaign: number | { id: number } } | [voiceCampaign: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resume.url(args, options),
    method: 'post',
})

resume.definition = {
    methods: ["post"],
    url: '/campanhas-voz/{voiceCampaign}/resume',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\VoiceCampaignController::resume
 * @see app/Http/Controllers/VoiceCampaignController.php:114
 * @route '/campanhas-voz/{voiceCampaign}/resume'
 */
resume.url = (args: { voiceCampaign: number | { id: number } } | [voiceCampaign: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { voiceCampaign: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { voiceCampaign: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    voiceCampaign: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        voiceCampaign: typeof args.voiceCampaign === 'object'
                ? args.voiceCampaign.id
                : args.voiceCampaign,
                }

    return resume.definition.url
            .replace('{voiceCampaign}', parsedArgs.voiceCampaign.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\VoiceCampaignController::resume
 * @see app/Http/Controllers/VoiceCampaignController.php:114
 * @route '/campanhas-voz/{voiceCampaign}/resume'
 */
resume.post = (args: { voiceCampaign: number | { id: number } } | [voiceCampaign: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resume.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\VoiceCampaignController::resume
 * @see app/Http/Controllers/VoiceCampaignController.php:114
 * @route '/campanhas-voz/{voiceCampaign}/resume'
 */
    const resumeForm = (args: { voiceCampaign: number | { id: number } } | [voiceCampaign: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: resume.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\VoiceCampaignController::resume
 * @see app/Http/Controllers/VoiceCampaignController.php:114
 * @route '/campanhas-voz/{voiceCampaign}/resume'
 */
        resumeForm.post = (args: { voiceCampaign: number | { id: number } } | [voiceCampaign: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: resume.url(args, options),
            method: 'post',
        })
    
    resume.form = resumeForm
const VoiceCampaignController = { index, create, store, show, start, pause, resume }

export default VoiceCampaignController