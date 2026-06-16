import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\ContactListController::preview
 * @see app/Http/Controllers/ContactListController.php:145
 * @route '/listas-contato/preview'
 */
export const preview = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: preview.url(options),
    method: 'post',
})

preview.definition = {
    methods: ["post"],
    url: '/listas-contato/preview',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ContactListController::preview
 * @see app/Http/Controllers/ContactListController.php:145
 * @route '/listas-contato/preview'
 */
preview.url = (options?: RouteQueryOptions) => {
    return preview.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ContactListController::preview
 * @see app/Http/Controllers/ContactListController.php:145
 * @route '/listas-contato/preview'
 */
preview.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: preview.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ContactListController::preview
 * @see app/Http/Controllers/ContactListController.php:145
 * @route '/listas-contato/preview'
 */
    const previewForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: preview.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ContactListController::preview
 * @see app/Http/Controllers/ContactListController.php:145
 * @route '/listas-contato/preview'
 */
        previewForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: preview.url(options),
            method: 'post',
        })
    
    preview.form = previewForm
/**
* @see \App\Http\Controllers\ContactListController::create
 * @see app/Http/Controllers/ContactListController.php:39
 * @route '/listas-contato/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/listas-contato/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ContactListController::create
 * @see app/Http/Controllers/ContactListController.php:39
 * @route '/listas-contato/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ContactListController::create
 * @see app/Http/Controllers/ContactListController.php:39
 * @route '/listas-contato/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ContactListController::create
 * @see app/Http/Controllers/ContactListController.php:39
 * @route '/listas-contato/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\ContactListController::create
 * @see app/Http/Controllers/ContactListController.php:39
 * @route '/listas-contato/create'
 */
    const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\ContactListController::create
 * @see app/Http/Controllers/ContactListController.php:39
 * @route '/listas-contato/create'
 */
        createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\ContactListController::create
 * @see app/Http/Controllers/ContactListController.php:39
 * @route '/listas-contato/create'
 */
        createForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    create.form = createForm
/**
* @see \App\Http\Controllers\ContactListController::index
 * @see app/Http/Controllers/ContactListController.php:27
 * @route '/listas-contato'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/listas-contato',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ContactListController::index
 * @see app/Http/Controllers/ContactListController.php:27
 * @route '/listas-contato'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ContactListController::index
 * @see app/Http/Controllers/ContactListController.php:27
 * @route '/listas-contato'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ContactListController::index
 * @see app/Http/Controllers/ContactListController.php:27
 * @route '/listas-contato'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\ContactListController::index
 * @see app/Http/Controllers/ContactListController.php:27
 * @route '/listas-contato'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\ContactListController::index
 * @see app/Http/Controllers/ContactListController.php:27
 * @route '/listas-contato'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\ContactListController::index
 * @see app/Http/Controllers/ContactListController.php:27
 * @route '/listas-contato'
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
* @see \App\Http\Controllers\ContactListController::store
 * @see app/Http/Controllers/ContactListController.php:77
 * @route '/listas-contato'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/listas-contato',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ContactListController::store
 * @see app/Http/Controllers/ContactListController.php:77
 * @route '/listas-contato'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ContactListController::store
 * @see app/Http/Controllers/ContactListController.php:77
 * @route '/listas-contato'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ContactListController::store
 * @see app/Http/Controllers/ContactListController.php:77
 * @route '/listas-contato'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ContactListController::store
 * @see app/Http/Controllers/ContactListController.php:77
 * @route '/listas-contato'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\ContactListController::show
 * @see app/Http/Controllers/ContactListController.php:97
 * @route '/listas-contato/{list}'
 */
export const show = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/listas-contato/{list}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ContactListController::show
 * @see app/Http/Controllers/ContactListController.php:97
 * @route '/listas-contato/{list}'
 */
show.url = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{list}', parsedArgs.list.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ContactListController::show
 * @see app/Http/Controllers/ContactListController.php:97
 * @route '/listas-contato/{list}'
 */
show.get = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ContactListController::show
 * @see app/Http/Controllers/ContactListController.php:97
 * @route '/listas-contato/{list}'
 */
show.head = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\ContactListController::show
 * @see app/Http/Controllers/ContactListController.php:97
 * @route '/listas-contato/{list}'
 */
    const showForm = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\ContactListController::show
 * @see app/Http/Controllers/ContactListController.php:97
 * @route '/listas-contato/{list}'
 */
        showForm.get = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\ContactListController::show
 * @see app/Http/Controllers/ContactListController.php:97
 * @route '/listas-contato/{list}'
 */
        showForm.head = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
/**
* @see \App\Http\Controllers\ContactListController::destroy
 * @see app/Http/Controllers/ContactListController.php:122
 * @route '/listas-contato/{list}'
 */
export const destroy = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/listas-contato/{list}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\ContactListController::destroy
 * @see app/Http/Controllers/ContactListController.php:122
 * @route '/listas-contato/{list}'
 */
destroy.url = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{list}', parsedArgs.list.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ContactListController::destroy
 * @see app/Http/Controllers/ContactListController.php:122
 * @route '/listas-contato/{list}'
 */
destroy.delete = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\ContactListController::destroy
 * @see app/Http/Controllers/ContactListController.php:122
 * @route '/listas-contato/{list}'
 */
    const destroyForm = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ContactListController::destroy
 * @see app/Http/Controllers/ContactListController.php:122
 * @route '/listas-contato/{list}'
 */
        destroyForm.delete = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\ContactListController::importCsv
 * @see app/Http/Controllers/ContactListController.php:209
 * @route '/listas-contato/{list}/import-csv'
 */
export const importCsv = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importCsv.url(args, options),
    method: 'post',
})

importCsv.definition = {
    methods: ["post"],
    url: '/listas-contato/{list}/import-csv',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ContactListController::importCsv
 * @see app/Http/Controllers/ContactListController.php:209
 * @route '/listas-contato/{list}/import-csv'
 */
importCsv.url = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return importCsv.definition.url
            .replace('{list}', parsedArgs.list.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ContactListController::importCsv
 * @see app/Http/Controllers/ContactListController.php:209
 * @route '/listas-contato/{list}/import-csv'
 */
importCsv.post = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importCsv.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ContactListController::importCsv
 * @see app/Http/Controllers/ContactListController.php:209
 * @route '/listas-contato/{list}/import-csv'
 */
    const importCsvForm = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: importCsv.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ContactListController::importCsv
 * @see app/Http/Controllers/ContactListController.php:209
 * @route '/listas-contato/{list}/import-csv'
 */
        importCsvForm.post = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: importCsv.url(args, options),
            method: 'post',
        })
    
    importCsv.form = importCsvForm
/**
* @see \App\Http\Controllers\ContactListController::updateFilters
 * @see app/Http/Controllers/ContactListController.php:174
 * @route '/listas-contato/{list}/filters'
 */
export const updateFilters = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateFilters.url(args, options),
    method: 'patch',
})

updateFilters.definition = {
    methods: ["patch"],
    url: '/listas-contato/{list}/filters',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\ContactListController::updateFilters
 * @see app/Http/Controllers/ContactListController.php:174
 * @route '/listas-contato/{list}/filters'
 */
updateFilters.url = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return updateFilters.definition.url
            .replace('{list}', parsedArgs.list.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ContactListController::updateFilters
 * @see app/Http/Controllers/ContactListController.php:174
 * @route '/listas-contato/{list}/filters'
 */
updateFilters.patch = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateFilters.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\ContactListController::updateFilters
 * @see app/Http/Controllers/ContactListController.php:174
 * @route '/listas-contato/{list}/filters'
 */
    const updateFiltersForm = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: updateFilters.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ContactListController::updateFilters
 * @see app/Http/Controllers/ContactListController.php:174
 * @route '/listas-contato/{list}/filters'
 */
        updateFiltersForm.patch = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updateFilters.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    updateFilters.form = updateFiltersForm
/**
* @see \App\Http\Controllers\ContactListController::refresh
 * @see app/Http/Controllers/ContactListController.php:184
 * @route '/listas-contato/{list}/refresh'
 */
export const refresh = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: refresh.url(args, options),
    method: 'post',
})

refresh.definition = {
    methods: ["post"],
    url: '/listas-contato/{list}/refresh',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ContactListController::refresh
 * @see app/Http/Controllers/ContactListController.php:184
 * @route '/listas-contato/{list}/refresh'
 */
refresh.url = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return refresh.definition.url
            .replace('{list}', parsedArgs.list.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ContactListController::refresh
 * @see app/Http/Controllers/ContactListController.php:184
 * @route '/listas-contato/{list}/refresh'
 */
refresh.post = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: refresh.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ContactListController::refresh
 * @see app/Http/Controllers/ContactListController.php:184
 * @route '/listas-contato/{list}/refresh'
 */
    const refreshForm = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: refresh.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ContactListController::refresh
 * @see app/Http/Controllers/ContactListController.php:184
 * @route '/listas-contato/{list}/refresh'
 */
        refreshForm.post = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: refresh.url(args, options),
            method: 'post',
        })
    
    refresh.form = refreshForm
/**
* @see \App\Http\Controllers\ContactListController::freeze
 * @see app/Http/Controllers/ContactListController.php:198
 * @route '/listas-contato/{list}/freeze'
 */
export const freeze = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: freeze.url(args, options),
    method: 'post',
})

freeze.definition = {
    methods: ["post"],
    url: '/listas-contato/{list}/freeze',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ContactListController::freeze
 * @see app/Http/Controllers/ContactListController.php:198
 * @route '/listas-contato/{list}/freeze'
 */
freeze.url = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return freeze.definition.url
            .replace('{list}', parsedArgs.list.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ContactListController::freeze
 * @see app/Http/Controllers/ContactListController.php:198
 * @route '/listas-contato/{list}/freeze'
 */
freeze.post = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: freeze.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ContactListController::freeze
 * @see app/Http/Controllers/ContactListController.php:198
 * @route '/listas-contato/{list}/freeze'
 */
    const freezeForm = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: freeze.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ContactListController::freeze
 * @see app/Http/Controllers/ContactListController.php:198
 * @route '/listas-contato/{list}/freeze'
 */
        freezeForm.post = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: freeze.url(args, options),
            method: 'post',
        })
    
    freeze.form = freezeForm
const ContactListController = { preview, create, index, store, show, destroy, importCsv, updateFilters, refresh, freeze }

export default ContactListController