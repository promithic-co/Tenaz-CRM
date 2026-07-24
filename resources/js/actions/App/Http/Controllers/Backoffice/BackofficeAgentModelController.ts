import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentModelController::update
 * @see app/Http/Controllers/Backoffice/BackofficeAgentModelController.php:21
 * @route '/backoffice/agentes/{agent}/modelo'
 */
export const update = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/backoffice/agentes/{agent}/modelo',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentModelController::update
 * @see app/Http/Controllers/Backoffice/BackofficeAgentModelController.php:21
 * @route '/backoffice/agentes/{agent}/modelo'
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
* @see \App\Http\Controllers\Backoffice\BackofficeAgentModelController::update
 * @see app/Http/Controllers/Backoffice/BackofficeAgentModelController.php:21
 * @route '/backoffice/agentes/{agent}/modelo'
 */
update.put = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})
/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentModelController::update
 * @see app/Http/Controllers/Backoffice/BackofficeAgentModelController.php:21
 * @route '/backoffice/agentes/{agent}/modelo'
 */
update.patch = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentModelController::update
 * @see app/Http/Controllers/Backoffice/BackofficeAgentModelController.php:21
 * @route '/backoffice/agentes/{agent}/modelo'
 */
    const updateForm = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentModelController::update
 * @see app/Http/Controllers/Backoffice/BackofficeAgentModelController.php:21
 * @route '/backoffice/agentes/{agent}/modelo'
 */
        updateForm.put = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentModelController::update
 * @see app/Http/Controllers/Backoffice/BackofficeAgentModelController.php:21
 * @route '/backoffice/agentes/{agent}/modelo'
 */
        updateForm.patch = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
const BackofficeAgentModelController = { update }

export default BackofficeAgentModelController