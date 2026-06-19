import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\PlaygroundController::index
 * @see app/Http/Controllers/PlaygroundController.php:33
 * @route '/playground'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/playground',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PlaygroundController::index
 * @see app/Http/Controllers/PlaygroundController.php:33
 * @route '/playground'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlaygroundController::index
 * @see app/Http/Controllers/PlaygroundController.php:33
 * @route '/playground'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PlaygroundController::index
 * @see app/Http/Controllers/PlaygroundController.php:33
 * @route '/playground'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PlaygroundController::index
 * @see app/Http/Controllers/PlaygroundController.php:33
 * @route '/playground'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PlaygroundController::index
 * @see app/Http/Controllers/PlaygroundController.php:33
 * @route '/playground'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PlaygroundController::index
 * @see app/Http/Controllers/PlaygroundController.php:33
 * @route '/playground'
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
* @see \App\Http\Controllers\PlaygroundController::store
 * @see app/Http/Controllers/PlaygroundController.php:60
 * @route '/playground'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/playground',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PlaygroundController::store
 * @see app/Http/Controllers/PlaygroundController.php:60
 * @route '/playground'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlaygroundController::store
 * @see app/Http/Controllers/PlaygroundController.php:60
 * @route '/playground'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PlaygroundController::store
 * @see app/Http/Controllers/PlaygroundController.php:60
 * @route '/playground'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PlaygroundController::store
 * @see app/Http/Controllers/PlaygroundController.php:60
 * @route '/playground'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\PlaygroundController::destroy
 * @see app/Http/Controllers/PlaygroundController.php:87
 * @route '/playground/{lead}'
 */
export const destroy = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/playground/{lead}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\PlaygroundController::destroy
 * @see app/Http/Controllers/PlaygroundController.php:87
 * @route '/playground/{lead}'
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
* @see \App\Http\Controllers\PlaygroundController::destroy
 * @see app/Http/Controllers/PlaygroundController.php:87
 * @route '/playground/{lead}'
 */
destroy.delete = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\PlaygroundController::destroy
 * @see app/Http/Controllers/PlaygroundController.php:87
 * @route '/playground/{lead}'
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
* @see \App\Http\Controllers\PlaygroundController::destroy
 * @see app/Http/Controllers/PlaygroundController.php:87
 * @route '/playground/{lead}'
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
* @see \App\Http\Controllers\PlaygroundController::reset
 * @see app/Http/Controllers/PlaygroundController.php:96
 * @route '/playground/{lead}/reset'
 */
export const reset = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reset.url(args, options),
    method: 'post',
})

reset.definition = {
    methods: ["post"],
    url: '/playground/{lead}/reset',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PlaygroundController::reset
 * @see app/Http/Controllers/PlaygroundController.php:96
 * @route '/playground/{lead}/reset'
 */
reset.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return reset.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlaygroundController::reset
 * @see app/Http/Controllers/PlaygroundController.php:96
 * @route '/playground/{lead}/reset'
 */
reset.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reset.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PlaygroundController::reset
 * @see app/Http/Controllers/PlaygroundController.php:96
 * @route '/playground/{lead}/reset'
 */
    const resetForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: reset.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PlaygroundController::reset
 * @see app/Http/Controllers/PlaygroundController.php:96
 * @route '/playground/{lead}/reset'
 */
        resetForm.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: reset.url(args, options),
            method: 'post',
        })

    reset.form = resetForm
/**
* @see \App\Http\Controllers\PlaygroundController::updatePrompt
 * @see app/Http/Controllers/PlaygroundController.php:112
 * @route '/playground/{lead}/prompt'
 */
export const updatePrompt = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: updatePrompt.url(args, options),
    method: 'post',
})

updatePrompt.definition = {
    methods: ["post"],
    url: '/playground/{lead}/prompt',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PlaygroundController::updatePrompt
 * @see app/Http/Controllers/PlaygroundController.php:112
 * @route '/playground/{lead}/prompt'
 */
updatePrompt.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return updatePrompt.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlaygroundController::updatePrompt
 * @see app/Http/Controllers/PlaygroundController.php:112
 * @route '/playground/{lead}/prompt'
 */
updatePrompt.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: updatePrompt.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PlaygroundController::updatePrompt
 * @see app/Http/Controllers/PlaygroundController.php:112
 * @route '/playground/{lead}/prompt'
 */
    const updatePromptForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: updatePrompt.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PlaygroundController::updatePrompt
 * @see app/Http/Controllers/PlaygroundController.php:112
 * @route '/playground/{lead}/prompt'
 */
        updatePromptForm.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updatePrompt.url(args, options),
            method: 'post',
        })

    updatePrompt.form = updatePromptForm
/**
* @see \App\Http\Controllers\PlaygroundController::messages
 * @see app/Http/Controllers/PlaygroundController.php:137
 * @route '/playground/{lead}/messages'
 */
export const messages = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: messages.url(args, options),
    method: 'get',
})

messages.definition = {
    methods: ["get","head"],
    url: '/playground/{lead}/messages',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PlaygroundController::messages
 * @see app/Http/Controllers/PlaygroundController.php:137
 * @route '/playground/{lead}/messages'
 */
messages.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return messages.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlaygroundController::messages
 * @see app/Http/Controllers/PlaygroundController.php:137
 * @route '/playground/{lead}/messages'
 */
messages.get = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: messages.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PlaygroundController::messages
 * @see app/Http/Controllers/PlaygroundController.php:137
 * @route '/playground/{lead}/messages'
 */
messages.head = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: messages.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PlaygroundController::messages
 * @see app/Http/Controllers/PlaygroundController.php:137
 * @route '/playground/{lead}/messages'
 */
    const messagesForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: messages.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PlaygroundController::messages
 * @see app/Http/Controllers/PlaygroundController.php:137
 * @route '/playground/{lead}/messages'
 */
        messagesForm.get = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: messages.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PlaygroundController::messages
 * @see app/Http/Controllers/PlaygroundController.php:137
 * @route '/playground/{lead}/messages'
 */
        messagesForm.head = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: messages.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    messages.form = messagesForm
/**
* @see \App\Http\Controllers\PlaygroundController::generateScenario
 * @see app/Http/Controllers/PlaygroundController.php:257
 * @route '/playground/generate-scenario'
 */
export const generateScenario = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generateScenario.url(options),
    method: 'post',
})

generateScenario.definition = {
    methods: ["post"],
    url: '/playground/generate-scenario',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PlaygroundController::generateScenario
 * @see app/Http/Controllers/PlaygroundController.php:257
 * @route '/playground/generate-scenario'
 */
generateScenario.url = (options?: RouteQueryOptions) => {
    return generateScenario.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlaygroundController::generateScenario
 * @see app/Http/Controllers/PlaygroundController.php:257
 * @route '/playground/generate-scenario'
 */
generateScenario.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generateScenario.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PlaygroundController::generateScenario
 * @see app/Http/Controllers/PlaygroundController.php:257
 * @route '/playground/generate-scenario'
 */
    const generateScenarioForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: generateScenario.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PlaygroundController::generateScenario
 * @see app/Http/Controllers/PlaygroundController.php:257
 * @route '/playground/generate-scenario'
 */
        generateScenarioForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: generateScenario.url(options),
            method: 'post',
        })

    generateScenario.form = generateScenarioForm
/**
* @see \App\Http\Controllers\PlaygroundController::scanBlindspots
 * @see app/Http/Controllers/PlaygroundController.php:188
 * @route '/playground/scan-blindspots'
 */
export const scanBlindspots = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: scanBlindspots.url(options),
    method: 'post',
})

scanBlindspots.definition = {
    methods: ["post"],
    url: '/playground/scan-blindspots',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PlaygroundController::scanBlindspots
 * @see app/Http/Controllers/PlaygroundController.php:188
 * @route '/playground/scan-blindspots'
 */
scanBlindspots.url = (options?: RouteQueryOptions) => {
    return scanBlindspots.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlaygroundController::scanBlindspots
 * @see app/Http/Controllers/PlaygroundController.php:188
 * @route '/playground/scan-blindspots'
 */
scanBlindspots.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: scanBlindspots.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PlaygroundController::scanBlindspots
 * @see app/Http/Controllers/PlaygroundController.php:188
 * @route '/playground/scan-blindspots'
 */
    const scanBlindspotsForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: scanBlindspots.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PlaygroundController::scanBlindspots
 * @see app/Http/Controllers/PlaygroundController.php:188
 * @route '/playground/scan-blindspots'
 */
        scanBlindspotsForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: scanBlindspots.url(options),
            method: 'post',
        })

    scanBlindspots.form = scanBlindspotsForm
/**
* @see \App\Http\Controllers\PlaygroundController::chat
 * @see app/Http/Controllers/PlaygroundController.php:126
 * @route '/playground/{lead}/chat'
 */
export const chat = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: chat.url(args, options),
    method: 'post',
})

chat.definition = {
    methods: ["post"],
    url: '/playground/{lead}/chat',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PlaygroundController::chat
 * @see app/Http/Controllers/PlaygroundController.php:126
 * @route '/playground/{lead}/chat'
 */
chat.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return chat.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlaygroundController::chat
 * @see app/Http/Controllers/PlaygroundController.php:126
 * @route '/playground/{lead}/chat'
 */
chat.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: chat.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PlaygroundController::chat
 * @see app/Http/Controllers/PlaygroundController.php:126
 * @route '/playground/{lead}/chat'
 */
    const chatForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: chat.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PlaygroundController::chat
 * @see app/Http/Controllers/PlaygroundController.php:126
 * @route '/playground/{lead}/chat'
 */
        chatForm.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: chat.url(args, options),
            method: 'post',
        })

    chat.form = chatForm
/**
* @see \App\Http\Controllers\PlaygroundController::testerChat
 * @see app/Http/Controllers/PlaygroundController.php:145
 * @route '/playground/{lead}/tester-chat'
 */
export const testerChat = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: testerChat.url(args, options),
    method: 'post',
})

testerChat.definition = {
    methods: ["post"],
    url: '/playground/{lead}/tester-chat',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PlaygroundController::testerChat
 * @see app/Http/Controllers/PlaygroundController.php:145
 * @route '/playground/{lead}/tester-chat'
 */
testerChat.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return testerChat.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlaygroundController::testerChat
 * @see app/Http/Controllers/PlaygroundController.php:145
 * @route '/playground/{lead}/tester-chat'
 */
testerChat.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: testerChat.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PlaygroundController::testerChat
 * @see app/Http/Controllers/PlaygroundController.php:145
 * @route '/playground/{lead}/tester-chat'
 */
    const testerChatForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: testerChat.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PlaygroundController::testerChat
 * @see app/Http/Controllers/PlaygroundController.php:145
 * @route '/playground/{lead}/tester-chat'
 */
        testerChatForm.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: testerChat.url(args, options),
            method: 'post',
        })

    testerChat.form = testerChatForm
/**
* @see \App\Http\Controllers\PlaygroundController::evaluate
 * @see app/Http/Controllers/PlaygroundController.php:176
 * @route '/playground/{lead}/evaluate'
 */
export const evaluate = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: evaluate.url(args, options),
    method: 'post',
})

evaluate.definition = {
    methods: ["post"],
    url: '/playground/{lead}/evaluate',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PlaygroundController::evaluate
 * @see app/Http/Controllers/PlaygroundController.php:176
 * @route '/playground/{lead}/evaluate'
 */
evaluate.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return evaluate.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlaygroundController::evaluate
 * @see app/Http/Controllers/PlaygroundController.php:176
 * @route '/playground/{lead}/evaluate'
 */
evaluate.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: evaluate.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PlaygroundController::evaluate
 * @see app/Http/Controllers/PlaygroundController.php:176
 * @route '/playground/{lead}/evaluate'
 */
    const evaluateForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: evaluate.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PlaygroundController::evaluate
 * @see app/Http/Controllers/PlaygroundController.php:176
 * @route '/playground/{lead}/evaluate'
 */
        evaluateForm.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: evaluate.url(args, options),
            method: 'post',
        })

    evaluate.form = evaluateForm
const PlaygroundController = { index, store, destroy, reset, updatePrompt, messages, generateScenario, scanBlindspots, chat, testerChat, evaluate }

export default PlaygroundController
