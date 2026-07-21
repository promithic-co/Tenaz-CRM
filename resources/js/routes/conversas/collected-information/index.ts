import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\ConversasController::update
 * @see app/Http/Controllers/ConversasController.php:236
 * @route '/conversas/{lead}/informacoes-coletadas'
 */
export const update = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/conversas/{lead}/informacoes-coletadas',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\ConversasController::update
 * @see app/Http/Controllers/ConversasController.php:236
 * @route '/conversas/{lead}/informacoes-coletadas'
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
* @see \App\Http\Controllers\ConversasController::update
 * @see app/Http/Controllers/ConversasController.php:236
 * @route '/conversas/{lead}/informacoes-coletadas'
 */
update.patch = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\ConversasController::update
 * @see app/Http/Controllers/ConversasController.php:236
 * @route '/conversas/{lead}/informacoes-coletadas'
 */
    const updateForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ConversasController::update
 * @see app/Http/Controllers/ConversasController.php:236
 * @route '/conversas/{lead}/informacoes-coletadas'
 */
        updateForm.patch = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
const collectedInformation = {
    update: Object.assign(update, update),
}

export default collectedInformation