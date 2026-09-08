import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\BankTransferController::store
* @see app/Http/Controllers/Settings/BankTransferController.php:28
* @route '/settings/banks/transfers'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/settings/banks/transfers',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\BankTransferController::store
* @see app/Http/Controllers/Settings/BankTransferController.php:28
* @route '/settings/banks/transfers'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\BankTransferController::store
* @see app/Http/Controllers/Settings/BankTransferController.php:28
* @route '/settings/banks/transfers'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\BankTransferController::store
* @see app/Http/Controllers/Settings/BankTransferController.php:28
* @route '/settings/banks/transfers'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\BankTransferController::store
* @see app/Http/Controllers/Settings/BankTransferController.php:28
* @route '/settings/banks/transfers'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Settings\BankTransferController::destroy
* @see app/Http/Controllers/Settings/BankTransferController.php:39
* @route '/settings/banks/transfers/{transfer}'
*/
export const destroy = (args: { transfer: number | { id: number } } | [transfer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/settings/banks/transfers/{transfer}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Settings\BankTransferController::destroy
* @see app/Http/Controllers/Settings/BankTransferController.php:39
* @route '/settings/banks/transfers/{transfer}'
*/
destroy.url = (args: { transfer: number | { id: number } } | [transfer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { transfer: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { transfer: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            transfer: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        transfer: typeof args.transfer === 'object'
        ? args.transfer.id
        : args.transfer,
    }

    return destroy.definition.url
            .replace('{transfer}', parsedArgs.transfer.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\BankTransferController::destroy
* @see app/Http/Controllers/Settings/BankTransferController.php:39
* @route '/settings/banks/transfers/{transfer}'
*/
destroy.delete = (args: { transfer: number | { id: number } } | [transfer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Settings\BankTransferController::destroy
* @see app/Http/Controllers/Settings/BankTransferController.php:39
* @route '/settings/banks/transfers/{transfer}'
*/
const destroyForm = (args: { transfer: number | { id: number } } | [transfer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\BankTransferController::destroy
* @see app/Http/Controllers/Settings/BankTransferController.php:39
* @route '/settings/banks/transfers/{transfer}'
*/
destroyForm.delete = (args: { transfer: number | { id: number } } | [transfer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const BankTransferController = { store, destroy }

export default BankTransferController