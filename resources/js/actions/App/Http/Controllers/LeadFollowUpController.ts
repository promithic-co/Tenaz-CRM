import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\LeadFollowUpController::pause
 * @see app/Http/Controllers/LeadFollowUpController.php:12
 * @route '/conversas/{lead}/followup-pause'
 */
export const pause = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pause.url(args, options),
    method: 'post',
})

pause.definition = {
    methods: ["post"],
    url: '/conversas/{lead}/followup-pause',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LeadFollowUpController::pause
 * @see app/Http/Controllers/LeadFollowUpController.php:12
 * @route '/conversas/{lead}/followup-pause'
 */
pause.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return pause.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadFollowUpController::pause
 * @see app/Http/Controllers/LeadFollowUpController.php:12
 * @route '/conversas/{lead}/followup-pause'
 */
pause.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pause.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\LeadFollowUpController::resume
 * @see app/Http/Controllers/LeadFollowUpController.php:27
 * @route '/conversas/{lead}/followup-resume'
 */
export const resume = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resume.url(args, options),
    method: 'post',
})

resume.definition = {
    methods: ["post"],
    url: '/conversas/{lead}/followup-resume',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LeadFollowUpController::resume
 * @see app/Http/Controllers/LeadFollowUpController.php:27
 * @route '/conversas/{lead}/followup-resume'
 */
resume.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return resume.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadFollowUpController::resume
 * @see app/Http/Controllers/LeadFollowUpController.php:27
 * @route '/conversas/{lead}/followup-resume'
 */
resume.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resume.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\LeadFollowUpController::disable
 * @see app/Http/Controllers/LeadFollowUpController.php:53
 * @route '/conversas/{lead}/followup-disable'
 */
export const disable = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: disable.url(args, options),
    method: 'post',
})

disable.definition = {
    methods: ["post"],
    url: '/conversas/{lead}/followup-disable',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LeadFollowUpController::disable
 * @see app/Http/Controllers/LeadFollowUpController.php:53
 * @route '/conversas/{lead}/followup-disable'
 */
disable.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return disable.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadFollowUpController::disable
 * @see app/Http/Controllers/LeadFollowUpController.php:53
 * @route '/conversas/{lead}/followup-disable'
 */
disable.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: disable.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\LeadFollowUpController::reactivate
 * @see app/Http/Controllers/LeadFollowUpController.php:72
 * @route '/conversas/{lead}/followup-reactivate'
 */
export const reactivate = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reactivate.url(args, options),
    method: 'post',
})

reactivate.definition = {
    methods: ["post"],
    url: '/conversas/{lead}/followup-reactivate',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LeadFollowUpController::reactivate
 * @see app/Http/Controllers/LeadFollowUpController.php:72
 * @route '/conversas/{lead}/followup-reactivate'
 */
reactivate.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return reactivate.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadFollowUpController::reactivate
 * @see app/Http/Controllers/LeadFollowUpController.php:72
 * @route '/conversas/{lead}/followup-reactivate'
 */
reactivate.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reactivate.url(args, options),
    method: 'post',
})
const LeadFollowUpController = { pause, resume, disable, reactivate }

export default LeadFollowUpController