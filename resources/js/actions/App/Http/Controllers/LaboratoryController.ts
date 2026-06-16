import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\LaboratoryController::index
 * @see app/Http/Controllers/LaboratoryController.php:21
 * @route '/laboratory'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/laboratory',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LaboratoryController::index
 * @see app/Http/Controllers/LaboratoryController.php:21
 * @route '/laboratory'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LaboratoryController::index
 * @see app/Http/Controllers/LaboratoryController.php:21
 * @route '/laboratory'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\LaboratoryController::index
 * @see app/Http/Controllers/LaboratoryController.php:21
 * @route '/laboratory'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\LaboratoryController::index
 * @see app/Http/Controllers/LaboratoryController.php:21
 * @route '/laboratory'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\LaboratoryController::index
 * @see app/Http/Controllers/LaboratoryController.php:21
 * @route '/laboratory'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\LaboratoryController::index
 * @see app/Http/Controllers/LaboratoryController.php:21
 * @route '/laboratory'
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
* @see \App\Http\Controllers\LaboratoryController::datasets
 * @see app/Http/Controllers/LaboratoryController.php:41
 * @route '/laboratory/datasets-page'
 */
export const datasets = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: datasets.url(options),
    method: 'get',
})

datasets.definition = {
    methods: ["get","head"],
    url: '/laboratory/datasets-page',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LaboratoryController::datasets
 * @see app/Http/Controllers/LaboratoryController.php:41
 * @route '/laboratory/datasets-page'
 */
datasets.url = (options?: RouteQueryOptions) => {
    return datasets.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LaboratoryController::datasets
 * @see app/Http/Controllers/LaboratoryController.php:41
 * @route '/laboratory/datasets-page'
 */
datasets.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: datasets.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\LaboratoryController::datasets
 * @see app/Http/Controllers/LaboratoryController.php:41
 * @route '/laboratory/datasets-page'
 */
datasets.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: datasets.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\LaboratoryController::datasets
 * @see app/Http/Controllers/LaboratoryController.php:41
 * @route '/laboratory/datasets-page'
 */
    const datasetsForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: datasets.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\LaboratoryController::datasets
 * @see app/Http/Controllers/LaboratoryController.php:41
 * @route '/laboratory/datasets-page'
 */
        datasetsForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: datasets.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\LaboratoryController::datasets
 * @see app/Http/Controllers/LaboratoryController.php:41
 * @route '/laboratory/datasets-page'
 */
        datasetsForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: datasets.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    datasets.form = datasetsForm
/**
* @see \App\Http\Controllers\LaboratoryController::stressTest
 * @see app/Http/Controllers/LaboratoryController.php:61
 * @route '/laboratory/stress-test'
 */
export const stressTest = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: stressTest.url(options),
    method: 'get',
})

stressTest.definition = {
    methods: ["get","head"],
    url: '/laboratory/stress-test',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LaboratoryController::stressTest
 * @see app/Http/Controllers/LaboratoryController.php:61
 * @route '/laboratory/stress-test'
 */
stressTest.url = (options?: RouteQueryOptions) => {
    return stressTest.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LaboratoryController::stressTest
 * @see app/Http/Controllers/LaboratoryController.php:61
 * @route '/laboratory/stress-test'
 */
stressTest.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: stressTest.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\LaboratoryController::stressTest
 * @see app/Http/Controllers/LaboratoryController.php:61
 * @route '/laboratory/stress-test'
 */
stressTest.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: stressTest.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\LaboratoryController::stressTest
 * @see app/Http/Controllers/LaboratoryController.php:61
 * @route '/laboratory/stress-test'
 */
    const stressTestForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: stressTest.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\LaboratoryController::stressTest
 * @see app/Http/Controllers/LaboratoryController.php:61
 * @route '/laboratory/stress-test'
 */
        stressTestForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: stressTest.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\LaboratoryController::stressTest
 * @see app/Http/Controllers/LaboratoryController.php:61
 * @route '/laboratory/stress-test'
 */
        stressTestForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: stressTest.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    stressTest.form = stressTestForm
/**
* @see \App\Http\Controllers\LaboratoryController::stressTestResults
 * @see app/Http/Controllers/LaboratoryController.php:90
 * @route '/laboratory/stress-test/{run}'
 */
export const stressTestResults = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: stressTestResults.url(args, options),
    method: 'get',
})

stressTestResults.definition = {
    methods: ["get","head"],
    url: '/laboratory/stress-test/{run}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LaboratoryController::stressTestResults
 * @see app/Http/Controllers/LaboratoryController.php:90
 * @route '/laboratory/stress-test/{run}'
 */
stressTestResults.url = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { run: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { run: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    run: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        run: typeof args.run === 'object'
                ? args.run.id
                : args.run,
                }

    return stressTestResults.definition.url
            .replace('{run}', parsedArgs.run.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LaboratoryController::stressTestResults
 * @see app/Http/Controllers/LaboratoryController.php:90
 * @route '/laboratory/stress-test/{run}'
 */
stressTestResults.get = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: stressTestResults.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\LaboratoryController::stressTestResults
 * @see app/Http/Controllers/LaboratoryController.php:90
 * @route '/laboratory/stress-test/{run}'
 */
stressTestResults.head = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: stressTestResults.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\LaboratoryController::stressTestResults
 * @see app/Http/Controllers/LaboratoryController.php:90
 * @route '/laboratory/stress-test/{run}'
 */
    const stressTestResultsForm = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: stressTestResults.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\LaboratoryController::stressTestResults
 * @see app/Http/Controllers/LaboratoryController.php:90
 * @route '/laboratory/stress-test/{run}'
 */
        stressTestResultsForm.get = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: stressTestResults.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\LaboratoryController::stressTestResults
 * @see app/Http/Controllers/LaboratoryController.php:90
 * @route '/laboratory/stress-test/{run}'
 */
        stressTestResultsForm.head = (args: { run: number | { id: number } } | [run: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: stressTestResults.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    stressTestResults.form = stressTestResultsForm
/**
* @see \App\Http\Controllers\LaboratoryController::aiUsage
 * @see app/Http/Controllers/LaboratoryController.php:144
 * @route '/laboratory/ai-usage'
 */
export const aiUsage = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: aiUsage.url(options),
    method: 'get',
})

aiUsage.definition = {
    methods: ["get","head"],
    url: '/laboratory/ai-usage',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LaboratoryController::aiUsage
 * @see app/Http/Controllers/LaboratoryController.php:144
 * @route '/laboratory/ai-usage'
 */
aiUsage.url = (options?: RouteQueryOptions) => {
    return aiUsage.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LaboratoryController::aiUsage
 * @see app/Http/Controllers/LaboratoryController.php:144
 * @route '/laboratory/ai-usage'
 */
aiUsage.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: aiUsage.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\LaboratoryController::aiUsage
 * @see app/Http/Controllers/LaboratoryController.php:144
 * @route '/laboratory/ai-usage'
 */
aiUsage.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: aiUsage.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\LaboratoryController::aiUsage
 * @see app/Http/Controllers/LaboratoryController.php:144
 * @route '/laboratory/ai-usage'
 */
    const aiUsageForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: aiUsage.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\LaboratoryController::aiUsage
 * @see app/Http/Controllers/LaboratoryController.php:144
 * @route '/laboratory/ai-usage'
 */
        aiUsageForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: aiUsage.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\LaboratoryController::aiUsage
 * @see app/Http/Controllers/LaboratoryController.php:144
 * @route '/laboratory/ai-usage'
 */
        aiUsageForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: aiUsage.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    aiUsage.form = aiUsageForm
/**
* @see \App\Http\Controllers\LaboratoryController::health
 * @see app/Http/Controllers/LaboratoryController.php:187
 * @route '/laboratory/health'
 */
export const health = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: health.url(options),
    method: 'get',
})

health.definition = {
    methods: ["get","head"],
    url: '/laboratory/health',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LaboratoryController::health
 * @see app/Http/Controllers/LaboratoryController.php:187
 * @route '/laboratory/health'
 */
health.url = (options?: RouteQueryOptions) => {
    return health.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LaboratoryController::health
 * @see app/Http/Controllers/LaboratoryController.php:187
 * @route '/laboratory/health'
 */
health.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: health.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\LaboratoryController::health
 * @see app/Http/Controllers/LaboratoryController.php:187
 * @route '/laboratory/health'
 */
health.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: health.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\LaboratoryController::health
 * @see app/Http/Controllers/LaboratoryController.php:187
 * @route '/laboratory/health'
 */
    const healthForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: health.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\LaboratoryController::health
 * @see app/Http/Controllers/LaboratoryController.php:187
 * @route '/laboratory/health'
 */
        healthForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: health.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\LaboratoryController::health
 * @see app/Http/Controllers/LaboratoryController.php:187
 * @route '/laboratory/health'
 */
        healthForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: health.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    health.form = healthForm
/**
* @see \App\Http\Controllers\LaboratoryController::interactionTimeline
 * @see app/Http/Controllers/LaboratoryController.php:202
 * @route '/laboratory/interactions/{interactionId}'
 */
export const interactionTimeline = (args: { interactionId: string | number } | [interactionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: interactionTimeline.url(args, options),
    method: 'get',
})

interactionTimeline.definition = {
    methods: ["get","head"],
    url: '/laboratory/interactions/{interactionId}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LaboratoryController::interactionTimeline
 * @see app/Http/Controllers/LaboratoryController.php:202
 * @route '/laboratory/interactions/{interactionId}'
 */
interactionTimeline.url = (args: { interactionId: string | number } | [interactionId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { interactionId: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    interactionId: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        interactionId: args.interactionId,
                }

    return interactionTimeline.definition.url
            .replace('{interactionId}', parsedArgs.interactionId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LaboratoryController::interactionTimeline
 * @see app/Http/Controllers/LaboratoryController.php:202
 * @route '/laboratory/interactions/{interactionId}'
 */
interactionTimeline.get = (args: { interactionId: string | number } | [interactionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: interactionTimeline.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\LaboratoryController::interactionTimeline
 * @see app/Http/Controllers/LaboratoryController.php:202
 * @route '/laboratory/interactions/{interactionId}'
 */
interactionTimeline.head = (args: { interactionId: string | number } | [interactionId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: interactionTimeline.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\LaboratoryController::interactionTimeline
 * @see app/Http/Controllers/LaboratoryController.php:202
 * @route '/laboratory/interactions/{interactionId}'
 */
    const interactionTimelineForm = (args: { interactionId: string | number } | [interactionId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: interactionTimeline.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\LaboratoryController::interactionTimeline
 * @see app/Http/Controllers/LaboratoryController.php:202
 * @route '/laboratory/interactions/{interactionId}'
 */
        interactionTimelineForm.get = (args: { interactionId: string | number } | [interactionId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: interactionTimeline.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\LaboratoryController::interactionTimeline
 * @see app/Http/Controllers/LaboratoryController.php:202
 * @route '/laboratory/interactions/{interactionId}'
 */
        interactionTimelineForm.head = (args: { interactionId: string | number } | [interactionId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: interactionTimeline.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    interactionTimeline.form = interactionTimelineForm
/**
* @see \App\Http\Controllers\LaboratoryController::leadInteractionTimeline
 * @see app/Http/Controllers/LaboratoryController.php:231
 * @route '/laboratory/leads/{lead}/interactions'
 */
export const leadInteractionTimeline = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: leadInteractionTimeline.url(args, options),
    method: 'get',
})

leadInteractionTimeline.definition = {
    methods: ["get","head"],
    url: '/laboratory/leads/{lead}/interactions',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LaboratoryController::leadInteractionTimeline
 * @see app/Http/Controllers/LaboratoryController.php:231
 * @route '/laboratory/leads/{lead}/interactions'
 */
leadInteractionTimeline.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return leadInteractionTimeline.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LaboratoryController::leadInteractionTimeline
 * @see app/Http/Controllers/LaboratoryController.php:231
 * @route '/laboratory/leads/{lead}/interactions'
 */
leadInteractionTimeline.get = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: leadInteractionTimeline.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\LaboratoryController::leadInteractionTimeline
 * @see app/Http/Controllers/LaboratoryController.php:231
 * @route '/laboratory/leads/{lead}/interactions'
 */
leadInteractionTimeline.head = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: leadInteractionTimeline.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\LaboratoryController::leadInteractionTimeline
 * @see app/Http/Controllers/LaboratoryController.php:231
 * @route '/laboratory/leads/{lead}/interactions'
 */
    const leadInteractionTimelineForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: leadInteractionTimeline.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\LaboratoryController::leadInteractionTimeline
 * @see app/Http/Controllers/LaboratoryController.php:231
 * @route '/laboratory/leads/{lead}/interactions'
 */
        leadInteractionTimelineForm.get = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: leadInteractionTimeline.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\LaboratoryController::leadInteractionTimeline
 * @see app/Http/Controllers/LaboratoryController.php:231
 * @route '/laboratory/leads/{lead}/interactions'
 */
        leadInteractionTimelineForm.head = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: leadInteractionTimeline.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    leadInteractionTimeline.form = leadInteractionTimelineForm
const LaboratoryController = { index, datasets, stressTest, stressTestResults, aiUsage, health, interactionTimeline, leadInteractionTimeline }

export default LaboratoryController