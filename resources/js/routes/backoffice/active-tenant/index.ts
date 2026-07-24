import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Backoffice\BackofficeActiveTenantController::store
 * @see app/Http/Controllers/Backoffice/BackofficeActiveTenantController.php:18
 * @route '/backoffice/empresa-ativa'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/backoffice/empresa-ativa',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Backoffice\BackofficeActiveTenantController::store
 * @see app/Http/Controllers/Backoffice/BackofficeActiveTenantController.php:18
 * @route '/backoffice/empresa-ativa'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Backoffice\BackofficeActiveTenantController::store
 * @see app/Http/Controllers/Backoffice/BackofficeActiveTenantController.php:18
 * @route '/backoffice/empresa-ativa'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Backoffice\BackofficeActiveTenantController::store
 * @see app/Http/Controllers/Backoffice/BackofficeActiveTenantController.php:18
 * @route '/backoffice/empresa-ativa'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Backoffice\BackofficeActiveTenantController::store
 * @see app/Http/Controllers/Backoffice/BackofficeActiveTenantController.php:18
 * @route '/backoffice/empresa-ativa'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Backoffice\BackofficeActiveTenantController::destroy
 * @see app/Http/Controllers/Backoffice/BackofficeActiveTenantController.php:32
 * @route '/backoffice/empresa-ativa'
 */
export const destroy = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/backoffice/empresa-ativa',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Backoffice\BackofficeActiveTenantController::destroy
 * @see app/Http/Controllers/Backoffice/BackofficeActiveTenantController.php:32
 * @route '/backoffice/empresa-ativa'
 */
destroy.url = (options?: RouteQueryOptions) => {
    return destroy.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Backoffice\BackofficeActiveTenantController::destroy
 * @see app/Http/Controllers/Backoffice/BackofficeActiveTenantController.php:32
 * @route '/backoffice/empresa-ativa'
 */
destroy.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Backoffice\BackofficeActiveTenantController::destroy
 * @see app/Http/Controllers/Backoffice/BackofficeActiveTenantController.php:32
 * @route '/backoffice/empresa-ativa'
 */
    const destroyForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Backoffice\BackofficeActiveTenantController::destroy
 * @see app/Http/Controllers/Backoffice/BackofficeActiveTenantController.php:32
 * @route '/backoffice/empresa-ativa'
 */
        destroyForm.delete = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const activeTenant = {
    store: Object.assign(store, store),
destroy: Object.assign(destroy, destroy),
}

export default activeTenant