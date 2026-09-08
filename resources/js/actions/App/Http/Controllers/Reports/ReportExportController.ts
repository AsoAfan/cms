import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Reports\ReportExportController::__invoke
* @see app/Http/Controllers/Reports/ReportExportController.php:27
* @route '/reports/export'
*/
const ReportExportController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ReportExportController.url(options),
    method: 'get',
})

ReportExportController.definition = {
    methods: ["get","head"],
    url: '/reports/export',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Reports\ReportExportController::__invoke
* @see app/Http/Controllers/Reports/ReportExportController.php:27
* @route '/reports/export'
*/
ReportExportController.url = (options?: RouteQueryOptions) => {
    return ReportExportController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Reports\ReportExportController::__invoke
* @see app/Http/Controllers/Reports/ReportExportController.php:27
* @route '/reports/export'
*/
ReportExportController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ReportExportController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Reports\ReportExportController::__invoke
* @see app/Http/Controllers/Reports/ReportExportController.php:27
* @route '/reports/export'
*/
ReportExportController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ReportExportController.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Reports\ReportExportController::__invoke
* @see app/Http/Controllers/Reports/ReportExportController.php:27
* @route '/reports/export'
*/
const ReportExportControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: ReportExportController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Reports\ReportExportController::__invoke
* @see app/Http/Controllers/Reports/ReportExportController.php:27
* @route '/reports/export'
*/
ReportExportControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: ReportExportController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Reports\ReportExportController::__invoke
* @see app/Http/Controllers/Reports/ReportExportController.php:27
* @route '/reports/export'
*/
ReportExportControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: ReportExportController.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

ReportExportController.form = ReportExportControllerForm

export default ReportExportController