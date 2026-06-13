import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\HealthController::__invoke
 * @see app/Http/Controllers/Api/HealthController.php:14
 * @route '/api/health'
 */
const HealthController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: HealthController.url(options),
    method: 'get',
})

HealthController.definition = {
    methods: ["get","head"],
    url: '/api/health',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\HealthController::__invoke
 * @see app/Http/Controllers/Api/HealthController.php:14
 * @route '/api/health'
 */
HealthController.url = (options?: RouteQueryOptions) => {
    return HealthController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\HealthController::__invoke
 * @see app/Http/Controllers/Api/HealthController.php:14
 * @route '/api/health'
 */
HealthController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: HealthController.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Api\HealthController::__invoke
 * @see app/Http/Controllers/Api/HealthController.php:14
 * @route '/api/health'
 */
HealthController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: HealthController.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Api\HealthController::__invoke
 * @see app/Http/Controllers/Api/HealthController.php:14
 * @route '/api/health'
 */
    const HealthControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: HealthController.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Api\HealthController::__invoke
 * @see app/Http/Controllers/Api/HealthController.php:14
 * @route '/api/health'
 */
        HealthControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: HealthController.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Api\HealthController::__invoke
 * @see app/Http/Controllers/Api/HealthController.php:14
 * @route '/api/health'
 */
        HealthControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: HealthController.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    HealthController.form = HealthControllerForm
export default HealthController