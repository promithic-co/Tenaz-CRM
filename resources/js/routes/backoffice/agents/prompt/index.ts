import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentPromptController::edit
 * @see app/Http/Controllers/Backoffice/BackofficeAgentPromptController.php:40
 * @route '/backoffice/agentes/{agent}/prompt'
 */
export const edit = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/backoffice/agentes/{agent}/prompt',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentPromptController::edit
 * @see app/Http/Controllers/Backoffice/BackofficeAgentPromptController.php:40
 * @route '/backoffice/agentes/{agent}/prompt'
 */
edit.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return edit.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentPromptController::edit
 * @see app/Http/Controllers/Backoffice/BackofficeAgentPromptController.php:40
 * @route '/backoffice/agentes/{agent}/prompt'
 */
edit.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentPromptController::edit
 * @see app/Http/Controllers/Backoffice/BackofficeAgentPromptController.php:40
 * @route '/backoffice/agentes/{agent}/prompt'
 */
edit.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentPromptController::edit
 * @see app/Http/Controllers/Backoffice/BackofficeAgentPromptController.php:40
 * @route '/backoffice/agentes/{agent}/prompt'
 */
    const editForm = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: edit.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentPromptController::edit
 * @see app/Http/Controllers/Backoffice/BackofficeAgentPromptController.php:40
 * @route '/backoffice/agentes/{agent}/prompt'
 */
        editForm.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentPromptController::edit
 * @see app/Http/Controllers/Backoffice/BackofficeAgentPromptController.php:40
 * @route '/backoffice/agentes/{agent}/prompt'
 */
        editForm.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    edit.form = editForm
/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentPromptController::update
 * @see app/Http/Controllers/Backoffice/BackofficeAgentPromptController.php:66
 * @route '/backoffice/agentes/{agent}/prompt'
 */
export const update = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/backoffice/agentes/{agent}/prompt',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentPromptController::update
 * @see app/Http/Controllers/Backoffice/BackofficeAgentPromptController.php:66
 * @route '/backoffice/agentes/{agent}/prompt'
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
* @see \App\Http\Controllers\Backoffice\BackofficeAgentPromptController::update
 * @see app/Http/Controllers/Backoffice/BackofficeAgentPromptController.php:66
 * @route '/backoffice/agentes/{agent}/prompt'
 */
update.put = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})
/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentPromptController::update
 * @see app/Http/Controllers/Backoffice/BackofficeAgentPromptController.php:66
 * @route '/backoffice/agentes/{agent}/prompt'
 */
update.patch = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentPromptController::update
 * @see app/Http/Controllers/Backoffice/BackofficeAgentPromptController.php:66
 * @route '/backoffice/agentes/{agent}/prompt'
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
* @see \App\Http\Controllers\Backoffice\BackofficeAgentPromptController::update
 * @see app/Http/Controllers/Backoffice/BackofficeAgentPromptController.php:66
 * @route '/backoffice/agentes/{agent}/prompt'
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
* @see \App\Http\Controllers\Backoffice\BackofficeAgentPromptController::update
 * @see app/Http/Controllers/Backoffice/BackofficeAgentPromptController.php:66
 * @route '/backoffice/agentes/{agent}/prompt'
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
/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentPromptController::destroy
 * @see app/Http/Controllers/Backoffice/BackofficeAgentPromptController.php:111
 * @route '/backoffice/agentes/{agent}/prompt'
 */
export const destroy = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/backoffice/agentes/{agent}/prompt',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentPromptController::destroy
 * @see app/Http/Controllers/Backoffice/BackofficeAgentPromptController.php:111
 * @route '/backoffice/agentes/{agent}/prompt'
 */
destroy.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentPromptController::destroy
 * @see app/Http/Controllers/Backoffice/BackofficeAgentPromptController.php:111
 * @route '/backoffice/agentes/{agent}/prompt'
 */
destroy.delete = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentPromptController::destroy
 * @see app/Http/Controllers/Backoffice/BackofficeAgentPromptController.php:111
 * @route '/backoffice/agentes/{agent}/prompt'
 */
    const destroyForm = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentPromptController::destroy
 * @see app/Http/Controllers/Backoffice/BackofficeAgentPromptController.php:111
 * @route '/backoffice/agentes/{agent}/prompt'
 */
        destroyForm.delete = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const prompt = {
    edit: Object.assign(edit, edit),
update: Object.assign(update, update),
destroy: Object.assign(destroy, destroy),
}

export default prompt