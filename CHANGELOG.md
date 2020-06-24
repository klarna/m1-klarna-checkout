
2.5.2 / 2020-06-23
==================

  * MAGE-2170 Changed label from v3 UK to v3 EU

2.5.1 / 2020-02-11
==================

  * MAGE-1538 Partial invoiced order - Cancel not possible
  * MAGE-1642 Update Merchant Portal URLs

2.5.0 / 2019-11-08
==================

  * MAGE-639 Improve order flow for customers who don't return to merchant
  * MAGE-1283 Fix issue with different shipping taxes per country
  * MAGE-1424 Removed dependency on serialize/unserialize

2.4.2 / 2019-10-10
==================

  * MAGE-1176 Code cleanup per MEQP1 code sniffer rules

2.4.1 / 2019-10-04
==================

  * MAGE-713 Update config settings to not list as viewable at store view level
  * MAGE-946 Fix endless loop situation because quote hasn't been set
  * MAGE-1115 Setting the attributes if they are not empty
  * MAGE-1174 Add support for order total variance
  * MAGE-1174 Fix issue with discount rounding

2.4.0 / 2019-06-20
==================

  * MAGE-271 Add support for Klarna Shipping Service

2.3.0 / 2019-03-26
==================

  * MAGE-181 Single place to enable KCO
  * MAGE-237 Strip last four digits from postal code on address update for US zip+4
  * MAGE-252 Fix 0.01 cent issue on the discount value
  * MAGE-253 Fix issue with order total not matching for order (difference in tax)
  * MAGE-280 Fix missing collect totals call
  * MAGE-313 Update base en_US and system xml
  * MAGE-399 Apply provided patch to allow PayPal to redirect with store codes

2.2.2 / 2018-12-20
==================

  * MAGE-215 Set product/image to NULL if empty
  * MAGE-216 Use empty() instead of checking for ''

2.2.0 / 2018-10-18
==================

  * PI-438 Fix discount on tax issue
  * PI-456 Fix 0.01 rounding issue with discount when switching shipping method
  * PI-472 Add better logging when VALIDATE callback fails
  * PPI-425 Add sending of ALL order line items in OM calls (capture & refund)
  * PPI-411 Ported the v3 specific stuff from the DACH config.xml.
  * PPI-502 Add support for enabling National identification number as mandatory

2.1.0 / 2018-08-24
==================

  * PI-395 Fix GA code not working on success page
  * PPI-258 Add link to Merchant Portal
  * PPI-318 Add support for Fixed Product Tax
  * PPI-401 Add support for multiple checkboxes in iframe
  * PPI-404 Add onboarding link
  * PPI-422 Add display of customer's selected payment method when viewing order

2.0.0 / 2018-06-29
==================

  * PI-91 Magento support shipping_info update upon shipment
  * PPI-238 Add additional product details to products (URL & Image)
  * PPI-372 Add Fraud STOPPED notification type handling
  * PPI-401 Support multiple checkboxes
  * PPI-406 M1 Fix compatibility between KCO and KP

1.7.0 / 2018-03-28
==================

  * PPI-56 Add support for EMD
  * PPI-315 Include Magento order number on KCO confirmation page
  * Include magento default order success information
  * PPI-253 Migrate DACH functionality to kco module
  * Add config control for telephone number mandatory

1.6.0 / 2018-02-20
==================

  * Add support for recurring orders
  * Add email notice for failed orders

1.5.0 / 2018-01-23
==================

  * Add logging to all exceptions
  * Add b2b support
  * Fix issue with quote object not being set
  * update copyright message

v1.4.4 / 2017-10-09
===================

  * Add some German translations
  * Remove unneeded conditional from customer registration checkbox

v1.4.3 / 2017-09-08
===================

  * Update code for Marketplace release

v1.4.2 / 2017-08-17
===================

  * Fix title mandatory to allow disabling
  * Use round() instead of casting to int

v1.4.1 / 2017-06-13
===================

  * Fix missing class property
  * Fix tax rate stuff on capture
  * Fix so that shipping address always gets sent to Klarna

v1.4.0 / 2017-04-20
===================

  * Removed address update call back. Now a JS event is used to create a new session on change event.
  * Added cancel order button on Klarna payment view
  * Misc cleanup and added navigation buttons on Klarna pages
  * PPI-210 Updated tax total calculation to work with Klarna's validation. Updated discount item to use the weighted average of the order item tax rates.
  * PPI-213 Tax totals are now using default Magento totals instead of recalculating them. Changed method that converts floats to integers for Klarna API to no round, to truncate.
  * PPI-205 Changed street name logic to prioritize street_name over concatenating other attributes
  * PPI-190 Added additional user agent details to the rest client
  * Disabled shipping methods in iframe for NL
  * Added support for MOTO

v1.3.7 / 2016-12-12
===================

  * PPI-148 Further fixes to order line logic to fix configurable products Added code comments for easier debugging.

v1.3.6 / 2016-11-23
===================

  * Fixed simple products not being able to be captured due to change with bundled products

v1.3.5 / 2016-11-15
===================

  * Fixed fixed amount bundled products
  * PPI-31 Fixed bundles for Kred
  * PPI-104 Added checkout border radius style
  * Fixed bug that caused shipping address not to save in some countries when billing is set to be the same as shipping.
  * Changed date format depending on Magento version for dob setting from api response
  * Changed DOB to use store local to fix date value on place order
  * Changed gender ID to use labels from attribute options for support for all versions of Magento.

v1.3.4 / 2016-09-30
===================

  * Add support for DOB and Gender requirements in checkout for certain markets.

v1.3.3 / 2016-09-21
===================

  * Fixed issue for failed push notification observer when the order is booked before the push comes in a PHP fatal error can be thrown.

v1.3.2 / 2016-08-31
===================

  * Added additional observer method to core module to allow the response code to be modified. The Kred module now returns header 200 if the push request was queued
  * Added modman file
  * Bundled products on previous version of Magento (below 1.9) will now use simple items rather than a main product.

v1.3.1 / 2016-07-27
===================

  * Fixed bundles but with capture and refund for Magento ce1.9+
  * Changed partial payment event name with type
  * Updated partial payment event to have checkout type
  * Unified event observers for disabling partial payments events to streamline integration.

v1.3 / 2016-07-11
=================

  * Added observer for API option to disable partial captures. (This will be used by the Kred add-on.)
  * Merchant reference update now happens on push regardless of pending status
  * Added API option to disable partial captures. (This will be used by the Kred add-on.)

v1.2.3 / 2016-05-24
===================

  * Fixed bundle product totals for older version of Magento
  * Fixed bundled products when the bundle has a higher qty than 1
  * Created order total validation on confirmation and validation call. Force Magento to use the addresses of Klarna on confirmation.

v1.2.2 / 2016-05-12
===================

  * Made phone always mandatory if it's available to be disabled. Magento always requires a phone number.

v1.2.1 / 2016-05-05
===================

  * Fixed issue where failed captures are not communicated properly to Magento to prevent invoice creation.

v1.2 / 2016-04-28
=================

  * Fixed address validation error on CE 1.6
  * Fixed bundles in older version of Magento (less than v1.9)
  * Updating address error messaging response
  * Prevent empty shipping method codes

v1.1 / 2016-03-18
=================

  * Fixed divide by zero problem on item tax calculation
  * Added backwards compatibility for new order emails
  * Fixed push notification issues
  * Fixed shipping calculation on builder shipping total collector
  * Added notification url with fraud status update

