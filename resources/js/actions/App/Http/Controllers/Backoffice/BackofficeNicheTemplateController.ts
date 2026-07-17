import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Backoffice\BackofficeNicheTemplateController::index
 * @see app/Http/Controllers/Backoffice/BackofficeNicheTemplateController.php:19
 * @route '/backoffice/modelos'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/backoffice/modelos',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Backoffice\BackofficeNicheTemplateController::index
 * @see app/Http/Controllers/Backoffice/BackofficeNicheTemplateController.php:19
 * @route '/backoffice/modelos'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Backoffice\BackofficeNicheTemplateController::index
 * @see app/Http/Controllers/Backoffice/BackofficeNicheTemplateController.php:19
 * @route '/backoffice/modelos'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Backoffice\BackofficeNicheTemplateController::index
 * @see app/Http/Controllers/Backoffice/BackofficeNicheTemplateController.php:19
 * @route '/backoffice/modelos'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Backoffice\BackofficeNicheTemplateController::index
 * @see app/Http/Controllers/Backoffice/BackofficeNicheTemplateController.php:19
 * @route '/backoffice/modelos'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Backoffice\BackofficeNicheTemplateController::index
 * @see app/Http/Controllers/Backoffice/BackofficeNicheTemplateController.php:19
 * @route '/backoffice/modelos'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Backoffice\BackofficeNicheTemplateController::index
 * @see app/Http/Controllers/Backoffice/BackofficeNicheTemplateController.php:19
 * @route '/backoffice/modelos'
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
* @see \App\Http\Controllers\Backoffice\BackofficeNicheTemplateController::update
 * @see app/Http/Controllers/Backoffice/BackofficeNicheTemplateController.php:32
 * @route '/backoffice/modelos/{nicheTemplate}'
 */
export const update = (args: { nicheTemplate: number | { id: number } } | [nicheTemplate: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/backoffice/modelos/{nicheTemplate}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Backoffice\BackofficeNicheTemplateController::update
 * @see app/Http/Controllers/Backoffice/BackofficeNicheTemplateController.php:32
 * @route '/backoffice/modelos/{nicheTemplate}'
 */
update.url = (args: { nicheTemplate: number | { id: number } } | [nicheTemplate: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { nicheTemplate: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { nicheTemplate: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    nicheTemplate: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        nicheTemplate: typeof args.nicheTemplate === 'object'
                ? args.nicheTemplate.id
                : args.nicheTemplate,
                }

    return update.definition.url
            .replace('{nicheTemplate}', parsedArgs.nicheTemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Backoffice\BackofficeNicheTemplateController::update
 * @see app/Http/Controllers/Backoffice/BackofficeNicheTemplateController.php:32
 * @route '/backoffice/modelos/{nicheTemplate}'
 */
update.put = (args: { nicheTemplate: number | { id: number } } | [nicheTemplate: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})
/**
* @see \App\Http\Controllers\Backoffice\BackofficeNicheTemplateController::update
 * @see app/Http/Controllers/Backoffice/BackofficeNicheTemplateController.php:32
 * @route '/backoffice/modelos/{nicheTemplate}'
 */
update.patch = (args: { nicheTemplate: number | { id: number } } | [nicheTemplate: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Backoffice\BackofficeNicheTemplateController::update
 * @see app/Http/Controllers/Backoffice/BackofficeNicheTemplateController.php:32
 * @route '/backoffice/modelos/{nicheTemplate}'
 */
    const updateForm = (args: { nicheTemplate: number | { id: number } } | [nicheTemplate: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Backoffice\BackofficeNicheTemplateController::update
 * @see app/Http/Controllers/Backoffice/BackofficeNicheTemplateController.php:32
 * @route '/backoffice/modelos/{nicheTemplate}'
 */
        updateForm.put = (args: { nicheTemplate: number | { id: number } } | [nicheTemplate: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \App\Http\Controllers\Backoffice\BackofficeNicheTemplateController::update
 * @see app/Http/Controllers/Backoffice/BackofficeNicheTemplateController.php:32
 * @route '/backoffice/modelos/{nicheTemplate}'
 */
        updateForm.patch = (args: { nicheTemplate: number | { id: number } } | [nicheTemplate: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
const BackofficeNicheTemplateController = { index, update }

export default BackofficeNicheTemplateController