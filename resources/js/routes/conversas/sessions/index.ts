import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\ConversationSessionController::store
 * @see app/Http/Controllers/ConversationSessionController.php:27
 * @route '/conversas/{lead}/sessions'
 */
export const store = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/conversas/{lead}/sessions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ConversationSessionController::store
 * @see app/Http/Controllers/ConversationSessionController.php:27
 * @route '/conversas/{lead}/sessions'
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
* @see \App\Http\Controllers\ConversationSessionController::store
 * @see app/Http/Controllers/ConversationSessionController.php:27
 * @route '/conversas/{lead}/sessions'
 */
store.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ConversationSessionController::store
 * @see app/Http/Controllers/ConversationSessionController.php:27
 * @route '/conversas/{lead}/sessions'
 */
    const storeForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ConversationSessionController::store
 * @see app/Http/Controllers/ConversationSessionController.php:27
 * @route '/conversas/{lead}/sessions'
 */
        storeForm.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\ConversationSessionController::close
 * @see app/Http/Controllers/ConversationSessionController.php:40
 * @route '/conversas/{lead}/sessions/{session}/close'
 */
export const close = (args: { lead: number | { id: number }, session: number | { id: number } } | [lead: number | { id: number }, session: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: close.url(args, options),
    method: 'post',
})

close.definition = {
    methods: ["post"],
    url: '/conversas/{lead}/sessions/{session}/close',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ConversationSessionController::close
 * @see app/Http/Controllers/ConversationSessionController.php:40
 * @route '/conversas/{lead}/sessions/{session}/close'
 */
close.url = (args: { lead: number | { id: number }, session: number | { id: number } } | [lead: number | { id: number }, session: number | { id: number } ], options?: RouteQueryOptions) => {
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

    return close.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace('{session}', parsedArgs.session.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConversationSessionController::close
 * @see app/Http/Controllers/ConversationSessionController.php:40
 * @route '/conversas/{lead}/sessions/{session}/close'
 */
close.post = (args: { lead: number | { id: number }, session: number | { id: number } } | [lead: number | { id: number }, session: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: close.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ConversationSessionController::close
 * @see app/Http/Controllers/ConversationSessionController.php:40
 * @route '/conversas/{lead}/sessions/{session}/close'
 */
    const closeForm = (args: { lead: number | { id: number }, session: number | { id: number } } | [lead: number | { id: number }, session: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: close.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ConversationSessionController::close
 * @see app/Http/Controllers/ConversationSessionController.php:40
 * @route '/conversas/{lead}/sessions/{session}/close'
 */
        closeForm.post = (args: { lead: number | { id: number }, session: number | { id: number } } | [lead: number | { id: number }, session: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: close.url(args, options),
            method: 'post',
        })
    
    close.form = closeForm
const sessions = {
    store: Object.assign(store, store),
close: Object.assign(close, close),
}

export default sessions