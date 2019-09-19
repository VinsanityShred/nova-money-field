<?php

namespace Vyuldashev\NovaMoneyField;

use Cknow\Money\Money as LaravelMoney;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Http\Requests\NovaRequest;
use Money\Currencies\AggregateCurrencies;
use Money\Currencies\BitcoinCurrencies;
use Money\Currencies\ISOCurrencies;
use Money\Currency;

class Money extends Number
{
    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'nova-money-field';

    public $inMinorUnits;

    public $prependCurrency;

    public function __construct($name, $currency = 'USD', $attribute = null, $resolveCallback = null)
    {
        parent::__construct($name, $attribute, $resolveCallback);

        $this->withMeta([
            'currency' => $currency,
            'subUnits' => $this->subUnits($currency),
        ]);

        $this->step(1 / $this->minorUnit($currency));

        $this->displayUsing(function ($value) use ($currency) {
            if ($value instanceof LaravelMoney) {
                return $this->getCurrencyAttribute()
                    ? $this->getCurrencyAttribute() . $value->formatByDecimal()
                    : $value->format();
            }

            return $this->getCurrencyAttribute() .
                   $this->inMinorUnits ? $value / $this->minorUnit($currency) : (float)$value;
        })->resolveUsing(function ($value) use ($currency) {
            if ($value instanceof LaravelMoney) {
                return $value->formatByDecimal();
            }

            return $this->inMinorUnits ? $value / $this->minorUnit($currency) : (float)$value;
        })->fillUsing(function (NovaRequest $request, $model, $attribute, $requestAttribute) use ($currency) {
            $currency = new Currency($this->meta()['currency']);
            $value    = $request[$requestAttribute];

            if ($this->inMinorUnits) {
                $value *= $this->minorUnit($currency);
            }

            $model->{$attribute} = $value instanceof LaravelMoney ? $value : LaravelMoney::{$currency->getCode()}($value);
        });
    }

    public function prependCurrency($attribute = 'symbol')
    {
        $this->prependCurrency = $attribute;

        return $this;
    }

    protected function getCurrencyAttribute()
    {
        switch ($this->prependCurrency) {
            case 'symbol':
            case 'name':
            case 'symbol_native':
            case 'code':
            case 'name_plural':
                $currencyData = json_decode(file_get_contents(app_path('Services/Billing/currencies.json')), true);

                return $currencyData[$this->meta()['currency']][$this->prependCurrency] ?? '';
            default:
                return '';
        }
    }

    /**
     * The value in database is store in minor units (cents for dollars).
     */
    public function storedInMinorUnits()
    {
        $this->inMinorUnits = true;

        return $this;
    }

    public function currency($currency)
    {
        if ($currency instanceof Currency) {
            $currency = $currency->getCode();
        }

        $this->withMeta(['currency' => $currency]);

        return $this;
    }

    public function locale($locale)
    {
        return $this->withMeta(['locale' => $locale]);
    }

    public function subUnits(string $currency)
    {
        return (new AggregateCurrencies([
            new ISOCurrencies(),
            new BitcoinCurrencies(),
        ]))->subunitFor(new Currency($currency));
    }

    public function minorUnit($currency)
    {
        return 10 ** $this->subUnits($currency);
    }
}
