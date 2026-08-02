import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\CustomFieldController::index
 * @see app/Http/Controllers/CustomFieldController.php:28
 * @route '/settings/campos'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/settings/campos',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CustomFieldController::index
 * @see app/Http/Controllers/CustomFieldController.php:28
 * @route '/settings/campos'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CustomFieldController::index
 * @see app/Http/Controllers/CustomFieldController.php:28
 * @route '/settings/campos'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\CustomFieldController::index
 * @see app/Http/Controllers/CustomFieldController.php:28
 * @route '/settings/campos'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\CustomFieldController::index
 * @see app/Http/Controllers/CustomFieldController.php:28
 * @route '/settings/campos'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\CustomFieldController::index
 * @see app/Http/Controllers/CustomFieldController.php:28
 * @route '/settings/campos'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\CustomFieldController::index
 * @see app/Http/Controllers/CustomFieldController.php:28
 * @route '/settings/campos'
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
* @see \App\Http\Controllers\CustomFieldController::store
 * @see app/Http/Controllers/CustomFieldController.php:37
 * @route '/settings/campos'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/settings/campos',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CustomFieldController::store
 * @see app/Http/Controllers/CustomFieldController.php:37
 * @route '/settings/campos'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CustomFieldController::store
 * @see app/Http/Controllers/CustomFieldController.php:37
 * @route '/settings/campos'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CustomFieldController::store
 * @see app/Http/Controllers/CustomFieldController.php:37
 * @route '/settings/campos'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CustomFieldController::store
 * @see app/Http/Controllers/CustomFieldController.php:37
 * @route '/settings/campos'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\CustomFieldController::reorder
 * @see app/Http/Controllers/CustomFieldController.php:62
 * @route '/settings/campos/reorder'
 */
export const reorder = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reorder.url(options),
    method: 'post',
})

reorder.definition = {
    methods: ["post"],
    url: '/settings/campos/reorder',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CustomFieldController::reorder
 * @see app/Http/Controllers/CustomFieldController.php:62
 * @route '/settings/campos/reorder'
 */
reorder.url = (options?: RouteQueryOptions) => {
    return reorder.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CustomFieldController::reorder
 * @see app/Http/Controllers/CustomFieldController.php:62
 * @route '/settings/campos/reorder'
 */
reorder.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reorder.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CustomFieldController::reorder
 * @see app/Http/Controllers/CustomFieldController.php:62
 * @route '/settings/campos/reorder'
 */
    const reorderForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: reorder.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CustomFieldController::reorder
 * @see app/Http/Controllers/CustomFieldController.php:62
 * @route '/settings/campos/reorder'
 */
        reorderForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: reorder.url(options),
            method: 'post',
        })
    
    reorder.form = reorderForm
/**
* @see \App\Http\Controllers\CustomFieldController::update
 * @see app/Http/Controllers/CustomFieldController.php:44
 * @route '/settings/campos/{customField}'
 */
export const update = (args: { customField: number | { id: number } } | [customField: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/settings/campos/{customField}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\CustomFieldController::update
 * @see app/Http/Controllers/CustomFieldController.php:44
 * @route '/settings/campos/{customField}'
 */
update.url = (args: { customField: number | { id: number } } | [customField: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { customField: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { customField: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    customField: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        customField: typeof args.customField === 'object'
                ? args.customField.id
                : args.customField,
                }

    return update.definition.url
            .replace('{customField}', parsedArgs.customField.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CustomFieldController::update
 * @see app/Http/Controllers/CustomFieldController.php:44
 * @route '/settings/campos/{customField}'
 */
update.patch = (args: { customField: number | { id: number } } | [customField: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\CustomFieldController::update
 * @see app/Http/Controllers/CustomFieldController.php:44
 * @route '/settings/campos/{customField}'
 */
    const updateForm = (args: { customField: number | { id: number } } | [customField: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CustomFieldController::update
 * @see app/Http/Controllers/CustomFieldController.php:44
 * @route '/settings/campos/{customField}'
 */
        updateForm.patch = (args: { customField: number | { id: number } } | [customField: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\CustomFieldController::destroy
 * @see app/Http/Controllers/CustomFieldController.php:53
 * @route '/settings/campos/{customField}'
 */
export const destroy = (args: { customField: number | { id: number } } | [customField: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/settings/campos/{customField}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\CustomFieldController::destroy
 * @see app/Http/Controllers/CustomFieldController.php:53
 * @route '/settings/campos/{customField}'
 */
destroy.url = (args: { customField: number | { id: number } } | [customField: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { customField: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { customField: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    customField: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        customField: typeof args.customField === 'object'
                ? args.customField.id
                : args.customField,
                }

    return destroy.definition.url
            .replace('{customField}', parsedArgs.customField.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CustomFieldController::destroy
 * @see app/Http/Controllers/CustomFieldController.php:53
 * @route '/settings/campos/{customField}'
 */
destroy.delete = (args: { customField: number | { id: number } } | [customField: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\CustomFieldController::destroy
 * @see app/Http/Controllers/CustomFieldController.php:53
 * @route '/settings/campos/{customField}'
 */
    const destroyForm = (args: { customField: number | { id: number } } | [customField: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CustomFieldController::destroy
 * @see app/Http/Controllers/CustomFieldController.php:53
 * @route '/settings/campos/{customField}'
 */
        destroyForm.delete = (args: { customField: number | { id: number } } | [customField: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const campos = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
reorder: Object.assign(reorder, reorder),
update: Object.assign(update, update),
destroy: Object.assign(destroy, destroy),
}

export default campos