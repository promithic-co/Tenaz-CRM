import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\LeadTagController::__invoke
 * @see app/Http/Controllers/LeadTagController.php:25
 * @route '/leads/{lead}/tags'
 */
export const sync = (args: { lead: string | number } | [lead: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sync.url(args, options),
    method: 'post',
})

sync.definition = {
    methods: ["post"],
    url: '/leads/{lead}/tags',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LeadTagController::__invoke
 * @see app/Http/Controllers/LeadTagController.php:25
 * @route '/leads/{lead}/tags'
 */
sync.url = (args: { lead: string | number } | [lead: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return sync.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadTagController::__invoke
 * @see app/Http/Controllers/LeadTagController.php:25
 * @route '/leads/{lead}/tags'
 */
sync.post = (args: { lead: string | number } | [lead: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sync.url(args, options),
    method: 'post',
})
const tags = {
    sync: Object.assign(sync, sync),
}

export default tags