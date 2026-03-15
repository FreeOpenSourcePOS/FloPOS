angular.module('starter.config', [])
.constant('GCM_SENDER_ID', '574597432927')
.constant('SERVER', {
  urlformobilecode: 'https://reportingserver.codify.tech/module.php?to=MobileAPPAPI.verifyMobile&callback=JSON_CALLBACK',
  urlforcodesubmittion: 'https://reportingserver.codify.tech/module.php?to=MobileAPPAPI.verifyMobileCode&callback=JSON_CALLBACK',
  urlfornewstores: 'https://reportingserver.codify.tech/module.php?to=MobileAPPAPI.apiGetAuthorisedStores&callback=JSON_CALLBACK',
  urlAPIforGetDiscountOffered: 'https://reportingserver.codify.tech/module.php?to=MobileAPPAPI.apiGetDiscountedConsumerOrdersByStore&callback=JSON_CALLBACK',
  urlAPIforGetCashStatus: 'https://reportingserver.codify.tech/module.php?to=MobileAPPAPI.apiGetCashStatusforGivenDayByStore&callback=JSON_CALLBACK',
  urlAPIforGetItemWiseSale: 'https://reportingserver.codify.tech/module.php?to=MobileAPPAPI.apiGetOrdersByItemsBetweenDatesByStore&callback=JSON_CALLBACK',
  urlAPIforGetListOfOrders: 'https://reportingserver.codify.tech/module.php?to=MobileAPPAPI.callStore&call=API.getListOfOrders&callback=JSON_CALLBACK',
  urlAPIforGetCashStatusBetweenDays: 'https://reportingserver.codify.tech/module.php?to=MobileAPPAPI.callStore&call=API.getCashStatusBetweenDays&callback=JSON_CALLBACK',
  urlAPIforGetSalesByEmployeesBetweenDates: 'https://reportingserver.codify.tech/module.php?to=MobileAPPAPI.callStore&call=API.getSalesByEmployeesBetweenDates&callback=JSON_CALLBACK',
  urlAPIforGetTopCustomersBetweenDates: 'https://reportingserver.codify.tech/module.php?to=MobileAPPAPI.callStore&call=API.getTopCustomersBetweenDates&callback=JSON_CALLBACK',
  urlAPIforGetInventoryConsumedBetweenDates: 'https://reportingserver.codify.tech/module.php?to=MobileAPPAPI.callStore&call=API.getInventoryConsumed&callback=JSON_CALLBACK',
  urlAPIforGetTablesSummaryBetweenDays: 'https://reportingserver.codify.tech/module.php?to=MobileAPPAPI.callStore&call=API.getTablesSummaryBetweenDays&callback=JSON_CALLBACK',
  urlAPIforGetCancelledOrders: 'https://reportingserver.codify.tech/module.php?to=MobileAPPAPI.callStore&call=API.getListOfOrders&callback=JSON_CALLBACK',
  urlAPIforGetUnpaidOrders: 'https://reportingserver.codify.tech/module.php?to=MobileAPPAPI.callStore&call=API.getListOfOrders&callback=JSON_CALLBACK'
})
;
