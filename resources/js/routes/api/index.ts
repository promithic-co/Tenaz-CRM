import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Api\HealthController::__invoke
 * @see app/Http/Controllers/Api/HealthController.php:14
 * @route '/api/health'
 */
export const health = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: health.url(options),
    method: 'get',
})

health.definition = {
    methods: ["get","head"],
    url: '/api/health',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\HealthController::__invoke
 * @see app/Http/Controllers/Api/HealthController.php:14
 * @route '/api/health'
 */
health.url = (options?: RouteQueryOptions) => {
    return health.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\HealthController::__invoke
 * @see app/Http/Controllers/Api/HealthController.php:14
 * @route '/api/health'
 */
health.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: health.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Api\HealthController::__invoke
 * @see app/Http/Controllers/Api/HealthController.php:14
 * @route '/api/health'
 */
health.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: health.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Api\HealthController::__invoke
 * @see app/Http/Controllers/Api/HealthController.php:14
 * @route '/api/health'
 */
    const healthForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: health.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Api\HealthController::__invoke
 * @see app/Http/Controllers/Api/HealthController.php:14
 * @route '/api/health'
 */
        healthForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: health.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Api\HealthController::__invoke
 * @see app/Http/Controllers/Api/HealthController.php:14
 * @route '/api/health'
 */
        healthForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: health.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    health.form = healthForm
/**
* @see \App\Http\Controllers\AgentController::tenaz
 * @see app/Http/Controllers/AgentController.php:21
 * @route '/api/tenaz'
 */
export const tenaz = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: tenaz.url(options),
    method: 'post',
})

tenaz.definition = {
    methods: ["post"],
    url: '/api/tenaz',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AgentController::tenaz
 * @see app/Http/Controllers/AgentController.php:21
 * @route '/api/tenaz'
 */
tenaz.url = (options?: RouteQueryOptions) => {
    return tenaz.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AgentController::tenaz
 * @see app/Http/Controllers/AgentController.php:21
 * @route '/api/tenaz'
 */
tenaz.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: tenaz.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\AgentController::tenaz
 * @see app/Http/Controllers/AgentController.php:21
 * @route '/api/tenaz'
 */
    const tenazForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: tenaz.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AgentController::tenaz
 * @see app/Http/Controllers/AgentController.php:21
 * @route '/api/tenaz'
 */
        tenazForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: tenaz.url(options),
            method: 'post',
        })
    
    tenaz.form = tenazForm
/**
* @see \App\Http\Controllers\AgentController::aria
 * @see app/Http/Controllers/AgentController.php:32
 * @route '/api/aria'
 */
export const aria = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: aria.url(options),
    method: 'post',
})

aria.definition = {
    methods: ["post"],
    url: '/api/aria',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AgentController::aria
 * @see app/Http/Controllers/AgentController.php:32
 * @route '/api/aria'
 */
aria.url = (options?: RouteQueryOptions) => {
    return aria.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AgentController::aria
 * @see app/Http/Controllers/AgentController.php:32
 * @route '/api/aria'
 */
aria.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: aria.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\AgentController::aria
 * @see app/Http/Controllers/AgentController.php:32
 * @route '/api/aria'
 */
    const ariaForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: aria.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AgentController::aria
 * @see app/Http/Controllers/AgentController.php:32
 * @route '/api/aria'
 */
        ariaForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: aria.url(options),
            method: 'post',
        })
    
    aria.form = ariaForm
const api = {
    health: Object.assign(health, health),
tenaz: Object.assign(tenaz, tenaz),
aria: Object.assign(aria, aria),
}

export default api