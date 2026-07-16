import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\IvrController::script
 * @see app/Http/Controllers/IvrController.php:18
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
 * @see app/Http/Controllers/IvrController.php:18
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
 * @see app/Http/Controllers/IvrController.php:18
 * @route '/api/ivr/call/{voiceCampaignCall}/script'
 */
script.post = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: script.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\IvrController::script
 * @see app/Http/Controllers/IvrController.php:18
 * @route '/api/ivr/call/{voiceCampaignCall}/script'
 */
    const scriptForm = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: script.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\IvrController::script
 * @see app/Http/Controllers/IvrController.php:18
 * @route '/api/ivr/call/{voiceCampaignCall}/script'
 */
        scriptForm.post = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: script.url(args, options),
            method: 'post',
        })
    
    script.form = scriptForm
/**
* @see \App\Http\Controllers\IvrController::handleDtmf
 * @see app/Http/Controllers/IvrController.php:70
 * @route '/api/ivr/call/{voiceCampaignCall}/dtmf'
 */
export const handleDtmf = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: handleDtmf.url(args, options),
    method: 'post',
})

handleDtmf.definition = {
    methods: ["post"],
    url: '/api/ivr/call/{voiceCampaignCall}/dtmf',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\IvrController::handleDtmf
 * @see app/Http/Controllers/IvrController.php:70
 * @route '/api/ivr/call/{voiceCampaignCall}/dtmf'
 */
handleDtmf.url = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return handleDtmf.definition.url
            .replace('{voiceCampaignCall}', parsedArgs.voiceCampaignCall.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\IvrController::handleDtmf
 * @see app/Http/Controllers/IvrController.php:70
 * @route '/api/ivr/call/{voiceCampaignCall}/dtmf'
 */
handleDtmf.post = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: handleDtmf.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\IvrController::handleDtmf
 * @see app/Http/Controllers/IvrController.php:70
 * @route '/api/ivr/call/{voiceCampaignCall}/dtmf'
 */
    const handleDtmfForm = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: handleDtmf.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\IvrController::handleDtmf
 * @see app/Http/Controllers/IvrController.php:70
 * @route '/api/ivr/call/{voiceCampaignCall}/dtmf'
 */
        handleDtmfForm.post = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: handleDtmf.url(args, options),
            method: 'post',
        })
    
    handleDtmf.form = handleDtmfForm
/**
* @see \App\Http\Controllers\IvrController::statusCallback
 * @see app/Http/Controllers/IvrController.php:147
 * @route '/api/ivr/call/{voiceCampaignCall}/status'
 */
export const statusCallback = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: statusCallback.url(args, options),
    method: 'post',
})

statusCallback.definition = {
    methods: ["post"],
    url: '/api/ivr/call/{voiceCampaignCall}/status',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\IvrController::statusCallback
 * @see app/Http/Controllers/IvrController.php:147
 * @route '/api/ivr/call/{voiceCampaignCall}/status'
 */
statusCallback.url = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return statusCallback.definition.url
            .replace('{voiceCampaignCall}', parsedArgs.voiceCampaignCall.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\IvrController::statusCallback
 * @see app/Http/Controllers/IvrController.php:147
 * @route '/api/ivr/call/{voiceCampaignCall}/status'
 */
statusCallback.post = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: statusCallback.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\IvrController::statusCallback
 * @see app/Http/Controllers/IvrController.php:147
 * @route '/api/ivr/call/{voiceCampaignCall}/status'
 */
    const statusCallbackForm = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: statusCallback.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\IvrController::statusCallback
 * @see app/Http/Controllers/IvrController.php:147
 * @route '/api/ivr/call/{voiceCampaignCall}/status'
 */
        statusCallbackForm.post = (args: { voiceCampaignCall: number | { id: number } } | [voiceCampaignCall: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: statusCallback.url(args, options),
            method: 'post',
        })
    
    statusCallback.form = statusCallbackForm
const IvrController = { script, handleDtmf, statusCallback }

export default IvrController