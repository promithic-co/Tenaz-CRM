import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
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
* @see \App\Http\Controllers\OnboardingController::agent
 * @see app/Http/Controllers/OnboardingController.php:115
 * @route '/onboarding/agent'
 */
export const agent = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: agent.url(options),
    method: 'post',
})

agent.definition = {
    methods: ["post"],
    url: '/onboarding/agent',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OnboardingController::agent
 * @see app/Http/Controllers/OnboardingController.php:115
 * @route '/onboarding/agent'
 */
agent.url = (options?: RouteQueryOptions) => {
    return agent.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OnboardingController::agent
 * @see app/Http/Controllers/OnboardingController.php:115
 * @route '/onboarding/agent'
 */
agent.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: agent.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OnboardingController::instance
 * @see app/Http/Controllers/OnboardingController.php:174
 * @route '/onboarding/instance'
 */
export const instance = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: instance.url(options),
    method: 'post',
})

instance.definition = {
    methods: ["post"],
    url: '/onboarding/instance',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OnboardingController::instance
 * @see app/Http/Controllers/OnboardingController.php:174
 * @route '/onboarding/instance'
 */
instance.url = (options?: RouteQueryOptions) => {
    return instance.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OnboardingController::instance
 * @see app/Http/Controllers/OnboardingController.php:174
 * @route '/onboarding/instance'
 */
instance.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: instance.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\OnboardingController::persona
 * @see app/Http/Controllers/OnboardingController.php:256
 * @route '/onboarding/persona'
 */
export const persona = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: persona.url(options),
    method: 'post',
})

persona.definition = {
    methods: ["post"],
    url: '/onboarding/persona',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\OnboardingController::persona
 * @see app/Http/Controllers/OnboardingController.php:256
 * @route '/onboarding/persona'
 */
persona.url = (options?: RouteQueryOptions) => {
    return persona.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\OnboardingController::persona
 * @see app/Http/Controllers/OnboardingController.php:256
 * @route '/onboarding/persona'
 */
persona.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: persona.url(options),
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
const onboarding = {
    show: Object.assign(show, show),
agent: Object.assign(agent, agent),
instance: Object.assign(instance, instance),
persona: Object.assign(persona, persona),
complete: Object.assign(complete, complete),
}

export default onboarding