import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\LaboratoryController::show
 * @see app/Http/Controllers/LaboratoryController.php:269
 * @route '/laboratory/interactions/{interactionId}'
 */
export const show = (args: { interactionId: string | number } | [interactionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/laboratory/interactions/{interactionId}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LaboratoryController::show
 * @see app/Http/Controllers/LaboratoryController.php:269
 * @route '/laboratory/interactions/{interactionId}'
 */
show.url = (args: { interactionId: string | number } | [interactionId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { interactionId: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    interactionId: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        interactionId: args.interactionId,
                }

    return show.definition.url
            .replace('{interactionId}', parsedArgs.interactionId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LaboratoryController::show
 * @see app/Http/Controllers/LaboratoryController.php:269
 * @route '/laboratory/interactions/{interactionId}'
 */
show.get = (args: { interactionId: string | number } | [interactionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\LaboratoryController::show
 * @see app/Http/Controllers/LaboratoryController.php:269
 * @route '/laboratory/interactions/{interactionId}'
 */
show.head = (args: { interactionId: string | number } | [interactionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\LaboratoryController::show
 * @see app/Http/Controllers/LaboratoryController.php:269
 * @route '/laboratory/interactions/{interactionId}'
 */
    const showForm = (args: { interactionId: string | number } | [interactionId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\LaboratoryController::show
 * @see app/Http/Controllers/LaboratoryController.php:269
 * @route '/laboratory/interactions/{interactionId}'
 */
        showForm.get = (args: { interactionId: string | number } | [interactionId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\LaboratoryController::show
 * @see app/Http/Controllers/LaboratoryController.php:269
 * @route '/laboratory/interactions/{interactionId}'
 */
        showForm.head = (args: { interactionId: string | number } | [interactionId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
const interactions = {
    show: Object.assign(show, show),
}

export default interactions