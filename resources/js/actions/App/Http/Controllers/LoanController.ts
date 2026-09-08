import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\LoanController::__invoke
* @see app/Http/Controllers/LoanController.php:34
* @route '/loans'
*/
const LoanController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: LoanController.url(options),
    method: 'get',
})

LoanController.definition = {
    methods: ["get","head"],
    url: '/loans',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LoanController::__invoke
* @see app/Http/Controllers/LoanController.php:34
* @route '/loans'
*/
LoanController.url = (options?: RouteQueryOptions) => {
    return LoanController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LoanController::__invoke
* @see app/Http/Controllers/LoanController.php:34
* @route '/loans'
*/
LoanController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: LoanController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\LoanController::__invoke
* @see app/Http/Controllers/LoanController.php:34
* @route '/loans'
*/
LoanController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: LoanController.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\LoanController::__invoke
* @see app/Http/Controllers/LoanController.php:34
* @route '/loans'
*/
const LoanControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: LoanController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\LoanController::__invoke
* @see app/Http/Controllers/LoanController.php:34
* @route '/loans'
*/
LoanControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: LoanController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\LoanController::__invoke
* @see app/Http/Controllers/LoanController.php:34
* @route '/loans'
*/
LoanControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: LoanController.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

LoanController.form = LoanControllerForm

export default LoanController