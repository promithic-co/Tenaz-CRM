import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\AutoTagSettingsController::edit
 * @see app/Http/Controllers/Settings/AutoTagSettingsController.php:14
 * @route '/settings/auto-tag'
 */
export const edit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/settings/auto-tag',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\AutoTagSettingsController::edit
 * @see app/Http/Controllers/Settings/AutoTagSettingsController.php:14
 * @route '/settings/auto-tag'
 */
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\AutoTagSettingsController::edit
 * @see app/Http/Controllers/Settings/AutoTagSettingsController.php:14
 * @route '/settings/auto-tag'
 */
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Settings\AutoTagSettingsController::edit
 * @see app/Http/Controllers/Settings/AutoTagSettingsController.php:14
 * @route '/settings/auto-tag'
 */
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Settings\AutoTagSettingsController::edit
 * @see app/Http/Controllers/Settings/AutoTagSettingsController.php:14
 * @route '/settings/auto-tag'
 */
    const editForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: edit.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Settings\AutoTagSettingsController::edit
 * @see app/Http/Controllers/Settings/AutoTagSettingsController.php:14
 * @route '/settings/auto-tag'
 */
        editForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Settings\AutoTagSettingsController::edit
 * @see app/Http/Controllers/Settings/AutoTagSettingsController.php:14
 * @route '/settings/auto-tag'
 */
        editForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    edit.form = editForm
/**
* @see \App\Http\Controllers\Settings\AutoTagSettingsController::update
 * @see app/Http/Controllers/Settings/AutoTagSettingsController.php:24
 * @route '/settings/auto-tag'
 */
export const update = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/settings/auto-tag',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Settings\AutoTagSettingsController::update
 * @see app/Http/Controllers/Settings/AutoTagSettingsController.php:24
 * @route '/settings/auto-tag'
 */
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\AutoTagSettingsController::update
 * @see app/Http/Controllers/Settings/AutoTagSettingsController.php:24
 * @route '/settings/auto-tag'
 */
update.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Settings\AutoTagSettingsController::update
 * @see app/Http/Controllers/Settings/AutoTagSettingsController.php:24
 * @route '/settings/auto-tag'
 */
    const updateForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Settings\AutoTagSettingsController::update
 * @see app/Http/Controllers/Settings/AutoTagSettingsController.php:24
 * @route '/settings/auto-tag'
 */
        updateForm.patch = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
const AutoTagSettingsController = { edit, update }

export default AutoTagSettingsController