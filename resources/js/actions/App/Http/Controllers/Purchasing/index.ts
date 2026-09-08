import QuickPurchaseController from './QuickPurchaseController'
import PurchaseController from './PurchaseController'

const Purchasing = {
    QuickPurchaseController: Object.assign(QuickPurchaseController, QuickPurchaseController),
    PurchaseController: Object.assign(PurchaseController, PurchaseController),
}

export default Purchasing