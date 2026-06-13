import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
import followup from './followup'
/**
* @see \App\Http\Controllers\ServiceTicketController::index
 * @see app/Http/Controllers/ServiceTicketController.php:18
 * @route '/atendimentos'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/atendimentos',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ServiceTicketController::index
 * @see app/Http/Controllers/ServiceTicketController.php:18
 * @route '/atendimentos'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ServiceTicketController::index
 * @see app/Http/Controllers/ServiceTicketController.php:18
 * @route '/atendimentos'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ServiceTicketController::index
 * @see app/Http/Controllers/ServiceTicketController.php:18
 * @route '/atendimentos'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\ServiceTicketController::index
 * @see app/Http/Controllers/ServiceTicketController.php:18
 * @route '/atendimentos'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\ServiceTicketController::index
 * @see app/Http/Controllers/ServiceTicketController.php:18
 * @route '/atendimentos'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\ServiceTicketController::index
 * @see app/Http/Controllers/ServiceTicketController.php:18
 * @route '/atendimentos'
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
/**
* @see \App\Http\Controllers\ServiceTicketController::claim
 * @see app/Http/Controllers/ServiceTicketController.php:42
 * @route '/atendimentos/{ticket}/claim'
 */
export const claim = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: claim.url(args, options),
    method: 'post',
})

claim.definition = {
    methods: ["post"],
    url: '/atendimentos/{ticket}/claim',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ServiceTicketController::claim
 * @see app/Http/Controllers/ServiceTicketController.php:42
 * @route '/atendimentos/{ticket}/claim'
 */
claim.url = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { ticket: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { ticket: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    ticket: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        ticket: typeof args.ticket === 'object'
                ? args.ticket.id
                : args.ticket,
                }

    return claim.definition.url
            .replace('{ticket}', parsedArgs.ticket.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ServiceTicketController::claim
 * @see app/Http/Controllers/ServiceTicketController.php:42
 * @route '/atendimentos/{ticket}/claim'
 */
claim.post = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: claim.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ServiceTicketController::claim
 * @see app/Http/Controllers/ServiceTicketController.php:42
 * @route '/atendimentos/{ticket}/claim'
 */
    const claimForm = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: claim.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ServiceTicketController::claim
 * @see app/Http/Controllers/ServiceTicketController.php:42
 * @route '/atendimentos/{ticket}/claim'
 */
        claimForm.post = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: claim.url(args, options),
            method: 'post',
        })
    
    claim.form = claimForm
/**
* @see \App\Http\Controllers\ServiceTicketController::resolve
 * @see app/Http/Controllers/ServiceTicketController.php:74
 * @route '/atendimentos/{ticket}/resolve'
 */
export const resolve = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resolve.url(args, options),
    method: 'post',
})

resolve.definition = {
    methods: ["post"],
    url: '/atendimentos/{ticket}/resolve',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ServiceTicketController::resolve
 * @see app/Http/Controllers/ServiceTicketController.php:74
 * @route '/atendimentos/{ticket}/resolve'
 */
resolve.url = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { ticket: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { ticket: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    ticket: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        ticket: typeof args.ticket === 'object'
                ? args.ticket.id
                : args.ticket,
                }

    return resolve.definition.url
            .replace('{ticket}', parsedArgs.ticket.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ServiceTicketController::resolve
 * @see app/Http/Controllers/ServiceTicketController.php:74
 * @route '/atendimentos/{ticket}/resolve'
 */
resolve.post = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resolve.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ServiceTicketController::resolve
 * @see app/Http/Controllers/ServiceTicketController.php:74
 * @route '/atendimentos/{ticket}/resolve'
 */
    const resolveForm = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: resolve.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ServiceTicketController::resolve
 * @see app/Http/Controllers/ServiceTicketController.php:74
 * @route '/atendimentos/{ticket}/resolve'
 */
        resolveForm.post = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: resolve.url(args, options),
            method: 'post',
        })
    
    resolve.form = resolveForm
/**
* @see \App\Http\Controllers\ServiceTicketController::close
 * @see app/Http/Controllers/ServiceTicketController.php:90
 * @route '/atendimentos/{ticket}/close'
 */
export const close = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: close.url(args, options),
    method: 'post',
})

close.definition = {
    methods: ["post"],
    url: '/atendimentos/{ticket}/close',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ServiceTicketController::close
 * @see app/Http/Controllers/ServiceTicketController.php:90
 * @route '/atendimentos/{ticket}/close'
 */
close.url = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { ticket: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { ticket: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    ticket: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        ticket: typeof args.ticket === 'object'
                ? args.ticket.id
                : args.ticket,
                }

    return close.definition.url
            .replace('{ticket}', parsedArgs.ticket.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ServiceTicketController::close
 * @see app/Http/Controllers/ServiceTicketController.php:90
 * @route '/atendimentos/{ticket}/close'
 */
close.post = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: close.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ServiceTicketController::close
 * @see app/Http/Controllers/ServiceTicketController.php:90
 * @route '/atendimentos/{ticket}/close'
 */
    const closeForm = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: close.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ServiceTicketController::close
 * @see app/Http/Controllers/ServiceTicketController.php:90
 * @route '/atendimentos/{ticket}/close'
 */
        closeForm.post = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: close.url(args, options),
            method: 'post',
        })
    
    close.form = closeForm
/**
* @see \App\Http\Controllers\ServiceTicketController::returnToAi
 * @see app/Http/Controllers/ServiceTicketController.php:106
 * @route '/atendimentos/{ticket}/return-to-ai'
 */
export const returnToAi = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: returnToAi.url(args, options),
    method: 'post',
})

returnToAi.definition = {
    methods: ["post"],
    url: '/atendimentos/{ticket}/return-to-ai',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ServiceTicketController::returnToAi
 * @see app/Http/Controllers/ServiceTicketController.php:106
 * @route '/atendimentos/{ticket}/return-to-ai'
 */
returnToAi.url = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { ticket: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { ticket: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    ticket: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        ticket: typeof args.ticket === 'object'
                ? args.ticket.id
                : args.ticket,
                }

    return returnToAi.definition.url
            .replace('{ticket}', parsedArgs.ticket.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ServiceTicketController::returnToAi
 * @see app/Http/Controllers/ServiceTicketController.php:106
 * @route '/atendimentos/{ticket}/return-to-ai'
 */
returnToAi.post = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: returnToAi.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ServiceTicketController::returnToAi
 * @see app/Http/Controllers/ServiceTicketController.php:106
 * @route '/atendimentos/{ticket}/return-to-ai'
 */
    const returnToAiForm = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: returnToAi.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ServiceTicketController::returnToAi
 * @see app/Http/Controllers/ServiceTicketController.php:106
 * @route '/atendimentos/{ticket}/return-to-ai'
 */
        returnToAiForm.post = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: returnToAi.url(args, options),
            method: 'post',
        })
    
    returnToAi.form = returnToAiForm
/**
* @see \App\Http\Controllers\ServiceTicketController::keepManual
 * @see app/Http/Controllers/ServiceTicketController.php:115
 * @route '/atendimentos/{ticket}/keep-manual'
 */
export const keepManual = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: keepManual.url(args, options),
    method: 'post',
})

keepManual.definition = {
    methods: ["post"],
    url: '/atendimentos/{ticket}/keep-manual',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ServiceTicketController::keepManual
 * @see app/Http/Controllers/ServiceTicketController.php:115
 * @route '/atendimentos/{ticket}/keep-manual'
 */
keepManual.url = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { ticket: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { ticket: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    ticket: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        ticket: typeof args.ticket === 'object'
                ? args.ticket.id
                : args.ticket,
                }

    return keepManual.definition.url
            .replace('{ticket}', parsedArgs.ticket.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ServiceTicketController::keepManual
 * @see app/Http/Controllers/ServiceTicketController.php:115
 * @route '/atendimentos/{ticket}/keep-manual'
 */
keepManual.post = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: keepManual.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ServiceTicketController::keepManual
 * @see app/Http/Controllers/ServiceTicketController.php:115
 * @route '/atendimentos/{ticket}/keep-manual'
 */
    const keepManualForm = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: keepManual.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ServiceTicketController::keepManual
 * @see app/Http/Controllers/ServiceTicketController.php:115
 * @route '/atendimentos/{ticket}/keep-manual'
 */
        keepManualForm.post = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: keepManual.url(args, options),
            method: 'post',
        })
    
    keepManual.form = keepManualForm
const atendimentos = {
    index: Object.assign(index, index),
claim: Object.assign(claim, claim),
followup: Object.assign(followup, followup),
resolve: Object.assign(resolve, resolve),
close: Object.assign(close, close),
returnToAi: Object.assign(returnToAi, returnToAi),
keepManual: Object.assign(keepManual, keepManual),
}

export default atendimentos