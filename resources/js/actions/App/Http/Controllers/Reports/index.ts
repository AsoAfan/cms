import ReportController from './ReportController'
import ReportExportController from './ReportExportController'

const Reports = {
    ReportController: Object.assign(ReportController, ReportController),
    ReportExportController: Object.assign(ReportExportController, ReportExportController),
}

export default Reports