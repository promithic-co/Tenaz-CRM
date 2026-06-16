import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\MetaEmbeddedSignupController::embeddedSignup
 * @see app/Http/Controllers/MetaEmbeddedSignupController.php:21
 * @route '/whatsapp/meta/embedded-signup'
 */
export const embeddedSignup = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: embeddedSignup.url(options),
    method: 'post',
})

embeddedSignup.definition = {
    methods: ["post"],
    url: '/whatsapp/meta/embedded-signup',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\MetaEmbeddedSignupController::embeddedSignup
 * @see app/Http/Controllers/MetaEmbeddedSignupController.php:21
 * @route '/whatsapp/meta/embedded-signup'
 */
embeddedSignup.url = (options?: RouteQueryOptions) => {
    return embeddedSignup.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MetaEmbeddedSignupController::embeddedSignup
 * @see app/Http/Controllers/MetaEmbeddedSignupController.php:21
 * @route '/whatsapp/meta/embedded-signup'
 */
embeddedSignup.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: embeddedSignup.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\MetaEmbeddedSignupController::embeddedSignup
 * @see app/Http/Controllers/MetaEmbeddedSignupController.php:21
 * @route '/whatsapp/meta/embedded-signup'
 */
    const embeddedSignupForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: embeddedSignup.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\MetaEmbeddedSignupController::embeddedSignup
 * @see app/Http/Controllers/MetaEmbeddedSignupController.php:21
 * @route '/whatsapp/meta/embedded-signup'
 */
        embeddedSignupForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: embeddedSignup.url(options),
            method: 'post',
        })
    
    embeddedSignup.form = embeddedSignupForm
const meta = {
    embeddedSignup: Object.assign(embeddedSignup, embeddedSignup),
}

export default meta