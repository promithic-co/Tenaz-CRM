import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\TagController::index
 * @see app/Http/Controllers/TagController.php:23
 * @route '/tags'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/tags',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TagController::index
 * @see app/Http/Controllers/TagController.php:23
 * @route '/tags'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TagController::index
 * @see app/Http/Controllers/TagController.php:23
 * @route '/tags'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\TagController::index
 * @see app/Http/Controllers/TagController.php:23
 * @route '/tags'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\TagController::index
 * @see app/Http/Controllers/TagController.php:23
 * @route '/tags'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\TagController::index
 * @see app/Http/Controllers/TagController.php:23
 * @route '/tags'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\TagController::index
 * @see app/Http/Controllers/TagController.php:23
 * @route '/tags'
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
* @see \App\Http\Controllers\TagController::store
 * @see app/Http/Controllers/TagController.php:50
 * @route '/tags'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/tags',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\TagController::store
 * @see app/Http/Controllers/TagController.php:50
 * @route '/tags'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\TagController::store
 * @see app/Http/Controllers/TagController.php:50
 * @route '/tags'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\TagController::store
 * @see app/Http/Controllers/TagController.php:50
 * @route '/tags'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\TagController::store
 * @see app/Http/Controllers/TagController.php:50
 * @route '/tags'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\TagController::update
 * @see app/Http/Controllers/TagController.php:87
 * @route '/tags/{tag}'
 */
export const update = (args: { tag: number | { id: number } } | [tag: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/tags/{tag}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\TagController::update
 * @see app/Http/Controllers/TagController.php:87
 * @route '/tags/{tag}'
 */
update.url = (args: { tag: number | { id: number } } | [tag: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { tag: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { tag: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    tag: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        tag: typeof args.tag === 'object'
                ? args.tag.id
                : args.tag,
                }

    return update.definition.url
            .replace('{tag}', parsedArgs.tag.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TagController::update
 * @see app/Http/Controllers/TagController.php:87
 * @route '/tags/{tag}'
 */
update.patch = (args: { tag: number | { id: number } } | [tag: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\TagController::update
 * @see app/Http/Controllers/TagController.php:87
 * @route '/tags/{tag}'
 */
    const updateForm = (args: { tag: number | { id: number } } | [tag: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\TagController::update
 * @see app/Http/Controllers/TagController.php:87
 * @route '/tags/{tag}'
 */
        updateForm.patch = (args: { tag: number | { id: number } } | [tag: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\TagController::destroy
 * @see app/Http/Controllers/TagController.php:123
 * @route '/tags/{tag}'
 */
export const destroy = (args: { tag: number | { id: number } } | [tag: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/tags/{tag}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\TagController::destroy
 * @see app/Http/Controllers/TagController.php:123
 * @route '/tags/{tag}'
 */
destroy.url = (args: { tag: number | { id: number } } | [tag: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { tag: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { tag: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    tag: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        tag: typeof args.tag === 'object'
                ? args.tag.id
                : args.tag,
                }

    return destroy.definition.url
            .replace('{tag}', parsedArgs.tag.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TagController::destroy
 * @see app/Http/Controllers/TagController.php:123
 * @route '/tags/{tag}'
 */
destroy.delete = (args: { tag: number | { id: number } } | [tag: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\TagController::destroy
 * @see app/Http/Controllers/TagController.php:123
 * @route '/tags/{tag}'
 */
    const destroyForm = (args: { tag: number | { id: number } } | [tag: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\TagController::destroy
 * @see app/Http/Controllers/TagController.php:123
 * @route '/tags/{tag}'
 */
        destroyForm.delete = (args: { tag: number | { id: number } } | [tag: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const TagController = { index, store, update, destroy }

export default TagController