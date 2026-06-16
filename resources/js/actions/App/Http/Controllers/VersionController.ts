import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\VersionController::__invoke
 * @see app/Http/Controllers/VersionController.php:13
 * @route '/__version'
 */
const VersionController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: VersionController.url(options),
    method: 'get',
})

VersionController.definition = {
    methods: ["get","head"],
    url: '/__version',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\VersionController::__invoke
 * @see app/Http/Controllers/VersionController.php:13
 * @route '/__version'
 */
VersionController.url = (options?: RouteQueryOptions) => {
    return VersionController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\VersionController::__invoke
 * @see app/Http/Controllers/VersionController.php:13
 * @route '/__version'
 */
VersionController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: VersionController.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\VersionController::__invoke
 * @see app/Http/Controllers/VersionController.php:13
 * @route '/__version'
 */
VersionController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: VersionController.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\VersionController::__invoke
 * @see app/Http/Controllers/VersionController.php:13
 * @route '/__version'
 */
    const VersionControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: VersionController.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\VersionController::__invoke
 * @see app/Http/Controllers/VersionController.php:13
 * @route '/__version'
 */
        VersionControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: VersionController.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\VersionController::__invoke
 * @see app/Http/Controllers/VersionController.php:13
 * @route '/__version'
 */
        VersionControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: VersionController.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    VersionController.form = VersionControllerForm
export default VersionController