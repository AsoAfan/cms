import QuickSaleController from './QuickSaleController'
import SaleController from './SaleController'

const Sales = {
    QuickSaleController: Object.assign(QuickSaleController, QuickSaleController),
    SaleController: Object.assign(SaleController, SaleController),
}

export default Sales