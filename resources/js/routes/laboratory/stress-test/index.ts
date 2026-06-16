import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\LaboratoryController::results
 * @see app/Http/Controllers/LaboratoryController.php:90
 * @route '/laboratory/stress-test/{run}'
 */
export const results = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: results.url(args, options),
    method: 'get',
})

results.definition = {
    methods: ["get","head"],
    url: '/laboratory/stress-test/{run}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LaboratoryController::results
 * @see app/Http/Controllers/LaboratoryController.php:90
 * @route '/laboratory/stress-test/{run}'
 */
results.url = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return results.definition.url
            .replace('{run}', parsedArgs.run.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LaboratoryController::results
 * @see app/Http/Controllers/LaboratoryController.php:90
 * @route '/laboratory/stress-test/{run}'
 */
results.get = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: results.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\LaboratoryController::results
 * @see app/Http/Controllers/LaboratoryController.php:90
 * @route '/laboratory/stress-test/{run}'
 */
results.head = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: results.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\LaboratoryController::results
 * @see app/Http/Controllers/LaboratoryController.php:90
 * @route '/laboratory/stress-test/{run}'
 */
    const resultsForm = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: results.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\LaboratoryController::results
 * @see app/Http/Controllers/LaboratoryController.php:90
 * @route '/laboratory/stress-test/{run}'
 */
        resultsForm.get = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: results.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\LaboratoryController::results
 * @see app/Http/Controllers/LaboratoryController.php:90
 * @route '/laboratory/stress-test/{run}'
 */
        resultsForm.head = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: results.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    results.form = resultsForm
const stressTest = {
    results: Object.assign(results, results),
}

export default stressTest