import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\PipelineController::index
 * @see app/Http/Controllers/PipelineController.php:34
 * @route '/pipeline'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/pipeline',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PipelineController::index
 * @see app/Http/Controllers/PipelineController.php:34
 * @route '/pipeline'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PipelineController::index
 * @see app/Http/Controllers/PipelineController.php:34
 * @route '/pipeline'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PipelineController::index
 * @see app/Http/Controllers/PipelineController.php:34
 * @route '/pipeline'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\PipelineController::column
 * @see app/Http/Controllers/PipelineController.php:103
 * @route '/pipeline/columns/{slug}'
 */
export const column = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: column.url(args, options),
    method: 'get',
})

column.definition = {
    methods: ["get","head"],
    url: '/pipeline/columns/{slug}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PipelineController::column
 * @see app/Http/Controllers/PipelineController.php:103
 * @route '/pipeline/columns/{slug}'
 */
column.url = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { slug: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    slug: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        slug: args.slug,
                }

    return column.definition.url
            .replace('{slug}', parsedArgs.slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PipelineController::column
 * @see app/Http/Controllers/PipelineController.php:103
 * @route '/pipeline/columns/{slug}'
 */
column.get = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: column.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PipelineController::column
 * @see app/Http/Controllers/PipelineController.php:103
 * @route '/pipeline/columns/{slug}'
 */
column.head = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: column.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\PipelineController::move
 * @see app/Http/Controllers/PipelineController.php:126
 * @route '/pipeline/move'
 */
export const move = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: move.url(options),
    method: 'post',
})

move.definition = {
    methods: ["post"],
    url: '/pipeline/move',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PipelineController::move
 * @see app/Http/Controllers/PipelineController.php:126
 * @route '/pipeline/move'
 */
move.url = (options?: RouteQueryOptions) => {
    return move.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PipelineController::move
 * @see app/Http/Controllers/PipelineController.php:126
 * @route '/pipeline/move'
 */
move.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: move.url(options),
    method: 'post',
})
const pipeline = {
    index: Object.assign(index, index),
column: Object.assign(column, column),
move: Object.assign(move, move),
}

export default pipeline