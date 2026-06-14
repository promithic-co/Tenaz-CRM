import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\LeadAutoTagController::store
 * @see app/Http/Controllers/LeadAutoTagController.php:19
 * @route '/leads/{lead}/auto-tag'
 */
export const store = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/leads/{lead}/auto-tag',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LeadAutoTagController::store
 * @see app/Http/Controllers/LeadAutoTagController.php:19
 * @route '/leads/{lead}/auto-tag'
 */
store.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadAutoTagController::store
 * @see app/Http/Controllers/LeadAutoTagController.php:19
 * @route '/leads/{lead}/auto-tag'
 */
store.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})
const autoTag = {
    store: Object.assign(store, store),
}

export default autoTag