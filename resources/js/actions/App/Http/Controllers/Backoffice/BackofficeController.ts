import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Backoffice\BackofficeController::index
 * @see app/Http/Controllers/Backoffice/BackofficeController.php:11
 * @route '/backoffice'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/backoffice',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Backoffice\BackofficeController::index
 * @see app/Http/Controllers/Backoffice/BackofficeController.php:11
 * @route '/backoffice'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Backoffice\BackofficeController::index
 * @see app/Http/Controllers/Backoffice/BackofficeController.php:11
 * @route '/backoffice'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Backoffice\BackofficeController::index
 * @see app/Http/Controllers/Backoffice/BackofficeController.php:11
 * @route '/backoffice'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Backoffice\BackofficeController::index
 * @see app/Http/Controllers/Backoffice/BackofficeController.php:11
 * @route '/backoffice'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Backoffice\BackofficeController::index
 * @see app/Http/Controllers/Backoffice/BackofficeController.php:11
 * @route '/backoffice'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Backoffice\BackofficeController::index
 * @see app/Http/Controllers/Backoffice/BackofficeController.php:11
 * @route '/backoffice'
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
const BackofficeController = { index }

export default BackofficeController