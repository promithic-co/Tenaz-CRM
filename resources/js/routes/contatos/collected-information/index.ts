import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\ContactController::update
 * @see app/Http/Controllers/ContactController.php:199
 * @route '/contatos/{contact}/informacoes-coletadas'
 */
export const update = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/contatos/{contact}/informacoes-coletadas',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\ContactController::update
 * @see app/Http/Controllers/ContactController.php:199
 * @route '/contatos/{contact}/informacoes-coletadas'
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
 * @see app/Http/Controllers/ContactController.php:199
 * @route '/contatos/{contact}/informacoes-coletadas'
 */
update.patch = (args: { contact: number | { id: number } } | [contact: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\ContactController::update
 * @see app/Http/Controllers/ContactController.php:199
 * @route '/contatos/{contact}/informacoes-coletadas'
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
 * @see app/Http/Controllers/ContactController.php:199
 * @route '/contatos/{contact}/informacoes-coletadas'
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
const collectedInformation = {
    update: Object.assign(update, update),
}

export default collectedInformation