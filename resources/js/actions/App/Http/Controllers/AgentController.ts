import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
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
const AgentController = { tenaz, aria }

export default AgentController