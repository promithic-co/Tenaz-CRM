import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
import datasetsD3c146 from './datasets'
import stressTest2dc68a from './stress-test'
import interactions from './interactions'
import leads from './leads'
import stressTests from './stress-tests'
/**
* @see \App\Http\Controllers\LaboratoryController::datasets
 * @see app/Http/Controllers/LaboratoryController.php:61
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
 * @see app/Http/Controllers/LaboratoryController.php:61
 * @route '/laboratory/datasets-page'
 */
datasets.url = (options?: RouteQueryOptions) => {
    return datasets.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LaboratoryController::datasets
 * @see app/Http/Controllers/LaboratoryController.php:61
 * @route '/laboratory/datasets-page'
 */
datasets.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: datasets.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\LaboratoryController::datasets
 * @see app/Http/Controllers/LaboratoryController.php:61
 * @route '/laboratory/datasets-page'
 */
datasets.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: datasets.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\LaboratoryController::datasets
 * @see app/Http/Controllers/LaboratoryController.php:61
 * @route '/laboratory/datasets-page'
 */
    const datasetsForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: datasets.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\LaboratoryController::datasets
 * @see app/Http/Controllers/LaboratoryController.php:61
 * @route '/laboratory/datasets-page'
 */
        datasetsForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: datasets.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\LaboratoryController::datasets
 * @see app/Http/Controllers/LaboratoryController.php:61
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
 * @see app/Http/Controllers/LaboratoryController.php:81
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
 * @see app/Http/Controllers/LaboratoryController.php:81
 * @route '/laboratory/stress-test'
 */
stressTest.url = (options?: RouteQueryOptions) => {
    return stressTest.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LaboratoryController::stressTest
 * @see app/Http/Controllers/LaboratoryController.php:81
 * @route '/laboratory/stress-test'
 */
stressTest.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: stressTest.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\LaboratoryController::stressTest
 * @see app/Http/Controllers/LaboratoryController.php:81
 * @route '/laboratory/stress-test'
 */
stressTest.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: stressTest.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\LaboratoryController::stressTest
 * @see app/Http/Controllers/LaboratoryController.php:81
 * @route '/laboratory/stress-test'
 */
    const stressTestForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: stressTest.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\LaboratoryController::stressTest
 * @see app/Http/Controllers/LaboratoryController.php:81
 * @route '/laboratory/stress-test'
 */
        stressTestForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: stressTest.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\LaboratoryController::stressTest
 * @see app/Http/Controllers/LaboratoryController.php:81
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
* @see \App\Http\Controllers\LaboratoryController::aiUsage
 * @see app/Http/Controllers/LaboratoryController.php:164
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
 * @see app/Http/Controllers/LaboratoryController.php:164
 * @route '/laboratory/ai-usage'
 */
aiUsage.url = (options?: RouteQueryOptions) => {
    return aiUsage.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LaboratoryController::aiUsage
 * @see app/Http/Controllers/LaboratoryController.php:164
 * @route '/laboratory/ai-usage'
 */
aiUsage.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: aiUsage.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\LaboratoryController::aiUsage
 * @see app/Http/Controllers/LaboratoryController.php:164
 * @route '/laboratory/ai-usage'
 */
aiUsage.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: aiUsage.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\LaboratoryController::aiUsage
 * @see app/Http/Controllers/LaboratoryController.php:164
 * @route '/laboratory/ai-usage'
 */
    const aiUsageForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: aiUsage.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\LaboratoryController::aiUsage
 * @see app/Http/Controllers/LaboratoryController.php:164
 * @route '/laboratory/ai-usage'
 */
        aiUsageForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: aiUsage.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\LaboratoryController::aiUsage
 * @see app/Http/Controllers/LaboratoryController.php:164
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
 * @see app/Http/Controllers/LaboratoryController.php:254
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
 * @see app/Http/Controllers/LaboratoryController.php:254
 * @route '/laboratory/health'
 */
health.url = (options?: RouteQueryOptions) => {
    return health.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LaboratoryController::health
 * @see app/Http/Controllers/LaboratoryController.php:254
 * @route '/laboratory/health'
 */
health.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: health.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\LaboratoryController::health
 * @see app/Http/Controllers/LaboratoryController.php:254
 * @route '/laboratory/health'
 */
health.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: health.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\LaboratoryController::health
 * @see app/Http/Controllers/LaboratoryController.php:254
 * @route '/laboratory/health'
 */
    const healthForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: health.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\LaboratoryController::health
 * @see app/Http/Controllers/LaboratoryController.php:254
 * @route '/laboratory/health'
 */
        healthForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: health.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\LaboratoryController::health
 * @see app/Http/Controllers/LaboratoryController.php:254
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
const laboratory = {
    datasets: Object.assign(datasets, datasetsD3c146),
stressTest: Object.assign(stressTest, stressTest2dc68a),
aiUsage: Object.assign(aiUsage, aiUsage),
health: Object.assign(health, health),
interactions: Object.assign(interactions, interactions),
leads: Object.assign(leads, leads),
stressTests: Object.assign(stressTests, stressTests),
}

export default laboratory