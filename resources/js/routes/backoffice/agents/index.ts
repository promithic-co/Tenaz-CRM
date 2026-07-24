import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import model from './model'
import tools from './tools'
import prompt from './prompt'
/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentController::index
 * @see app/Http/Controllers/Backoffice/BackofficeAgentController.php:27
 * @route '/backoffice/agentes'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/backoffice/agentes',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentController::index
 * @see app/Http/Controllers/Backoffice/BackofficeAgentController.php:27
 * @route '/backoffice/agentes'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentController::index
 * @see app/Http/Controllers/Backoffice/BackofficeAgentController.php:27
 * @route '/backoffice/agentes'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentController::index
 * @see app/Http/Controllers/Backoffice/BackofficeAgentController.php:27
 * @route '/backoffice/agentes'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentController::index
 * @see app/Http/Controllers/Backoffice/BackofficeAgentController.php:27
 * @route '/backoffice/agentes'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentController::index
 * @see app/Http/Controllers/Backoffice/BackofficeAgentController.php:27
 * @route '/backoffice/agentes'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentController::index
 * @see app/Http/Controllers/Backoffice/BackofficeAgentController.php:27
 * @route '/backoffice/agentes'
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
* @see \App\Http\Controllers\Backoffice\BackofficeAgentController::show
 * @see app/Http/Controllers/Backoffice/BackofficeAgentController.php:57
 * @route '/backoffice/agentes/{agent}'
 */
export const show = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/backoffice/agentes/{agent}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentController::show
 * @see app/Http/Controllers/Backoffice/BackofficeAgentController.php:57
 * @route '/backoffice/agentes/{agent}'
 */
show.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { agent: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { agent: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    agent: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        agent: typeof args.agent === 'object'
                ? args.agent.id
                : args.agent,
                }

    return show.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentController::show
 * @see app/Http/Controllers/Backoffice/BackofficeAgentController.php:57
 * @route '/backoffice/agentes/{agent}'
 */
show.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentController::show
 * @see app/Http/Controllers/Backoffice/BackofficeAgentController.php:57
 * @route '/backoffice/agentes/{agent}'
 */
show.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentController::show
 * @see app/Http/Controllers/Backoffice/BackofficeAgentController.php:57
 * @route '/backoffice/agentes/{agent}'
 */
    const showForm = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentController::show
 * @see app/Http/Controllers/Backoffice/BackofficeAgentController.php:57
 * @route '/backoffice/agentes/{agent}'
 */
        showForm.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Backoffice\BackofficeAgentController::show
 * @see app/Http/Controllers/Backoffice/BackofficeAgentController.php:57
 * @route '/backoffice/agentes/{agent}'
 */
        showForm.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
const agents = {
    index: Object.assign(index, index),
show: Object.assign(show, show),
model: Object.assign(model, model),
tools: Object.assign(tools, tools),
prompt: Object.assign(prompt, prompt),
}

export default agents