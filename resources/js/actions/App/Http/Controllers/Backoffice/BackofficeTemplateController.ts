import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Backoffice\BackofficeTemplateController::index
 * @see app/Http/Controllers/Backoffice/BackofficeTemplateController.php:14
 * @route '/backoffice/templates'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/backoffice/templates',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Backoffice\BackofficeTemplateController::index
 * @see app/Http/Controllers/Backoffice/BackofficeTemplateController.php:14
 * @route '/backoffice/templates'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Backoffice\BackofficeTemplateController::index
 * @see app/Http/Controllers/Backoffice/BackofficeTemplateController.php:14
 * @route '/backoffice/templates'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Backoffice\BackofficeTemplateController::index
 * @see app/Http/Controllers/Backoffice/BackofficeTemplateController.php:14
 * @route '/backoffice/templates'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Backoffice\BackofficeTemplateController::edit
 * @see app/Http/Controllers/Backoffice/BackofficeTemplateController.php:28
 * @route '/backoffice/templates/{template_slug}/edit'
 */
export const edit = (args: { template_slug: string | number } | [template_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/backoffice/templates/{template_slug}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Backoffice\BackofficeTemplateController::edit
 * @see app/Http/Controllers/Backoffice/BackofficeTemplateController.php:28
 * @route '/backoffice/templates/{template_slug}/edit'
 */
edit.url = (args: { template_slug: string | number } | [template_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { template_slug: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    template_slug: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        template_slug: args.template_slug,
                }

    return edit.definition.url
            .replace('{template_slug}', parsedArgs.template_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Backoffice\BackofficeTemplateController::edit
 * @see app/Http/Controllers/Backoffice/BackofficeTemplateController.php:28
 * @route '/backoffice/templates/{template_slug}/edit'
 */
edit.get = (args: { template_slug: string | number } | [template_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Backoffice\BackofficeTemplateController::edit
 * @see app/Http/Controllers/Backoffice/BackofficeTemplateController.php:28
 * @route '/backoffice/templates/{template_slug}/edit'
 */
edit.head = (args: { template_slug: string | number } | [template_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Backoffice\BackofficeTemplateController::update
 * @see app/Http/Controllers/Backoffice/BackofficeTemplateController.php:40
 * @route '/backoffice/templates/{template_slug}'
 */
export const update = (args: { template_slug: string | number } | [template_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/backoffice/templates/{template_slug}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Backoffice\BackofficeTemplateController::update
 * @see app/Http/Controllers/Backoffice/BackofficeTemplateController.php:40
 * @route '/backoffice/templates/{template_slug}'
 */
update.url = (args: { template_slug: string | number } | [template_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { template_slug: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    template_slug: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        template_slug: args.template_slug,
                }

    return update.definition.url
            .replace('{template_slug}', parsedArgs.template_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Backoffice\BackofficeTemplateController::update
 * @see app/Http/Controllers/Backoffice/BackofficeTemplateController.php:40
 * @route '/backoffice/templates/{template_slug}'
 */
update.put = (args: { template_slug: string | number } | [template_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})
/**
* @see \App\Http\Controllers\Backoffice\BackofficeTemplateController::update
 * @see app/Http/Controllers/Backoffice/BackofficeTemplateController.php:40
 * @route '/backoffice/templates/{template_slug}'
 */
update.patch = (args: { template_slug: string | number } | [template_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})
const BackofficeTemplateController = { index, edit, update }

export default BackofficeTemplateController