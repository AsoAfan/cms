import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Purchasing\QuickPurchaseController::__invoke
* @see app/Http/Controllers/Purchasing/QuickPurchaseController.php:20
* @route '/products/{product}/purchase'
*/
const QuickPurchaseController = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: QuickPurchaseController.url(args, options),
    method: 'post',
})

QuickPurchaseController.definition = {
    methods: ["post"],
    url: '/products/{product}/purchase',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Purchasing\QuickPurchaseController::__invoke
* @see app/Http/Controllers/Purchasing/QuickPurchaseController.php:20
* @route '/products/{product}/purchase'
*/
QuickPurchaseController.url = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { product: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { product: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            product: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        product: typeof args.product === 'object'
        ? args.product.id
        : args.product,
    }

    return QuickPurchaseController.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Purchasing\QuickPurchaseController::__invoke
* @see app/Http/Controllers/Purchasing/QuickPurchaseController.php:20
* @route '/products/{product}/purchase'
*/
QuickPurchaseController.post = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: QuickPurchaseController.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Purchasing\QuickPurchaseController::__invoke
* @see app/Http/Controllers/Purchasing/QuickPurchaseController.php:20
* @route '/products/{product}/purchase'
*/
const QuickPurchaseControllerForm = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: QuickPurchaseController.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Purchasing\QuickPurchaseController::__invoke
* @see app/Http/Controllers/Purchasing/QuickPurchaseController.php:20
* @route '/products/{product}/purchase'
*/
QuickPurchaseControllerForm.post = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: QuickPurchaseController.url(args, options),
    method: 'post',
})

QuickPurchaseController.form = QuickPurchaseControllerForm

export default QuickPurchaseController