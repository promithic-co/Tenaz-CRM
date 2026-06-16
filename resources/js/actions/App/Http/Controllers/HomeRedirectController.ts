import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\HomeRedirectController::__invoke
 * @see app/Http/Controllers/HomeRedirectController.php:13
 * @route '/'
 */
const HomeRedirectController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: HomeRedirectController.url(options),
    method: 'get',
})

HomeRedirectController.definition = {
    methods: ["get","head"],
    url: '/',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\HomeRedirectController::__invoke
 * @see app/Http/Controllers/HomeRedirectController.php:13
 * @route '/'
 */
HomeRedirectController.url = (options?: RouteQueryOptions) => {
    return HomeRedirectController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\HomeRedirectController::__invoke
 * @see app/Http/Controllers/HomeRedirectController.php:13
 * @route '/'
 */
HomeRedirectController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: HomeRedirectController.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\HomeRedirectController::__invoke
 * @see app/Http/Controllers/HomeRedirectController.php:13
 * @route '/'
 */
HomeRedirectController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: HomeRedirectController.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\HomeRedirectController::__invoke
 * @see app/Http/Controllers/HomeRedirectController.php:13
 * @route '/'
 */
    const HomeRedirectControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: HomeRedirectController.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\HomeRedirectController::__invoke
 * @see app/Http/Controllers/HomeRedirectController.php:13
 * @route '/'
 */
        HomeRedirectControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: HomeRedirectController.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\HomeRedirectController::__invoke
 * @see app/Http/Controllers/HomeRedirectController.php:13
 * @route '/'
 */
        HomeRedirectControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: HomeRedirectController.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    HomeRedirectController.form = HomeRedirectControllerForm
export default HomeRedirectController