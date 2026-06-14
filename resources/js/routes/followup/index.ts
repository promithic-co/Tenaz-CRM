import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\ConfiguracoesController::index
 * @see app/Http/Controllers/ConfiguracoesController.php:10
 * @route '/agente/follow-up'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/agente/follow-up',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ConfiguracoesController::index
 * @see app/Http/Controllers/ConfiguracoesController.php:10
 * @route '/agente/follow-up'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConfiguracoesController::index
 * @see app/Http/Controllers/ConfiguracoesController.php:10
 * @route '/agente/follow-up'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ConfiguracoesController::index
 * @see app/Http/Controllers/ConfiguracoesController.php:10
 * @route '/agente/follow-up'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ConfiguracoesController::update
 * @see app/Http/Controllers/ConfiguracoesController.php:25
 * @route '/agente/follow-up'
 */
export const update = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(options),
    method: 'post',
})

update.definition = {
    methods: ["post"],
    url: '/agente/follow-up',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ConfiguracoesController::update
 * @see app/Http/Controllers/ConfiguracoesController.php:25
 * @route '/agente/follow-up'
 */
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConfiguracoesController::update
 * @see app/Http/Controllers/ConfiguracoesController.php:25
 * @route '/agente/follow-up'
 */
update.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(options),
    method: 'post',
})
const followup = {
    index: Object.assign(index, index),
update: Object.assign(update, update),
}

export default followup