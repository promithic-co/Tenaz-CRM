import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\ConversationSessionController::update
 * @see app/Http/Controllers/ConversationSessionController.php:59
 * @route '/conversas/{lead}/sessions/{session}/informacoes'
 */
export const update = (args: { lead: number | { id: number }, session: number | { id: number } } | [lead: number | { id: number }, session: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/conversas/{lead}/sessions/{session}/informacoes',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\ConversationSessionController::update
 * @see app/Http/Controllers/ConversationSessionController.php:59
 * @route '/conversas/{lead}/sessions/{session}/informacoes'
 */
update.url = (args: { lead: number | { id: number }, session: number | { id: number } } | [lead: number | { id: number }, session: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    lead: args[0],
                    session: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        lead: typeof args.lead === 'object'
                ? args.lead.id
                : args.lead,
                                session: typeof args.session === 'object'
                ? args.session.id
                : args.session,
                }

    return update.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace('{session}', parsedArgs.session.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConversationSessionController::update
 * @see app/Http/Controllers/ConversationSessionController.php:59
 * @route '/conversas/{lead}/sessions/{session}/informacoes'
 */
update.patch = (args: { lead: number | { id: number }, session: number | { id: number } } | [lead: number | { id: number }, session: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\ConversationSessionController::update
 * @see app/Http/Controllers/ConversationSessionController.php:59
 * @route '/conversas/{lead}/sessions/{session}/informacoes'
 */
    const updateForm = (args: { lead: number | { id: number }, session: number | { id: number } } | [lead: number | { id: number }, session: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ConversationSessionController::update
 * @see app/Http/Controllers/ConversationSessionController.php:59
 * @route '/conversas/{lead}/sessions/{session}/informacoes'
 */
        updateForm.patch = (args: { lead: number | { id: number }, session: number | { id: number } } | [lead: number | { id: number }, session: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
const information = {
    update: Object.assign(update, update),
}

export default information