import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
import entries from './entries'
/**
* @see \App\Http\Controllers\ContactController::addContacts
 * @see app/Http/Controllers/ContactController.php:184
 * @route '/listas-contato/{list}/contatos'
 */
export const addContacts = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: addContacts.url(args, options),
    method: 'post',
})

addContacts.definition = {
    methods: ["post"],
    url: '/listas-contato/{list}/contatos',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ContactController::addContacts
 * @see app/Http/Controllers/ContactController.php:184
 * @route '/listas-contato/{list}/contatos'
 */
addContacts.url = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return addContacts.definition.url
            .replace('{list}', parsedArgs.list.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ContactController::addContacts
 * @see app/Http/Controllers/ContactController.php:184
 * @route '/listas-contato/{list}/contatos'
 */
addContacts.post = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: addContacts.url(args, options),
    method: 'post',
})

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
const listasContato = {
    addContacts: Object.assign(addContacts, addContacts),
preview: Object.assign(preview, preview),
create: Object.assign(create, create),
index: Object.assign(index, index),
store: Object.assign(store, store),
show: Object.assign(show, show),
destroy: Object.assign(destroy, destroy),
importCsv: Object.assign(importCsv, importCsv),
entries: Object.assign(entries, entries),
updateFilters: Object.assign(updateFilters, updateFilters),
refresh: Object.assign(refresh, refresh),
freeze: Object.assign(freeze, freeze),
}

export default listasContato