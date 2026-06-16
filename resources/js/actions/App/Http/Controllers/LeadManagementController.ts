import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\LeadManagementController::store
 * @see app/Http/Controllers/LeadManagementController.php:33
 * @route '/conversas'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/conversas',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LeadManagementController::store
 * @see app/Http/Controllers/LeadManagementController.php:33
 * @route '/conversas'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadManagementController::store
 * @see app/Http/Controllers/LeadManagementController.php:33
 * @route '/conversas'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\LeadManagementController::store
 * @see app/Http/Controllers/LeadManagementController.php:33
 * @route '/conversas'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\LeadManagementController::store
 * @see app/Http/Controllers/LeadManagementController.php:33
 * @route '/conversas'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\LeadManagementController::bulkAction
 * @see app/Http/Controllers/LeadManagementController.php:96
 * @route '/conversas/bulk-action'
 */
export const bulkAction = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkAction.url(options),
    method: 'post',
})

bulkAction.definition = {
    methods: ["post"],
    url: '/conversas/bulk-action',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LeadManagementController::bulkAction
 * @see app/Http/Controllers/LeadManagementController.php:96
 * @route '/conversas/bulk-action'
 */
bulkAction.url = (options?: RouteQueryOptions) => {
    return bulkAction.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadManagementController::bulkAction
 * @see app/Http/Controllers/LeadManagementController.php:96
 * @route '/conversas/bulk-action'
 */
bulkAction.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkAction.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\LeadManagementController::bulkAction
 * @see app/Http/Controllers/LeadManagementController.php:96
 * @route '/conversas/bulk-action'
 */
    const bulkActionForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: bulkAction.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\LeadManagementController::bulkAction
 * @see app/Http/Controllers/LeadManagementController.php:96
 * @route '/conversas/bulk-action'
 */
        bulkActionForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: bulkAction.url(options),
            method: 'post',
        })
    
    bulkAction.form = bulkActionForm
/**
* @see \App\Http\Controllers\LeadManagementController::destroy
 * @see app/Http/Controllers/LeadManagementController.php:58
 * @route '/conversas/{lead}'
 */
export const destroy = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/conversas/{lead}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\LeadManagementController::destroy
 * @see app/Http/Controllers/LeadManagementController.php:58
 * @route '/conversas/{lead}'
 */
destroy.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadManagementController::destroy
 * @see app/Http/Controllers/LeadManagementController.php:58
 * @route '/conversas/{lead}'
 */
destroy.delete = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\LeadManagementController::destroy
 * @see app/Http/Controllers/LeadManagementController.php:58
 * @route '/conversas/{lead}'
 */
    const destroyForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\LeadManagementController::destroy
 * @see app/Http/Controllers/LeadManagementController.php:58
 * @route '/conversas/{lead}'
 */
        destroyForm.delete = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
/**
* @see \App\Http\Controllers\LeadManagementController::prepareCampaign
 * @see app/Http/Controllers/LeadManagementController.php:138
 * @route '/conversas/{lead}/prepare-campaign'
 */
export const prepareCampaign = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: prepareCampaign.url(args, options),
    method: 'post',
})

prepareCampaign.definition = {
    methods: ["post"],
    url: '/conversas/{lead}/prepare-campaign',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LeadManagementController::prepareCampaign
 * @see app/Http/Controllers/LeadManagementController.php:138
 * @route '/conversas/{lead}/prepare-campaign'
 */
prepareCampaign.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return prepareCampaign.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadManagementController::prepareCampaign
 * @see app/Http/Controllers/LeadManagementController.php:138
 * @route '/conversas/{lead}/prepare-campaign'
 */
prepareCampaign.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: prepareCampaign.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\LeadManagementController::prepareCampaign
 * @see app/Http/Controllers/LeadManagementController.php:138
 * @route '/conversas/{lead}/prepare-campaign'
 */
    const prepareCampaignForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: prepareCampaign.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\LeadManagementController::prepareCampaign
 * @see app/Http/Controllers/LeadManagementController.php:138
 * @route '/conversas/{lead}/prepare-campaign'
 */
        prepareCampaignForm.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: prepareCampaign.url(args, options),
            method: 'post',
        })
    
    prepareCampaign.form = prepareCampaignForm
/**
* @see \App\Http\Controllers\LeadManagementController::addToContacts
 * @see app/Http/Controllers/LeadManagementController.php:197
 * @route '/conversas/{lead}/add-to-contacts'
 */
export const addToContacts = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: addToContacts.url(args, options),
    method: 'post',
})

addToContacts.definition = {
    methods: ["post"],
    url: '/conversas/{lead}/add-to-contacts',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LeadManagementController::addToContacts
 * @see app/Http/Controllers/LeadManagementController.php:197
 * @route '/conversas/{lead}/add-to-contacts'
 */
addToContacts.url = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return addToContacts.definition.url
            .replace('{lead}', parsedArgs.lead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\LeadManagementController::addToContacts
 * @see app/Http/Controllers/LeadManagementController.php:197
 * @route '/conversas/{lead}/add-to-contacts'
 */
addToContacts.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: addToContacts.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\LeadManagementController::addToContacts
 * @see app/Http/Controllers/LeadManagementController.php:197
 * @route '/conversas/{lead}/add-to-contacts'
 */
    const addToContactsForm = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: addToContacts.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\LeadManagementController::addToContacts
 * @see app/Http/Controllers/LeadManagementController.php:197
 * @route '/conversas/{lead}/add-to-contacts'
 */
        addToContactsForm.post = (args: { lead: number | { id: number } } | [lead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: addToContacts.url(args, options),
            method: 'post',
        })
    
    addToContacts.form = addToContactsForm
const LeadManagementController = { store, bulkAction, destroy, prepareCampaign, addToContacts }

export default LeadManagementController