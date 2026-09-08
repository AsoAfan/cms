import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\CurrencyController::store
* @see app/Http/Controllers/Settings/CurrencyController.php:23
* @route '/settings/currencies'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/settings/currencies',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\CurrencyController::store
* @see app/Http/Controllers/Settings/CurrencyController.php:23
* @route '/settings/currencies'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\CurrencyController::store
* @see app/Http/Controllers/Settings/CurrencyController.php:23
* @route '/settings/currencies'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\CurrencyController::store
* @see app/Http/Controllers/Settings/CurrencyController.php:23
* @route '/settings/currencies'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\CurrencyController::store
* @see app/Http/Controllers/Settings/CurrencyController.php:23
* @route '/settings/currencies'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Settings\CurrencyController::makeDefault
* @see app/Http/Controllers/Settings/CurrencyController.php:45
* @route '/settings/currencies/{currency}/default'
*/
export const makeDefault = (args: { currency: number | { id: number } } | [currency: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: makeDefault.url(args, options),
    method: 'post',
})

makeDefault.definition = {
    methods: ["post"],
    url: '/settings/currencies/{currency}/default',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\CurrencyController::makeDefault
* @see app/Http/Controllers/Settings/CurrencyController.php:45
* @route '/settings/currencies/{currency}/default'
*/
makeDefault.url = (args: { currency: number | { id: number } } | [currency: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { currency: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { currency: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            currency: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        currency: typeof args.currency === 'object'
        ? args.currency.id
        : args.currency,
    }

    return makeDefault.definition.url
            .replace('{currency}', parsedArgs.currency.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\CurrencyController::makeDefault
* @see app/Http/Controllers/Settings/CurrencyController.php:45
* @route '/settings/currencies/{currency}/default'
*/
makeDefault.post = (args: { currency: number | { id: number } } | [currency: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: makeDefault.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\CurrencyController::makeDefault
* @see app/Http/Controllers/Settings/CurrencyController.php:45
* @route '/settings/currencies/{currency}/default'
*/
const makeDefaultForm = (args: { currency: number | { id: number } } | [currency: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: makeDefault.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\CurrencyController::makeDefault
* @see app/Http/Controllers/Settings/CurrencyController.php:45
* @route '/settings/currencies/{currency}/default'
*/
makeDefaultForm.post = (args: { currency: number | { id: number } } | [currency: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: makeDefault.url(args, options),
    method: 'post',
})

makeDefault.form = makeDefaultForm

/**
* @see \App\Http\Controllers\Settings\CurrencyController::destroy
* @see app/Http/Controllers/Settings/CurrencyController.php:60
* @route '/settings/currencies/{currency}'
*/
export const destroy = (args: { currency: number | { id: number } } | [currency: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/settings/currencies/{currency}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Settings\CurrencyController::destroy
* @see app/Http/Controllers/Settings/CurrencyController.php:60
* @route '/settings/currencies/{currency}'
*/
destroy.url = (args: { currency: number | { id: number } } | [currency: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { currency: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { currency: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            currency: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        currency: typeof args.currency === 'object'
        ? args.currency.id
        : args.currency,
    }

    return destroy.definition.url
            .replace('{currency}', parsedArgs.currency.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\CurrencyController::destroy
* @see app/Http/Controllers/Settings/CurrencyController.php:60
* @route '/settings/currencies/{currency}'
*/
destroy.delete = (args: { currency: number | { id: number } } | [currency: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Settings\CurrencyController::destroy
* @see app/Http/Controllers/Settings/CurrencyController.php:60
* @route '/settings/currencies/{currency}'
*/
const destroyForm = (args: { currency: number | { id: number } } | [currency: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\CurrencyController::destroy
* @see app/Http/Controllers/Settings/CurrencyController.php:60
* @route '/settings/currencies/{currency}'
*/
destroyForm.delete = (args: { currency: number | { id: number } } | [currency: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const CurrencyController = { store, makeDefault, destroy }

export default CurrencyController