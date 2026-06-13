import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\TeamController::index
 * @see app/Http/Controllers/Settings/TeamController.php:22
 * @route '/settings/team'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/settings/team',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\TeamController::index
 * @see app/Http/Controllers/Settings/TeamController.php:22
 * @route '/settings/team'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\TeamController::index
 * @see app/Http/Controllers/Settings/TeamController.php:22
 * @route '/settings/team'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Settings\TeamController::index
 * @see app/Http/Controllers/Settings/TeamController.php:22
 * @route '/settings/team'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Settings\TeamController::index
 * @see app/Http/Controllers/Settings/TeamController.php:22
 * @route '/settings/team'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Settings\TeamController::index
 * @see app/Http/Controllers/Settings/TeamController.php:22
 * @route '/settings/team'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Settings\TeamController::index
 * @see app/Http/Controllers/Settings/TeamController.php:22
 * @route '/settings/team'
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
* @see \App\Http\Controllers\Settings\TeamController::inviteStore
 * @see app/Http/Controllers/Settings/TeamController.php:61
 * @route '/settings/team/invitations'
 */
export const inviteStore = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: inviteStore.url(options),
    method: 'post',
})

inviteStore.definition = {
    methods: ["post"],
    url: '/settings/team/invitations',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\TeamController::inviteStore
 * @see app/Http/Controllers/Settings/TeamController.php:61
 * @route '/settings/team/invitations'
 */
inviteStore.url = (options?: RouteQueryOptions) => {
    return inviteStore.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\TeamController::inviteStore
 * @see app/Http/Controllers/Settings/TeamController.php:61
 * @route '/settings/team/invitations'
 */
inviteStore.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: inviteStore.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Settings\TeamController::inviteStore
 * @see app/Http/Controllers/Settings/TeamController.php:61
 * @route '/settings/team/invitations'
 */
    const inviteStoreForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: inviteStore.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Settings\TeamController::inviteStore
 * @see app/Http/Controllers/Settings/TeamController.php:61
 * @route '/settings/team/invitations'
 */
        inviteStoreForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: inviteStore.url(options),
            method: 'post',
        })
    
    inviteStore.form = inviteStoreForm
/**
* @see \App\Http\Controllers\Settings\TeamController::inviteDestroy
 * @see app/Http/Controllers/Settings/TeamController.php:89
 * @route '/settings/team/invitations/{invitation}'
 */
export const inviteDestroy = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: inviteDestroy.url(args, options),
    method: 'delete',
})

inviteDestroy.definition = {
    methods: ["delete"],
    url: '/settings/team/invitations/{invitation}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Settings\TeamController::inviteDestroy
 * @see app/Http/Controllers/Settings/TeamController.php:89
 * @route '/settings/team/invitations/{invitation}'
 */
inviteDestroy.url = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return inviteDestroy.definition.url
            .replace('{invitation}', parsedArgs.invitation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\TeamController::inviteDestroy
 * @see app/Http/Controllers/Settings/TeamController.php:89
 * @route '/settings/team/invitations/{invitation}'
 */
inviteDestroy.delete = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: inviteDestroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Settings\TeamController::inviteDestroy
 * @see app/Http/Controllers/Settings/TeamController.php:89
 * @route '/settings/team/invitations/{invitation}'
 */
    const inviteDestroyForm = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: inviteDestroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Settings\TeamController::inviteDestroy
 * @see app/Http/Controllers/Settings/TeamController.php:89
 * @route '/settings/team/invitations/{invitation}'
 */
        inviteDestroyForm.delete = (args: { invitation: number | { id: number } } | [invitation: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: inviteDestroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    inviteDestroy.form = inviteDestroyForm
/**
* @see \App\Http\Controllers\Settings\TeamController::memberUpdate
 * @see app/Http/Controllers/Settings/TeamController.php:97
 * @route '/settings/team/members/{user}'
 */
export const memberUpdate = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: memberUpdate.url(args, options),
    method: 'patch',
})

memberUpdate.definition = {
    methods: ["patch"],
    url: '/settings/team/members/{user}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Settings\TeamController::memberUpdate
 * @see app/Http/Controllers/Settings/TeamController.php:97
 * @route '/settings/team/members/{user}'
 */
memberUpdate.url = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { user: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    user: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        user: typeof args.user === 'object'
                ? args.user.id
                : args.user,
                }

    return memberUpdate.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\TeamController::memberUpdate
 * @see app/Http/Controllers/Settings/TeamController.php:97
 * @route '/settings/team/members/{user}'
 */
memberUpdate.patch = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: memberUpdate.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Settings\TeamController::memberUpdate
 * @see app/Http/Controllers/Settings/TeamController.php:97
 * @route '/settings/team/members/{user}'
 */
    const memberUpdateForm = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: memberUpdate.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Settings\TeamController::memberUpdate
 * @see app/Http/Controllers/Settings/TeamController.php:97
 * @route '/settings/team/members/{user}'
 */
        memberUpdateForm.patch = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: memberUpdate.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    memberUpdate.form = memberUpdateForm
/**
* @see \App\Http\Controllers\Settings\TeamController::memberDestroy
 * @see app/Http/Controllers/Settings/TeamController.php:119
 * @route '/settings/team/members/{user}'
 */
export const memberDestroy = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: memberDestroy.url(args, options),
    method: 'delete',
})

memberDestroy.definition = {
    methods: ["delete"],
    url: '/settings/team/members/{user}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Settings\TeamController::memberDestroy
 * @see app/Http/Controllers/Settings/TeamController.php:119
 * @route '/settings/team/members/{user}'
 */
memberDestroy.url = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { user: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    user: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        user: typeof args.user === 'object'
                ? args.user.id
                : args.user,
                }

    return memberDestroy.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\TeamController::memberDestroy
 * @see app/Http/Controllers/Settings/TeamController.php:119
 * @route '/settings/team/members/{user}'
 */
memberDestroy.delete = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: memberDestroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Settings\TeamController::memberDestroy
 * @see app/Http/Controllers/Settings/TeamController.php:119
 * @route '/settings/team/members/{user}'
 */
    const memberDestroyForm = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: memberDestroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Settings\TeamController::memberDestroy
 * @see app/Http/Controllers/Settings/TeamController.php:119
 * @route '/settings/team/members/{user}'
 */
        memberDestroyForm.delete = (args: { user: number | { id: number } } | [user: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: memberDestroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    memberDestroy.form = memberDestroyForm
const TeamController = { index, inviteStore, inviteDestroy, memberUpdate, memberDestroy }

export default TeamController