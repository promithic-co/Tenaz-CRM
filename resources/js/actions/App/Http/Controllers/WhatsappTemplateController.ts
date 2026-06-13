import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\WhatsappTemplateController::index
 * @see app/Http/Controllers/WhatsappTemplateController.php:24
 * @route '/templates'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/templates',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\WhatsappTemplateController::index
 * @see app/Http/Controllers/WhatsappTemplateController.php:24
 * @route '/templates'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\WhatsappTemplateController::index
 * @see app/Http/Controllers/WhatsappTemplateController.php:24
 * @route '/templates'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\WhatsappTemplateController::index
 * @see app/Http/Controllers/WhatsappTemplateController.php:24
 * @route '/templates'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\WhatsappTemplateController::index
 * @see app/Http/Controllers/WhatsappTemplateController.php:24
 * @route '/templates'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\WhatsappTemplateController::index
 * @see app/Http/Controllers/WhatsappTemplateController.php:24
 * @route '/templates'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\WhatsappTemplateController::index
 * @see app/Http/Controllers/WhatsappTemplateController.php:24
 * @route '/templates'
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
* @see \App\Http\Controllers\WhatsappTemplateController::store
 * @see app/Http/Controllers/WhatsappTemplateController.php:60
 * @route '/templates'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/templates',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\WhatsappTemplateController::store
 * @see app/Http/Controllers/WhatsappTemplateController.php:60
 * @route '/templates'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\WhatsappTemplateController::store
 * @see app/Http/Controllers/WhatsappTemplateController.php:60
 * @route '/templates'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\WhatsappTemplateController::store
 * @see app/Http/Controllers/WhatsappTemplateController.php:60
 * @route '/templates'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\WhatsappTemplateController::store
 * @see app/Http/Controllers/WhatsappTemplateController.php:60
 * @route '/templates'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\WhatsappTemplateController::update
 * @see app/Http/Controllers/WhatsappTemplateController.php:153
 * @route '/templates/{template}'
 */
export const update = (args: { template: number | { id: number } } | [template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/templates/{template}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\WhatsappTemplateController::update
 * @see app/Http/Controllers/WhatsappTemplateController.php:153
 * @route '/templates/{template}'
 */
update.url = (args: { template: number | { id: number } } | [template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { template: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { template: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    template: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        template: typeof args.template === 'object'
                ? args.template.id
                : args.template,
                }

    return update.definition.url
            .replace('{template}', parsedArgs.template.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\WhatsappTemplateController::update
 * @see app/Http/Controllers/WhatsappTemplateController.php:153
 * @route '/templates/{template}'
 */
update.put = (args: { template: number | { id: number } } | [template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})
/**
* @see \App\Http\Controllers\WhatsappTemplateController::update
 * @see app/Http/Controllers/WhatsappTemplateController.php:153
 * @route '/templates/{template}'
 */
update.patch = (args: { template: number | { id: number } } | [template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\WhatsappTemplateController::update
 * @see app/Http/Controllers/WhatsappTemplateController.php:153
 * @route '/templates/{template}'
 */
    const updateForm = (args: { template: number | { id: number } } | [template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\WhatsappTemplateController::update
 * @see app/Http/Controllers/WhatsappTemplateController.php:153
 * @route '/templates/{template}'
 */
        updateForm.put = (args: { template: number | { id: number } } | [template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \App\Http\Controllers\WhatsappTemplateController::update
 * @see app/Http/Controllers/WhatsappTemplateController.php:153
 * @route '/templates/{template}'
 */
        updateForm.patch = (args: { template: number | { id: number } } | [template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\WhatsappTemplateController::destroy
 * @see app/Http/Controllers/WhatsappTemplateController.php:179
 * @route '/templates/{template}'
 */
export const destroy = (args: { template: number | { id: number } } | [template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/templates/{template}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\WhatsappTemplateController::destroy
 * @see app/Http/Controllers/WhatsappTemplateController.php:179
 * @route '/templates/{template}'
 */
destroy.url = (args: { template: number | { id: number } } | [template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { template: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { template: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    template: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        template: typeof args.template === 'object'
                ? args.template.id
                : args.template,
                }

    return destroy.definition.url
            .replace('{template}', parsedArgs.template.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\WhatsappTemplateController::destroy
 * @see app/Http/Controllers/WhatsappTemplateController.php:179
 * @route '/templates/{template}'
 */
destroy.delete = (args: { template: number | { id: number } } | [template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\WhatsappTemplateController::destroy
 * @see app/Http/Controllers/WhatsappTemplateController.php:179
 * @route '/templates/{template}'
 */
    const destroyForm = (args: { template: number | { id: number } } | [template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\WhatsappTemplateController::destroy
 * @see app/Http/Controllers/WhatsappTemplateController.php:179
 * @route '/templates/{template}'
 */
        destroyForm.delete = (args: { template: number | { id: number } } | [template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\WhatsappTemplateController::syncMeta
 * @see app/Http/Controllers/WhatsappTemplateController.php:163
 * @route '/templates/sync-meta'
 */
export const syncMeta = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: syncMeta.url(options),
    method: 'post',
})

syncMeta.definition = {
    methods: ["post"],
    url: '/templates/sync-meta',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\WhatsappTemplateController::syncMeta
 * @see app/Http/Controllers/WhatsappTemplateController.php:163
 * @route '/templates/sync-meta'
 */
syncMeta.url = (options?: RouteQueryOptions) => {
    return syncMeta.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\WhatsappTemplateController::syncMeta
 * @see app/Http/Controllers/WhatsappTemplateController.php:163
 * @route '/templates/sync-meta'
 */
syncMeta.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: syncMeta.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\WhatsappTemplateController::syncMeta
 * @see app/Http/Controllers/WhatsappTemplateController.php:163
 * @route '/templates/sync-meta'
 */
    const syncMetaForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: syncMeta.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\WhatsappTemplateController::syncMeta
 * @see app/Http/Controllers/WhatsappTemplateController.php:163
 * @route '/templates/sync-meta'
 */
        syncMetaForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: syncMeta.url(options),
            method: 'post',
        })
    
    syncMeta.form = syncMetaForm
const WhatsappTemplateController = { index, store, update, destroy, syncMeta }

export default WhatsappTemplateController