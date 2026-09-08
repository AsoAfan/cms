import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\BankAdjustmentController::store
* @see app/Http/Controllers/Settings/BankAdjustmentController.php:30
* @route '/settings/banks/{bank}/balance'
*/
export const store = (args: { bank: number | { id: number } } | [bank: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/settings/banks/{bank}/balance',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\BankAdjustmentController::store
* @see app/Http/Controllers/Settings/BankAdjustmentController.php:30
* @route '/settings/banks/{bank}/balance'
*/
store.url = (args: { bank: number | { id: number } } | [bank: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{bank}', parsedArgs.bank.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\BankAdjustmentController::store
* @see app/Http/Controllers/Settings/BankAdjustmentController.php:30
* @route '/settings/banks/{bank}/balance'
*/
store.post = (args: { bank: number | { id: number } } | [bank: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\BankAdjustmentController::store
* @see app/Http/Controllers/Settings/BankAdjustmentController.php:30
* @route '/settings/banks/{bank}/balance'
*/
const storeForm = (args: { bank: number | { id: number } } | [bank: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\BankAdjustmentController::store
* @see app/Http/Controllers/Settings/BankAdjustmentController.php:30
* @route '/settings/banks/{bank}/balance'
*/
storeForm.post = (args: { bank: number | { id: number } } | [bank: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Settings\BankAdjustmentController::destroy
* @see app/Http/Controllers/Settings/BankAdjustmentController.php:41
* @route '/settings/bank-balance/{adjustment}'
*/
export const destroy = (args: { adjustment: number | { id: number } } | [adjustment: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/settings/bank-balance/{adjustment}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Settings\BankAdjustmentController::destroy
* @see app/Http/Controllers/Settings/BankAdjustmentController.php:41
* @route '/settings/bank-balance/{adjustment}'
*/
destroy.url = (args: { adjustment: number | { id: number } } | [adjustment: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { adjustment: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { adjustment: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            adjustment: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        adjustment: typeof args.adjustment === 'object'
        ? args.adjustment.id
        : args.adjustment,
    }

    return destroy.definition.url
            .replace('{adjustment}', parsedArgs.adjustment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\BankAdjustmentController::destroy
* @see app/Http/Controllers/Settings/BankAdjustmentController.php:41
* @route '/settings/bank-balance/{adjustment}'
*/
destroy.delete = (args: { adjustment: number | { id: number } } | [adjustment: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Settings\BankAdjustmentController::destroy
* @see app/Http/Controllers/Settings/BankAdjustmentController.php:41
* @route '/settings/bank-balance/{adjustment}'
*/
const destroyForm = (args: { adjustment: number | { id: number } } | [adjustment: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\BankAdjustmentController::destroy
* @see app/Http/Controllers/Settings/BankAdjustmentController.php:41
* @route '/settings/bank-balance/{adjustment}'
*/
destroyForm.delete = (args: { adjustment: number | { id: number } } | [adjustment: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const balance = {
    store: Object.assign(store, store),
    destroy: Object.assign(destroy, destroy),
}

export default balance