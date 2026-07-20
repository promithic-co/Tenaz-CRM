import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\AgentFollowUpController::update
 * @see app/Http/Controllers/AgentFollowUpController.php:47
 * @route '/agentes/{agent}/follow-up'
 */
export const update = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(args, options),
    method: 'post',
})

update.definition = {
    methods: ["post"],
    url: '/agentes/{agent}/follow-up',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AgentFollowUpController::update
 * @see app/Http/Controllers/AgentFollowUpController.php:47
 * @route '/agentes/{agent}/follow-up'
 */
update.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { agent: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { agent: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    agent: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        agent: typeof args.agent === 'object'
                ? args.agent.id
                : args.agent,
                }

    return update.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AgentFollowUpController::update
 * @see app/Http/Controllers/AgentFollowUpController.php:47
 * @route '/agentes/{agent}/follow-up'
 */
update.post = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\AgentFollowUpController::update
 * @see app/Http/Controllers/AgentFollowUpController.php:47
 * @route '/agentes/{agent}/follow-up'
 */
    const updateForm = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AgentFollowUpController::update
 * @see app/Http/Controllers/AgentFollowUpController.php:47
 * @route '/agentes/{agent}/follow-up'
 */
        updateForm.post = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, options),
            method: 'post',
        })
    
    update.form = updateForm
const followup = {
    update: Object.assign(update, update),
}

export default followup