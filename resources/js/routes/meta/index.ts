import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
 * @see routes/web.php:27
 * @route '/__version'
 */
export const version = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: version.url(options),
    method: 'get',
})

version.definition = {
    methods: ["get","head"],
    url: '/__version',
} satisfies RouteDefinition<["get","head"]>

/**
 * @see routes/web.php:27
 * @route '/__version'
 */
version.url = (options?: RouteQueryOptions) => {
    return version.definition.url + queryParams(options)
}

/**
 * @see routes/web.php:27
 * @route '/__version'
 */
version.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: version.url(options),
    method: 'get',
})
/**
 * @see routes/web.php:27
 * @route '/__version'
 */
version.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: version.url(options),
    method: 'head',
})
const meta = {
    version: Object.assign(version, version),
}

export default meta