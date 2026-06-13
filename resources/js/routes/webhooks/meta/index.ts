import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\MetaWebhookController::verify
 * @see app/Http/Controllers/MetaWebhookController.php:35
 * @route '/api/webhooks/meta'
 */
export const verify = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: verify.url(options),
    method: 'get',
})

verify.definition = {
    methods: ["get","head"],
    url: '/api/webhooks/meta',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MetaWebhookController::verify
 * @see app/Http/Controllers/MetaWebhookController.php:35
 * @route '/api/webhooks/meta'
 */
verify.url = (options?: RouteQueryOptions) => {
    return verify.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MetaWebhookController::verify
 * @see app/Http/Controllers/MetaWebhookController.php:35
 * @route '/api/webhooks/meta'
 */
verify.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: verify.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\MetaWebhookController::verify
 * @see app/Http/Controllers/MetaWebhookController.php:35
 * @route '/api/webhooks/meta'
 */
verify.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: verify.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\MetaWebhookController::verify
 * @see app/Http/Controllers/MetaWebhookController.php:35
 * @route '/api/webhooks/meta'
 */
    const verifyForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: verify.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\MetaWebhookController::verify
 * @see app/Http/Controllers/MetaWebhookController.php:35
 * @route '/api/webhooks/meta'
 */
        verifyForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: verify.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\MetaWebhookController::verify
 * @see app/Http/Controllers/MetaWebhookController.php:35
 * @route '/api/webhooks/meta'
 */
        verifyForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: verify.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    verify.form = verifyForm
/**
* @see \App\Http\Controllers\MetaWebhookController::handle
 * @see app/Http/Controllers/MetaWebhookController.php:60
 * @route '/api/webhooks/meta'
 */
export const handle = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: handle.url(options),
    method: 'post',
})

handle.definition = {
    methods: ["post"],
    url: '/api/webhooks/meta',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\MetaWebhookController::handle
 * @see app/Http/Controllers/MetaWebhookController.php:60
 * @route '/api/webhooks/meta'
 */
handle.url = (options?: RouteQueryOptions) => {
    return handle.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MetaWebhookController::handle
 * @see app/Http/Controllers/MetaWebhookController.php:60
 * @route '/api/webhooks/meta'
 */
handle.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: handle.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\MetaWebhookController::handle
 * @see app/Http/Controllers/MetaWebhookController.php:60
 * @route '/api/webhooks/meta'
 */
    const handleForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: handle.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\MetaWebhookController::handle
 * @see app/Http/Controllers/MetaWebhookController.php:60
 * @route '/api/webhooks/meta'
 */
        handleForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: handle.url(options),
            method: 'post',
        })
    
    handle.form = handleForm
const meta = {
    verify: Object.assign(verify, verify),
handle: Object.assign(handle, handle),
}

export default meta