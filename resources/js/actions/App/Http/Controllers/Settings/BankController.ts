import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\BankController::index
* @see app/Http/Controllers/Settings/BankController.php:41
* @route '/settings/banks'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/settings/banks',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\BankController::index
* @see app/Http/Controllers/Settings/BankController.php:41
* @route '/settings/banks'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\BankController::index
* @see app/Http/Controllers/Settings/BankController.php:41
* @route '/settings/banks'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\BankController::index
* @see app/Http/Controllers/Settings/BankController.php:41
* @route '/settings/banks'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\BankController::index
* @see app/Http/Controllers/Settings/BankController.php:41
* @route '/settings/banks'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\BankController::index
* @see app/Http/Controllers/Settings/BankController.php:41
* @route '/settings/banks'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\BankController::index
* @see app/Http/Controllers/Settings/BankController.php:41
* @route '/settings/banks'
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
* @see \App\Http\Controllers\Settings\BankController::store
* @see app/Http/Controllers/Settings/BankController.php:70
* @route '/settings/banks'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/settings/banks',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\BankController::store
* @see app/Http/Controllers/Settings/BankController.php:70
* @route '/settings/banks'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\BankController::store
* @see app/Http/Controllers/Settings/BankController.php:70
* @route '/settings/banks'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\BankController::store
* @see app/Http/Controllers/Settings/BankController.php:70
* @route '/settings/banks'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\BankController::store
* @see app/Http/Controllers/Settings/BankController.php:70
* @route '/settings/banks'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Settings\BankController::update
* @see app/Http/Controllers/Settings/BankController.php:79
* @route '/settings/banks/{bank}'
*/
export const update = (args: { bank: number | { id: number } } | [bank: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/settings/banks/{bank}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Settings\BankController::update
* @see app/Http/Controllers/Settings/BankController.php:79
* @route '/settings/banks/{bank}'
*/
update.url = (args: { bank: number | { id: number } } | [bank: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { bank: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { bank: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            bank: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        bank: typeof args.bank === 'object'
        ? args.bank.id
        : args.bank,
    }

    return update.definition.url
            .replace('{bank}', parsedArgs.bank.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\BankController::update
* @see app/Http/Controllers/Settings/BankController.php:79
* @route '/settings/banks/{bank}'
*/
update.put = (args: { bank: number | { id: number } } | [bank: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Settings\BankController::update
* @see app/Http/Controllers/Settings/BankController.php:79
* @route '/settings/banks/{bank}'
*/
const updateForm = (args: { bank: number | { id: number } } | [bank: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\BankController::update
* @see app/Http/Controllers/Settings/BankController.php:79
* @route '/settings/banks/{bank}'
*/
updateForm.put = (args: { bank: number | { id: number } } | [bank: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

/**
* @see \App\Http\Controllers\Settings\BankController::destroy
* @see app/Http/Controllers/Settings/BankController.php:121
* @route '/settings/banks/{bank}'
*/
export const destroy = (args: { bank: number | { id: number } } | [bank: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/settings/banks/{bank}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Settings\BankController::destroy
* @see app/Http/Controllers/Settings/BankController.php:121
* @route '/settings/banks/{bank}'
*/
destroy.url = (args: { bank: number | { id: number } } | [bank: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { bank: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { bank: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            bank: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        bank: typeof args.bank === 'object'
        ? args.bank.id
        : args.bank,
    }

    return destroy.definition.url
            .replace('{bank}', parsedArgs.bank.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\BankController::destroy
* @see app/Http/Controllers/Settings/BankController.php:121
* @route '/settings/banks/{bank}'
*/
destroy.delete = (args: { bank: number | { id: number } } | [bank: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Settings\BankController::destroy
* @see app/Http/Controllers/Settings/BankController.php:121
* @route '/settings/banks/{bank}'
*/
const destroyForm = (args: { bank: number | { id: number } } | [bank: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\BankController::destroy
* @see app/Http/Controllers/Settings/BankController.php:121
* @route '/settings/banks/{bank}'
*/
destroyForm.delete = (args: { bank: number | { id: number } } | [bank: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const BankController = { index, store, update, destroy }

export default BankController