import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
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
const entries = {
    destroy: Object.assign(destroy, destroy),
}

export default entries