import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\StatusPipelineController::store
 * @see app/Http/Controllers/StatusPipelineController.php:91
 * @route '/configuracoes/pipeline/statuses'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/configuracoes/pipeline/statuses',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\StatusPipelineController::store
 * @see app/Http/Controllers/StatusPipelineController.php:91
 * @route '/configuracoes/pipeline/statuses'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\StatusPipelineController::store
 * @see app/Http/Controllers/StatusPipelineController.php:91
 * @route '/configuracoes/pipeline/statuses'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\StatusPipelineController::store
 * @see app/Http/Controllers/StatusPipelineController.php:91
 * @route '/configuracoes/pipeline/statuses'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\StatusPipelineController::store
 * @see app/Http/Controllers/StatusPipelineController.php:91
 * @route '/configuracoes/pipeline/statuses'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\StatusPipelineController::update
 * @see app/Http/Controllers/StatusPipelineController.php:68
 * @route '/configuracoes/pipeline/statuses/{slug}'
 */
export const update = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/configuracoes/pipeline/statuses/{slug}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\StatusPipelineController::update
 * @see app/Http/Controllers/StatusPipelineController.php:68
 * @route '/configuracoes/pipeline/statuses/{slug}'
 */
update.url = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { slug: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    slug: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        slug: args.slug,
                }

    return update.definition.url
            .replace('{slug}', parsedArgs.slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\StatusPipelineController::update
 * @see app/Http/Controllers/StatusPipelineController.php:68
 * @route '/configuracoes/pipeline/statuses/{slug}'
 */
update.put = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\StatusPipelineController::update
 * @see app/Http/Controllers/StatusPipelineController.php:68
 * @route '/configuracoes/pipeline/statuses/{slug}'
 */
    const updateForm = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\StatusPipelineController::update
 * @see app/Http/Controllers/StatusPipelineController.php:68
 * @route '/configuracoes/pipeline/statuses/{slug}'
 */
        updateForm.put = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \App\Http\Controllers\StatusPipelineController::destroy
 * @see app/Http/Controllers/StatusPipelineController.php:108
 * @route '/configuracoes/pipeline/statuses/{slug}'
 */
export const destroy = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/configuracoes/pipeline/statuses/{slug}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\StatusPipelineController::destroy
 * @see app/Http/Controllers/StatusPipelineController.php:108
 * @route '/configuracoes/pipeline/statuses/{slug}'
 */
destroy.url = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { slug: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    slug: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        slug: args.slug,
                }

    return destroy.definition.url
            .replace('{slug}', parsedArgs.slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\StatusPipelineController::destroy
 * @see app/Http/Controllers/StatusPipelineController.php:108
 * @route '/configuracoes/pipeline/statuses/{slug}'
 */
destroy.delete = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\StatusPipelineController::destroy
 * @see app/Http/Controllers/StatusPipelineController.php:108
 * @route '/configuracoes/pipeline/statuses/{slug}'
 */
    const destroyForm = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\StatusPipelineController::destroy
 * @see app/Http/Controllers/StatusPipelineController.php:108
 * @route '/configuracoes/pipeline/statuses/{slug}'
 */
        destroyForm.delete = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const statuses = {
    store: Object.assign(store, store),
update: Object.assign(update, update),
destroy: Object.assign(destroy, destroy),
}

export default statuses