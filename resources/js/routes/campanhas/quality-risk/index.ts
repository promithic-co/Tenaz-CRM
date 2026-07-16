import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\CampaignController::keepPaused
 * @see app/Http/Controllers/CampaignController.php:148
 * @route '/campanhas/{campanha}/quality-risk/keep-paused'
 */
export const keepPaused = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: keepPaused.url(args, options),
    method: 'post',
})

keepPaused.definition = {
    methods: ["post"],
    url: '/campanhas/{campanha}/quality-risk/keep-paused',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CampaignController::keepPaused
 * @see app/Http/Controllers/CampaignController.php:148
 * @route '/campanhas/{campanha}/quality-risk/keep-paused'
 */
keepPaused.url = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { campanha: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { campanha: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    campanha: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        campanha: typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
                }

    return keepPaused.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CampaignController::keepPaused
 * @see app/Http/Controllers/CampaignController.php:148
 * @route '/campanhas/{campanha}/quality-risk/keep-paused'
 */
keepPaused.post = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: keepPaused.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CampaignController::keepPaused
 * @see app/Http/Controllers/CampaignController.php:148
 * @route '/campanhas/{campanha}/quality-risk/keep-paused'
 */
    const keepPausedForm = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: keepPaused.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CampaignController::keepPaused
 * @see app/Http/Controllers/CampaignController.php:148
 * @route '/campanhas/{campanha}/quality-risk/keep-paused'
 */
        keepPausedForm.post = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: keepPaused.url(args, options),
            method: 'post',
        })
    
    keepPaused.form = keepPausedForm
/**
* @see \App\Http\Controllers\CampaignController::continueMethod
 * @see app/Http/Controllers/CampaignController.php:161
 * @route '/campanhas/{campanha}/quality-risk/continue'
 */
export const continueMethod = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: continueMethod.url(args, options),
    method: 'post',
})

continueMethod.definition = {
    methods: ["post"],
    url: '/campanhas/{campanha}/quality-risk/continue',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CampaignController::continueMethod
 * @see app/Http/Controllers/CampaignController.php:161
 * @route '/campanhas/{campanha}/quality-risk/continue'
 */
continueMethod.url = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { campanha: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { campanha: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    campanha: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        campanha: typeof args.campanha === 'object'
                ? args.campanha.id
                : args.campanha,
                }

    return continueMethod.definition.url
            .replace('{campanha}', parsedArgs.campanha.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CampaignController::continueMethod
 * @see app/Http/Controllers/CampaignController.php:161
 * @route '/campanhas/{campanha}/quality-risk/continue'
 */
continueMethod.post = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: continueMethod.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CampaignController::continueMethod
 * @see app/Http/Controllers/CampaignController.php:161
 * @route '/campanhas/{campanha}/quality-risk/continue'
 */
    const continueMethodForm = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: continueMethod.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CampaignController::continueMethod
 * @see app/Http/Controllers/CampaignController.php:161
 * @route '/campanhas/{campanha}/quality-risk/continue'
 */
        continueMethodForm.post = (args: { campanha: number | { id: number } } | [campanha: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: continueMethod.url(args, options),
            method: 'post',
        })
    
    continueMethod.form = continueMethodForm
const qualityRisk = {
    keepPaused: Object.assign(keepPaused, keepPaused),
continue: Object.assign(continueMethod, continueMethod),
}

export default qualityRisk