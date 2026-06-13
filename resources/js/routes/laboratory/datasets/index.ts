import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\StressTestController::index
 * @see app/Http/Controllers/StressTestController.php:21
 * @route '/laboratory/datasets'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/laboratory/datasets',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\StressTestController::index
 * @see app/Http/Controllers/StressTestController.php:21
 * @route '/laboratory/datasets'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\StressTestController::index
 * @see app/Http/Controllers/StressTestController.php:21
 * @route '/laboratory/datasets'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\StressTestController::index
 * @see app/Http/Controllers/StressTestController.php:21
 * @route '/laboratory/datasets'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\StressTestController::index
 * @see app/Http/Controllers/StressTestController.php:21
 * @route '/laboratory/datasets'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\StressTestController::index
 * @see app/Http/Controllers/StressTestController.php:21
 * @route '/laboratory/datasets'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\StressTestController::index
 * @see app/Http/Controllers/StressTestController.php:21
 * @route '/laboratory/datasets'
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
* @see \App\Http\Controllers\StressTestController::store
 * @see app/Http/Controllers/StressTestController.php:30
 * @route '/laboratory/datasets'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/laboratory/datasets',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\StressTestController::store
 * @see app/Http/Controllers/StressTestController.php:30
 * @route '/laboratory/datasets'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\StressTestController::store
 * @see app/Http/Controllers/StressTestController.php:30
 * @route '/laboratory/datasets'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\StressTestController::store
 * @see app/Http/Controllers/StressTestController.php:30
 * @route '/laboratory/datasets'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\StressTestController::store
 * @see app/Http/Controllers/StressTestController.php:30
 * @route '/laboratory/datasets'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\StressTestController::show
 * @see app/Http/Controllers/StressTestController.php:65
 * @route '/laboratory/datasets/{dataset}'
 */
export const show = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/laboratory/datasets/{dataset}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\StressTestController::show
 * @see app/Http/Controllers/StressTestController.php:65
 * @route '/laboratory/datasets/{dataset}'
 */
show.url = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { dataset: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { dataset: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    dataset: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        dataset: typeof args.dataset === 'object'
                ? args.dataset.id
                : args.dataset,
                }

    return show.definition.url
            .replace('{dataset}', parsedArgs.dataset.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\StressTestController::show
 * @see app/Http/Controllers/StressTestController.php:65
 * @route '/laboratory/datasets/{dataset}'
 */
show.get = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\StressTestController::show
 * @see app/Http/Controllers/StressTestController.php:65
 * @route '/laboratory/datasets/{dataset}'
 */
show.head = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\StressTestController::show
 * @see app/Http/Controllers/StressTestController.php:65
 * @route '/laboratory/datasets/{dataset}'
 */
    const showForm = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\StressTestController::show
 * @see app/Http/Controllers/StressTestController.php:65
 * @route '/laboratory/datasets/{dataset}'
 */
        showForm.get = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\StressTestController::show
 * @see app/Http/Controllers/StressTestController.php:65
 * @route '/laboratory/datasets/{dataset}'
 */
        showForm.head = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\StressTestController::destroy
 * @see app/Http/Controllers/StressTestController.php:94
 * @route '/laboratory/datasets/{dataset}'
 */
export const destroy = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/laboratory/datasets/{dataset}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\StressTestController::destroy
 * @see app/Http/Controllers/StressTestController.php:94
 * @route '/laboratory/datasets/{dataset}'
 */
destroy.url = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { dataset: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { dataset: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    dataset: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        dataset: typeof args.dataset === 'object'
                ? args.dataset.id
                : args.dataset,
                }

    return destroy.definition.url
            .replace('{dataset}', parsedArgs.dataset.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\StressTestController::destroy
 * @see app/Http/Controllers/StressTestController.php:94
 * @route '/laboratory/datasets/{dataset}'
 */
destroy.delete = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\StressTestController::destroy
 * @see app/Http/Controllers/StressTestController.php:94
 * @route '/laboratory/datasets/{dataset}'
 */
    const destroyForm = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\StressTestController::destroy
 * @see app/Http/Controllers/StressTestController.php:94
 * @route '/laboratory/datasets/{dataset}'
 */
        destroyForm.delete = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\StressTestController::prefetch
 * @see app/Http/Controllers/StressTestController.php:102
 * @route '/laboratory/datasets/{dataset}/prefetch'
 */
export const prefetch = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: prefetch.url(args, options),
    method: 'post',
})

prefetch.definition = {
    methods: ["post"],
    url: '/laboratory/datasets/{dataset}/prefetch',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\StressTestController::prefetch
 * @see app/Http/Controllers/StressTestController.php:102
 * @route '/laboratory/datasets/{dataset}/prefetch'
 */
prefetch.url = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { dataset: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { dataset: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    dataset: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        dataset: typeof args.dataset === 'object'
                ? args.dataset.id
                : args.dataset,
                }

    return prefetch.definition.url
            .replace('{dataset}', parsedArgs.dataset.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\StressTestController::prefetch
 * @see app/Http/Controllers/StressTestController.php:102
 * @route '/laboratory/datasets/{dataset}/prefetch'
 */
prefetch.post = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: prefetch.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\StressTestController::prefetch
 * @see app/Http/Controllers/StressTestController.php:102
 * @route '/laboratory/datasets/{dataset}/prefetch'
 */
    const prefetchForm = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: prefetch.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\StressTestController::prefetch
 * @see app/Http/Controllers/StressTestController.php:102
 * @route '/laboratory/datasets/{dataset}/prefetch'
 */
        prefetchForm.post = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: prefetch.url(args, options),
            method: 'post',
        })
    
    prefetch.form = prefetchForm
const datasets = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
show: Object.assign(show, show),
destroy: Object.assign(destroy, destroy),
prefetch: Object.assign(prefetch, prefetch),
}

export default datasets