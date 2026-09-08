import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\UpdateController::index
* @see app/Http/Controllers/Settings/UpdateController.php:32
* @route '/settings/update'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/settings/update',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\UpdateController::index
* @see app/Http/Controllers/Settings/UpdateController.php:32
* @route '/settings/update'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\UpdateController::index
* @see app/Http/Controllers/Settings/UpdateController.php:32
* @route '/settings/update'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\UpdateController::index
* @see app/Http/Controllers/Settings/UpdateController.php:32
* @route '/settings/update'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\UpdateController::index
* @see app/Http/Controllers/Settings/UpdateController.php:32
* @route '/settings/update'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\UpdateController::index
* @see app/Http/Controllers/Settings/UpdateController.php:32
* @route '/settings/update'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\UpdateController::index
* @see app/Http/Controllers/Settings/UpdateController.php:32
* @route '/settings/update'
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
* @see \App\Http\Controllers\Settings\UpdateController::check
* @see app/Http/Controllers/Settings/UpdateController.php:51
* @route '/settings/update/check'
*/
export const check = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: check.url(options),
    method: 'post',
})

check.definition = {
    methods: ["post"],
    url: '/settings/update/check',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\UpdateController::check
* @see app/Http/Controllers/Settings/UpdateController.php:51
* @route '/settings/update/check'
*/
check.url = (options?: RouteQueryOptions) => {
    return check.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\UpdateController::check
* @see app/Http/Controllers/Settings/UpdateController.php:51
* @route '/settings/update/check'
*/
check.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: check.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\UpdateController::check
* @see app/Http/Controllers/Settings/UpdateController.php:51
* @route '/settings/update/check'
*/
const checkForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: check.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\UpdateController::check
* @see app/Http/Controllers/Settings/UpdateController.php:51
* @route '/settings/update/check'
*/
checkForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: check.url(options),
    method: 'post',
})

check.form = checkForm

/**
* @see \App\Http\Controllers\Settings\UpdateController::store
* @see app/Http/Controllers/Settings/UpdateController.php:78
* @route '/settings/update'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/settings/update',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\UpdateController::store
* @see app/Http/Controllers/Settings/UpdateController.php:78
* @route '/settings/update'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\UpdateController::store
* @see app/Http/Controllers/Settings/UpdateController.php:78
* @route '/settings/update'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\UpdateController::store
* @see app/Http/Controllers/Settings/UpdateController.php:78
* @route '/settings/update'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\UpdateController::store
* @see app/Http/Controllers/Settings/UpdateController.php:78
* @route '/settings/update'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

const UpdateController = { index, check, store }

export default UpdateController