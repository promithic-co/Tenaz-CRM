import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
import statuses from './statuses'
import transitions from './transitions'
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
const pipeline = {
    index: Object.assign(index, index),
statuses: Object.assign(statuses, statuses),
transitions: Object.assign(transitions, transitions),
reorder: Object.assign(reorder, reorder),
reset: Object.assign(reset, reset),
}

export default pipeline