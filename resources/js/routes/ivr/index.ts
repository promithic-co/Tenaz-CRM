import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\IvrController::script
 * @see app/Http/Controllers/IvrController.php:17
 * @route '/api/ivr/call/{voiceCampaignCall}/script'
 */
export const script = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: script.url(args, options),
    method: 'post',
})

script.definition = {
    methods: ["post"],
    url: '/api/ivr/call/{voiceCampaignCall}/script',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\IvrController::script
 * @see app/Http/Controllers/IvrController.php:17
 * @route '/api/ivr/call/{voiceCampaignCall}/script'
 */
script.url = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { voiceCampaignCall: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { voiceCampaignCall: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    voiceCampaignCall: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        voiceCampaignCall: typeof args.voiceCampaignCall === 'object'
                ? args.voiceCampaignCall.id
                : args.voiceCampaignCall,
                }

    return script.definition.url
            .replace('{voiceCampaignCall}', parsedArgs.voiceCampaignCall.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\IvrController::script
 * @see app/Http/Controllers/IvrController.php:17
 * @route '/api/ivr/call/{voiceCampaignCall}/script'
 */
script.post = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: script.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\IvrController::script
 * @see app/Http/Controllers/IvrController.php:17
 * @route '/api/ivr/call/{voiceCampaignCall}/script'
 */
    const scriptForm = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: script.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\IvrController::script
 * @see app/Http/Controllers/IvrController.php:17
 * @route '/api/ivr/call/{voiceCampaignCall}/script'
 */
        scriptForm.post = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: script.url(args, options),
            method: 'post',
        })
    
    script.form = scriptForm
/**
* @see \App\Http\Controllers\IvrController::dtmf
 * @see app/Http/Controllers/IvrController.php:72
 * @route '/api/ivr/call/{voiceCampaignCall}/dtmf'
 */
export const dtmf = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: dtmf.url(args, options),
    method: 'post',
})

dtmf.definition = {
    methods: ["post"],
    url: '/api/ivr/call/{voiceCampaignCall}/dtmf',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\IvrController::dtmf
 * @see app/Http/Controllers/IvrController.php:72
 * @route '/api/ivr/call/{voiceCampaignCall}/dtmf'
 */
dtmf.url = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { voiceCampaignCall: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { voiceCampaignCall: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    voiceCampaignCall: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        voiceCampaignCall: typeof args.voiceCampaignCall === 'object'
                ? args.voiceCampaignCall.id
                : args.voiceCampaignCall,
                }

    return dtmf.definition.url
            .replace('{voiceCampaignCall}', parsedArgs.voiceCampaignCall.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\IvrController::dtmf
 * @see app/Http/Controllers/IvrController.php:72
 * @route '/api/ivr/call/{voiceCampaignCall}/dtmf'
 */
dtmf.post = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: dtmf.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\IvrController::dtmf
 * @see app/Http/Controllers/IvrController.php:72
 * @route '/api/ivr/call/{voiceCampaignCall}/dtmf'
 */
    const dtmfForm = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: dtmf.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\IvrController::dtmf
 * @see app/Http/Controllers/IvrController.php:72
 * @route '/api/ivr/call/{voiceCampaignCall}/dtmf'
 */
        dtmfForm.post = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: dtmf.url(args, options),
            method: 'post',
        })
    
    dtmf.form = dtmfForm
/**
* @see \App\Http\Controllers\IvrController::status
 * @see app/Http/Controllers/IvrController.php:148
 * @route '/api/ivr/call/{voiceCampaignCall}/status'
 */
export const status = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: status.url(args, options),
    method: 'post',
})

status.definition = {
    methods: ["post"],
    url: '/api/ivr/call/{voiceCampaignCall}/status',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\IvrController::status
 * @see app/Http/Controllers/IvrController.php:148
 * @route '/api/ivr/call/{voiceCampaignCall}/status'
 */
status.url = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { voiceCampaignCall: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { voiceCampaignCall: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    voiceCampaignCall: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        voiceCampaignCall: typeof args.voiceCampaignCall === 'object'
                ? args.voiceCampaignCall.id
                : args.voiceCampaignCall,
                }

    return status.definition.url
            .replace('{voiceCampaignCall}', parsedArgs.voiceCampaignCall.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\IvrController::status
 * @see app/Http/Controllers/IvrController.php:148
 * @route '/api/ivr/call/{voiceCampaignCall}/status'
 */
status.post = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: status.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\IvrController::status
 * @see app/Http/Controllers/IvrController.php:148
 * @route '/api/ivr/call/{voiceCampaignCall}/status'
 */
    const statusForm = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: status.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\IvrController::status
 * @see app/Http/Controllers/IvrController.php:148
 * @route '/api/ivr/call/{voiceCampaignCall}/status'
 */
        statusForm.post = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: status.url(args, options),
            method: 'post',
        })
    
    status.form = statusForm
const ivr = {
    script: Object.assign(script, script),
dtmf: Object.assign(dtmf, dtmf),
status: Object.assign(status, status),
}

export default ivr