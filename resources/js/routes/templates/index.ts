import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\WhatsappTemplateController::index
 * @see app/Http/Controllers/WhatsappTemplateController.php:24
 * @route '/templates'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/templates',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\WhatsappTemplateController::index
 * @see app/Http/Controllers/WhatsappTemplateController.php:24
 * @route '/templates'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\WhatsappTemplateController::index
 * @see app/Http/Controllers/WhatsappTemplateController.php:24
 * @route '/templates'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\WhatsappTemplateController::index
 * @see app/Http/Controllers/WhatsappTemplateController.php:24
 * @route '/templates'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\WhatsappTemplateController::store
 * @see app/Http/Controllers/WhatsappTemplateController.php:60
 * @route '/templates'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/templates',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\WhatsappTemplateController::store
 * @see app/Http/Controllers/WhatsappTemplateController.php:60
 * @route '/templates'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\WhatsappTemplateController::store
 * @see app/Http/Controllers/WhatsappTemplateController.php:60
 * @route '/templates'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\WhatsappTemplateController::update
 * @see app/Http/Controllers/WhatsappTemplateController.php:153
 * @route '/templates/{template}'
 */
export const update = (args: { template: number | { id: number } } | [template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/templates/{template}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\WhatsappTemplateController::update
 * @see app/Http/Controllers/WhatsappTemplateController.php:153
 * @route '/templates/{template}'
 */
update.url = (args: { template: number | { id: number } } | [template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { template: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { template: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    template: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        template: typeof args.template === 'object'
                ? args.template.id
                : args.template,
                }

    return update.definition.url
            .replace('{template}', parsedArgs.template.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\WhatsappTemplateController::update
 * @see app/Http/Controllers/WhatsappTemplateController.php:153
 * @route '/templates/{template}'
 */
update.put = (args: { template: number | { id: number } } | [template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})
/**
* @see \App\Http\Controllers\WhatsappTemplateController::update
 * @see app/Http/Controllers/WhatsappTemplateController.php:153
 * @route '/templates/{template}'
 */
update.patch = (args: { template: number | { id: number } } | [template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\WhatsappTemplateController::destroy
 * @see app/Http/Controllers/WhatsappTemplateController.php:179
 * @route '/templates/{template}'
 */
export const destroy = (args: { template: number | { id: number } } | [template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/templates/{template}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\WhatsappTemplateController::destroy
 * @see app/Http/Controllers/WhatsappTemplateController.php:179
 * @route '/templates/{template}'
 */
destroy.url = (args: { template: number | { id: number } } | [template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { template: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { template: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    template: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        template: typeof args.template === 'object'
                ? args.template.id
                : args.template,
                }

    return destroy.definition.url
            .replace('{template}', parsedArgs.template.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\WhatsappTemplateController::destroy
 * @see app/Http/Controllers/WhatsappTemplateController.php:179
 * @route '/templates/{template}'
 */
destroy.delete = (args: { template: number | { id: number } } | [template: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\WhatsappTemplateController::syncMeta
 * @see app/Http/Controllers/WhatsappTemplateController.php:163
 * @route '/templates/sync-meta'
 */
export const syncMeta = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: syncMeta.url(options),
    method: 'post',
})

syncMeta.definition = {
    methods: ["post"],
    url: '/templates/sync-meta',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\WhatsappTemplateController::syncMeta
 * @see app/Http/Controllers/WhatsappTemplateController.php:163
 * @route '/templates/sync-meta'
 */
syncMeta.url = (options?: RouteQueryOptions) => {
    return syncMeta.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\WhatsappTemplateController::syncMeta
 * @see app/Http/Controllers/WhatsappTemplateController.php:163
 * @route '/templates/sync-meta'
 */
syncMeta.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: syncMeta.url(options),
    method: 'post',
})
const templates = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
update: Object.assign(update, update),
destroy: Object.assign(destroy, destroy),
syncMeta: Object.assign(syncMeta, syncMeta),
}

export default templates