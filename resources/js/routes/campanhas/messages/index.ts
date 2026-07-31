import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\CampaignController::retry
 * @see app/Http/Controllers/CampaignController.php:188
 * @route '/campanhas/{campanha}/messages/{message}/retry'
 */
export const retry = (args: { campanha: number | { id: number }, message: number | { id: number } } | [campanha: number | { id: number }, message: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: retry.url(args, options),
    method: 'post',
})

retry.definition = {
    methods: ["post"],
    url: '/campanhas/{campanha}/messages/{message}/retry',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CampaignController::retry
 * @see app/Http/Controllers/CampaignController.php:188
 * @route '/campanhas/{campanha}/messages/{message}/retry'
 */
retry.url = (args: { campanha: number | { id: number }, message: number | { id: number } } | [campanha: number | { id: number }, message: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    campanha: args[0],
                    message: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        campanha: typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
                                message: typeof args.message === 'object'
                ? args.message.id
                : args.message,
                }

    return retry.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace('{message}', parsedArgs.message.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CampaignController::retry
 * @see app/Http/Controllers/CampaignController.php:188
 * @route '/campanhas/{campanha}/messages/{message}/retry'
 */
retry.post = (args: { campanha: number | { id: number }, message: number | { id: number } } | [campanha: number | { id: number }, message: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: retry.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CampaignController::retry
 * @see app/Http/Controllers/CampaignController.php:188
 * @route '/campanhas/{campanha}/messages/{message}/retry'
 */
    const retryForm = (args: { campanha: number | { id: number }, message: number | { id: number } } | [campanha: number | { id: number }, message: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: retry.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CampaignController::retry
 * @see app/Http/Controllers/CampaignController.php:188
 * @route '/campanhas/{campanha}/messages/{message}/retry'
 */
        retryForm.post = (args: { campanha: number | { id: number }, message: number | { id: number } } | [campanha: number | { id: number }, message: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: retry.url(args, options),
            method: 'post',
        })
    
    retry.form = retryForm
const messages = {
    retry: Object.assign(retry, retry),
}

export default messages