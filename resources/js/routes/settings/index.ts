import exchangeRates from './exchange-rates'
import banks from './banks'
import currencies from './currencies'
import update from './update'

const settings = {
    exchangeRates: Object.assign(exchangeRates, exchangeRates),
    banks: Object.assign(banks, banks),
    currencies: Object.assign(currencies, currencies),
    update: Object.assign(update, update),
}

export default settings