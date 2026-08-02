import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes'
 */
const RedirectController365c799721ffa05aa2f19639b3a02a62 = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: RedirectController365c799721ffa05aa2f19639b3a02a62.url(options),
    method: 'get',
})

RedirectController365c799721ffa05aa2f19639b3a02a62.definition = {
    methods: ["get","head","post","put","patch","delete","options"],
    url: '/configuracoes',
} satisfies RouteDefinition<["get","head","post","put","patch","delete","options"]>

/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes'
 */
RedirectController365c799721ffa05aa2f19639b3a02a62.url = (options?: RouteQueryOptions) => {
    return RedirectController365c799721ffa05aa2f19639b3a02a62.definition.url + queryParams(options)
}

/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes'
 */
RedirectController365c799721ffa05aa2f19639b3a02a62.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: RedirectController365c799721ffa05aa2f19639b3a02a62.url(options),
    method: 'get',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes'
 */
RedirectController365c799721ffa05aa2f19639b3a02a62.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: RedirectController365c799721ffa05aa2f19639b3a02a62.url(options),
    method: 'head',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes'
 */
RedirectController365c799721ffa05aa2f19639b3a02a62.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: RedirectController365c799721ffa05aa2f19639b3a02a62.url(options),
    method: 'post',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes'
 */
RedirectController365c799721ffa05aa2f19639b3a02a62.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: RedirectController365c799721ffa05aa2f19639b3a02a62.url(options),
    method: 'put',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes'
 */
RedirectController365c799721ffa05aa2f19639b3a02a62.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: RedirectController365c799721ffa05aa2f19639b3a02a62.url(options),
    method: 'patch',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes'
 */
RedirectController365c799721ffa05aa2f19639b3a02a62.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: RedirectController365c799721ffa05aa2f19639b3a02a62.url(options),
    method: 'delete',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes'
 */
RedirectController365c799721ffa05aa2f19639b3a02a62.options = (options?: RouteQueryOptions): RouteDefinition<'options'> => ({
    url: RedirectController365c799721ffa05aa2f19639b3a02a62.url(options),
    method: 'options',
})

    /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes'
 */
    const RedirectController365c799721ffa05aa2f19639b3a02a62Form = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: RedirectController365c799721ffa05aa2f19639b3a02a62.url(options),
        method: 'get',
    })

            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes'
 */
        RedirectController365c799721ffa05aa2f19639b3a02a62Form.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: RedirectController365c799721ffa05aa2f19639b3a02a62.url(options),
            method: 'get',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes'
 */
        RedirectController365c799721ffa05aa2f19639b3a02a62Form.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: RedirectController365c799721ffa05aa2f19639b3a02a62.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes'
 */
        RedirectController365c799721ffa05aa2f19639b3a02a62Form.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: RedirectController365c799721ffa05aa2f19639b3a02a62.url(options),
            method: 'post',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes'
 */
        RedirectController365c799721ffa05aa2f19639b3a02a62Form.put = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: RedirectController365c799721ffa05aa2f19639b3a02a62.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes'
 */
        RedirectController365c799721ffa05aa2f19639b3a02a62Form.patch = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: RedirectController365c799721ffa05aa2f19639b3a02a62.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes'
 */
        RedirectController365c799721ffa05aa2f19639b3a02a62Form.delete = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: RedirectController365c799721ffa05aa2f19639b3a02a62.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes'
 */
        RedirectController365c799721ffa05aa2f19639b3a02a62Form.options = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: RedirectController365c799721ffa05aa2f19639b3a02a62.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'OPTIONS',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    RedirectController365c799721ffa05aa2f19639b3a02a62.form = RedirectController365c799721ffa05aa2f19639b3a02a62Form
    /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/pipeline'
 */
const RedirectController28431e69d8781f4690182ce79a606ca0 = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: RedirectController28431e69d8781f4690182ce79a606ca0.url(options),
    method: 'get',
})

RedirectController28431e69d8781f4690182ce79a606ca0.definition = {
    methods: ["get","head","post","put","patch","delete","options"],
    url: '/configuracoes/pipeline',
} satisfies RouteDefinition<["get","head","post","put","patch","delete","options"]>

/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/pipeline'
 */
RedirectController28431e69d8781f4690182ce79a606ca0.url = (options?: RouteQueryOptions) => {
    return RedirectController28431e69d8781f4690182ce79a606ca0.definition.url + queryParams(options)
}

/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/pipeline'
 */
RedirectController28431e69d8781f4690182ce79a606ca0.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: RedirectController28431e69d8781f4690182ce79a606ca0.url(options),
    method: 'get',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/pipeline'
 */
RedirectController28431e69d8781f4690182ce79a606ca0.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: RedirectController28431e69d8781f4690182ce79a606ca0.url(options),
    method: 'head',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/pipeline'
 */
RedirectController28431e69d8781f4690182ce79a606ca0.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: RedirectController28431e69d8781f4690182ce79a606ca0.url(options),
    method: 'post',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/pipeline'
 */
RedirectController28431e69d8781f4690182ce79a606ca0.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: RedirectController28431e69d8781f4690182ce79a606ca0.url(options),
    method: 'put',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/pipeline'
 */
RedirectController28431e69d8781f4690182ce79a606ca0.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: RedirectController28431e69d8781f4690182ce79a606ca0.url(options),
    method: 'patch',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/pipeline'
 */
RedirectController28431e69d8781f4690182ce79a606ca0.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: RedirectController28431e69d8781f4690182ce79a606ca0.url(options),
    method: 'delete',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/pipeline'
 */
RedirectController28431e69d8781f4690182ce79a606ca0.options = (options?: RouteQueryOptions): RouteDefinition<'options'> => ({
    url: RedirectController28431e69d8781f4690182ce79a606ca0.url(options),
    method: 'options',
})

    /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/pipeline'
 */
    const RedirectController28431e69d8781f4690182ce79a606ca0Form = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: RedirectController28431e69d8781f4690182ce79a606ca0.url(options),
        method: 'get',
    })

            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/pipeline'
 */
        RedirectController28431e69d8781f4690182ce79a606ca0Form.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: RedirectController28431e69d8781f4690182ce79a606ca0.url(options),
            method: 'get',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/pipeline'
 */
        RedirectController28431e69d8781f4690182ce79a606ca0Form.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: RedirectController28431e69d8781f4690182ce79a606ca0.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/pipeline'
 */
        RedirectController28431e69d8781f4690182ce79a606ca0Form.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: RedirectController28431e69d8781f4690182ce79a606ca0.url(options),
            method: 'post',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/pipeline'
 */
        RedirectController28431e69d8781f4690182ce79a606ca0Form.put = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: RedirectController28431e69d8781f4690182ce79a606ca0.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/pipeline'
 */
        RedirectController28431e69d8781f4690182ce79a606ca0Form.patch = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: RedirectController28431e69d8781f4690182ce79a606ca0.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/pipeline'
 */
        RedirectController28431e69d8781f4690182ce79a606ca0Form.delete = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: RedirectController28431e69d8781f4690182ce79a606ca0.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/pipeline'
 */
        RedirectController28431e69d8781f4690182ce79a606ca0Form.options = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: RedirectController28431e69d8781f4690182ce79a606ca0.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'OPTIONS',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    RedirectController28431e69d8781f4690182ce79a606ca0.form = RedirectController28431e69d8781f4690182ce79a606ca0Form
    /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/campos'
 */
const RedirectController87f04f20ffc075c38d968c079f9238bf = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: RedirectController87f04f20ffc075c38d968c079f9238bf.url(options),
    method: 'get',
})

RedirectController87f04f20ffc075c38d968c079f9238bf.definition = {
    methods: ["get","head","post","put","patch","delete","options"],
    url: '/configuracoes/campos',
} satisfies RouteDefinition<["get","head","post","put","patch","delete","options"]>

/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/campos'
 */
RedirectController87f04f20ffc075c38d968c079f9238bf.url = (options?: RouteQueryOptions) => {
    return RedirectController87f04f20ffc075c38d968c079f9238bf.definition.url + queryParams(options)
}

/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/campos'
 */
RedirectController87f04f20ffc075c38d968c079f9238bf.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: RedirectController87f04f20ffc075c38d968c079f9238bf.url(options),
    method: 'get',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/campos'
 */
RedirectController87f04f20ffc075c38d968c079f9238bf.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: RedirectController87f04f20ffc075c38d968c079f9238bf.url(options),
    method: 'head',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/campos'
 */
RedirectController87f04f20ffc075c38d968c079f9238bf.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: RedirectController87f04f20ffc075c38d968c079f9238bf.url(options),
    method: 'post',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/campos'
 */
RedirectController87f04f20ffc075c38d968c079f9238bf.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: RedirectController87f04f20ffc075c38d968c079f9238bf.url(options),
    method: 'put',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/campos'
 */
RedirectController87f04f20ffc075c38d968c079f9238bf.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: RedirectController87f04f20ffc075c38d968c079f9238bf.url(options),
    method: 'patch',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/campos'
 */
RedirectController87f04f20ffc075c38d968c079f9238bf.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: RedirectController87f04f20ffc075c38d968c079f9238bf.url(options),
    method: 'delete',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/campos'
 */
RedirectController87f04f20ffc075c38d968c079f9238bf.options = (options?: RouteQueryOptions): RouteDefinition<'options'> => ({
    url: RedirectController87f04f20ffc075c38d968c079f9238bf.url(options),
    method: 'options',
})

    /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/campos'
 */
    const RedirectController87f04f20ffc075c38d968c079f9238bfForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: RedirectController87f04f20ffc075c38d968c079f9238bf.url(options),
        method: 'get',
    })

            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/campos'
 */
        RedirectController87f04f20ffc075c38d968c079f9238bfForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: RedirectController87f04f20ffc075c38d968c079f9238bf.url(options),
            method: 'get',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/campos'
 */
        RedirectController87f04f20ffc075c38d968c079f9238bfForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: RedirectController87f04f20ffc075c38d968c079f9238bf.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/campos'
 */
        RedirectController87f04f20ffc075c38d968c079f9238bfForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: RedirectController87f04f20ffc075c38d968c079f9238bf.url(options),
            method: 'post',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/campos'
 */
        RedirectController87f04f20ffc075c38d968c079f9238bfForm.put = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: RedirectController87f04f20ffc075c38d968c079f9238bf.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/campos'
 */
        RedirectController87f04f20ffc075c38d968c079f9238bfForm.patch = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: RedirectController87f04f20ffc075c38d968c079f9238bf.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/campos'
 */
        RedirectController87f04f20ffc075c38d968c079f9238bfForm.delete = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: RedirectController87f04f20ffc075c38d968c079f9238bf.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/configuracoes/campos'
 */
        RedirectController87f04f20ffc075c38d968c079f9238bfForm.options = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: RedirectController87f04f20ffc075c38d968c079f9238bf.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'OPTIONS',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    RedirectController87f04f20ffc075c38d968c079f9238bf.form = RedirectController87f04f20ffc075c38d968c079f9238bfForm
    /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/settings'
 */
const RedirectController4b87d2df7e3aa853f6720faea796e36c = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: RedirectController4b87d2df7e3aa853f6720faea796e36c.url(options),
    method: 'get',
})

RedirectController4b87d2df7e3aa853f6720faea796e36c.definition = {
    methods: ["get","head","post","put","patch","delete","options"],
    url: '/settings',
} satisfies RouteDefinition<["get","head","post","put","patch","delete","options"]>

/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/settings'
 */
RedirectController4b87d2df7e3aa853f6720faea796e36c.url = (options?: RouteQueryOptions) => {
    return RedirectController4b87d2df7e3aa853f6720faea796e36c.definition.url + queryParams(options)
}

/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/settings'
 */
RedirectController4b87d2df7e3aa853f6720faea796e36c.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: RedirectController4b87d2df7e3aa853f6720faea796e36c.url(options),
    method: 'get',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/settings'
 */
RedirectController4b87d2df7e3aa853f6720faea796e36c.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: RedirectController4b87d2df7e3aa853f6720faea796e36c.url(options),
    method: 'head',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/settings'
 */
RedirectController4b87d2df7e3aa853f6720faea796e36c.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: RedirectController4b87d2df7e3aa853f6720faea796e36c.url(options),
    method: 'post',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/settings'
 */
RedirectController4b87d2df7e3aa853f6720faea796e36c.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: RedirectController4b87d2df7e3aa853f6720faea796e36c.url(options),
    method: 'put',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/settings'
 */
RedirectController4b87d2df7e3aa853f6720faea796e36c.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: RedirectController4b87d2df7e3aa853f6720faea796e36c.url(options),
    method: 'patch',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/settings'
 */
RedirectController4b87d2df7e3aa853f6720faea796e36c.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: RedirectController4b87d2df7e3aa853f6720faea796e36c.url(options),
    method: 'delete',
})
/**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/settings'
 */
RedirectController4b87d2df7e3aa853f6720faea796e36c.options = (options?: RouteQueryOptions): RouteDefinition<'options'> => ({
    url: RedirectController4b87d2df7e3aa853f6720faea796e36c.url(options),
    method: 'options',
})

    /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/settings'
 */
    const RedirectController4b87d2df7e3aa853f6720faea796e36cForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: RedirectController4b87d2df7e3aa853f6720faea796e36c.url(options),
        method: 'get',
    })

            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/settings'
 */
        RedirectController4b87d2df7e3aa853f6720faea796e36cForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: RedirectController4b87d2df7e3aa853f6720faea796e36c.url(options),
            method: 'get',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/settings'
 */
        RedirectController4b87d2df7e3aa853f6720faea796e36cForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: RedirectController4b87d2df7e3aa853f6720faea796e36c.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/settings'
 */
        RedirectController4b87d2df7e3aa853f6720faea796e36cForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: RedirectController4b87d2df7e3aa853f6720faea796e36c.url(options),
            method: 'post',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/settings'
 */
        RedirectController4b87d2df7e3aa853f6720faea796e36cForm.put = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: RedirectController4b87d2df7e3aa853f6720faea796e36c.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/settings'
 */
        RedirectController4b87d2df7e3aa853f6720faea796e36cForm.patch = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: RedirectController4b87d2df7e3aa853f6720faea796e36c.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/settings'
 */
        RedirectController4b87d2df7e3aa853f6720faea796e36cForm.delete = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: RedirectController4b87d2df7e3aa853f6720faea796e36c.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \Illuminate\Routing\RedirectController::__invoke
 * @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
 * @route '/settings'
 */
        RedirectController4b87d2df7e3aa853f6720faea796e36cForm.options = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: RedirectController4b87d2df7e3aa853f6720faea796e36c.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'OPTIONS',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    RedirectController4b87d2df7e3aa853f6720faea796e36c.form = RedirectController4b87d2df7e3aa853f6720faea796e36cForm

/**
* Multiple routes resolve to \Illuminate\Routing\RedirectController::RedirectController, so this export is a
* dictionary keyed by URI rather than a callable. Call a specific route with `RedirectController['<uri>'](...)`,
* or import the route by name from your generated `routes/` directory.
*/
const RedirectController = {
    '/configuracoes': RedirectController365c799721ffa05aa2f19639b3a02a62,
    '/configuracoes/pipeline': RedirectController28431e69d8781f4690182ce79a606ca0,
    '/configuracoes/campos': RedirectController87f04f20ffc075c38d968c079f9238bf,
    '/settings': RedirectController4b87d2df7e3aa853f6720faea796e36c,
}

export default RedirectController