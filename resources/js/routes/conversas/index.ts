import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
import followup from './followup'
/**
* @see \App\Http\Controllers\ConversasController::index
 * @see app/Http/Controllers/ConversasController.php:39
 * @route '/conversas'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/conversas',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ConversasController::index
 * @see app/Http/Controllers/ConversasController.php:39
 * @route '/conversas'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConversasController::index
 * @see app/Http/Controllers/ConversasController.php:39
 * @route '/conversas'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ConversasController::index
 * @see app/Http/Controllers/ConversasController.php:39
 * @route '/conversas'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\ConversasController::index
 * @see app/Http/Controllers/ConversasController.php:39
 * @route '/conversas'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\ConversasController::index
 * @see app/Http/Controllers/ConversasController.php:39
 * @route '/conversas'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\ConversasController::index
 * @see app/Http/Controllers/ConversasController.php:39
 * @route '/conversas'
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
* @see \App\Http\Controllers\LeadManagementController::store
 * @see app/Http/Controllers/LeadManagementController.php:31
 * @route '/conversas'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/conversas',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LeadManagementController::store
 * @see app/Http/Controllers/LeadManagementController.php:31
 * @route '/conversas'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadManagementController::store
 * @see app/Http/Controllers/LeadManagementController.php:31
 * @route '/conversas'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\LeadManagementController::store
 * @see app/Http/Controllers/LeadManagementController.php:31
 * @route '/conversas'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\LeadManagementController::store
 * @see app/Http/Controllers/LeadManagementController.php:31
 * @route '/conversas'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\LeadManagementController::bulkAction
 * @see app/Http/Controllers/LeadManagementController.php:94
 * @route '/conversas/bulk-action'
 */
export const bulkAction = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkAction.url(options),
    method: 'post',
})

bulkAction.definition = {
    methods: ["post"],
    url: '/conversas/bulk-action',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LeadManagementController::bulkAction
 * @see app/Http/Controllers/LeadManagementController.php:94
 * @route '/conversas/bulk-action'
 */
bulkAction.url = (options?: RouteQueryOptions) => {
    return bulkAction.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadManagementController::bulkAction
 * @see app/Http/Controllers/LeadManagementController.php:94
 * @route '/conversas/bulk-action'
 */
bulkAction.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkAction.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\LeadManagementController::bulkAction
 * @see app/Http/Controllers/LeadManagementController.php:94
 * @route '/conversas/bulk-action'
 */
    const bulkActionForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: bulkAction.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\LeadManagementController::bulkAction
 * @see app/Http/Controllers/LeadManagementController.php:94
 * @route '/conversas/bulk-action'
 */
        bulkActionForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: bulkAction.url(options),
            method: 'post',
        })
    
    bulkAction.form = bulkActionForm
/**
* @see \App\Http\Controllers\ConversasController::transfer
 * @see app/Http/Controllers/ConversasController.php:332
 * @route '/conversas/transfer'
 */
export const transfer = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: transfer.url(options),
    method: 'post',
})

transfer.definition = {
    methods: ["post"],
    url: '/conversas/transfer',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ConversasController::transfer
 * @see app/Http/Controllers/ConversasController.php:332
 * @route '/conversas/transfer'
 */
transfer.url = (options?: RouteQueryOptions) => {
    return transfer.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConversasController::transfer
 * @see app/Http/Controllers/ConversasController.php:332
 * @route '/conversas/transfer'
 */
transfer.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: transfer.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ConversasController::transfer
 * @see app/Http/Controllers/ConversasController.php:332
 * @route '/conversas/transfer'
 */
    const transferForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: transfer.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ConversasController::transfer
 * @see app/Http/Controllers/ConversasController.php:332
 * @route '/conversas/transfer'
 */
        transferForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: transfer.url(options),
            method: 'post',
        })
    
    transfer.form = transferForm
/**
* @see \App\Http\Controllers\ConversasController::preview
 * @see app/Http/Controllers/ConversasController.php:223
 * @route '/conversas/{lead}/preview'
 */
export const preview = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(args, options),
    method: 'get',
})

preview.definition = {
    methods: ["get","head"],
    url: '/conversas/{lead}/preview',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ConversasController::preview
 * @see app/Http/Controllers/ConversasController.php:223
 * @route '/conversas/{lead}/preview'
 */
preview.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { lead: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { lead: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    lead: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        lead: typeof args.lead === 'object'
                ? args.lead.id
                : args.lead,
                }

    return preview.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConversasController::preview
 * @see app/Http/Controllers/ConversasController.php:223
 * @route '/conversas/{lead}/preview'
 */
preview.get = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ConversasController::preview
 * @see app/Http/Controllers/ConversasController.php:223
 * @route '/conversas/{lead}/preview'
 */
preview.head = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: preview.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\ConversasController::preview
 * @see app/Http/Controllers/ConversasController.php:223
 * @route '/conversas/{lead}/preview'
 */
    const previewForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: preview.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\ConversasController::preview
 * @see app/Http/Controllers/ConversasController.php:223
 * @route '/conversas/{lead}/preview'
 */
        previewForm.get = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: preview.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\ConversasController::preview
 * @see app/Http/Controllers/ConversasController.php:223
 * @route '/conversas/{lead}/preview'
 */
        previewForm.head = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: preview.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    preview.form = previewForm
/**
* @see \App\Http\Controllers\ConversasController::show
 * @see app/Http/Controllers/ConversasController.php:44
 * @route '/conversas/{lead}'
 */
export const show = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/conversas/{lead}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ConversasController::show
 * @see app/Http/Controllers/ConversasController.php:44
 * @route '/conversas/{lead}'
 */
show.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { lead: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { lead: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    lead: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        lead: typeof args.lead === 'object'
                ? args.lead.id
                : args.lead,
                }

    return show.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConversasController::show
 * @see app/Http/Controllers/ConversasController.php:44
 * @route '/conversas/{lead}'
 */
show.get = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ConversasController::show
 * @see app/Http/Controllers/ConversasController.php:44
 * @route '/conversas/{lead}'
 */
show.head = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\ConversasController::show
 * @see app/Http/Controllers/ConversasController.php:44
 * @route '/conversas/{lead}'
 */
    const showForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\ConversasController::show
 * @see app/Http/Controllers/ConversasController.php:44
 * @route '/conversas/{lead}'
 */
        showForm.get = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\ConversasController::show
 * @see app/Http/Controllers/ConversasController.php:44
 * @route '/conversas/{lead}'
 */
        showForm.head = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
/**
* @see \App\Http\Controllers\LeadManagementController::destroy
 * @see app/Http/Controllers/LeadManagementController.php:56
 * @route '/conversas/{lead}'
 */
export const destroy = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/conversas/{lead}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\LeadManagementController::destroy
 * @see app/Http/Controllers/LeadManagementController.php:56
 * @route '/conversas/{lead}'
 */
destroy.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { lead: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { lead: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    lead: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        lead: typeof args.lead === 'object'
                ? args.lead.id
                : args.lead,
                }

    return destroy.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadManagementController::destroy
 * @see app/Http/Controllers/LeadManagementController.php:56
 * @route '/conversas/{lead}'
 */
destroy.delete = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\LeadManagementController::destroy
 * @see app/Http/Controllers/LeadManagementController.php:56
 * @route '/conversas/{lead}'
 */
    const destroyForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\LeadManagementController::destroy
 * @see app/Http/Controllers/LeadManagementController.php:56
 * @route '/conversas/{lead}'
 */
        destroyForm.delete = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\ConversasController::pause
 * @see app/Http/Controllers/ConversasController.php:254
 * @route '/conversas/{lead}/pause'
 */
export const pause = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pause.url(args, options),
    method: 'post',
})

pause.definition = {
    methods: ["post"],
    url: '/conversas/{lead}/pause',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ConversasController::pause
 * @see app/Http/Controllers/ConversasController.php:254
 * @route '/conversas/{lead}/pause'
 */
pause.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { lead: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { lead: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    lead: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        lead: typeof args.lead === 'object'
                ? args.lead.id
                : args.lead,
                }

    return pause.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConversasController::pause
 * @see app/Http/Controllers/ConversasController.php:254
 * @route '/conversas/{lead}/pause'
 */
pause.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pause.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ConversasController::pause
 * @see app/Http/Controllers/ConversasController.php:254
 * @route '/conversas/{lead}/pause'
 */
    const pauseForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: pause.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ConversasController::pause
 * @see app/Http/Controllers/ConversasController.php:254
 * @route '/conversas/{lead}/pause'
 */
        pauseForm.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: pause.url(args, options),
            method: 'post',
        })
    
    pause.form = pauseForm
/**
* @see \App\Http\Controllers\ConversasController::resume
 * @see app/Http/Controllers/ConversasController.php:272
 * @route '/conversas/{lead}/resume'
 */
export const resume = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resume.url(args, options),
    method: 'post',
})

resume.definition = {
    methods: ["post"],
    url: '/conversas/{lead}/resume',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ConversasController::resume
 * @see app/Http/Controllers/ConversasController.php:272
 * @route '/conversas/{lead}/resume'
 */
resume.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { lead: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { lead: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    lead: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        lead: typeof args.lead === 'object'
                ? args.lead.id
                : args.lead,
                }

    return resume.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConversasController::resume
 * @see app/Http/Controllers/ConversasController.php:272
 * @route '/conversas/{lead}/resume'
 */
resume.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resume.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ConversasController::resume
 * @see app/Http/Controllers/ConversasController.php:272
 * @route '/conversas/{lead}/resume'
 */
    const resumeForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: resume.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ConversasController::resume
 * @see app/Http/Controllers/ConversasController.php:272
 * @route '/conversas/{lead}/resume'
 */
        resumeForm.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: resume.url(args, options),
            method: 'post',
        })
    
    resume.form = resumeForm
/**
* @see \App\Http\Controllers\ConversasController::claim
 * @see app/Http/Controllers/ConversasController.php:294
 * @route '/conversas/{lead}/claim'
 */
export const claim = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: claim.url(args, options),
    method: 'post',
})

claim.definition = {
    methods: ["post"],
    url: '/conversas/{lead}/claim',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ConversasController::claim
 * @see app/Http/Controllers/ConversasController.php:294
 * @route '/conversas/{lead}/claim'
 */
claim.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { lead: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { lead: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    lead: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        lead: typeof args.lead === 'object'
                ? args.lead.id
                : args.lead,
                }

    return claim.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConversasController::claim
 * @see app/Http/Controllers/ConversasController.php:294
 * @route '/conversas/{lead}/claim'
 */
claim.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: claim.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ConversasController::claim
 * @see app/Http/Controllers/ConversasController.php:294
 * @route '/conversas/{lead}/claim'
 */
    const claimForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: claim.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ConversasController::claim
 * @see app/Http/Controllers/ConversasController.php:294
 * @route '/conversas/{lead}/claim'
 */
        claimForm.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: claim.url(args, options),
            method: 'post',
        })
    
    claim.form = claimForm
/**
* @see \App\Http\Controllers\ConversasController::aiMode
 * @see app/Http/Controllers/ConversasController.php:377
 * @route '/conversas/{lead}/ai-mode'
 */
export const aiMode = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: aiMode.url(args, options),
    method: 'patch',
})

aiMode.definition = {
    methods: ["patch"],
    url: '/conversas/{lead}/ai-mode',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\ConversasController::aiMode
 * @see app/Http/Controllers/ConversasController.php:377
 * @route '/conversas/{lead}/ai-mode'
 */
aiMode.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { lead: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { lead: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    lead: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        lead: typeof args.lead === 'object'
                ? args.lead.id
                : args.lead,
                }

    return aiMode.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConversasController::aiMode
 * @see app/Http/Controllers/ConversasController.php:377
 * @route '/conversas/{lead}/ai-mode'
 */
aiMode.patch = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: aiMode.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\ConversasController::aiMode
 * @see app/Http/Controllers/ConversasController.php:377
 * @route '/conversas/{lead}/ai-mode'
 */
    const aiModeForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: aiMode.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ConversasController::aiMode
 * @see app/Http/Controllers/ConversasController.php:377
 * @route '/conversas/{lead}/ai-mode'
 */
        aiModeForm.patch = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: aiMode.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    aiMode.form = aiModeForm
/**
* @see \App\Http\Controllers\ConversasController::assume
 * @see app/Http/Controllers/ConversasController.php:322
 * @route '/conversas/{lead}/assume'
 */
export const assume = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: assume.url(args, options),
    method: 'post',
})

assume.definition = {
    methods: ["post"],
    url: '/conversas/{lead}/assume',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ConversasController::assume
 * @see app/Http/Controllers/ConversasController.php:322
 * @route '/conversas/{lead}/assume'
 */
assume.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { lead: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { lead: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    lead: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        lead: typeof args.lead === 'object'
                ? args.lead.id
                : args.lead,
                }

    return assume.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConversasController::assume
 * @see app/Http/Controllers/ConversasController.php:322
 * @route '/conversas/{lead}/assume'
 */
assume.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: assume.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ConversasController::assume
 * @see app/Http/Controllers/ConversasController.php:322
 * @route '/conversas/{lead}/assume'
 */
    const assumeForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: assume.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ConversasController::assume
 * @see app/Http/Controllers/ConversasController.php:322
 * @route '/conversas/{lead}/assume'
 */
        assumeForm.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: assume.url(args, options),
            method: 'post',
        })
    
    assume.form = assumeForm
/**
* @see \App\Http\Controllers\ConversasController::clearHistory
 * @see app/Http/Controllers/ConversasController.php:395
 * @route '/conversas/{lead}/clear-history'
 */
export const clearHistory = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: clearHistory.url(args, options),
    method: 'post',
})

clearHistory.definition = {
    methods: ["post"],
    url: '/conversas/{lead}/clear-history',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ConversasController::clearHistory
 * @see app/Http/Controllers/ConversasController.php:395
 * @route '/conversas/{lead}/clear-history'
 */
clearHistory.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { lead: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { lead: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    lead: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        lead: typeof args.lead === 'object'
                ? args.lead.id
                : args.lead,
                }

    return clearHistory.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConversasController::clearHistory
 * @see app/Http/Controllers/ConversasController.php:395
 * @route '/conversas/{lead}/clear-history'
 */
clearHistory.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: clearHistory.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ConversasController::clearHistory
 * @see app/Http/Controllers/ConversasController.php:395
 * @route '/conversas/{lead}/clear-history'
 */
    const clearHistoryForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: clearHistory.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ConversasController::clearHistory
 * @see app/Http/Controllers/ConversasController.php:395
 * @route '/conversas/{lead}/clear-history'
 */
        clearHistoryForm.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: clearHistory.url(args, options),
            method: 'post',
        })
    
    clearHistory.form = clearHistoryForm
/**
* @see \App\Http\Controllers\ConversasController::send
 * @see app/Http/Controllers/ConversasController.php:419
 * @route '/conversas/{lead}/send'
 */
export const send = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: send.url(args, options),
    method: 'post',
})

send.definition = {
    methods: ["post"],
    url: '/conversas/{lead}/send',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ConversasController::send
 * @see app/Http/Controllers/ConversasController.php:419
 * @route '/conversas/{lead}/send'
 */
send.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { lead: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { lead: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    lead: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        lead: typeof args.lead === 'object'
                ? args.lead.id
                : args.lead,
                }

    return send.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConversasController::send
 * @see app/Http/Controllers/ConversasController.php:419
 * @route '/conversas/{lead}/send'
 */
send.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: send.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ConversasController::send
 * @see app/Http/Controllers/ConversasController.php:419
 * @route '/conversas/{lead}/send'
 */
    const sendForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: send.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ConversasController::send
 * @see app/Http/Controllers/ConversasController.php:419
 * @route '/conversas/{lead}/send'
 */
        sendForm.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: send.url(args, options),
            method: 'post',
        })
    
    send.form = sendForm
/**
* @see \App\Http\Controllers\LeadManagementController::prepareCampaign
 * @see app/Http/Controllers/LeadManagementController.php:136
 * @route '/conversas/{lead}/prepare-campaign'
 */
export const prepareCampaign = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: prepareCampaign.url(args, options),
    method: 'post',
})

prepareCampaign.definition = {
    methods: ["post"],
    url: '/conversas/{lead}/prepare-campaign',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LeadManagementController::prepareCampaign
 * @see app/Http/Controllers/LeadManagementController.php:136
 * @route '/conversas/{lead}/prepare-campaign'
 */
prepareCampaign.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { lead: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { lead: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    lead: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        lead: typeof args.lead === 'object'
                ? args.lead.id
                : args.lead,
                }

    return prepareCampaign.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadManagementController::prepareCampaign
 * @see app/Http/Controllers/LeadManagementController.php:136
 * @route '/conversas/{lead}/prepare-campaign'
 */
prepareCampaign.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: prepareCampaign.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\LeadManagementController::prepareCampaign
 * @see app/Http/Controllers/LeadManagementController.php:136
 * @route '/conversas/{lead}/prepare-campaign'
 */
    const prepareCampaignForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: prepareCampaign.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\LeadManagementController::prepareCampaign
 * @see app/Http/Controllers/LeadManagementController.php:136
 * @route '/conversas/{lead}/prepare-campaign'
 */
        prepareCampaignForm.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: prepareCampaign.url(args, options),
            method: 'post',
        })
    
    prepareCampaign.form = prepareCampaignForm
const conversas = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
bulkAction: Object.assign(bulkAction, bulkAction),
transfer: Object.assign(transfer, transfer),
preview: Object.assign(preview, preview),
show: Object.assign(show, show),
destroy: Object.assign(destroy, destroy),
pause: Object.assign(pause, pause),
resume: Object.assign(resume, resume),
claim: Object.assign(claim, claim),
aiMode: Object.assign(aiMode, aiMode),
assume: Object.assign(assume, assume),
followup: Object.assign(followup, followup),
clearHistory: Object.assign(clearHistory, clearHistory),
send: Object.assign(send, send),
prepareCampaign: Object.assign(prepareCampaign, prepareCampaign),
}

export default conversas