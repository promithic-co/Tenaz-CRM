import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\UraApiKeyController::index
 * @see app/Http/Controllers/UraApiKeyController.php:17
 * @route '/ura'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/ura',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\UraApiKeyController::index
 * @see app/Http/Controllers/UraApiKeyController.php:17
 * @route '/ura'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\UraApiKeyController::index
 * @see app/Http/Controllers/UraApiKeyController.php:17
 * @route '/ura'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\UraApiKeyController::index
 * @see app/Http/Controllers/UraApiKeyController.php:17
 * @route '/ura'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\UraApiKeyController::store
 * @see app/Http/Controllers/UraApiKeyController.php:45
 * @route '/ura'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/ura',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\UraApiKeyController::store
 * @see app/Http/Controllers/UraApiKeyController.php:45
 * @route '/ura'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\UraApiKeyController::store
 * @see app/Http/Controllers/UraApiKeyController.php:45
 * @route '/ura'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\UraApiKeyController::update
 * @see app/Http/Controllers/UraApiKeyController.php:68
 * @route '/ura/{uraApiKey}'
 */
export const update = (args: { uraApiKey: number | { id: number } } | [uraApiKey: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/ura/{uraApiKey}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\UraApiKeyController::update
 * @see app/Http/Controllers/UraApiKeyController.php:68
 * @route '/ura/{uraApiKey}'
 */
update.url = (args: { uraApiKey: number | { id: number } } | [uraApiKey: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { uraApiKey: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { uraApiKey: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    uraApiKey: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        uraApiKey: typeof args.uraApiKey === 'object'
                ? args.uraApiKey.id
                : args.uraApiKey,
                }

    return update.definition.url
            .replace('{uraApiKey}', parsedArgs.uraApiKey.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\UraApiKeyController::update
 * @see app/Http/Controllers/UraApiKeyController.php:68
 * @route '/ura/{uraApiKey}'
 */
update.patch = (args: { uraApiKey: number | { id: number } } | [uraApiKey: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\UraApiKeyController::destroy
 * @see app/Http/Controllers/UraApiKeyController.php:93
 * @route '/ura/{uraApiKey}'
 */
export const destroy = (args: { uraApiKey: number | { id: number } } | [uraApiKey: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/ura/{uraApiKey}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\UraApiKeyController::destroy
 * @see app/Http/Controllers/UraApiKeyController.php:93
 * @route '/ura/{uraApiKey}'
 */
destroy.url = (args: { uraApiKey: number | { id: number } } | [uraApiKey: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { uraApiKey: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { uraApiKey: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    uraApiKey: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        uraApiKey: typeof args.uraApiKey === 'object'
                ? args.uraApiKey.id
                : args.uraApiKey,
                }

    return destroy.definition.url
            .replace('{uraApiKey}', parsedArgs.uraApiKey.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\UraApiKeyController::destroy
 * @see app/Http/Controllers/UraApiKeyController.php:93
 * @route '/ura/{uraApiKey}'
 */
destroy.delete = (args: { uraApiKey: number | { id: number } } | [uraApiKey: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})
const UraApiKeyController = { index, store, update, destroy }

export default UraApiKeyController