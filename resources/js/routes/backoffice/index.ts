import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
import activeTenant from './active-tenant'
import agents from './agents'
import templates from './templates'
import nicheTemplates from './niche-templates'
import tenants from './tenants'
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
const backoffice = {
    index: Object.assign(index, index),
activeTenant: Object.assign(activeTenant, activeTenant),
agents: Object.assign(agents, agents),
templates: Object.assign(templates, templates),
nicheTemplates: Object.assign(nicheTemplates, nicheTemplates),
tenants: Object.assign(tenants, tenants),
}

export default backoffice