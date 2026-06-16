import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\ContactListEntryController::store
 * @see app/Http/Controllers/ContactListEntryController.php:16
 * @route '/listas-contato/{list}/entries'
 */
export const store = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/listas-contato/{list}/entries',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ContactListEntryController::store
 * @see app/Http/Controllers/ContactListEntryController.php:16
 * @route '/listas-contato/{list}/entries'
 */
store.url = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { list: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { list: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    list: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        list: typeof args.list === 'object'
                ? args.list.id
                : args.list,
                }

    return store.definition.url
            .replace('{list}', parsedArgs.list.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ContactListEntryController::store
 * @see app/Http/Controllers/ContactListEntryController.php:16
 * @route '/listas-contato/{list}/entries'
 */
store.post = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ContactListEntryController::store
 * @see app/Http/Controllers/ContactListEntryController.php:16
 * @route '/listas-contato/{list}/entries'
 */
    const storeForm = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ContactListEntryController::store
 * @see app/Http/Controllers/ContactListEntryController.php:16
 * @route '/listas-contato/{list}/entries'
 */
        storeForm.post = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\ContactListEntryController::destroy
 * @see app/Http/Controllers/ContactListEntryController.php:32
 * @route '/entries/{entry}'
 */
export const destroy = (args: { entry: number | { id: number } } | [entry: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/entries/{entry}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\ContactListEntryController::destroy
 * @see app/Http/Controllers/ContactListEntryController.php:32
 * @route '/entries/{entry}'
 */
destroy.url = (args: { entry: number | { id: number } } | [entry: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { entry: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { entry: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    entry: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entry: typeof args.entry === 'object'
                ? args.entry.id
                : args.entry,
                }

    return destroy.definition.url
            .replace('{entry}', parsedArgs.entry.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ContactListEntryController::destroy
 * @see app/Http/Controllers/ContactListEntryController.php:32
 * @route '/entries/{entry}'
 */
destroy.delete = (args: { entry: number | { id: number } } | [entry: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\ContactListEntryController::destroy
 * @see app/Http/Controllers/ContactListEntryController.php:32
 * @route '/entries/{entry}'
 */
    const destroyForm = (args: { entry: number | { id: number } } | [entry: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ContactListEntryController::destroy
 * @see app/Http/Controllers/ContactListEntryController.php:32
 * @route '/entries/{entry}'
 */
        destroyForm.delete = (args: { entry: number | { id: number } } | [entry: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const ContactListEntryController = { store, destroy }

export default ContactListEntryController