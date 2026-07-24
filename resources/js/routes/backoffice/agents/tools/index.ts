import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentToolController::edit
 * @see app/Http/Controllers/Backoffice/BackofficeAgentToolController.php:27
 * @route '/backoffice/agentes/{agent}/ferramentas'
 */
export const edit = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/backoffice/agentes/{agent}/ferramentas',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentToolController::edit
 * @see app/Http/Controllers/Backoffice/BackofficeAgentToolController.php:27
 * @route '/backoffice/agentes/{agent}/ferramentas'
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
* @see \App\Http\Controllers\Backoffice\BackofficeAgentToolController::edit
 * @see app/Http/Controllers/Backoffice/BackofficeAgentToolController.php:27
 * @route '/backoffice/agentes/{agent}/ferramentas'
 */
edit.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentToolController::edit
 * @see app/Http/Controllers/Backoffice/BackofficeAgentToolController.php:27
 * @route '/backoffice/agentes/{agent}/ferramentas'
 */
edit.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentToolController::edit
 * @see app/Http/Controllers/Backoffice/BackofficeAgentToolController.php:27
 * @route '/backoffice/agentes/{agent}/ferramentas'
 */
    const editForm = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: edit.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentToolController::edit
 * @see app/Http/Controllers/Backoffice/BackofficeAgentToolController.php:27
 * @route '/backoffice/agentes/{agent}/ferramentas'
 */
        editForm.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentToolController::edit
 * @see app/Http/Controllers/Backoffice/BackofficeAgentToolController.php:27
 * @route '/backoffice/agentes/{agent}/ferramentas'
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
* @see \App\Http\Controllers\Backoffice\BackofficeAgentToolController::update
 * @see app/Http/Controllers/Backoffice/BackofficeAgentToolController.php:53
 * @route '/backoffice/agentes/{agent}/ferramentas'
 */
export const update = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/backoffice/agentes/{agent}/ferramentas',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentToolController::update
 * @see app/Http/Controllers/Backoffice/BackofficeAgentToolController.php:53
 * @route '/backoffice/agentes/{agent}/ferramentas'
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
* @see \App\Http\Controllers\Backoffice\BackofficeAgentToolController::update
 * @see app/Http/Controllers/Backoffice/BackofficeAgentToolController.php:53
 * @route '/backoffice/agentes/{agent}/ferramentas'
 */
update.put = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})
/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentToolController::update
 * @see app/Http/Controllers/Backoffice/BackofficeAgentToolController.php:53
 * @route '/backoffice/agentes/{agent}/ferramentas'
 */
update.patch = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentToolController::update
 * @see app/Http/Controllers/Backoffice/BackofficeAgentToolController.php:53
 * @route '/backoffice/agentes/{agent}/ferramentas'
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
* @see \App\Http\Controllers\Backoffice\BackofficeAgentToolController::update
 * @see app/Http/Controllers/Backoffice/BackofficeAgentToolController.php:53
 * @route '/backoffice/agentes/{agent}/ferramentas'
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
* @see \App\Http\Controllers\Backoffice\BackofficeAgentToolController::update
 * @see app/Http/Controllers/Backoffice/BackofficeAgentToolController.php:53
 * @route '/backoffice/agentes/{agent}/ferramentas'
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
const tools = {
    edit: Object.assign(edit, edit),
update: Object.assign(update, update),
}

export default tools