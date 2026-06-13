import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\AgenteConfigController::index
 * @see app/Http/Controllers/AgenteConfigController.php:10
 * @route '/agente'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/agente',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AgenteConfigController::index
 * @see app/Http/Controllers/AgenteConfigController.php:10
 * @route '/agente'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AgenteConfigController::index
 * @see app/Http/Controllers/AgenteConfigController.php:10
 * @route '/agente'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AgenteConfigController::index
 * @see app/Http/Controllers/AgenteConfigController.php:10
 * @route '/agente'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\AgenteConfigController::index
 * @see app/Http/Controllers/AgenteConfigController.php:10
 * @route '/agente'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\AgenteConfigController::index
 * @see app/Http/Controllers/AgenteConfigController.php:10
 * @route '/agente'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\AgenteConfigController::index
 * @see app/Http/Controllers/AgenteConfigController.php:10
 * @route '/agente'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
/**
* @see \App\Http\Controllers\AgenteConfigController::update
 * @see app/Http/Controllers/AgenteConfigController.php:25
 * @route '/agente'
 */
export const update = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(options),
    method: 'post',
})

update.definition = {
    methods: ["post"],
    url: '/agente',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AgenteConfigController::update
 * @see app/Http/Controllers/AgenteConfigController.php:25
 * @route '/agente'
 */
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AgenteConfigController::update
 * @see app/Http/Controllers/AgenteConfigController.php:25
 * @route '/agente'
 */
update.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\AgenteConfigController::update
 * @see app/Http/Controllers/AgenteConfigController.php:25
 * @route '/agente'
 */
    const updateForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AgenteConfigController::update
 * @see app/Http/Controllers/AgenteConfigController.php:25
 * @route '/agente'
 */
        updateForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(options),
            method: 'post',
        })
    
    update.form = updateForm
const AgenteConfigController = { index, update }

export default AgenteConfigController