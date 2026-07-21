import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\ConversasController::index
 * @see app/Http/Controllers/ConversasController.php:38
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
 * @see app/Http/Controllers/ConversasController.php:38
 * @route '/conversas'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConversasController::index
 * @see app/Http/Controllers/ConversasController.php:38
 * @route '/conversas'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ConversasController::index
 * @see app/Http/Controllers/ConversasController.php:38
 * @route '/conversas'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\ConversasController::index
 * @see app/Http/Controllers/ConversasController.php:38
 * @route '/conversas'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\ConversasController::index
 * @see app/Http/Controllers/ConversasController.php:38
 * @route '/conversas'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\ConversasController::index
 * @see app/Http/Controllers/ConversasController.php:38
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
* @see \App\Http\Controllers\ConversasController::bulkTransfer
 * @see app/Http/Controllers/ConversasController.php:173
 * @route '/conversas/transfer'
 */
export const bulkTransfer = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkTransfer.url(options),
    method: 'post',
})

bulkTransfer.definition = {
    methods: ["post"],
    url: '/conversas/transfer',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ConversasController::bulkTransfer
 * @see app/Http/Controllers/ConversasController.php:173
 * @route '/conversas/transfer'
 */
bulkTransfer.url = (options?: RouteQueryOptions) => {
    return bulkTransfer.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConversasController::bulkTransfer
 * @see app/Http/Controllers/ConversasController.php:173
 * @route '/conversas/transfer'
 */
bulkTransfer.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkTransfer.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ConversasController::bulkTransfer
 * @see app/Http/Controllers/ConversasController.php:173
 * @route '/conversas/transfer'
 */
    const bulkTransferForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: bulkTransfer.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ConversasController::bulkTransfer
 * @see app/Http/Controllers/ConversasController.php:173
 * @route '/conversas/transfer'
 */
        bulkTransferForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: bulkTransfer.url(options),
            method: 'post',
        })
    
    bulkTransfer.form = bulkTransferForm
/**
* @see \App\Http\Controllers\ConversasController::preview
 * @see app/Http/Controllers/ConversasController.php:50
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
 * @see app/Http/Controllers/ConversasController.php:50
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
 * @see app/Http/Controllers/ConversasController.php:50
 * @route '/conversas/{lead}/preview'
 */
preview.get = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ConversasController::preview
 * @see app/Http/Controllers/ConversasController.php:50
 * @route '/conversas/{lead}/preview'
 */
preview.head = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: preview.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\ConversasController::preview
 * @see app/Http/Controllers/ConversasController.php:50
 * @route '/conversas/{lead}/preview'
 */
    const previewForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: preview.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\ConversasController::preview
 * @see app/Http/Controllers/ConversasController.php:50
 * @route '/conversas/{lead}/preview'
 */
        previewForm.get = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: preview.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\ConversasController::preview
 * @see app/Http/Controllers/ConversasController.php:50
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
 * @see app/Http/Controllers/ConversasController.php:43
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
 * @see app/Http/Controllers/ConversasController.php:43
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
 * @see app/Http/Controllers/ConversasController.php:43
 * @route '/conversas/{lead}'
 */
show.get = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ConversasController::show
 * @see app/Http/Controllers/ConversasController.php:43
 * @route '/conversas/{lead}'
 */
show.head = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\ConversasController::show
 * @see app/Http/Controllers/ConversasController.php:43
 * @route '/conversas/{lead}'
 */
    const showForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\ConversasController::show
 * @see app/Http/Controllers/ConversasController.php:43
 * @route '/conversas/{lead}'
 */
        showForm.get = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\ConversasController::show
 * @see app/Http/Controllers/ConversasController.php:43
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
* @see \App\Http\Controllers\ConversasController::pause
 * @see app/Http/Controllers/ConversasController.php:81
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
 * @see app/Http/Controllers/ConversasController.php:81
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
 * @see app/Http/Controllers/ConversasController.php:81
 * @route '/conversas/{lead}/pause'
 */
pause.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pause.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ConversasController::pause
 * @see app/Http/Controllers/ConversasController.php:81
 * @route '/conversas/{lead}/pause'
 */
    const pauseForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: pause.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ConversasController::pause
 * @see app/Http/Controllers/ConversasController.php:81
 * @route '/conversas/{lead}/pause'
 */
        pauseForm.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: pause.url(args, options),
            method: 'post',
        })
    
    pause.form = pauseForm
/**
* @see \App\Http\Controllers\ConversasController::resume
 * @see app/Http/Controllers/ConversasController.php:99
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
 * @see app/Http/Controllers/ConversasController.php:99
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
 * @see app/Http/Controllers/ConversasController.php:99
 * @route '/conversas/{lead}/resume'
 */
resume.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resume.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ConversasController::resume
 * @see app/Http/Controllers/ConversasController.php:99
 * @route '/conversas/{lead}/resume'
 */
    const resumeForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: resume.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ConversasController::resume
 * @see app/Http/Controllers/ConversasController.php:99
 * @route '/conversas/{lead}/resume'
 */
        resumeForm.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: resume.url(args, options),
            method: 'post',
        })
    
    resume.form = resumeForm
/**
* @see \App\Http\Controllers\ConversasController::claim
 * @see app/Http/Controllers/ConversasController.php:135
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
 * @see app/Http/Controllers/ConversasController.php:135
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
 * @see app/Http/Controllers/ConversasController.php:135
 * @route '/conversas/{lead}/claim'
 */
claim.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: claim.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ConversasController::claim
 * @see app/Http/Controllers/ConversasController.php:135
 * @route '/conversas/{lead}/claim'
 */
    const claimForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: claim.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ConversasController::claim
 * @see app/Http/Controllers/ConversasController.php:135
 * @route '/conversas/{lead}/claim'
 */
        claimForm.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: claim.url(args, options),
            method: 'post',
        })
    
    claim.form = claimForm
/**
* @see \App\Http\Controllers\ConversasController::updateAiMode
 * @see app/Http/Controllers/ConversasController.php:223
 * @route '/conversas/{lead}/ai-mode'
 */
export const updateAiMode = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateAiMode.url(args, options),
    method: 'patch',
})

updateAiMode.definition = {
    methods: ["patch"],
    url: '/conversas/{lead}/ai-mode',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\ConversasController::updateAiMode
 * @see app/Http/Controllers/ConversasController.php:223
 * @route '/conversas/{lead}/ai-mode'
 */
updateAiMode.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return updateAiMode.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConversasController::updateAiMode
 * @see app/Http/Controllers/ConversasController.php:223
 * @route '/conversas/{lead}/ai-mode'
 */
updateAiMode.patch = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateAiMode.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\ConversasController::updateAiMode
 * @see app/Http/Controllers/ConversasController.php:223
 * @route '/conversas/{lead}/ai-mode'
 */
    const updateAiModeForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: updateAiMode.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ConversasController::updateAiMode
 * @see app/Http/Controllers/ConversasController.php:223
 * @route '/conversas/{lead}/ai-mode'
 */
        updateAiModeForm.patch = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updateAiMode.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    updateAiMode.form = updateAiModeForm
/**
* @see \App\Http\Controllers\ConversasController::updateCollectedInformation
 * @see app/Http/Controllers/ConversasController.php:236
 * @route '/conversas/{lead}/informacoes-coletadas'
 */
export const updateCollectedInformation = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateCollectedInformation.url(args, options),
    method: 'patch',
})

updateCollectedInformation.definition = {
    methods: ["patch"],
    url: '/conversas/{lead}/informacoes-coletadas',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\ConversasController::updateCollectedInformation
 * @see app/Http/Controllers/ConversasController.php:236
 * @route '/conversas/{lead}/informacoes-coletadas'
 */
updateCollectedInformation.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return updateCollectedInformation.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConversasController::updateCollectedInformation
 * @see app/Http/Controllers/ConversasController.php:236
 * @route '/conversas/{lead}/informacoes-coletadas'
 */
updateCollectedInformation.patch = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateCollectedInformation.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\ConversasController::updateCollectedInformation
 * @see app/Http/Controllers/ConversasController.php:236
 * @route '/conversas/{lead}/informacoes-coletadas'
 */
    const updateCollectedInformationForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: updateCollectedInformation.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ConversasController::updateCollectedInformation
 * @see app/Http/Controllers/ConversasController.php:236
 * @route '/conversas/{lead}/informacoes-coletadas'
 */
        updateCollectedInformationForm.patch = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updateCollectedInformation.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    updateCollectedInformation.form = updateCollectedInformationForm
/**
* @see \App\Http\Controllers\ConversasController::assume
 * @see app/Http/Controllers/ConversasController.php:163
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
 * @see app/Http/Controllers/ConversasController.php:163
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
 * @see app/Http/Controllers/ConversasController.php:163
 * @route '/conversas/{lead}/assume'
 */
assume.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: assume.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ConversasController::assume
 * @see app/Http/Controllers/ConversasController.php:163
 * @route '/conversas/{lead}/assume'
 */
    const assumeForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: assume.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ConversasController::assume
 * @see app/Http/Controllers/ConversasController.php:163
 * @route '/conversas/{lead}/assume'
 */
        assumeForm.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: assume.url(args, options),
            method: 'post',
        })
    
    assume.form = assumeForm
/**
* @see \App\Http\Controllers\ConversasController::clearHistory
 * @see app/Http/Controllers/ConversasController.php:259
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
 * @see app/Http/Controllers/ConversasController.php:259
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
 * @see app/Http/Controllers/ConversasController.php:259
 * @route '/conversas/{lead}/clear-history'
 */
clearHistory.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: clearHistory.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ConversasController::clearHistory
 * @see app/Http/Controllers/ConversasController.php:259
 * @route '/conversas/{lead}/clear-history'
 */
    const clearHistoryForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: clearHistory.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ConversasController::clearHistory
 * @see app/Http/Controllers/ConversasController.php:259
 * @route '/conversas/{lead}/clear-history'
 */
        clearHistoryForm.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: clearHistory.url(args, options),
            method: 'post',
        })
    
    clearHistory.form = clearHistoryForm
/**
* @see \App\Http\Controllers\ConversasController::sendMessage
 * @see app/Http/Controllers/ConversasController.php:283
 * @route '/conversas/{lead}/send'
 */
export const sendMessage = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sendMessage.url(args, options),
    method: 'post',
})

sendMessage.definition = {
    methods: ["post"],
    url: '/conversas/{lead}/send',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ConversasController::sendMessage
 * @see app/Http/Controllers/ConversasController.php:283
 * @route '/conversas/{lead}/send'
 */
sendMessage.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return sendMessage.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConversasController::sendMessage
 * @see app/Http/Controllers/ConversasController.php:283
 * @route '/conversas/{lead}/send'
 */
sendMessage.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sendMessage.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ConversasController::sendMessage
 * @see app/Http/Controllers/ConversasController.php:283
 * @route '/conversas/{lead}/send'
 */
    const sendMessageForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: sendMessage.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ConversasController::sendMessage
 * @see app/Http/Controllers/ConversasController.php:283
 * @route '/conversas/{lead}/send'
 */
        sendMessageForm.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: sendMessage.url(args, options),
            method: 'post',
        })
    
    sendMessage.form = sendMessageForm
const ConversasController = { index, bulkTransfer, preview, show, pause, resume, claim, updateAiMode, updateCollectedInformation, assume, clearHistory, sendMessage }

export default ConversasController