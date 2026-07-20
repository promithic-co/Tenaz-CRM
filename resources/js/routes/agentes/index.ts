import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
import config8555dc from './config'
import instance from './instance'
import followup2ac79d from './followup'
import regrasOperacionaisE4c376 from './regras-operacionais'
/**
* @see \App\Http\Controllers\AgentsController::index
 * @see app/Http/Controllers/AgentsController.php:25
 * @route '/agentes'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/agentes',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AgentsController::index
 * @see app/Http/Controllers/AgentsController.php:25
 * @route '/agentes'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AgentsController::index
 * @see app/Http/Controllers/AgentsController.php:25
 * @route '/agentes'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AgentsController::index
 * @see app/Http/Controllers/AgentsController.php:25
 * @route '/agentes'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\AgentsController::index
 * @see app/Http/Controllers/AgentsController.php:25
 * @route '/agentes'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\AgentsController::index
 * @see app/Http/Controllers/AgentsController.php:25
 * @route '/agentes'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\AgentsController::index
 * @see app/Http/Controllers/AgentsController.php:25
 * @route '/agentes'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
/**
* @see \App\Http\Controllers\AgentsController::create
 * @see app/Http/Controllers/AgentsController.php:72
 * @route '/agentes/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/agentes/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AgentsController::create
 * @see app/Http/Controllers/AgentsController.php:72
 * @route '/agentes/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AgentsController::create
 * @see app/Http/Controllers/AgentsController.php:72
 * @route '/agentes/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AgentsController::create
 * @see app/Http/Controllers/AgentsController.php:72
 * @route '/agentes/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\AgentsController::create
 * @see app/Http/Controllers/AgentsController.php:72
 * @route '/agentes/create'
 */
    const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\AgentsController::create
 * @see app/Http/Controllers/AgentsController.php:72
 * @route '/agentes/create'
 */
        createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\AgentsController::create
 * @see app/Http/Controllers/AgentsController.php:72
 * @route '/agentes/create'
 */
        createForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    create.form = createForm
/**
* @see \App\Http\Controllers\AgentsController::store
 * @see app/Http/Controllers/AgentsController.php:100
 * @route '/agentes'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/agentes',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AgentsController::store
 * @see app/Http/Controllers/AgentsController.php:100
 * @route '/agentes'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AgentsController::store
 * @see app/Http/Controllers/AgentsController.php:100
 * @route '/agentes'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\AgentsController::store
 * @see app/Http/Controllers/AgentsController.php:100
 * @route '/agentes'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AgentsController::store
 * @see app/Http/Controllers/AgentsController.php:100
 * @route '/agentes'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\AgentConfigController::config
 * @see app/Http/Controllers/AgentConfigController.php:15
 * @route '/agentes/{agent}/config'
 */
export const config = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: config.url(args, options),
    method: 'get',
})

config.definition = {
    methods: ["get","head"],
    url: '/agentes/{agent}/config',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AgentConfigController::config
 * @see app/Http/Controllers/AgentConfigController.php:15
 * @route '/agentes/{agent}/config'
 */
config.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return config.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AgentConfigController::config
 * @see app/Http/Controllers/AgentConfigController.php:15
 * @route '/agentes/{agent}/config'
 */
config.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: config.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AgentConfigController::config
 * @see app/Http/Controllers/AgentConfigController.php:15
 * @route '/agentes/{agent}/config'
 */
config.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: config.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\AgentConfigController::config
 * @see app/Http/Controllers/AgentConfigController.php:15
 * @route '/agentes/{agent}/config'
 */
    const configForm = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: config.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\AgentConfigController::config
 * @see app/Http/Controllers/AgentConfigController.php:15
 * @route '/agentes/{agent}/config'
 */
        configForm.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: config.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\AgentConfigController::config
 * @see app/Http/Controllers/AgentConfigController.php:15
 * @route '/agentes/{agent}/config'
 */
        configForm.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: config.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    config.form = configForm
/**
* @see \App\Http\Controllers\AgentsController::update
 * @see app/Http/Controllers/AgentsController.php:116
 * @route '/agentes/{agent}'
 */
export const update = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/agentes/{agent}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\AgentsController::update
 * @see app/Http/Controllers/AgentsController.php:116
 * @route '/agentes/{agent}'
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
* @see \App\Http\Controllers\AgentsController::update
 * @see app/Http/Controllers/AgentsController.php:116
 * @route '/agentes/{agent}'
 */
update.patch = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\AgentsController::update
 * @see app/Http/Controllers/AgentsController.php:116
 * @route '/agentes/{agent}'
 */
    const updateForm = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AgentsController::update
 * @see app/Http/Controllers/AgentsController.php:116
 * @route '/agentes/{agent}'
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
* @see \App\Http\Controllers\AgentsController::destroy
 * @see app/Http/Controllers/AgentsController.php:126
 * @route '/agentes/{agent}'
 */
export const destroy = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/agentes/{agent}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\AgentsController::destroy
 * @see app/Http/Controllers/AgentsController.php:126
 * @route '/agentes/{agent}'
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
* @see \App\Http\Controllers\AgentsController::destroy
 * @see app/Http/Controllers/AgentsController.php:126
 * @route '/agentes/{agent}'
 */
destroy.delete = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\AgentsController::destroy
 * @see app/Http/Controllers/AgentsController.php:126
 * @route '/agentes/{agent}'
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
* @see \App\Http\Controllers\AgentsController::destroy
 * @see app/Http/Controllers/AgentsController.php:126
 * @route '/agentes/{agent}'
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
/**
* @see \App\Http\Controllers\AgentsController::restore
 * @see app/Http/Controllers/AgentsController.php:148
 * @route '/agentes/{agent_id}/restore'
 */
export const restore = (args: { agent_id: string | number } | [agent_id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: restore.url(args, options),
    method: 'patch',
})

restore.definition = {
    methods: ["patch"],
    url: '/agentes/{agent_id}/restore',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\AgentsController::restore
 * @see app/Http/Controllers/AgentsController.php:148
 * @route '/agentes/{agent_id}/restore'
 */
restore.url = (args: { agent_id: string | number } | [agent_id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { agent_id: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    agent_id: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        agent_id: args.agent_id,
                }

    return restore.definition.url
            .replace('{agent_id}', parsedArgs.agent_id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AgentsController::restore
 * @see app/Http/Controllers/AgentsController.php:148
 * @route '/agentes/{agent_id}/restore'
 */
restore.patch = (args: { agent_id: string | number } | [agent_id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: restore.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\AgentsController::restore
 * @see app/Http/Controllers/AgentsController.php:148
 * @route '/agentes/{agent_id}/restore'
 */
    const restoreForm = (args: { agent_id: string | number } | [agent_id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: restore.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AgentsController::restore
 * @see app/Http/Controllers/AgentsController.php:148
 * @route '/agentes/{agent_id}/restore'
 */
        restoreForm.patch = (args: { agent_id: string | number } | [agent_id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: restore.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    restore.form = restoreForm
/**
* @see \App\Http\Controllers\AgentsController::toggleActive
 * @see app/Http/Controllers/AgentsController.php:160
 * @route '/agentes/{agent}/toggle-active'
 */
export const toggleActive = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: toggleActive.url(args, options),
    method: 'patch',
})

toggleActive.definition = {
    methods: ["patch"],
    url: '/agentes/{agent}/toggle-active',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\AgentsController::toggleActive
 * @see app/Http/Controllers/AgentsController.php:160
 * @route '/agentes/{agent}/toggle-active'
 */
toggleActive.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return toggleActive.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AgentsController::toggleActive
 * @see app/Http/Controllers/AgentsController.php:160
 * @route '/agentes/{agent}/toggle-active'
 */
toggleActive.patch = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: toggleActive.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\AgentsController::toggleActive
 * @see app/Http/Controllers/AgentsController.php:160
 * @route '/agentes/{agent}/toggle-active'
 */
    const toggleActiveForm = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: toggleActive.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AgentsController::toggleActive
 * @see app/Http/Controllers/AgentsController.php:160
 * @route '/agentes/{agent}/toggle-active'
 */
        toggleActiveForm.patch = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: toggleActive.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    toggleActive.form = toggleActiveForm
/**
* @see \App\Http\Controllers\AgentsController::assign
 * @see app/Http/Controllers/AgentsController.php:213
 * @route '/agentes/{agent}/assign'
 */
export const assign = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: assign.url(args, options),
    method: 'patch',
})

assign.definition = {
    methods: ["patch"],
    url: '/agentes/{agent}/assign',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\AgentsController::assign
 * @see app/Http/Controllers/AgentsController.php:213
 * @route '/agentes/{agent}/assign'
 */
assign.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return assign.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AgentsController::assign
 * @see app/Http/Controllers/AgentsController.php:213
 * @route '/agentes/{agent}/assign'
 */
assign.patch = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: assign.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\AgentsController::assign
 * @see app/Http/Controllers/AgentsController.php:213
 * @route '/agentes/{agent}/assign'
 */
    const assignForm = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: assign.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AgentsController::assign
 * @see app/Http/Controllers/AgentsController.php:213
 * @route '/agentes/{agent}/assign'
 */
        assignForm.patch = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: assign.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    assign.form = assignForm
/**
* @see \App\Http\Controllers\AgentsController::snapshot
 * @see app/Http/Controllers/AgentsController.php:196
 * @route '/agentes/{agent}/snapshot'
 */
export const snapshot = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: snapshot.url(args, options),
    method: 'post',
})

snapshot.definition = {
    methods: ["post"],
    url: '/agentes/{agent}/snapshot',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AgentsController::snapshot
 * @see app/Http/Controllers/AgentsController.php:196
 * @route '/agentes/{agent}/snapshot'
 */
snapshot.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return snapshot.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AgentsController::snapshot
 * @see app/Http/Controllers/AgentsController.php:196
 * @route '/agentes/{agent}/snapshot'
 */
snapshot.post = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: snapshot.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\AgentsController::snapshot
 * @see app/Http/Controllers/AgentsController.php:196
 * @route '/agentes/{agent}/snapshot'
 */
    const snapshotForm = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: snapshot.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AgentsController::snapshot
 * @see app/Http/Controllers/AgentsController.php:196
 * @route '/agentes/{agent}/snapshot'
 */
        snapshotForm.post = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: snapshot.url(args, options),
            method: 'post',
        })
    
    snapshot.form = snapshotForm
/**
* @see \App\Http\Controllers\AgentFollowUpController::followup
 * @see app/Http/Controllers/AgentFollowUpController.php:18
 * @route '/agentes/{agent}/follow-up'
 */
export const followup = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: followup.url(args, options),
    method: 'get',
})

followup.definition = {
    methods: ["get","head"],
    url: '/agentes/{agent}/follow-up',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AgentFollowUpController::followup
 * @see app/Http/Controllers/AgentFollowUpController.php:18
 * @route '/agentes/{agent}/follow-up'
 */
followup.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return followup.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AgentFollowUpController::followup
 * @see app/Http/Controllers/AgentFollowUpController.php:18
 * @route '/agentes/{agent}/follow-up'
 */
followup.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: followup.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AgentFollowUpController::followup
 * @see app/Http/Controllers/AgentFollowUpController.php:18
 * @route '/agentes/{agent}/follow-up'
 */
followup.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: followup.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\AgentFollowUpController::followup
 * @see app/Http/Controllers/AgentFollowUpController.php:18
 * @route '/agentes/{agent}/follow-up'
 */
    const followupForm = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: followup.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\AgentFollowUpController::followup
 * @see app/Http/Controllers/AgentFollowUpController.php:18
 * @route '/agentes/{agent}/follow-up'
 */
        followupForm.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: followup.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\AgentFollowUpController::followup
 * @see app/Http/Controllers/AgentFollowUpController.php:18
 * @route '/agentes/{agent}/follow-up'
 */
        followupForm.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: followup.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    followup.form = followupForm
/**
* @see \App\Http\Controllers\RegrasOperacionaisController::regrasOperacionais
 * @see app/Http/Controllers/RegrasOperacionaisController.php:15
 * @route '/agentes/{agent}/regras-operacionais'
 */
export const regrasOperacionais = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: regrasOperacionais.url(args, options),
    method: 'get',
})

regrasOperacionais.definition = {
    methods: ["get","head"],
    url: '/agentes/{agent}/regras-operacionais',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\RegrasOperacionaisController::regrasOperacionais
 * @see app/Http/Controllers/RegrasOperacionaisController.php:15
 * @route '/agentes/{agent}/regras-operacionais'
 */
regrasOperacionais.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return regrasOperacionais.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RegrasOperacionaisController::regrasOperacionais
 * @see app/Http/Controllers/RegrasOperacionaisController.php:15
 * @route '/agentes/{agent}/regras-operacionais'
 */
regrasOperacionais.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: regrasOperacionais.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\RegrasOperacionaisController::regrasOperacionais
 * @see app/Http/Controllers/RegrasOperacionaisController.php:15
 * @route '/agentes/{agent}/regras-operacionais'
 */
regrasOperacionais.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: regrasOperacionais.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\RegrasOperacionaisController::regrasOperacionais
 * @see app/Http/Controllers/RegrasOperacionaisController.php:15
 * @route '/agentes/{agent}/regras-operacionais'
 */
    const regrasOperacionaisForm = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: regrasOperacionais.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\RegrasOperacionaisController::regrasOperacionais
 * @see app/Http/Controllers/RegrasOperacionaisController.php:15
 * @route '/agentes/{agent}/regras-operacionais'
 */
        regrasOperacionaisForm.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: regrasOperacionais.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\RegrasOperacionaisController::regrasOperacionais
 * @see app/Http/Controllers/RegrasOperacionaisController.php:15
 * @route '/agentes/{agent}/regras-operacionais'
 */
        regrasOperacionaisForm.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: regrasOperacionais.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    regrasOperacionais.form = regrasOperacionaisForm
const agentes = {
    index: Object.assign(index, index),
create: Object.assign(create, create),
store: Object.assign(store, store),
config: Object.assign(config, config8555dc),
update: Object.assign(update, update),
destroy: Object.assign(destroy, destroy),
restore: Object.assign(restore, restore),
toggleActive: Object.assign(toggleActive, toggleActive),
instance: Object.assign(instance, instance),
assign: Object.assign(assign, assign),
snapshot: Object.assign(snapshot, snapshot),
followup: Object.assign(followup, followup2ac79d),
regrasOperacionais: Object.assign(regrasOperacionais, regrasOperacionaisE4c376),
}

export default agentes