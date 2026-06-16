import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\VoiceInstanceController::index
 * @see app/Http/Controllers/VoiceInstanceController.php:14
 * @route '/voz'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/voz',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\VoiceInstanceController::index
 * @see app/Http/Controllers/VoiceInstanceController.php:14
 * @route '/voz'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\VoiceInstanceController::index
 * @see app/Http/Controllers/VoiceInstanceController.php:14
 * @route '/voz'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\VoiceInstanceController::index
 * @see app/Http/Controllers/VoiceInstanceController.php:14
 * @route '/voz'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\VoiceInstanceController::index
 * @see app/Http/Controllers/VoiceInstanceController.php:14
 * @route '/voz'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\VoiceInstanceController::index
 * @see app/Http/Controllers/VoiceInstanceController.php:14
 * @route '/voz'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\VoiceInstanceController::index
 * @see app/Http/Controllers/VoiceInstanceController.php:14
 * @route '/voz'
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
* @see \App\Http\Controllers\VoiceInstanceController::store
 * @see app/Http/Controllers/VoiceInstanceController.php:33
 * @route '/voz'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/voz',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\VoiceInstanceController::store
 * @see app/Http/Controllers/VoiceInstanceController.php:33
 * @route '/voz'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\VoiceInstanceController::store
 * @see app/Http/Controllers/VoiceInstanceController.php:33
 * @route '/voz'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\VoiceInstanceController::store
 * @see app/Http/Controllers/VoiceInstanceController.php:33
 * @route '/voz'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\VoiceInstanceController::store
 * @see app/Http/Controllers/VoiceInstanceController.php:33
 * @route '/voz'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\VoiceInstanceController::update
 * @see app/Http/Controllers/VoiceInstanceController.php:44
 * @route '/voz/{voiceInstance}'
 */
export const update = (args: { voiceInstance: number | { id: number } } | [voiceInstance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/voz/{voiceInstance}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\VoiceInstanceController::update
 * @see app/Http/Controllers/VoiceInstanceController.php:44
 * @route '/voz/{voiceInstance}'
 */
update.url = (args: { voiceInstance: number | { id: number } } | [voiceInstance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { voiceInstance: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { voiceInstance: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    voiceInstance: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        voiceInstance: typeof args.voiceInstance === 'object'
                ? args.voiceInstance.id
                : args.voiceInstance,
                }

    return update.definition.url
            .replace('{voiceInstance}', parsedArgs.voiceInstance.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\VoiceInstanceController::update
 * @see app/Http/Controllers/VoiceInstanceController.php:44
 * @route '/voz/{voiceInstance}'
 */
update.put = (args: { voiceInstance: number | { id: number } } | [voiceInstance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\VoiceInstanceController::update
 * @see app/Http/Controllers/VoiceInstanceController.php:44
 * @route '/voz/{voiceInstance}'
 */
    const updateForm = (args: { voiceInstance: number | { id: number } } | [voiceInstance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\VoiceInstanceController::update
 * @see app/Http/Controllers/VoiceInstanceController.php:44
 * @route '/voz/{voiceInstance}'
 */
        updateForm.put = (args: { voiceInstance: number | { id: number } } | [voiceInstance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \App\Http\Controllers\VoiceInstanceController::destroy
 * @see app/Http/Controllers/VoiceInstanceController.php:53
 * @route '/voz/{voiceInstance}'
 */
export const destroy = (args: { voiceInstance: number | { id: number } } | [voiceInstance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/voz/{voiceInstance}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\VoiceInstanceController::destroy
 * @see app/Http/Controllers/VoiceInstanceController.php:53
 * @route '/voz/{voiceInstance}'
 */
destroy.url = (args: { voiceInstance: number | { id: number } } | [voiceInstance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { voiceInstance: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { voiceInstance: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    voiceInstance: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        voiceInstance: typeof args.voiceInstance === 'object'
                ? args.voiceInstance.id
                : args.voiceInstance,
                }

    return destroy.definition.url
            .replace('{voiceInstance}', parsedArgs.voiceInstance.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\VoiceInstanceController::destroy
 * @see app/Http/Controllers/VoiceInstanceController.php:53
 * @route '/voz/{voiceInstance}'
 */
destroy.delete = (args: { voiceInstance: number | { id: number } } | [voiceInstance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\VoiceInstanceController::destroy
 * @see app/Http/Controllers/VoiceInstanceController.php:53
 * @route '/voz/{voiceInstance}'
 */
    const destroyForm = (args: { voiceInstance: number | { id: number } } | [voiceInstance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\VoiceInstanceController::destroy
 * @see app/Http/Controllers/VoiceInstanceController.php:53
 * @route '/voz/{voiceInstance}'
 */
        destroyForm.delete = (args: { voiceInstance: number | { id: number } } | [voiceInstance: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const VoiceInstanceController = { index, store, update, destroy }

export default VoiceInstanceController