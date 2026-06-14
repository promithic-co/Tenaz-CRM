import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\UraInboundController::store
 * @see app/Http/Controllers/UraInboundController.php:14
 * @route '/api/ura/inbound-lead'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/ura/inbound-lead',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\UraInboundController::store
 * @see app/Http/Controllers/UraInboundController.php:14
 * @route '/api/ura/inbound-lead'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\UraInboundController::store
 * @see app/Http/Controllers/UraInboundController.php:14
 * @route '/api/ura/inbound-lead'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\UraInboundController::trigger
 * @see app/Http/Controllers/UraInboundController.php:29
 * @route '/api/ura/trigger'
 */
export const trigger = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: trigger.url(options),
    method: 'post',
})

trigger.definition = {
    methods: ["post"],
    url: '/api/ura/trigger',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\UraInboundController::trigger
 * @see app/Http/Controllers/UraInboundController.php:29
 * @route '/api/ura/trigger'
 */
trigger.url = (options?: RouteQueryOptions) => {
    return trigger.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\UraInboundController::trigger
 * @see app/Http/Controllers/UraInboundController.php:29
 * @route '/api/ura/trigger'
 */
trigger.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: trigger.url(options),
    method: 'post',
})
const UraInboundController = { store, trigger }

export default UraInboundController