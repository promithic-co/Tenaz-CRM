import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\StressTestController::index
 * @see app/Http/Controllers/StressTestController.php:115
 * @route '/laboratory/stress-tests'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/laboratory/stress-tests',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\StressTestController::index
 * @see app/Http/Controllers/StressTestController.php:115
 * @route '/laboratory/stress-tests'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\StressTestController::index
 * @see app/Http/Controllers/StressTestController.php:115
 * @route '/laboratory/stress-tests'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\StressTestController::index
 * @see app/Http/Controllers/StressTestController.php:115
 * @route '/laboratory/stress-tests'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\StressTestController::store
 * @see app/Http/Controllers/StressTestController.php:142
 * @route '/laboratory/stress-tests'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/laboratory/stress-tests',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\StressTestController::store
 * @see app/Http/Controllers/StressTestController.php:142
 * @route '/laboratory/stress-tests'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\StressTestController::store
 * @see app/Http/Controllers/StressTestController.php:142
 * @route '/laboratory/stress-tests'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\StressTestController::show
 * @see app/Http/Controllers/StressTestController.php:177
 * @route '/laboratory/stress-tests/{run}'
 */
export const show = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/laboratory/stress-tests/{run}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\StressTestController::show
 * @see app/Http/Controllers/StressTestController.php:177
 * @route '/laboratory/stress-tests/{run}'
 */
show.url = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{run}', parsedArgs.run.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\StressTestController::show
 * @see app/Http/Controllers/StressTestController.php:177
 * @route '/laboratory/stress-tests/{run}'
 */
show.get = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\StressTestController::show
 * @see app/Http/Controllers/StressTestController.php:177
 * @route '/laboratory/stress-tests/{run}'
 */
show.head = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\StressTestController::cancel
 * @see app/Http/Controllers/StressTestController.php:215
 * @route '/laboratory/stress-tests/{run}/cancel'
 */
export const cancel = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})

cancel.definition = {
    methods: ["post"],
    url: '/laboratory/stress-tests/{run}/cancel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\StressTestController::cancel
 * @see app/Http/Controllers/StressTestController.php:215
 * @route '/laboratory/stress-tests/{run}/cancel'
 */
cancel.url = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return cancel.definition.url
            .replace('{run}', parsedArgs.run.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\StressTestController::cancel
 * @see app/Http/Controllers/StressTestController.php:215
 * @route '/laboratory/stress-tests/{run}/cancel'
 */
cancel.post = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})
const stressTests = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
show: Object.assign(show, show),
cancel: Object.assign(cancel, cancel),
}

export default stressTests