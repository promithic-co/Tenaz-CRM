import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\OnboardingController::show
 * @see app/Http/Controllers/OnboardingController.php:36
 * @route '/onboarding'
 */
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/onboarding',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OnboardingController::show
 * @see app/Http/Controllers/OnboardingController.php:36
 * @route '/onboarding'
 */
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OnboardingController::show
 * @see app/Http/Controllers/OnboardingController.php:36
 * @route '/onboarding'
 */
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\OnboardingController::show
 * @see app/Http/Controllers/OnboardingController.php:36
 * @route '/onboarding'
 */
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\OnboardingController::storeAgent
 * @see app/Http/Controllers/OnboardingController.php:115
 * @route '/onboarding/agent'
 */
export const storeAgent = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeAgent.url(options),
    method: 'post',
})

storeAgent.definition = {
    methods: ["post"],
    url: '/onboarding/agent',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OnboardingController::storeAgent
 * @see app/Http/Controllers/OnboardingController.php:115
 * @route '/onboarding/agent'
 */
storeAgent.url = (options?: RouteQueryOptions) => {
    return storeAgent.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OnboardingController::storeAgent
 * @see app/Http/Controllers/OnboardingController.php:115
 * @route '/onboarding/agent'
 */
storeAgent.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeAgent.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OnboardingController::storeInstance
 * @see app/Http/Controllers/OnboardingController.php:174
 * @route '/onboarding/instance'
 */
export const storeInstance = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeInstance.url(options),
    method: 'post',
})

storeInstance.definition = {
    methods: ["post"],
    url: '/onboarding/instance',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OnboardingController::storeInstance
 * @see app/Http/Controllers/OnboardingController.php:174
 * @route '/onboarding/instance'
 */
storeInstance.url = (options?: RouteQueryOptions) => {
    return storeInstance.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OnboardingController::storeInstance
 * @see app/Http/Controllers/OnboardingController.php:174
 * @route '/onboarding/instance'
 */
storeInstance.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeInstance.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OnboardingController::storePersona
 * @see app/Http/Controllers/OnboardingController.php:256
 * @route '/onboarding/persona'
 */
export const storePersona = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storePersona.url(options),
    method: 'post',
})

storePersona.definition = {
    methods: ["post"],
    url: '/onboarding/persona',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OnboardingController::storePersona
 * @see app/Http/Controllers/OnboardingController.php:256
 * @route '/onboarding/persona'
 */
storePersona.url = (options?: RouteQueryOptions) => {
    return storePersona.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OnboardingController::storePersona
 * @see app/Http/Controllers/OnboardingController.php:256
 * @route '/onboarding/persona'
 */
storePersona.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storePersona.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OnboardingController::complete
 * @see app/Http/Controllers/OnboardingController.php:308
 * @route '/onboarding/complete/{agent}'
 */
export const complete = (args: { agent: string | number } | [agent: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: complete.url(args, options),
    method: 'get',
})

complete.definition = {
    methods: ["get","head"],
    url: '/onboarding/complete/{agent}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\OnboardingController::complete
 * @see app/Http/Controllers/OnboardingController.php:308
 * @route '/onboarding/complete/{agent}'
 */
complete.url = (args: { agent: string | number } | [agent: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { agent: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    agent: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        agent: args.agent,
                }

    return complete.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\OnboardingController::complete
 * @see app/Http/Controllers/OnboardingController.php:308
 * @route '/onboarding/complete/{agent}'
 */
complete.get = (args: { agent: string | number } | [agent: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: complete.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\OnboardingController::complete
 * @see app/Http/Controllers/OnboardingController.php:308
 * @route '/onboarding/complete/{agent}'
 */
complete.head = (args: { agent: string | number } | [agent: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: complete.url(args, options),
    method: 'head',
})
const OnboardingController = { show, storeAgent, storeInstance, storePersona, complete }

export default OnboardingController