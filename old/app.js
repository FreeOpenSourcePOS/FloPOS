// Ionic Starter App
angular.module('starter', ['ionic','ionic.service.core', 'ionic-material', 'starter.controllers', 'starter.services', 'starter.config', 'ngCordova', 'chart.js', 'pascalprecht.translate', 'starter.translations', 'ionic-cache-src'])

.run(function($ionicPlatform, $timeout) {
  $ionicPlatform.ready(function() {
    // Hide the accessory bar by default (remove this to show the accessory bar above the keyboard
    // for form inputs)
    if (window.cordova && window.cordova.plugins.Keyboard) {
      window.cordova.plugins.Keyboard.hideKeyboardAccessoryBar(true);
    }
    if (window.StatusBar) {
      // org.apache.cordova.statusbar required
      StatusBar.styleDefault();
    }

    $timeout(function() {

      //OneSignal Code Starts
      // Enable to debug issues.
      // window.plugins.OneSignal.setLogLevel({logLevel: 4, visualLevel: 4});

      var notificationOpenedCallback = function(jsonData) {
        console.log('notificationOpenedCallback: ' + JSON.stringify(jsonData));
      };

      if(navigator.OneSignal) {
        navigator.OneSignal.setLogLevel(window.plugins.OneSignal.LOG_LEVEL.DEBUG, navigator.OneSignal.LOG_LEVEL.DEBUG);
        navigator.OneSignal
          .startInit("0c692785-92e2-4b3e-b974-e3460d8e701d")
          .handleNotificationOpened(notificationOpenedCallback)
          .endInit();
        console.log("OneSignal init OK");
      }
      else
        console.log("OneSignal not found");

      if(navigator.OneSignal) {
        navigator.chromeWebviewChecker.getChromeWebviewVersion()
        .then(function(enabled) { console.log(enabled); })
        .catch(function(error) { console.error(error); });
      }

      // Call syncHashedEmail anywhere in your app if you have the user's email.
      // This improves the effectiveness of OneSignal's "best-time" notification scheduling feature.
      // window.plugins.OneSignal.syncHashedEmail(userEmail);
      //OneSignal Code Ends
    });

  });
})

.config(function($stateProvider, $urlRouterProvider) {
  $stateProvider
  .state('app.signup', {
    url: '/signup',
    views: {
      'menuContent': {
        templateUrl: 'templates/signup.html',
        controller: 'SignupCtrl'
      },
      'fabContent': {
        template: ''
      }
    }
  })
  .state('app.verifycode', {
    url: '/verifycode',
    views: {
      'menuContent': {
        templateUrl: 'templates/verifycode.html',
        controller: 'VerifyCodeCtrl'
      },
      'fabContent': {
        template: ''
      }
    }
  })

  .state('app', {
    url: '/app',
    abstract: true,
    templateUrl: 'templates/menu.html',
    controller: 'AppCtrl'
  })

  .state('app.lists', {
    url: '/lists/:menulistname/:storeid',
    views: {
      'menuContent': {
        templateUrl: 'templates/lists.html',
        controller: 'ListsCtrl'
      }
    }
  })

  .state('app.components', {
    url: '/components',
    views: {
      'menuContent': {
        templateUrl: 'templates/components.html',
        controller: 'ComponentsCtrl'
      }
    }
  })

  .state('app.discountOffered', {
      url: '/discountOfferedToday',
      views: {
          'menuContent': {
              templateUrl: 'templates/discountOffered.html',
              controller: 'discountOfferedCtrl'
          }
      }
  })

  .state('app.displayOrders', {
      url: '/displayOrders',
      views: {
          'menuContent': {
              templateUrl: 'templates/displayOrders.html',
              controller: 'displayOrdersCtrl'
          }
      }
  })

  .state('app.cashStatus', {
      url: '/cashStatus',
      views: {
          'menuContent': {
              templateUrl: 'templates/cashStatus.html',
              controller: 'cashStatusCtrl'
          }
      }
  })
  .state('app.cashStatusBetweenDays', {
      url: '/cashStatusBetweenDays',
      views: {
          'menuContent': {
              templateUrl: 'templates/cashStatusBetweenDays.html',
              controller: 'cashStatusBetweenDaysCtrl'
          }
      }
  })
  .state('app.salesByEmployeesBetweenDates', {
      url: '/salesByEmployeesBetweenDates',
      views: {
          'menuContent': {
              templateUrl: 'templates/salesByEmployeesBetweenDates.html',
              controller: 'salesByEmployeesBetweenDatesCtrl'
          }
      }
  })
  .state('app.salesByTablesBetweenDates', {
    url: '/salesByTablesBetweenDates',
    views: {
        'menuContent': {
            templateUrl: 'templates/salesByTablesBetweenDates.html',
            controller: 'salesByTablesBetweenDatesCtrl'
        }
    }
  })
  .state('app.topCustomersBetweenDates', {
      url: '/topCustomersBetweenDates',
      views: {
          'menuContent': {
              templateUrl: 'templates/topCustomersBetweenDates.html',
              controller: 'topCustomersBetweenDatesCtrl'
          }
      }
  })
  .state('app.itemWiseSale', {
    url: '/sales/:reportby',
    views: {
      'menuContent': {
        templateUrl: 'templates/itemWiseSale.html',
        controller: 'itemWiseSaleCtrl'
      }
    }
  })

  .state('app.categoryWiseSale', {
    url: '/sales/:reportby',
    views: {
      'menuContent': {
        templateUrl: 'templates/itemWiseSale.html',
        controller: 'itemWiseSaleCtrl'
      }
    }
  })
  .state('app.kitchenWiseSale', {
    url: '/sales/:reportby',
    views: {
      'menuContent': {
        templateUrl: 'templates/itemWiseSale.html',
        controller: 'itemWiseSaleCtrl'
      }
    }
  })
  .state('app.settings', {
    url: '/settings',
    views: {
      'menuContent': {
        templateUrl: 'templates/settings.html',
        controller: 'SettingsCtrl'
      }
    }
  })
  .state('app.about', {
    url: '/about',
    views: {
      'menuContent': {
        templateUrl: 'templates/aboutus.html',
        controller: 'AboutCtrl'
      }
    }
  })
  .state('app.inventoryConsumedBetweenDates', {
      url: '/inventoryConsumedBetweenDates',
      views: {
          'menuContent': {
              templateUrl: 'templates/inventoryConsumedBetweenDates.html',
              controller: 'inventoryConsumedBetweenDatesCtrl'
          }
      }
  })
  .state('app.cancelledOrdersBetweenDates', {
    url: '/cancelledOrdersBetweenDates',
    views: {
        'menuContent': {
            templateUrl: 'templates/cancelledOrdersBetweenDates.html',
            controller: 'cancelledOrdersBetweenDatesCtrl'
        }
    }
  })
  .state('app.unpaidOrdersBetweenDates', {
    url: '/unpaidOrdersBetweenDates',
    views: {
        'menuContent': {
            templateUrl: 'templates/unpaidOrdersBetweenDates.html',
            controller: 'unpaidOrdersBetweenDatesCtrl'
        }
    }
  })
  ;

  // if none of the above states are matched, use this as the fallback
  $urlRouterProvider.otherwise('/app/signup');
});
