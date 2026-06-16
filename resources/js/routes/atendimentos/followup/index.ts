import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\ServiceTicketController::disable
 * @see app/Http/Controllers/ServiceTicketController.php:55
 * @route '/atendimentos/{ticket}/followup-disable'
 */
export const disable = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: disable.url(args, options),
    method: 'post',
})

disable.definition = {
    methods: ["post"],
    url: '/atendimentos/{ticket}/followup-disable',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ServiceTicketController::disable
 * @see app/Http/Controllers/ServiceTicketController.php:55
 * @route '/atendimentos/{ticket}/followup-disable'
 */
disable.url = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { ticket: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { ticket: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    ticket: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        ticket: typeof args.ticket === 'object'
                ? args.ticket.id
                : args.ticket,
                }

    return disable.definition.url
            .replace('{ticket}', parsedArgs.ticket.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ServiceTicketController::disable
 * @see app/Http/Controllers/ServiceTicketController.php:55
 * @route '/atendimentos/{ticket}/followup-disable'
 */
disable.post = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: disable.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ServiceTicketController::disable
 * @see app/Http/Controllers/ServiceTicketController.php:55
 * @route '/atendimentos/{ticket}/followup-disable'
 */
    const disableForm = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: disable.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ServiceTicketController::disable
 * @see app/Http/Controllers/ServiceTicketController.php:55
 * @route '/atendimentos/{ticket}/followup-disable'
 */
        disableForm.post = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: disable.url(args, options),
            method: 'post',
        })
    
    disable.form = disableForm
const followup = {
    disable: Object.assign(disable, disable),
}

export default followup