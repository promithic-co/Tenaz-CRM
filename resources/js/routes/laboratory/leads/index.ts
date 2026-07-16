import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\LaboratoryController::interactions
 * @see app/Http/Controllers/LaboratoryController.php:318
 * @route '/laboratory/leads/{lead}/interactions'
 */
export const interactions = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: interactions.url(args, options),
    method: 'get',
})

interactions.definition = {
    methods: ["get","head"],
    url: '/laboratory/leads/{lead}/interactions',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LaboratoryController::interactions
 * @see app/Http/Controllers/LaboratoryController.php:318
 * @route '/laboratory/leads/{lead}/interactions'
 */
interactions.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { lead: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { lead: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    lead: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        lead: typeof args.lead === 'object'
                ? args.lead.id
                : args.lead,
                }

    return interactions.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LaboratoryController::interactions
 * @see app/Http/Controllers/LaboratoryController.php:318
 * @route '/laboratory/leads/{lead}/interactions'
 */
interactions.get = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: interactions.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\LaboratoryController::interactions
 * @see app/Http/Controllers/LaboratoryController.php:318
 * @route '/laboratory/leads/{lead}/interactions'
 */
interactions.head = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: interactions.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\LaboratoryController::interactions
 * @see app/Http/Controllers/LaboratoryController.php:318
 * @route '/laboratory/leads/{lead}/interactions'
 */
    const interactionsForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: interactions.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\LaboratoryController::interactions
 * @see app/Http/Controllers/LaboratoryController.php:318
 * @route '/laboratory/leads/{lead}/interactions'
 */
        interactionsForm.get = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: interactions.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\LaboratoryController::interactions
 * @see app/Http/Controllers/LaboratoryController.php:318
 * @route '/laboratory/leads/{lead}/interactions'
 */
        interactionsForm.head = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: interactions.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    interactions.form = interactionsForm
const leads = {
    interactions: Object.assign(interactions, interactions),
}

export default leads