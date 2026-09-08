import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Reports\ReportController::__invoke
* @see app/Http/Controllers/Reports/ReportController.php:43
* @route '/reports'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/reports',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Reports\ReportController::__invoke
* @see app/Http/Controllers/Reports/ReportController.php:43
* @route '/reports'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Reports\ReportController::__invoke
* @see app/Http/Controllers/Reports/ReportController.php:43
* @route '/reports'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Reports\ReportController::__invoke
* @see app/Http/Controllers/Reports/ReportController.php:43
* @route '/reports'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Reports\ReportController::__invoke
* @see app/Http/Controllers/Reports/ReportController.php:43
* @route '/reports'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Reports\ReportController::__invoke
* @see app/Http/Controllers/Reports/ReportController.php:43
* @route '/reports'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Reports\ReportController::__invoke
* @see app/Http/Controllers/Reports/ReportController.php:43
* @route '/reports'
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
* @see \App\Http\Controllers\Reports\ReportExportController::__invoke
* @see app/Http/Controllers/Reports/ReportExportController.php:27
* @route '/reports/export'
*/
export const exportMethod = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(options),
    method: 'get',
})

exportMethod.definition = {
    methods: ["get","head"],
    url: '/reports/export',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Reports\ReportExportController::__invoke
* @see app/Http/Controllers/Reports/ReportExportController.php:27
* @route '/reports/export'
*/
exportMethod.url = (options?: RouteQueryOptions) => {
    return exportMethod.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Reports\ReportExportController::__invoke
* @see app/Http/Controllers/Reports/ReportExportController.php:27
* @route '/reports/export'
*/
exportMethod.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Reports\ReportExportController::__invoke
* @see app/Http/Controllers/Reports/ReportExportController.php:27
* @route '/reports/export'
*/
exportMethod.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportMethod.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Reports\ReportExportController::__invoke
* @see app/Http/Controllers/Reports/ReportExportController.php:27
* @route '/reports/export'
*/
const exportMethodForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportMethod.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Reports\ReportExportController::__invoke
* @see app/Http/Controllers/Reports/ReportExportController.php:27
* @route '/reports/export'
*/
exportMethodForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportMethod.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Reports\ReportExportController::__invoke
* @see app/Http/Controllers/Reports/ReportExportController.php:27
* @route '/reports/export'
*/
exportMethodForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportMethod.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

exportMethod.form = exportMethodForm

const reports = {
    index: Object.assign(index, index),
    export: Object.assign(exportMethod, exportMethod),
}

export default reports