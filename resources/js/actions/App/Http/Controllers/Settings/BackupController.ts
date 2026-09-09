import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\BackupController::index
* @see app/Http/Controllers/Settings/BackupController.php:29
* @route '/settings/backup'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/settings/backup',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\BackupController::index
* @see app/Http/Controllers/Settings/BackupController.php:29
* @route '/settings/backup'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\BackupController::index
* @see app/Http/Controllers/Settings/BackupController.php:29
* @route '/settings/backup'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\BackupController::index
* @see app/Http/Controllers/Settings/BackupController.php:29
* @route '/settings/backup'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\BackupController::index
* @see app/Http/Controllers/Settings/BackupController.php:29
* @route '/settings/backup'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\BackupController::index
* @see app/Http/Controllers/Settings/BackupController.php:29
* @route '/settings/backup'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\BackupController::index
* @see app/Http/Controllers/Settings/BackupController.php:29
* @route '/settings/backup'
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
* @see \App\Http\Controllers\Settings\BackupController::store
* @see app/Http/Controllers/Settings/BackupController.php:39
* @route '/settings/backup'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/settings/backup',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\BackupController::store
* @see app/Http/Controllers/Settings/BackupController.php:39
* @route '/settings/backup'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\BackupController::store
* @see app/Http/Controllers/Settings/BackupController.php:39
* @route '/settings/backup'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\BackupController::store
* @see app/Http/Controllers/Settings/BackupController.php:39
* @route '/settings/backup'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\BackupController::store
* @see app/Http/Controllers/Settings/BackupController.php:39
* @route '/settings/backup'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Settings\BackupController::download
* @see app/Http/Controllers/Settings/BackupController.php:58
* @route '/settings/backup/{name}'
*/
export const download = (args: { name: string | number } | [name: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/settings/backup/{name}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\BackupController::download
* @see app/Http/Controllers/Settings/BackupController.php:58
* @route '/settings/backup/{name}'
*/
download.url = (args: { name: string | number } | [name: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { name: args }
    }

    if (Array.isArray(args)) {
        args = {
            name: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        name: args.name,
    }

    return download.definition.url
            .replace('{name}', parsedArgs.name.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\BackupController::download
* @see app/Http/Controllers/Settings/BackupController.php:58
* @route '/settings/backup/{name}'
*/
download.get = (args: { name: string | number } | [name: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\BackupController::download
* @see app/Http/Controllers/Settings/BackupController.php:58
* @route '/settings/backup/{name}'
*/
download.head = (args: { name: string | number } | [name: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\BackupController::download
* @see app/Http/Controllers/Settings/BackupController.php:58
* @route '/settings/backup/{name}'
*/
const downloadForm = (args: { name: string | number } | [name: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: download.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\BackupController::download
* @see app/Http/Controllers/Settings/BackupController.php:58
* @route '/settings/backup/{name}'
*/
downloadForm.get = (args: { name: string | number } | [name: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: download.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\BackupController::download
* @see app/Http/Controllers/Settings/BackupController.php:58
* @route '/settings/backup/{name}'
*/
downloadForm.head = (args: { name: string | number } | [name: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: download.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

download.form = downloadForm

const BackupController = { index, store, download }

export default BackupController