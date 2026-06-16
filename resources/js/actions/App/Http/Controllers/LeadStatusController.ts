import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\LeadStatusController::update
 * @see app/Http/Controllers/LeadStatusController.php:29
 * @route '/leads/{lead}/status'
 */
export const update = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(args, options),
    method: 'post',
})

update.definition = {
    methods: ["post"],
    url: '/leads/{lead}/status',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LeadStatusController::update
 * @see app/Http/Controllers/LeadStatusController.php:29
 * @route '/leads/{lead}/status'
 */
update.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadStatusController::update
 * @see app/Http/Controllers/LeadStatusController.php:29
 * @route '/leads/{lead}/status'
 */
update.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\LeadStatusController::update
 * @see app/Http/Controllers/LeadStatusController.php:29
 * @route '/leads/{lead}/status'
 */
    const updateForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\LeadStatusController::update
 * @see app/Http/Controllers/LeadStatusController.php:29
 * @route '/leads/{lead}/status'
 */
        updateForm.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, options),
            method: 'post',
        })
    
    update.form = updateForm
const LeadStatusController = { update }

export default LeadStatusController