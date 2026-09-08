import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Sales\QuickSaleController::__invoke
* @see app/Http/Controllers/Sales/QuickSaleController.php:20
* @route '/products/{product}/sell'
*/
const QuickSaleController = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: QuickSaleController.url(args, options),
    method: 'post',
})

QuickSaleController.definition = {
    methods: ["post"],
    url: '/products/{product}/sell',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Sales\QuickSaleController::__invoke
* @see app/Http/Controllers/Sales/QuickSaleController.php:20
* @route '/products/{product}/sell'
*/
QuickSaleController.url = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return QuickSaleController.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Sales\QuickSaleController::__invoke
* @see app/Http/Controllers/Sales/QuickSaleController.php:20
* @route '/products/{product}/sell'
*/
QuickSaleController.post = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: QuickSaleController.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Sales\QuickSaleController::__invoke
* @see app/Http/Controllers/Sales/QuickSaleController.php:20
* @route '/products/{product}/sell'
*/
const QuickSaleControllerForm = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: QuickSaleController.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Sales\QuickSaleController::__invoke
* @see app/Http/Controllers/Sales/QuickSaleController.php:20
* @route '/products/{product}/sell'
*/
QuickSaleControllerForm.post = (args: { product: number | { id: number } } | [product: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: QuickSaleController.url(args, options),
    method: 'post',
})

QuickSaleController.form = QuickSaleControllerForm

export default QuickSaleController