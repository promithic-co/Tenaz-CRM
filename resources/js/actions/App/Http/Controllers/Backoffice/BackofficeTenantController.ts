import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Backoffice\BackofficeTenantController::index
 * @see app/Http/Controllers/Backoffice/BackofficeTenantController.php:12
 * @route '/backoffice/tenants'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/backoffice/tenants',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Backoffice\BackofficeTenantController::index
 * @see app/Http/Controllers/Backoffice/BackofficeTenantController.php:12
 * @route '/backoffice/tenants'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Backoffice\BackofficeTenantController::index
 * @see app/Http/Controllers/Backoffice/BackofficeTenantController.php:12
 * @route '/backoffice/tenants'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Backoffice\BackofficeTenantController::index
 * @see app/Http/Controllers/Backoffice/BackofficeTenantController.php:12
 * @route '/backoffice/tenants'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})
const BackofficeTenantController = { index }

export default BackofficeTenantController