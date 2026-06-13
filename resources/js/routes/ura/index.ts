import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\UraInboundController::inboundLead
 * @see app/Http/Controllers/UraInboundController.php:14
 * @route '/api/ura/inbound-lead'
 */
export const inboundLead = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: inboundLead.url(options),
    method: 'post',
})

inboundLead.definition = {
    methods: ["post"],
    url: '/api/ura/inbound-lead',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\UraInboundController::inboundLead
 * @see app/Http/Controllers/UraInboundController.php:14
 * @route '/api/ura/inbound-lead'
 */
inboundLead.url = (options?: RouteQueryOptions) => {
    return inboundLead.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\UraInboundController::inboundLead
 * @see app/Http/Controllers/UraInboundController.php:14
 * @route '/api/ura/inbound-lead'
 */
inboundLead.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: inboundLead.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\UraInboundController::inboundLead
 * @see app/Http/Controllers/UraInboundController.php:14
 * @route '/api/ura/inbound-lead'
 */
    const inboundLeadForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: inboundLead.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\UraInboundController::inboundLead
 * @see app/Http/Controllers/UraInboundController.php:14
 * @route '/api/ura/inbound-lead'
 */
        inboundLeadForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: inboundLead.url(options),
            method: 'post',
        })
    
    inboundLead.form = inboundLeadForm
/**
* @see \App\Http\Controllers\UraInboundController::trigger
 * @see app/Http/Controllers/UraInboundController.php:29
 * @route '/api/ura/trigger'
 */
export const trigger = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: trigger.url(options),
    method: 'post',
})

trigger.definition = {
    methods: ["post"],
    url: '/api/ura/trigger',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\UraInboundController::trigger
 * @see app/Http/Controllers/UraInboundController.php:29
 * @route '/api/ura/trigger'
 */
trigger.url = (options?: RouteQueryOptions) => {
    return trigger.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\UraInboundController::trigger
 * @see app/Http/Controllers/UraInboundController.php:29
 * @route '/api/ura/trigger'
 */
trigger.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: trigger.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\UraInboundController::trigger
 * @see app/Http/Controllers/UraInboundController.php:29
 * @route '/api/ura/trigger'
 */
    const triggerForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: trigger.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\UraInboundController::trigger
 * @see app/Http/Controllers/UraInboundController.php:29
 * @route '/api/ura/trigger'
 */
        triggerForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: trigger.url(options),
            method: 'post',
        })
    
    trigger.form = triggerForm
/**
* @see \App\Http\Controllers\UraApiKeyController::index
 * @see app/Http/Controllers/UraApiKeyController.php:17
 * @route '/ura'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/ura',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\UraApiKeyController::index
 * @see app/Http/Controllers/UraApiKeyController.php:17
 * @route '/ura'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\UraApiKeyController::index
 * @see app/Http/Controllers/UraApiKeyController.php:17
 * @route '/ura'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\UraApiKeyController::index
 * @see app/Http/Controllers/UraApiKeyController.php:17
 * @route '/ura'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\UraApiKeyController::index
 * @see app/Http/Controllers/UraApiKeyController.php:17
 * @route '/ura'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\UraApiKeyController::index
 * @see app/Http/Controllers/UraApiKeyController.php:17
 * @route '/ura'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\UraApiKeyController::index
 * @see app/Http/Controllers/UraApiKeyController.php:17
 * @route '/ura'
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
* @see \App\Http\Controllers\UraApiKeyController::store
 * @see app/Http/Controllers/UraApiKeyController.php:45
 * @route '/ura'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/ura',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\UraApiKeyController::store
 * @see app/Http/Controllers/UraApiKeyController.php:45
 * @route '/ura'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\UraApiKeyController::store
 * @see app/Http/Controllers/UraApiKeyController.php:45
 * @route '/ura'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\UraApiKeyController::store
 * @see app/Http/Controllers/UraApiKeyController.php:45
 * @route '/ura'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\UraApiKeyController::store
 * @see app/Http/Controllers/UraApiKeyController.php:45
 * @route '/ura'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\UraApiKeyController::update
 * @see app/Http/Controllers/UraApiKeyController.php:68
 * @route '/ura/{uraApiKey}'
 */
export const update = (args: { uraApiKey: number | { id: number } } | [uraApiKey: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/ura/{uraApiKey}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\UraApiKeyController::update
 * @see app/Http/Controllers/UraApiKeyController.php:68
 * @route '/ura/{uraApiKey}'
 */
update.url = (args: { uraApiKey: number | { id: number } } | [uraApiKey: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { uraApiKey: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { uraApiKey: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    uraApiKey: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        uraApiKey: typeof args.uraApiKey === 'object'
                ? args.uraApiKey.id
                : args.uraApiKey,
                }

    return update.definition.url
            .replace('{uraApiKey}', parsedArgs.uraApiKey.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\UraApiKeyController::update
 * @see app/Http/Controllers/UraApiKeyController.php:68
 * @route '/ura/{uraApiKey}'
 */
update.patch = (args: { uraApiKey: number | { id: number } } | [uraApiKey: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\UraApiKeyController::update
 * @see app/Http/Controllers/UraApiKeyController.php:68
 * @route '/ura/{uraApiKey}'
 */
    const updateForm = (args: { uraApiKey: number | { id: number } } | [uraApiKey: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\UraApiKeyController::update
 * @see app/Http/Controllers/UraApiKeyController.php:68
 * @route '/ura/{uraApiKey}'
 */
        updateForm.patch = (args: { uraApiKey: number | { id: number } } | [uraApiKey: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\UraApiKeyController::destroy
 * @see app/Http/Controllers/UraApiKeyController.php:93
 * @route '/ura/{uraApiKey}'
 */
export const destroy = (args: { uraApiKey: number | { id: number } } | [uraApiKey: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/ura/{uraApiKey}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\UraApiKeyController::destroy
 * @see app/Http/Controllers/UraApiKeyController.php:93
 * @route '/ura/{uraApiKey}'
 */
destroy.url = (args: { uraApiKey: number | { id: number } } | [uraApiKey: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { uraApiKey: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { uraApiKey: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    uraApiKey: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        uraApiKey: typeof args.uraApiKey === 'object'
                ? args.uraApiKey.id
                : args.uraApiKey,
                }

    return destroy.definition.url
            .replace('{uraApiKey}', parsedArgs.uraApiKey.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\UraApiKeyController::destroy
 * @see app/Http/Controllers/UraApiKeyController.php:93
 * @route '/ura/{uraApiKey}'
 */
destroy.delete = (args: { uraApiKey: number | { id: number } } | [uraApiKey: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\UraApiKeyController::destroy
 * @see app/Http/Controllers/UraApiKeyController.php:93
 * @route '/ura/{uraApiKey}'
 */
    const destroyForm = (args: { uraApiKey: number | { id: number } } | [uraApiKey: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\UraApiKeyController::destroy
 * @see app/Http/Controllers/UraApiKeyController.php:93
 * @route '/ura/{uraApiKey}'
 */
        destroyForm.delete = (args: { uraApiKey: number | { id: number } } | [uraApiKey: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const ura = {
    inboundLead: Object.assign(inboundLead, inboundLead),
trigger: Object.assign(trigger, trigger),
index: Object.assign(index, index),
store: Object.assign(store, store),
update: Object.assign(update, update),
destroy: Object.assign(destroy, destroy),
}

export default ura