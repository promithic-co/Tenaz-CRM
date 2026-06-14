import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
import invitations from './invitations'
import members from './members'
/**
* @see \App\Http\Controllers\Settings\TeamController::index
 * @see app/Http/Controllers/Settings/TeamController.php:22
 * @route '/settings/team'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/settings/team',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\TeamController::index
 * @see app/Http/Controllers/Settings/TeamController.php:22
 * @route '/settings/team'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\TeamController::index
 * @see app/Http/Controllers/Settings/TeamController.php:22
 * @route '/settings/team'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Settings\TeamController::index
 * @see app/Http/Controllers/Settings/TeamController.php:22
 * @route '/settings/team'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})
const team = {
    index: Object.assign(index, index),
invitations: Object.assign(invitations, invitations),
members: Object.assign(members, members),
}

export default team