import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\MetaEmbeddedSignupController::callback
 * @see app/Http/Controllers/MetaEmbeddedSignupController.php:21
 * @route '/whatsapp/meta/embedded-signup'
 */
export const callback = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: callback.url(options),
    method: 'post',
})

callback.definition = {
    methods: ["post"],
    url: '/whatsapp/meta/embedded-signup',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\MetaEmbeddedSignupController::callback
 * @see app/Http/Controllers/MetaEmbeddedSignupController.php:21
 * @route '/whatsapp/meta/embedded-signup'
 */
callback.url = (options?: RouteQueryOptions) => {
    return callback.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MetaEmbeddedSignupController::callback
 * @see app/Http/Controllers/MetaEmbeddedSignupController.php:21
 * @route '/whatsapp/meta/embedded-signup'
 */
callback.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: callback.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\MetaEmbeddedSignupController::callback
 * @see app/Http/Controllers/MetaEmbeddedSignupController.php:21
 * @route '/whatsapp/meta/embedded-signup'
 */
    const callbackForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: callback.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\MetaEmbeddedSignupController::callback
 * @see app/Http/Controllers/MetaEmbeddedSignupController.php:21
 * @route '/whatsapp/meta/embedded-signup'
 */
        callbackForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: callback.url(options),
            method: 'post',
        })
    
    callback.form = callbackForm
const MetaEmbeddedSignupController = { callback }

export default MetaEmbeddedSignupController