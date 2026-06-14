import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\StatusPipelineController::index
 * @see app/Http/Controllers/StatusPipelineController.php:41
 * @route '/configuracoes/pipeline'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/configuracoes/pipeline',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\StatusPipelineController::index
 * @see app/Http/Controllers/StatusPipelineController.php:41
 * @route '/configuracoes/pipeline'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\StatusPipelineController::index
 * @see app/Http/Controllers/StatusPipelineController.php:41
 * @route '/configuracoes/pipeline'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\StatusPipelineController::index
 * @see app/Http/Controllers/StatusPipelineController.php:41
 * @route '/configuracoes/pipeline'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\StatusPipelineController::storeStatus
 * @see app/Http/Controllers/StatusPipelineController.php:91
 * @route '/configuracoes/pipeline/statuses'
 */
export const storeStatus = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeStatus.url(options),
    method: 'post',
})

storeStatus.definition = {
    methods: ["post"],
    url: '/configuracoes/pipeline/statuses',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\StatusPipelineController::storeStatus
 * @see app/Http/Controllers/StatusPipelineController.php:91
 * @route '/configuracoes/pipeline/statuses'
 */
storeStatus.url = (options?: RouteQueryOptions) => {
    return storeStatus.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\StatusPipelineController::storeStatus
 * @see app/Http/Controllers/StatusPipelineController.php:91
 * @route '/configuracoes/pipeline/statuses'
 */
storeStatus.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeStatus.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\StatusPipelineController::updateStatus
 * @see app/Http/Controllers/StatusPipelineController.php:68
 * @route '/configuracoes/pipeline/statuses/{slug}'
 */
export const updateStatus = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateStatus.url(args, options),
    method: 'put',
})

updateStatus.definition = {
    methods: ["put"],
    url: '/configuracoes/pipeline/statuses/{slug}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\StatusPipelineController::updateStatus
 * @see app/Http/Controllers/StatusPipelineController.php:68
 * @route '/configuracoes/pipeline/statuses/{slug}'
 */
updateStatus.url = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return updateStatus.definition.url
            .replace('{slug}', parsedArgs.slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\StatusPipelineController::updateStatus
 * @see app/Http/Controllers/StatusPipelineController.php:68
 * @route '/configuracoes/pipeline/statuses/{slug}'
 */
updateStatus.put = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateStatus.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\StatusPipelineController::destroyStatus
 * @see app/Http/Controllers/StatusPipelineController.php:108
 * @route '/configuracoes/pipeline/statuses/{slug}'
 */
export const destroyStatus = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyStatus.url(args, options),
    method: 'delete',
})

destroyStatus.definition = {
    methods: ["delete"],
    url: '/configuracoes/pipeline/statuses/{slug}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\StatusPipelineController::destroyStatus
 * @see app/Http/Controllers/StatusPipelineController.php:108
 * @route '/configuracoes/pipeline/statuses/{slug}'
 */
destroyStatus.url = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return destroyStatus.definition.url
            .replace('{slug}', parsedArgs.slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\StatusPipelineController::destroyStatus
 * @see app/Http/Controllers/StatusPipelineController.php:108
 * @route '/configuracoes/pipeline/statuses/{slug}'
 */
destroyStatus.delete = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyStatus.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\StatusPipelineController::storeTransition
 * @see app/Http/Controllers/StatusPipelineController.php:141
 * @route '/configuracoes/pipeline/transitions'
 */
export const storeTransition = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeTransition.url(options),
    method: 'post',
})

storeTransition.definition = {
    methods: ["post"],
    url: '/configuracoes/pipeline/transitions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\StatusPipelineController::storeTransition
 * @see app/Http/Controllers/StatusPipelineController.php:141
 * @route '/configuracoes/pipeline/transitions'
 */
storeTransition.url = (options?: RouteQueryOptions) => {
    return storeTransition.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\StatusPipelineController::storeTransition
 * @see app/Http/Controllers/StatusPipelineController.php:141
 * @route '/configuracoes/pipeline/transitions'
 */
storeTransition.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeTransition.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\StatusPipelineController::destroyTransition
 * @see app/Http/Controllers/StatusPipelineController.php:160
 * @route '/configuracoes/pipeline/transitions/{from}/{to}'
 */
export const destroyTransition = (args: { from: string | number, to: string | number } | [from: string | number, to: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyTransition.url(args, options),
    method: 'delete',
})

destroyTransition.definition = {
    methods: ["delete"],
    url: '/configuracoes/pipeline/transitions/{from}/{to}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\StatusPipelineController::destroyTransition
 * @see app/Http/Controllers/StatusPipelineController.php:160
 * @route '/configuracoes/pipeline/transitions/{from}/{to}'
 */
destroyTransition.url = (args: { from: string | number, to: string | number } | [from: string | number, to: string | number ], options?: RouteQueryOptions) => {
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

    return destroyTransition.definition.url
            .replace('{from}', parsedArgs.from.toString())
            .replace('{to}', parsedArgs.to.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\StatusPipelineController::destroyTransition
 * @see app/Http/Controllers/StatusPipelineController.php:160
 * @route '/configuracoes/pipeline/transitions/{from}/{to}'
 */
destroyTransition.delete = (args: { from: string | number, to: string | number } | [from: string | number, to: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyTransition.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\StatusPipelineController::reorder
 * @see app/Http/Controllers/StatusPipelineController.php:179
 * @route '/configuracoes/pipeline/reorder'
 */
export const reorder = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reorder.url(options),
    method: 'post',
})

reorder.definition = {
    methods: ["post"],
    url: '/configuracoes/pipeline/reorder',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\StatusPipelineController::reorder
 * @see app/Http/Controllers/StatusPipelineController.php:179
 * @route '/configuracoes/pipeline/reorder'
 */
reorder.url = (options?: RouteQueryOptions) => {
    return reorder.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\StatusPipelineController::reorder
 * @see app/Http/Controllers/StatusPipelineController.php:179
 * @route '/configuracoes/pipeline/reorder'
 */
reorder.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reorder.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\StatusPipelineController::reset
 * @see app/Http/Controllers/StatusPipelineController.php:203
 * @route '/configuracoes/pipeline/reset'
 */
export const reset = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reset.url(options),
    method: 'post',
})

reset.definition = {
    methods: ["post"],
    url: '/configuracoes/pipeline/reset',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\StatusPipelineController::reset
 * @see app/Http/Controllers/StatusPipelineController.php:203
 * @route '/configuracoes/pipeline/reset'
 */
reset.url = (options?: RouteQueryOptions) => {
    return reset.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\StatusPipelineController::reset
 * @see app/Http/Controllers/StatusPipelineController.php:203
 * @route '/configuracoes/pipeline/reset'
 */
reset.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reset.url(options),
    method: 'post',
})
const StatusPipelineController = { index, storeStatus, updateStatus, destroyStatus, storeTransition, destroyTransition, reorder, reset }

export default StatusPipelineController