import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\StressTestController::datasets
 * @see app/Http/Controllers/StressTestController.php:21
 * @route '/laboratory/datasets'
 */
export const datasets = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: datasets.url(options),
    method: 'get',
})

datasets.definition = {
    methods: ["get","head"],
    url: '/laboratory/datasets',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\StressTestController::datasets
 * @see app/Http/Controllers/StressTestController.php:21
 * @route '/laboratory/datasets'
 */
datasets.url = (options?: RouteQueryOptions) => {
    return datasets.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\StressTestController::datasets
 * @see app/Http/Controllers/StressTestController.php:21
 * @route '/laboratory/datasets'
 */
datasets.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: datasets.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\StressTestController::datasets
 * @see app/Http/Controllers/StressTestController.php:21
 * @route '/laboratory/datasets'
 */
datasets.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: datasets.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\StressTestController::storeDataset
 * @see app/Http/Controllers/StressTestController.php:30
 * @route '/laboratory/datasets'
 */
export const storeDataset = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeDataset.url(options),
    method: 'post',
})

storeDataset.definition = {
    methods: ["post"],
    url: '/laboratory/datasets',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\StressTestController::storeDataset
 * @see app/Http/Controllers/StressTestController.php:30
 * @route '/laboratory/datasets'
 */
storeDataset.url = (options?: RouteQueryOptions) => {
    return storeDataset.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\StressTestController::storeDataset
 * @see app/Http/Controllers/StressTestController.php:30
 * @route '/laboratory/datasets'
 */
storeDataset.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeDataset.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\StressTestController::showDataset
 * @see app/Http/Controllers/StressTestController.php:65
 * @route '/laboratory/datasets/{dataset}'
 */
export const showDataset = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showDataset.url(args, options),
    method: 'get',
})

showDataset.definition = {
    methods: ["get","head"],
    url: '/laboratory/datasets/{dataset}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\StressTestController::showDataset
 * @see app/Http/Controllers/StressTestController.php:65
 * @route '/laboratory/datasets/{dataset}'
 */
showDataset.url = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return showDataset.definition.url
            .replace('{dataset}', parsedArgs.dataset.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\StressTestController::showDataset
 * @see app/Http/Controllers/StressTestController.php:65
 * @route '/laboratory/datasets/{dataset}'
 */
showDataset.get = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showDataset.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\StressTestController::showDataset
 * @see app/Http/Controllers/StressTestController.php:65
 * @route '/laboratory/datasets/{dataset}'
 */
showDataset.head = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showDataset.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\StressTestController::destroyDataset
 * @see app/Http/Controllers/StressTestController.php:94
 * @route '/laboratory/datasets/{dataset}'
 */
export const destroyDataset = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyDataset.url(args, options),
    method: 'delete',
})

destroyDataset.definition = {
    methods: ["delete"],
    url: '/laboratory/datasets/{dataset}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\StressTestController::destroyDataset
 * @see app/Http/Controllers/StressTestController.php:94
 * @route '/laboratory/datasets/{dataset}'
 */
destroyDataset.url = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return destroyDataset.definition.url
            .replace('{dataset}', parsedArgs.dataset.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\StressTestController::destroyDataset
 * @see app/Http/Controllers/StressTestController.php:94
 * @route '/laboratory/datasets/{dataset}'
 */
destroyDataset.delete = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyDataset.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\StressTestController::prefetchDataset
 * @see app/Http/Controllers/StressTestController.php:102
 * @route '/laboratory/datasets/{dataset}/prefetch'
 */
export const prefetchDataset = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: prefetchDataset.url(args, options),
    method: 'post',
})

prefetchDataset.definition = {
    methods: ["post"],
    url: '/laboratory/datasets/{dataset}/prefetch',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\StressTestController::prefetchDataset
 * @see app/Http/Controllers/StressTestController.php:102
 * @route '/laboratory/datasets/{dataset}/prefetch'
 */
prefetchDataset.url = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return prefetchDataset.definition.url
            .replace('{dataset}', parsedArgs.dataset.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\StressTestController::prefetchDataset
 * @see app/Http/Controllers/StressTestController.php:102
 * @route '/laboratory/datasets/{dataset}/prefetch'
 */
prefetchDataset.post = (args: { dataset: number | { id: number } } | [dataset: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: prefetchDataset.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\StressTestController::runs
 * @see app/Http/Controllers/StressTestController.php:115
 * @route '/laboratory/stress-tests'
 */
export const runs = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: runs.url(options),
    method: 'get',
})

runs.definition = {
    methods: ["get","head"],
    url: '/laboratory/stress-tests',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\StressTestController::runs
 * @see app/Http/Controllers/StressTestController.php:115
 * @route '/laboratory/stress-tests'
 */
runs.url = (options?: RouteQueryOptions) => {
    return runs.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\StressTestController::runs
 * @see app/Http/Controllers/StressTestController.php:115
 * @route '/laboratory/stress-tests'
 */
runs.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: runs.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\StressTestController::runs
 * @see app/Http/Controllers/StressTestController.php:115
 * @route '/laboratory/stress-tests'
 */
runs.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: runs.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\StressTestController::storeRun
 * @see app/Http/Controllers/StressTestController.php:142
 * @route '/laboratory/stress-tests'
 */
export const storeRun = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeRun.url(options),
    method: 'post',
})

storeRun.definition = {
    methods: ["post"],
    url: '/laboratory/stress-tests',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\StressTestController::storeRun
 * @see app/Http/Controllers/StressTestController.php:142
 * @route '/laboratory/stress-tests'
 */
storeRun.url = (options?: RouteQueryOptions) => {
    return storeRun.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\StressTestController::storeRun
 * @see app/Http/Controllers/StressTestController.php:142
 * @route '/laboratory/stress-tests'
 */
storeRun.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeRun.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\StressTestController::showRun
 * @see app/Http/Controllers/StressTestController.php:177
 * @route '/laboratory/stress-tests/{run}'
 */
export const showRun = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showRun.url(args, options),
    method: 'get',
})

showRun.definition = {
    methods: ["get","head"],
    url: '/laboratory/stress-tests/{run}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\StressTestController::showRun
 * @see app/Http/Controllers/StressTestController.php:177
 * @route '/laboratory/stress-tests/{run}'
 */
showRun.url = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { run: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { run: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    run: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        run: typeof args.run === 'object'
                ? args.run.id
                : args.run,
                }

    return showRun.definition.url
            .replace('{run}', parsedArgs.run.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\StressTestController::showRun
 * @see app/Http/Controllers/StressTestController.php:177
 * @route '/laboratory/stress-tests/{run}'
 */
showRun.get = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showRun.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\StressTestController::showRun
 * @see app/Http/Controllers/StressTestController.php:177
 * @route '/laboratory/stress-tests/{run}'
 */
showRun.head = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showRun.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\StressTestController::cancelRun
 * @see app/Http/Controllers/StressTestController.php:215
 * @route '/laboratory/stress-tests/{run}/cancel'
 */
export const cancelRun = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancelRun.url(args, options),
    method: 'post',
})

cancelRun.definition = {
    methods: ["post"],
    url: '/laboratory/stress-tests/{run}/cancel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\StressTestController::cancelRun
 * @see app/Http/Controllers/StressTestController.php:215
 * @route '/laboratory/stress-tests/{run}/cancel'
 */
cancelRun.url = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { run: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { run: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    run: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        run: typeof args.run === 'object'
                ? args.run.id
                : args.run,
                }

    return cancelRun.definition.url
            .replace('{run}', parsedArgs.run.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\StressTestController::cancelRun
 * @see app/Http/Controllers/StressTestController.php:215
 * @route '/laboratory/stress-tests/{run}/cancel'
 */
cancelRun.post = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancelRun.url(args, options),
    method: 'post',
})
const StressTestController = { datasets, storeDataset, showDataset, destroyDataset, prefetchDataset, runs, storeRun, showRun, cancelRun }

export default StressTestController