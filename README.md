![](https://icepay.com/app/themes/icepay/dist/images/logos/logo_icepay.svg)

NOTE: PLEASE USE THE NEW API PLUGINS ON https://www.sellxed.com/shop/en/eur/extensions/module/payment-service-provider/icepay.html, WITH THE OLD API NOT ALL PAYMENT METHODS ARE SUPPORTED

## Payment Module for PrestaShop

Make payments in your PrestaShop webshop possible. Download the special Prestashop webshop module [here](https://github.com/ICEPAYdev/Prestashop/releases), and you will be able to offer the most frequently used national and international online payment methods.

The master branch may not be stable. See the [release list](https://github.com/ICEPAY/Prestashop/releases) for stable versions of this module.

Installation and configuration instruction is available [here](https://github.com/ICEPAY/Prestashop/wiki).

### Requirements

Type       | Value
---------- | ------------------
PrestaShop | 1.7.0.0 - 1.7.4.3

### License

Our module is available under the BSD-2-Clause. See the [LICENSE](https://github.com/ICEPAY/Prestashop/blob/master/LICENSE.md) file for more information.

### Changelog

Version      | Release date   | Changes
------------ | -------------- | -------------------------------------
2.3.1        | 24/10/2018     | New payment methods
2.3.0 alpha 1| 11/01/2018     | Compatibility with Prestashop 1.7
2.2.0 beta 4 | 26/09/2016    | PHP 7.0 compatibility issues resolved
2.2.0 beta 3 | 05/09/2016     | Dutch transnslations added
2.2.0 beta 2 | 18/08/2016     | BugFix: Autoloader conflicting with other modules using the same calss name. Compatibility with PHP 5.2 (namespaces removed)
2.2.0 beta 1 | 11/08/2016     | REST API version
2.1.2        | 23/09/2015     | On some hosts, check for new updates results in a error.
2.1.1        | 21/09/2015     | This emergency release disables SSL intermediate certificate checking to allow merchants on shared hosting providers to continue processing transactions while the hosting providers update their certificate store.
2.1.0        | 23/08/2015     | Compatiblity with PrestaShop 1.6.1.1.<br>Using Bootstrap on configuration page.

### Contributing

* Fork it
* Create your feature branch (`git checkout -b my-new-feature`)
* Commit your changes (`git commit -am 'Add some feature'`)
* Push to the branch (`git push origin my-new-feature`)
* Create new Pull Request

### Bug report

If you found a repeatable bug, and troubleshooting tips didn't help, then be sure to [search existing issues](https://github.com/icepay/Prestashop/issues) first. Include steps to consistently reproduce the problem, actual vs. expected results, screenshots, and your PrestaShop version and Payment module version number. Disable all other third party extensions to verify the issue is a bug in the Payment module.
