import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\ContactController::index
 * @see app/Http/Controllers/ContactController.php:31
 * @route '/contatos'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/contatos',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ContactController::index
 * @see app/Http/Controllers/ContactController.php:31
 * @route '/contatos'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ContactController::index
 * @see app/Http/Controllers/ContactController.php:31
 * @route '/contatos'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ContactController::index
 * @see app/Http/Controllers/ContactController.php:31
 * @route '/contatos'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\ContactController::index
 * @see app/Http/Controllers/ContactController.php:31
 * @route '/contatos'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\ContactController::index
 * @see app/Http/Controllers/ContactController.php:31
 * @route '/contatos'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\ContactController::index
 * @see app/Http/Controllers/ContactController.php:31
 * @route '/contatos'
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
* @see \App\Http\Controllers\ContactController::search
 * @see app/Http/Controllers/ContactController.php:87
 * @route '/contatos/search'
 */
export const search = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: search.url(options),
    method: 'get',
})

search.definition = {
    methods: ["get","head"],
    url: '/contatos/search',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ContactController::search
 * @see app/Http/Controllers/ContactController.php:87
 * @route '/contatos/search'
 */
search.url = (options?: RouteQueryOptions) => {
    return search.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ContactController::search
 * @see app/Http/Controllers/ContactController.php:87
 * @route '/contatos/search'
 */
search.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: search.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ContactController::search
 * @see app/Http/Controllers/ContactController.php:87
 * @route '/contatos/search'
 */
search.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: search.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\ContactController::search
 * @see app/Http/Controllers/ContactController.php:87
 * @route '/contatos/search'
 */
    const searchForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: search.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\ContactController::search
 * @see app/Http/Controllers/ContactController.php:87
 * @route '/contatos/search'
 */
        searchForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: search.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\ContactController::search
 * @see app/Http/Controllers/ContactController.php:87
 * @route '/contatos/search'
 */
        searchForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: search.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    search.form = searchForm
/**
* @see \App\Http\Controllers\ContactController::show
 * @see app/Http/Controllers/ContactController.php:117
 * @route '/contatos/{contact}'
 */
export const show = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/contatos/{contact}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ContactController::show
 * @see app/Http/Controllers/ContactController.php:117
 * @route '/contatos/{contact}'
 */
show.url = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { contact: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { contact: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    contact: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        contact: typeof args.contact === 'object'
                ? args.contact.id
                : args.contact,
                }

    return show.definition.url
            .replace('{contact}', parsedArgs.contact.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ContactController::show
 * @see app/Http/Controllers/ContactController.php:117
 * @route '/contatos/{contact}'
 */
show.get = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ContactController::show
 * @see app/Http/Controllers/ContactController.php:117
 * @route '/contatos/{contact}'
 */
show.head = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\ContactController::show
 * @see app/Http/Controllers/ContactController.php:117
 * @route '/contatos/{contact}'
 */
    const showForm = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\ContactController::show
 * @see app/Http/Controllers/ContactController.php:117
 * @route '/contatos/{contact}'
 */
        showForm.get = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\ContactController::show
 * @see app/Http/Controllers/ContactController.php:117
 * @route '/contatos/{contact}'
 */
        showForm.head = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\ContactController::store
 * @see app/Http/Controllers/ContactController.php:150
 * @route '/contatos'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/contatos',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ContactController::store
 * @see app/Http/Controllers/ContactController.php:150
 * @route '/contatos'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ContactController::store
 * @see app/Http/Controllers/ContactController.php:150
 * @route '/contatos'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ContactController::store
 * @see app/Http/Controllers/ContactController.php:150
 * @route '/contatos'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ContactController::store
 * @see app/Http/Controllers/ContactController.php:150
 * @route '/contatos'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\ContactController::update
 * @see app/Http/Controllers/ContactController.php:176
 * @route '/contatos/{contact}'
 */
export const update = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/contatos/{contact}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\ContactController::update
 * @see app/Http/Controllers/ContactController.php:176
 * @route '/contatos/{contact}'
 */
update.url = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { contact: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { contact: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    contact: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        contact: typeof args.contact === 'object'
                ? args.contact.id
                : args.contact,
                }

    return update.definition.url
            .replace('{contact}', parsedArgs.contact.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ContactController::update
 * @see app/Http/Controllers/ContactController.php:176
 * @route '/contatos/{contact}'
 */
update.patch = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\ContactController::update
 * @see app/Http/Controllers/ContactController.php:176
 * @route '/contatos/{contact}'
 */
    const updateForm = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ContactController::update
 * @see app/Http/Controllers/ContactController.php:176
 * @route '/contatos/{contact}'
 */
        updateForm.patch = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \App\Http\Controllers\ContactController::updateCollectedInformation
 * @see app/Http/Controllers/ContactController.php:199
 * @route '/contatos/{contact}/informacoes-coletadas'
 */
export const updateCollectedInformation = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateCollectedInformation.url(args, options),
    method: 'patch',
})

updateCollectedInformation.definition = {
    methods: ["patch"],
    url: '/contatos/{contact}/informacoes-coletadas',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\ContactController::updateCollectedInformation
 * @see app/Http/Controllers/ContactController.php:199
 * @route '/contatos/{contact}/informacoes-coletadas'
 */
updateCollectedInformation.url = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { contact: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { contact: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    contact: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        contact: typeof args.contact === 'object'
                ? args.contact.id
                : args.contact,
                }

    return updateCollectedInformation.definition.url
            .replace('{contact}', parsedArgs.contact.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ContactController::updateCollectedInformation
 * @see app/Http/Controllers/ContactController.php:199
 * @route '/contatos/{contact}/informacoes-coletadas'
 */
updateCollectedInformation.patch = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateCollectedInformation.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\ContactController::updateCollectedInformation
 * @see app/Http/Controllers/ContactController.php:199
 * @route '/contatos/{contact}/informacoes-coletadas'
 */
    const updateCollectedInformationForm = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: updateCollectedInformation.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ContactController::updateCollectedInformation
 * @see app/Http/Controllers/ContactController.php:199
 * @route '/contatos/{contact}/informacoes-coletadas'
 */
        updateCollectedInformationForm.patch = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updateCollectedInformation.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    updateCollectedInformation.form = updateCollectedInformationForm
/**
* @see \App\Http\Controllers\ContactController::destroy
 * @see app/Http/Controllers/ContactController.php:209
 * @route '/contatos/{contact}'
 */
export const destroy = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/contatos/{contact}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\ContactController::destroy
 * @see app/Http/Controllers/ContactController.php:209
 * @route '/contatos/{contact}'
 */
destroy.url = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { contact: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { contact: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    contact: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        contact: typeof args.contact === 'object'
                ? args.contact.id
                : args.contact,
                }

    return destroy.definition.url
            .replace('{contact}', parsedArgs.contact.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ContactController::destroy
 * @see app/Http/Controllers/ContactController.php:209
 * @route '/contatos/{contact}'
 */
destroy.delete = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\ContactController::destroy
 * @see app/Http/Controllers/ContactController.php:209
 * @route '/contatos/{contact}'
 */
    const destroyForm = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ContactController::destroy
 * @see app/Http/Controllers/ContactController.php:209
 * @route '/contatos/{contact}'
 */
        destroyForm.delete = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\ContactController::addToList
 * @see app/Http/Controllers/ContactController.php:223
 * @route '/listas-contato/{list}/contatos'
 */
export const addToList = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: addToList.url(args, options),
    method: 'post',
})

addToList.definition = {
    methods: ["post"],
    url: '/listas-contato/{list}/contatos',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ContactController::addToList
 * @see app/Http/Controllers/ContactController.php:223
 * @route '/listas-contato/{list}/contatos'
 */
addToList.url = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return addToList.definition.url
            .replace('{list}', parsedArgs.list.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ContactController::addToList
 * @see app/Http/Controllers/ContactController.php:223
 * @route '/listas-contato/{list}/contatos'
 */
addToList.post = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: addToList.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ContactController::addToList
 * @see app/Http/Controllers/ContactController.php:223
 * @route '/listas-contato/{list}/contatos'
 */
    const addToListForm = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: addToList.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ContactController::addToList
 * @see app/Http/Controllers/ContactController.php:223
 * @route '/listas-contato/{list}/contatos'
 */
        addToListForm.post = (args: { list: number | { id: number } } | [list: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: addToList.url(args, options),
            method: 'post',
        })
    
    addToList.form = addToListForm
const ContactController = { index, search, show, store, update, updateCollectedInformation, destroy, addToList }

export default ContactController