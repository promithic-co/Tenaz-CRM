import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
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
* @see \App\Http\Controllers\AgentsController::updateInstance
 * @see app/Http/Controllers/AgentsController.php:176
 * @route '/agentes/{agent}/instance'
 */
export const updateInstance = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateInstance.url(args, options),
    method: 'patch',
})

updateInstance.definition = {
    methods: ["patch"],
    url: '/agentes/{agent}/instance',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\AgentsController::updateInstance
 * @see app/Http/Controllers/AgentsController.php:176
 * @route '/agentes/{agent}/instance'
 */
updateInstance.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return updateInstance.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AgentsController::updateInstance
 * @see app/Http/Controllers/AgentsController.php:176
 * @route '/agentes/{agent}/instance'
 */
updateInstance.patch = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateInstance.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\AgentsController::updateInstance
 * @see app/Http/Controllers/AgentsController.php:176
 * @route '/agentes/{agent}/instance'
 */
    const updateInstanceForm = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: updateInstance.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AgentsController::updateInstance
 * @see app/Http/Controllers/AgentsController.php:176
 * @route '/agentes/{agent}/instance'
 */
        updateInstanceForm.patch = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updateInstance.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    updateInstance.form = updateInstanceForm
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
const AgentsController = { index, create, store, update, destroy, restore, toggleActive, updateInstance, assign, snapshot }

export default AgentsController