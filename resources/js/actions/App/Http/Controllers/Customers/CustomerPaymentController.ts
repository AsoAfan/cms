import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Customers\CustomerPaymentController::store
* @see app/Http/Controllers/Customers/CustomerPaymentController.php:22
* @route '/customers/{customer}/payments'
*/
export const store = (args: { customer: number | { id: number } } | [customer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/customers/{customer}/payments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Customers\CustomerPaymentController::store
* @see app/Http/Controllers/Customers/CustomerPaymentController.php:22
* @route '/customers/{customer}/payments'
*/
store.url = (args: { customer: number | { id: number } } | [customer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { customer: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { customer: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            customer: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        customer: typeof args.customer === 'object'
        ? args.customer.id
        : args.customer,
    }

    return store.definition.url
            .replace('{customer}', parsedArgs.customer.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Customers\CustomerPaymentController::store
* @see app/Http/Controllers/Customers/CustomerPaymentController.php:22
* @route '/customers/{customer}/payments'
*/
store.post = (args: { customer: number | { id: number } } | [customer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Customers\CustomerPaymentController::store
* @see app/Http/Controllers/Customers/CustomerPaymentController.php:22
* @route '/customers/{customer}/payments'
*/
const storeForm = (args: { customer: number | { id: number } } | [customer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Customers\CustomerPaymentController::store
* @see app/Http/Controllers/Customers/CustomerPaymentController.php:22
* @route '/customers/{customer}/payments'
*/
storeForm.post = (args: { customer: number | { id: number } } | [customer: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Customers\CustomerPaymentController::destroy
* @see app/Http/Controllers/Customers/CustomerPaymentController.php:48
* @route '/customers/{customer}/payments/{payment}'
*/
export const destroy = (args: { customer: number | { id: number }, payment: number | { id: number } } | [customer: number | { id: number }, payment: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/customers/{customer}/payments/{payment}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Customers\CustomerPaymentController::destroy
* @see app/Http/Controllers/Customers/CustomerPaymentController.php:48
* @route '/customers/{customer}/payments/{payment}'
*/
destroy.url = (args: { customer: number | { id: number }, payment: number | { id: number } } | [customer: number | { id: number }, payment: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            customer: args[0],
            payment: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        customer: typeof args.customer === 'object'
        ? args.customer.id
        : args.customer,
        payment: typeof args.payment === 'object'
        ? args.payment.id
        : args.payment,
    }

    return destroy.definition.url
            .replace('{customer}', parsedArgs.customer.toString())
            .replace('{payment}', parsedArgs.payment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Customers\CustomerPaymentController::destroy
* @see app/Http/Controllers/Customers/CustomerPaymentController.php:48
* @route '/customers/{customer}/payments/{payment}'
*/
destroy.delete = (args: { customer: number | { id: number }, payment: number | { id: number } } | [customer: number | { id: number }, payment: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Customers\CustomerPaymentController::destroy
* @see app/Http/Controllers/Customers/CustomerPaymentController.php:48
* @route '/customers/{customer}/payments/{payment}'
*/
const destroyForm = (args: { customer: number | { id: number }, payment: number | { id: number } } | [customer: number | { id: number }, payment: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Customers\CustomerPaymentController::destroy
* @see app/Http/Controllers/Customers/CustomerPaymentController.php:48
* @route '/customers/{customer}/payments/{payment}'
*/
destroyForm.delete = (args: { customer: number | { id: number }, payment: number | { id: number } } | [customer: number | { id: number }, payment: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const CustomerPaymentController = { store, destroy }

export default CustomerPaymentController