import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\LeadTagController::__invoke
 * @see app/Http/Controllers/LeadTagController.php:25
 * @route '/leads/{lead}/tags'
 */
const LeadTagController = (args: { lead: string | number } | [lead: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: LeadTagController.url(args, options),
    method: 'post',
})

LeadTagController.definition = {
    methods: ["post"],
    url: '/leads/{lead}/tags',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LeadTagController::__invoke
 * @see app/Http/Controllers/LeadTagController.php:25
 * @route '/leads/{lead}/tags'
 */
LeadTagController.url = (args: { lead: string | number } | [lead: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { lead: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    lead: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        lead: args.lead,
                }

    return LeadTagController.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadTagController::__invoke
 * @see app/Http/Controllers/LeadTagController.php:25
 * @route '/leads/{lead}/tags'
 */
LeadTagController.post = (args: { lead: string | number } | [lead: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: LeadTagController.url(args, options),
    method: 'post',
})
export default LeadTagController