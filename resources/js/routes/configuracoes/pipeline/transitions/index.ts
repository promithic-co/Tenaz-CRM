import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\StatusPipelineController::store
 * @see app/Http/Controllers/StatusPipelineController.php:141
 * @route '/configuracoes/pipeline/transitions'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/configuracoes/pipeline/transitions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\StatusPipelineController::store
 * @see app/Http/Controllers/StatusPipelineController.php:141
 * @route '/configuracoes/pipeline/transitions'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\StatusPipelineController::store
 * @see app/Http/Controllers/StatusPipelineController.php:141
 * @route '/configuracoes/pipeline/transitions'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\StatusPipelineController::store
 * @see app/Http/Controllers/StatusPipelineController.php:141
 * @route '/configuracoes/pipeline/transitions'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\StatusPipelineController::store
 * @see app/Http/Controllers/StatusPipelineController.php:141
 * @route '/configuracoes/pipeline/transitions'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\StatusPipelineController::destroy
 * @see app/Http/Controllers/StatusPipelineController.php:160
 * @route '/configuracoes/pipeline/transitions/{from}/{to}'
 */
export const destroy = (args: { from: string | number, to: string | number } | [from: string | number, to: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/configuracoes/pipeline/transitions/{from}/{to}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\StatusPipelineController::destroy
 * @see app/Http/Controllers/StatusPipelineController.php:160
 * @route '/configuracoes/pipeline/transitions/{from}/{to}'
 */
destroy.url = (args: { from: string | number, to: string | number } | [from: string | number, to: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    from: args[0],
                    to: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        from: args.from,
                                to: args.to,
                }

    return destroy.definition.url
            .replace('{from}', parsedArgs.from.toString())
            .replace('{to}', parsedArgs.to.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\StatusPipelineController::destroy
 * @see app/Http/Controllers/StatusPipelineController.php:160
 * @route '/configuracoes/pipeline/transitions/{from}/{to}'
 */
destroy.delete = (args: { from: string | number, to: string | number } | [from: string | number, to: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\StatusPipelineController::destroy
 * @see app/Http/Controllers/StatusPipelineController.php:160
 * @route '/configuracoes/pipeline/transitions/{from}/{to}'
 */
    const destroyForm = (args: { from: string | number, to: string | number } | [from: string | number, to: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
 * @see app/Http/Controllers/StatusPipelineController.php:160
 * @route '/configuracoes/pipeline/transitions/{from}/{to}'
 */
        destroyForm.delete = (args: { from: string | number, to: string | number } | [from: string | number, to: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const transitions = {
    store: Object.assign(store, store),
destroy: Object.assign(destroy, destroy),
}

export default transitions