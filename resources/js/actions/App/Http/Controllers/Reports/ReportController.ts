import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Reports\ReportController::__invoke
* @see app/Http/Controllers/Reports/ReportController.php:43
* @route '/reports'
*/
const ReportController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ReportController.url(options),
    method: 'get',
})

ReportController.definition = {
    methods: ["get","head"],
    url: '/reports',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Reports\ReportController::__invoke
* @see app/Http/Controllers/Reports/ReportController.php:43
* @route '/reports'
*/
ReportController.url = (options?: RouteQueryOptions) => {
    return ReportController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Reports\ReportController::__invoke
* @see app/Http/Controllers/Reports/ReportController.php:43
* @route '/reports'
*/
ReportController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ReportController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Reports\ReportController::__invoke
* @see app/Http/Controllers/Reports/ReportController.php:43
* @route '/reports'
*/
ReportController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ReportController.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Reports\ReportController::__invoke
* @see app/Http/Controllers/Reports/ReportController.php:43
* @route '/reports'
*/
const ReportControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: ReportController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Reports\ReportController::__invoke
* @see app/Http/Controllers/Reports/ReportController.php:43
* @route '/reports'
*/
ReportControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: ReportController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Reports\ReportController::__invoke
* @see app/Http/Controllers/Reports/ReportController.php:43
* @route '/reports'
*/
ReportControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: ReportController.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

ReportController.form = ReportControllerForm

export default ReportController