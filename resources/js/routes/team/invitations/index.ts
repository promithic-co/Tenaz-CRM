import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\TeamController::store
 * @see app/Http/Controllers/Settings/TeamController.php:61
 * @route '/settings/team/invitations'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/settings/team/invitations',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\TeamController::store
 * @see app/Http/Controllers/Settings/TeamController.php:61
 * @route '/settings/team/invitations'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\TeamController::store
 * @see app/Http/Controllers/Settings/TeamController.php:61
 * @route '/settings/team/invitations'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Settings\TeamController::store
 * @see app/Http/Controllers/Settings/TeamController.php:61
 * @route '/settings/team/invitations'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Settings\TeamController::store
 * @see app/Http/Controllers/Settings/TeamController.php:61
 * @route '/settings/team/invitations'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Settings\TeamController::destroy
 * @see app/Http/Controllers/Settings/TeamController.php:89
 * @route '/settings/team/invitations/{invitation}'
 */
export const destroy = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/settings/team/invitations/{invitation}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Settings\TeamController::destroy
 * @see app/Http/Controllers/Settings/TeamController.php:89
 * @route '/settings/team/invitations/{invitation}'
 */
destroy.url = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { invitation: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { invitation: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    invitation: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        invitation: typeof args.invitation === 'object'
                ? args.invitation.id
                : args.invitation,
                }

    return destroy.definition.url
            .replace('{invitation}', parsedArgs.invitation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\TeamController::destroy
 * @see app/Http/Controllers/Settings/TeamController.php:89
 * @route '/settings/team/invitations/{invitation}'
 */
destroy.delete = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Settings\TeamController::destroy
 * @see app/Http/Controllers/Settings/TeamController.php:89
 * @route '/settings/team/invitations/{invitation}'
 */
    const destroyForm = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Settings\TeamController::destroy
 * @see app/Http/Controllers/Settings/TeamController.php:89
 * @route '/settings/team/invitations/{invitation}'
 */
        destroyForm.delete = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const invitations = {
    store: Object.assign(store, store),
destroy: Object.assign(destroy, destroy),
}

export default invitations