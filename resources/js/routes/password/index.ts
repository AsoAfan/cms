import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\PasswordResetLinkController::request
* @see app/Http/Controllers/Auth/PasswordResetLinkController.php:18
* @route '/forgot-password'
*/
export const request = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: request.url(options),
    method: 'get',
})

request.definition = {
    methods: ["get","head"],
    url: '/forgot-password',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\PasswordResetLinkController::request
* @see app/Http/Controllers/Auth/PasswordResetLinkController.php:18
* @route '/forgot-password'
*/
request.url = (options?: RouteQueryOptions) => {
    return request.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\PasswordResetLinkController::request
* @see app/Http/Controllers/Auth/PasswordResetLinkController.php:18
* @route '/forgot-password'
*/
request.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: request.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\PasswordResetLinkController::request
* @see app/Http/Controllers/Auth/PasswordResetLinkController.php:18
* @route '/forgot-password'
*/
request.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: request.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\PasswordResetLinkController::request
* @see app/Http/Controllers/Auth/PasswordResetLinkController.php:18
* @route '/forgot-password'
*/
const requestForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: request.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\PasswordResetLinkController::request
* @see app/Http/Controllers/Auth/PasswordResetLinkController.php:18
* @route '/forgot-password'
*/
requestForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: request.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\PasswordResetLinkController::request
* @see app/Http/Controllers/Auth/PasswordResetLinkController.php:18
* @route '/forgot-password'
*/
requestForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: request.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

request.form = requestForm

/**
* @see \App\Http\Controllers\Auth\PasswordResetLinkController::email
* @see app/Http/Controllers/Auth/PasswordResetLinkController.php:31
* @route '/forgot-password'
*/
export const email = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: email.url(options),
    method: 'post',
})

email.definition = {
    methods: ["post"],
    url: '/forgot-password',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\PasswordResetLinkController::email
* @see app/Http/Controllers/Auth/PasswordResetLinkController.php:31
* @route '/forgot-password'
*/
email.url = (options?: RouteQueryOptions) => {
    return email.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\PasswordResetLinkController::email
* @see app/Http/Controllers/Auth/PasswordResetLinkController.php:31
* @route '/forgot-password'
*/
email.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: email.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Auth\PasswordResetLinkController::email
* @see app/Http/Controllers/Auth/PasswordResetLinkController.php:31
* @route '/forgot-password'
*/
const emailForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: email.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Auth\PasswordResetLinkController::email
* @see app/Http/Controllers/Auth/PasswordResetLinkController.php:31
* @route '/forgot-password'
*/
emailForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: email.url(options),
    method: 'post',
})

email.form = emailForm

/**
* @see \App\Http\Controllers\Auth\NewPasswordController::reset
* @see app/Http/Controllers/Auth/NewPasswordController.php:22
* @route '/reset-password/{token}'
*/
export const reset = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: reset.url(args, options),
    method: 'get',
})

reset.definition = {
    methods: ["get","head"],
    url: '/reset-password/{token}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\NewPasswordController::reset
* @see app/Http/Controllers/Auth/NewPasswordController.php:22
* @route '/reset-password/{token}'
*/
reset.url = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { token: args }
    }

    if (Array.isArray(args)) {
        args = {
            token: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        token: args.token,
    }

    return reset.definition.url
            .replace('{token}', parsedArgs.token.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\NewPasswordController::reset
* @see app/Http/Controllers/Auth/NewPasswordController.php:22
* @route '/reset-password/{token}'
*/
reset.get = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: reset.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\NewPasswordController::reset
* @see app/Http/Controllers/Auth/NewPasswordController.php:22
* @route '/reset-password/{token}'
*/
reset.head = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: reset.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\NewPasswordController::reset
* @see app/Http/Controllers/Auth/NewPasswordController.php:22
* @route '/reset-password/{token}'
*/
const resetForm = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: reset.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\NewPasswordController::reset
* @see app/Http/Controllers/Auth/NewPasswordController.php:22
* @route '/reset-password/{token}'
*/
resetForm.get = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: reset.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\NewPasswordController::reset
* @see app/Http/Controllers/Auth/NewPasswordController.php:22
* @route '/reset-password/{token}'
*/
resetForm.head = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: reset.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

reset.form = resetForm

/**
* @see \App\Http\Controllers\Auth\NewPasswordController::store
* @see app/Http/Controllers/Auth/NewPasswordController.php:35
* @route '/reset-password'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/reset-password',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\NewPasswordController::store
* @see app/Http/Controllers/Auth/NewPasswordController.php:35
* @route '/reset-password'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\NewPasswordController::store
* @see app/Http/Controllers/Auth/NewPasswordController.php:35
* @route '/reset-password'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Auth\NewPasswordController::store
* @see app/Http/Controllers/Auth/NewPasswordController.php:35
* @route '/reset-password'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Auth\NewPasswordController::store
* @see app/Http/Controllers/Auth/NewPasswordController.php:35
* @route '/reset-password'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

const password = {
    request: Object.assign(request, request),
    email: Object.assign(email, email),
    reset: Object.assign(reset, reset),
    store: Object.assign(store, store),
}

export default password