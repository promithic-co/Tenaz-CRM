import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\WhatsAppInstanceController::index
 * @see app/Http/Controllers/WhatsAppInstanceController.php:28
 * @route '/whatsapp'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/whatsapp',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\WhatsAppInstanceController::index
 * @see app/Http/Controllers/WhatsAppInstanceController.php:28
 * @route '/whatsapp'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\WhatsAppInstanceController::index
 * @see app/Http/Controllers/WhatsAppInstanceController.php:28
 * @route '/whatsapp'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\WhatsAppInstanceController::index
 * @see app/Http/Controllers/WhatsAppInstanceController.php:28
 * @route '/whatsapp'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\WhatsAppInstanceController::index
 * @see app/Http/Controllers/WhatsAppInstanceController.php:28
 * @route '/whatsapp'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\WhatsAppInstanceController::index
 * @see app/Http/Controllers/WhatsAppInstanceController.php:28
 * @route '/whatsapp'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\WhatsAppInstanceController::index
 * @see app/Http/Controllers/WhatsAppInstanceController.php:28
 * @route '/whatsapp'
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
* @see \App\Http\Controllers\WhatsAppInstanceController::store
 * @see app/Http/Controllers/WhatsAppInstanceController.php:103
 * @route '/whatsapp'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/whatsapp',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\WhatsAppInstanceController::store
 * @see app/Http/Controllers/WhatsAppInstanceController.php:103
 * @route '/whatsapp'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\WhatsAppInstanceController::store
 * @see app/Http/Controllers/WhatsAppInstanceController.php:103
 * @route '/whatsapp'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\WhatsAppInstanceController::store
 * @see app/Http/Controllers/WhatsAppInstanceController.php:103
 * @route '/whatsapp'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\WhatsAppInstanceController::store
 * @see app/Http/Controllers/WhatsAppInstanceController.php:103
 * @route '/whatsapp'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\WhatsAppInstanceController::destroy
 * @see app/Http/Controllers/WhatsAppInstanceController.php:182
 * @route '/whatsapp/{instance}'
 */
export const destroy = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/whatsapp/{instance}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\WhatsAppInstanceController::destroy
 * @see app/Http/Controllers/WhatsAppInstanceController.php:182
 * @route '/whatsapp/{instance}'
 */
destroy.url = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { instance: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { instance: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    instance: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        instance: typeof args.instance === 'object'
                ? args.instance.id
                : args.instance,
                }

    return destroy.definition.url
            .replace('{instance}', parsedArgs.instance.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\WhatsAppInstanceController::destroy
 * @see app/Http/Controllers/WhatsAppInstanceController.php:182
 * @route '/whatsapp/{instance}'
 */
destroy.delete = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\WhatsAppInstanceController::destroy
 * @see app/Http/Controllers/WhatsAppInstanceController.php:182
 * @route '/whatsapp/{instance}'
 */
    const destroyForm = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\WhatsAppInstanceController::destroy
 * @see app/Http/Controllers/WhatsAppInstanceController.php:182
 * @route '/whatsapp/{instance}'
 */
        destroyForm.delete = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\WhatsAppInstanceController::status
 * @see app/Http/Controllers/WhatsAppInstanceController.php:191
 * @route '/whatsapp/{instance}/status'
 */
export const status = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: status.url(args, options),
    method: 'get',
})

status.definition = {
    methods: ["get","head"],
    url: '/whatsapp/{instance}/status',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\WhatsAppInstanceController::status
 * @see app/Http/Controllers/WhatsAppInstanceController.php:191
 * @route '/whatsapp/{instance}/status'
 */
status.url = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { instance: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { instance: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    instance: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        instance: typeof args.instance === 'object'
                ? args.instance.id
                : args.instance,
                }

    return status.definition.url
            .replace('{instance}', parsedArgs.instance.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\WhatsAppInstanceController::status
 * @see app/Http/Controllers/WhatsAppInstanceController.php:191
 * @route '/whatsapp/{instance}/status'
 */
status.get = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: status.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\WhatsAppInstanceController::status
 * @see app/Http/Controllers/WhatsAppInstanceController.php:191
 * @route '/whatsapp/{instance}/status'
 */
status.head = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: status.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\WhatsAppInstanceController::status
 * @see app/Http/Controllers/WhatsAppInstanceController.php:191
 * @route '/whatsapp/{instance}/status'
 */
    const statusForm = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: status.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\WhatsAppInstanceController::status
 * @see app/Http/Controllers/WhatsAppInstanceController.php:191
 * @route '/whatsapp/{instance}/status'
 */
        statusForm.get = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: status.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\WhatsAppInstanceController::status
 * @see app/Http/Controllers/WhatsAppInstanceController.php:191
 * @route '/whatsapp/{instance}/status'
 */
        statusForm.head = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: status.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    status.form = statusForm
/**
* @see \App\Http\Controllers\WhatsAppInstanceController::connect
 * @see app/Http/Controllers/WhatsAppInstanceController.php:204
 * @route '/whatsapp/{instance}/connect'
 */
export const connect = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: connect.url(args, options),
    method: 'post',
})

connect.definition = {
    methods: ["post"],
    url: '/whatsapp/{instance}/connect',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\WhatsAppInstanceController::connect
 * @see app/Http/Controllers/WhatsAppInstanceController.php:204
 * @route '/whatsapp/{instance}/connect'
 */
connect.url = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { instance: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { instance: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    instance: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        instance: typeof args.instance === 'object'
                ? args.instance.id
                : args.instance,
                }

    return connect.definition.url
            .replace('{instance}', parsedArgs.instance.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\WhatsAppInstanceController::connect
 * @see app/Http/Controllers/WhatsAppInstanceController.php:204
 * @route '/whatsapp/{instance}/connect'
 */
connect.post = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: connect.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\WhatsAppInstanceController::connect
 * @see app/Http/Controllers/WhatsAppInstanceController.php:204
 * @route '/whatsapp/{instance}/connect'
 */
    const connectForm = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: connect.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\WhatsAppInstanceController::connect
 * @see app/Http/Controllers/WhatsAppInstanceController.php:204
 * @route '/whatsapp/{instance}/connect'
 */
        connectForm.post = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: connect.url(args, options),
            method: 'post',
        })
    
    connect.form = connectForm
/**
* @see \App\Http\Controllers\WhatsAppInstanceController::disconnect
 * @see app/Http/Controllers/WhatsAppInstanceController.php:213
 * @route '/whatsapp/{instance}/disconnect'
 */
export const disconnect = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: disconnect.url(args, options),
    method: 'post',
})

disconnect.definition = {
    methods: ["post"],
    url: '/whatsapp/{instance}/disconnect',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\WhatsAppInstanceController::disconnect
 * @see app/Http/Controllers/WhatsAppInstanceController.php:213
 * @route '/whatsapp/{instance}/disconnect'
 */
disconnect.url = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { instance: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { instance: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    instance: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        instance: typeof args.instance === 'object'
                ? args.instance.id
                : args.instance,
                }

    return disconnect.definition.url
            .replace('{instance}', parsedArgs.instance.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\WhatsAppInstanceController::disconnect
 * @see app/Http/Controllers/WhatsAppInstanceController.php:213
 * @route '/whatsapp/{instance}/disconnect'
 */
disconnect.post = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: disconnect.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\WhatsAppInstanceController::disconnect
 * @see app/Http/Controllers/WhatsAppInstanceController.php:213
 * @route '/whatsapp/{instance}/disconnect'
 */
    const disconnectForm = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: disconnect.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\WhatsAppInstanceController::disconnect
 * @see app/Http/Controllers/WhatsAppInstanceController.php:213
 * @route '/whatsapp/{instance}/disconnect'
 */
        disconnectForm.post = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: disconnect.url(args, options),
            method: 'post',
        })
    
    disconnect.form = disconnectForm
/**
* @see \App\Http\Controllers\WhatsAppInstanceController::assign
 * @see app/Http/Controllers/WhatsAppInstanceController.php:226
 * @route '/whatsapp/{instance}/assign'
 */
export const assign = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: assign.url(args, options),
    method: 'patch',
})

assign.definition = {
    methods: ["patch"],
    url: '/whatsapp/{instance}/assign',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\WhatsAppInstanceController::assign
 * @see app/Http/Controllers/WhatsAppInstanceController.php:226
 * @route '/whatsapp/{instance}/assign'
 */
assign.url = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { instance: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { instance: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    instance: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        instance: typeof args.instance === 'object'
                ? args.instance.id
                : args.instance,
                }

    return assign.definition.url
            .replace('{instance}', parsedArgs.instance.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\WhatsAppInstanceController::assign
 * @see app/Http/Controllers/WhatsAppInstanceController.php:226
 * @route '/whatsapp/{instance}/assign'
 */
assign.patch = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: assign.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\WhatsAppInstanceController::assign
 * @see app/Http/Controllers/WhatsAppInstanceController.php:226
 * @route '/whatsapp/{instance}/assign'
 */
    const assignForm = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: assign.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\WhatsAppInstanceController::assign
 * @see app/Http/Controllers/WhatsAppInstanceController.php:226
 * @route '/whatsapp/{instance}/assign'
 */
        assignForm.patch = (args: { instance: number | { id: number } } | [instance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: assign.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    assign.form = assignForm
const WhatsAppInstanceController = { index, store, destroy, status, connect, disconnect, assign }

export default WhatsAppInstanceController