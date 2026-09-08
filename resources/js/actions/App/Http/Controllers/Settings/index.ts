import ExchangeRateController from './ExchangeRateController'
import BankController from './BankController'
import BankAdjustmentController from './BankAdjustmentController'
import CurrencyController from './CurrencyController'
import UpdateController from './UpdateController'

const Settings = {
    ExchangeRateController: Object.assign(ExchangeRateController, ExchangeRateController),
    BankController: Object.assign(BankController, BankController),
    BankAdjustmentController: Object.assign(BankAdjustmentController, BankAdjustmentController),
    CurrencyController: Object.assign(CurrencyController, CurrencyController),
    UpdateController: Object.assign(UpdateController, UpdateController),
}

export default Settings