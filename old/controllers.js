/* global angular, document, window */
var app = angular.module('starter.controllers', [])

.controller('AppCtrl', function($scope, $ionicModal, $ionicPopover, $ionicLoading, $ionicPlatform, $timeout,  $location) {
  // Form data for the login modal
  $scope.loginData = {};
  $scope.isExpanded = false;
  $scope.hasHeaderFabLeft = false;
  $scope.hasHeaderFabRight = false;

  $scope.setCurrentMobile = function(mobile) {
    $scope.mobile = mobile;
  };

  $ionicPlatform.ready(function() {
    $scope.deviceInformation = ionic.Platform.device();
    $scope.currentPlatform = ionic.Platform.platform() ;
    $scope.currentPlatformVersion = ionic.Platform.version();
    $scope.currentUA = ionic.Platform.ua;
    console.log(ionic.Platform.platform);

    try{
      var device = window.cordova.cordovaDevice.getDevice();
      if(device)
      {
        $scope.manufacturer = device.manufacturer;
        $scope.model = device.model;
        $scope.platform = device.platform;
        $scope.uuid = device.uuid;
      }
      else
        console.log("Device not defined");
    } catch(e) {
        console.log("Device check exception");
    }
  });

  ////////////////////////////////////////
  // Layout Methods
  ////////////////////////////////////////

  $scope.hideNavBar = function() {
      document.getElementsByTagName('ion-nav-bar')[0].style.display = 'none';
  };

  $scope.showNavBar = function() {
      document.getElementsByTagName('ion-nav-bar')[0].style.display = 'block';
  };

  $scope.noHeader = function() {
      var content = document.getElementsByTagName('ion-content');
      for (var i = 0; i < content.length; i++) {
          if (content[i].classList.contains('has-header')) {
              content[i].classList.toggle('has-header');
          }
      }
  };

  $scope.setExpanded = function(bool) {
      $scope.isExpanded = bool;
  };

  $scope.setHeaderFab = function(location) {
      var hasHeaderFabLeft = false;
      var hasHeaderFabRight = false;

      switch (location) {
          case 'left':
              hasHeaderFabLeft = true;
              break;
          case 'right':
              hasHeaderFabRight = true;
              break;
      }

      $scope.hasHeaderFabLeft = hasHeaderFabLeft;
      $scope.hasHeaderFabRight = hasHeaderFabRight;
  };

  $scope.hasHeader = function() {
      var content = document.getElementsByTagName('ion-content');
      for (var i = 0; i < content.length; i++) {
          if (!content[i].classList.contains('has-header')) {
              content[i].classList.toggle('has-header');
          }
      }

  };

  $scope.hideHeader = function() {
      $scope.hideNavBar();
      $scope.noHeader();
  };

  $scope.showHeader = function() {
      $scope.showNavBar();
      $scope.hasHeader();
  };

  $scope.clearFabs = function() {
      var fabs = document.getElementsByClassName('button-fab');
      if (fabs.length && fabs.length > 1) {
          fabs[0].remove();
      }
  };
  $scope.loading = function() {
      $ionicLoading.show({
          template: '<div class="loader"><svg class="circular"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>Loading'
      });

      // For example's sake, hide the sheet after two seconds
      $timeout(function() {
          $ionicLoading.hide();
      }, 2000);
  };
})

.controller('SignupCtrl', function($scope, $state, $timeout, $stateParams, ionicMaterialInk, $ionicPopup, $ionicLoading, $ionicHistory, AuthService, $translate) {
	$scope.mobile = null;
	$scope.user = {};

  $scope.$parent.clearFabs();
  $timeout(function() {
    $scope.$parent.hideHeader();
    $scope.$parent.clearFabs();
    ionicMaterialInk.displayEffect();
  }, 0);

  console.log('Sign UP called: '+$scope.mobile);

  console.log(AuthService.getLanguage());
  $translate.use(AuthService.getLanguage());

  if(AuthService.isAuthorized())
  {
    console.log('Auth OK, Token Auth: '+AuthService.getToken());
    $ionicHistory.nextViewOptions({
      disableAnimate: true,
      disableBack: true
    });

    //Header not showing up
    $timeout(function() {
      $scope.$parent.showHeader();
      $scope.$parent.clearFabs();
      ionicMaterialInk.displayEffect();
    }, 0);
		$state.go('app.components');
  }
  else
  {
    console.log('Not authorised');
    //do we have mobile alreaady available
    $scope.setCurrentMobile(AuthService.getMobile());
    if($scope.mobile === '' || $scope.mobile === null || typeof $scope.mobile == 'undefined')
    {
      console.log('Get Mobile null: '+$scope.mobile);
      //alert('Get Mobile says we are null: '+ $scope.mobile);
      //stay on this page
    }
    else
    {
      console.log('Get Mobile OK go to verify: '+$scope.mobile);
      console.log('Get Mobile from Auth: '+AuthService.getMobile());
      $state.go('app.verifycode');
    }

    ///mobile code generation
    $scope.doSignUp = function()
    {
      if(!$scope.user.mobile)
      {
        var alertPopup = $ionicPopup.alert({
            title: 'Invalid Mobile Phone Number '+$scope.user.mobile,
            template: "Please check your mobile number then retry!"
          });
      }
      else
      {
        $ionicLoading.show({
          template: '<div class="loader"><svg class="circular"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>Loading'
        });
        AuthService.generateLoginCode($scope.user.mobile).then(function(authenticated) {
          $scope.setCurrentMobile($scope.user.mobile);
          $ionicLoading.hide();
          $state.go('app.verifycode');
        }, function(err) {
          $ionicLoading.hide();
          var alertPopup = $ionicPopup.alert({
            title: 'Unable to Generate SMS Code!',
            template: err+"\nPlease check your Internet connection and mobile number then retry!"
          });
        });
      }
    };
  }
})

.controller('VerifyCodeCtrl', function($scope, $state, $timeout, $ionicPopup, $ionicLoading, ionicMaterialInk, $ionicHistory, AuthService) {
	$scope.user = {};
  $scope.setCurrentMobile(AuthService.getMobile());
  $scope.$parent.clearFabs();
  $timeout(function() {
    $scope.$parent.hideHeader();
    $scope.$parent.clearFabs();
    ionicMaterialInk.displayEffect();
  }, 0);

  //re enter mobile if so desired
  $scope.doReEnterMobile = function()
  {
    $scope.setCurrentMobile('');
    AuthService.setUserMobile('');
    $state.go('app.signup');
  };

  //verify PIN
	$scope.doVerifySMSPIN = function()
  {
    if(!$scope.user.pin)
    {
      var alertPopup = $ionicPopup.alert({
          title: 'Invalid SMS PIN',
          template: "Please check the SMS PIN received on your mobile then retry!"
        });
    }
    else
    {
		  $ionicLoading.show({
			  template: '<div class="loader"><svg class="circular"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>Loading'
		  });
      AuthService.login($scope.mobile, $scope.user.pin).then(function(authenticated) {
        $scope.setCurrentMobile($scope.user.mobile);
				$ionicLoading.hide();
        $ionicHistory.nextViewOptions({
          disableBack: true
        });
        //Clear cahced views immidiate after successful login
        $ionicHistory.clearCache().then(function()
        {
          $state.go('app.components').then(function()
            { $ionicHistory.clearCache()
          });
        });

    		//$state.go('app.components');
      }, function(err) {
				$ionicLoading.hide();
        var alertPopup = $ionicPopup.alert({
          title: 'Unable to Verify',
          template: err+"\nPlease check your Internet connection and SMS PIN then retry!"
        });
      });
    }
	};
})

.controller('ComponentsCtrl', function($scope, $http, $timeout, $stateParams, ionicMaterialInk, $ionicNavBarDelegate, $translate, AuthService, $interval) {
  //$ionicNavBarDelegate.showBackButton(false);
  console.log(AuthService.getLanguage());
  $translate.use(AuthService.getLanguage());

	$scope.main_menu = [];

  //See if we have ever requested any services/ complaints, then show the Old Reuqests button

  $scope.main_menu = AuthService.getRestaurantJSON();

  $interval(function(){
    $scope.main_menu = AuthService.getRestaurantJSON();
  }, 5000);

  console.log("Menu: "+JSON.stringify($scope.main_menu));
  $timeout(function() {
    $scope.$parent.showHeader();
    $scope.$parent.clearFabs();
    ionicMaterialInk.displayEffect();
  }, 0);

  $scope.doListRefresh = function() {
    //console.log("I am Working...");
    AuthService.fetchStores();
    $scope.main_menu = AuthService.getRestaurantJSON();
    $scope.$broadcast('scroll.refreshComplete');
  }
})

.controller('ListsCtrl', function($scope, $rootScope, $http, $stateParams, $timeout, ionicMaterialInk, ionicMaterialMotion) {

	$scope.menu_list = [];
  $scope.storeid = [];

	$scope.menulistname = $stateParams.menulistname;
	$scope.storeid = $stateParams.storeid;
  console.log($stateParams.menulistname+" : "+$scope.storeid);
  if($stateParams.storeid)
    $rootScope.storeid = $scope.storeid;
  else
    $rootScope.storeid = null;

	$http.get($scope.menulistname).success(function(response) {
		$scope.menu_list = response;
    console.log($scope.menulistname+" Menu List loaded");
	});

  var reset = function() {
      var inClass = document.querySelectorAll('.in');
      for (var i = 0; i < inClass.length; i++) {
          inClass[i].classList.remove('in');
          inClass[i].removeAttribute('style');
      }
      var done = document.querySelectorAll('.done');
      for (var j = 0; j < done.length; j++) {
          done[j].classList.remove('done');
          done[j].removeAttribute('style');
      }
      var ionList = document.getElementsByTagName('ion-list');
      for (var k = 0; k < ionList.length; k++) {
          var toRemove = ionList[k].className;
          if (/animate-/.test(toRemove)) {
              ionList[k].className = ionList[k].className.replace(/(?:^|\s)animate-\S*(?:$|\s)/, '');
          }
      }
  };

  $scope.fadeSlideInRight = function() {
      reset();
      document.getElementsByTagName('ion-list')[0].className += ' animate-fade-slide-in-right';
      setTimeout(function() {
          ionicMaterialMotion.fadeSlideInRight();
      }, 500);
  };

  $timeout(function() {
    $scope.$parent.showHeader();
    $scope.$parent.clearFabs();
    ionicMaterialInk.displayEffect();
  }, 0);
  //$scope.fadeSlideInRight();
})

.controller('SettingsCtrl', function($scope, $state, $timeout, $stateParams, $ionicActionSheet, $timeout, $ionicLoading, $ionicModal, $ionicPopup,  ionicMaterialInk, AuthService, $translate) {
  $timeout(function() {
    $scope.$parent.showHeader();
    $scope.$parent.clearFabs();
  }, 0);
  ionicMaterialInk.displayEffect();

  $scope.selectedlanguage = AuthService.getLanguage();
  console.log($scope.selectedlanguage);

  $scope.getLanguages = function() {
    return [{"id":'en',"name":"English"},{"id":'pa',"name":"Punjabi"}];
  }

  //Populate language list
  $scope.languages = $scope.getLanguages();

  $scope.doChangeLanguage = function(selectedlanguage) {
    console.log("Changing Language to: "+selectedlanguage);
    AuthService.setLanguage(selectedlanguage);
    //$translate.refresh();
    $translate.use(AuthService.getLanguage());
    //$translateProvider.preferredLanguage(AuthService.getLanguage());
  }

  $scope.isCheckedReportAutoRefresh = false;
  if(AuthService.getAutoRefreshReports())
    $scope.isCheckedReportAutoRefresh = true;

  console.log("Checked: Service "+AuthService.getAutoRefreshReports()+" Scope : "+$scope.isCheckedReportAutoRefresh);
  $scope.doChangeAutoRefreshReports = function(autorefresh) {
    console.log("Changing Auto Refresh to: "+autorefresh);
    AuthService.setAutoRefreshReports(autorefresh);
  }

  // Triggered on a button click, or some other target
  $scope.actionSheet = function() {
      // Show the action sheet
      var hideSheet = $ionicActionSheet.show({
          destructiveText: 'Logout',
          cancelText: 'Cancel',
          cancel: function() {
              // add cancel code..
          },
          destructiveButtonClicked: function(index) {
            AuthService.logout();
    				$state.go('app.signup');
            return true;
          },
          buttonClicked: function(index) {
              return true;
          }
      });

      // For example's sake, hide the sheet after two seconds
      $timeout(function() {
          hideSheet();
      }, 10000);

  };

  // Cleanup the modal when we're done with it
  $scope.$on('$destroy', function() {
      //$scope.modal.remove();
  });

  // Popover
  $scope.popover = function() {
      $scope.$parent.popover.show();
      $timeout(function () {
          $scope.$parent.popover.hide();
      }, 2000);
  };

  // Confirm
  $scope.showPopup = function() {
      var alertPopup = $ionicPopup.alert({
          title: 'Welcome',
          template: 'How are you!'
      });

      $timeout(function() {
          ionicMaterialInk.displayEffect();
      }, 0);
  };

  // Toggle Code Wrapper
  var code = document.getElementsByClassName('code-wrapper');
  for (var i = 0; i < code.length; i++) {
      code[i].addEventListener('click', function() {
          this.classList.toggle('active');
      });
  }
})

.controller('displayOrdersCtrl', function($scope, $rootScope, $stateParams, $timeout, $ionicLoading, $ionicHistory, $ionicModal, $state, $ionicPopup, APIServices, AuthService) {
  $scope.supplystatus = {};

  $ionicModal.fromTemplateUrl('bill-modal.html', {
    scope: $scope,
    animation: 'slide-in-up'
  }).then(function(modal) {
    $scope.modal = modal;
  });

  $scope.openBillModal = function(order) {
    $scope.bill=order;
    $scope.modal.show();
  };

  $scope.closeBillModal = function() {
    $scope.modal.hide();
  };

  // Cleanup the modal when we're done with it
  $scope.$on('$destroy', function() {
    $scope.modal.remove();
  });

	$scope.doRefresh = function(startdate = null, enddate= null) {
		$ionicLoading.show({
      template: '<div class="loader"><svg class="circular"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>Loading'
		});

    if(startdate == null)
    {
      startdate = new Date();
      startdate.setHours(0);
      startdate.setMinutes(0);
      startdate.setSeconds(0);
      startdate.setMilliseconds(0);
      $scope.startdate = startdate;

      enddate = new Date();
      enddate.setHours(23);
      enddate.setMinutes(59);
      enddate.setSeconds(0);
      enddate.setMilliseconds(0);
      $scope.enddate = enddate;
    }

		APIServices.getListOfOrders($rootScope.storeid, AuthService.getMobile(), AuthService.getToken(), startdate, enddate)
		.then(
      function(data){
        if(data.status == 'error')
        {
			    $ionicLoading.hide();
          var alertPopup = $ionicPopup.alert({
            title: 'Server Returned an Error',
            template: data.msg+".\nPlease retry after sometime!"
          });
          //$ionicHistory.goBack();
        }
        else
        {
  			  $scope.orders = data;
          console.log(JSON.stringify(data));
  			  $ionicLoading.hide();
        }
  		},
      function(err){
        console.log(err);
			  $ionicLoading.hide();
        var alertPopup = $ionicPopup.alert({
          title: 'Unable to Communicate with Server!',
          template: "Is your device connected to the Internet.\nPlease check your Internet connection and retry!"
        });
        $ionicHistory.goBack();
  		}
    );
	};

  if(AuthService.getAutoRefreshReports())
    $scope.doRefresh();
  else //initilise the dates etc
  {
    startdate = new Date();
    startdate.setHours(0);
    startdate.setMinutes(0);
    startdate.setSeconds(0);
    startdate.setMilliseconds(0);
    $scope.startdate = startdate;

    enddate = new Date();
    enddate.setHours(23);
    enddate.setMinutes(59);
    enddate.setSeconds(0);
    enddate.setMilliseconds(0);
    $scope.enddate = enddate;
  }
})

.controller('discountOfferedCtrl', function($scope, $rootScope, $stateParams, $timeout, $ionicLoading, $ionicHistory, $ionicModal, $state, $ionicPopup, APIServices, AuthService) {
  $scope.supplystatus = {};

  $ionicModal.fromTemplateUrl('bill-modal.html', {
    scope: $scope,
    animation: 'slide-in-up'
  }).then(function(modal) {
    $scope.modal = modal;
  });

  $scope.openBillModal = function(order) {
    $scope.bill=order;
    $scope.modal.show();
  };

  $scope.closeBillModal = function() {
    $scope.modal.hide();
  };

  // Cleanup the modal when we're done with it
  $scope.$on('$destroy', function() {
    $scope.modal.remove();
  });

	$scope.doRefresh = function(startdate = null, enddate= null) {
		$ionicLoading.show({
      template: '<div class="loader"><svg class="circular"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>Loading'
		});

    if(startdate == null)
    {
      startdate = new Date();
      startdate.setHours(0);
      startdate.setMinutes(0);
      startdate.setSeconds(0);
      startdate.setMilliseconds(0);
      $scope.startdate = startdate;

      enddate = new Date();
      enddate.setHours(23);
      enddate.setMinutes(59);
      enddate.setSeconds(0);
      enddate.setMilliseconds(0);
      $scope.enddate = enddate;
    }

		APIServices.getDiscountOffered($rootScope.storeid, AuthService.getMobile(), AuthService.getToken(), startdate, enddate)
		.then(
      function(data){
        if(data.status == 'error')
        {
			    $ionicLoading.hide();
          var alertPopup = $ionicPopup.alert({
            title: 'Server Returned an Error',
            template: data.msg+".\nPlease retry after sometime!"
          });
          //$ionicHistory.goBack();
        }
        else
        {
  			  $scope.discounts = data;
          console.log(JSON.stringify(data));
  			  $ionicLoading.hide();
        }
  		},
      function(err){
        console.log(err);
			  $ionicLoading.hide();
        var alertPopup = $ionicPopup.alert({
          title: 'Unable to Communicate with Server!',
          template: "Is your device connected to the Internet.\nPlease check your Internet connection and retry!"
        });
        $ionicHistory.goBack();
  		}
    );
	};
  if(AuthService.getAutoRefreshReports())
    $scope.doRefresh();
  else //initilise the dates etc
  {
    startdate = new Date();
    startdate.setHours(0);
    startdate.setMinutes(0);
    startdate.setSeconds(0);
    startdate.setMilliseconds(0);
    $scope.startdate = startdate;

    enddate = new Date();
    enddate.setHours(23);
    enddate.setMinutes(59);
    enddate.setSeconds(0);
    enddate.setMilliseconds(0);
    $scope.enddate = enddate;
  }
})

.controller('cashStatusCtrl', function($scope, $rootScope, $stateParams, $timeout, $ionicLoading, $ionicHistory, $state, $ionicPopup, APIServices, AuthService) {
  $scope.cash = {};
  $scope.day = null;
	$scope.doRefresh = function(day = null) {
		$ionicLoading.show({
      template: '<div class="loader"><svg class="circular"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>Loading'
		});

    if(day == null)
    {
      day = new Date();
      $scope.day = day;
    }

		APIServices.getCashStatus($rootScope.storeid, AuthService.getMobile(), AuthService.getToken(), day)
		.then(
      function(data){
        if(data.status == 'error')
        {
			    $ionicLoading.hide();
          var alertPopup = $ionicPopup.alert({
            title: 'Server Returned an Error',
            template: data.msg+".\nPlease retry after sometime!"
          });
          console.log(JSON.stringify(data));
          $ionicHistory.goBack();
        }
        else
        {
          $scope.showCharts = false;
  			  $scope.cash = data;
          if(data.TotalBill)
          {
            $scope.showCharts = true;
            var seriesorders = [];
            var seriessales = [];
            var seriesavgsales = [];
            var seriesminbill = [];
            var seriesmaxbill = [];
            var graphlabels = [];
            var startRecording = false;
            angular.forEach(data.hourlyData,function(value,index){
              //console.log(value);
              if(value.noOfOrders > 0 || value.totalSale > 0)
                startRecording = true;
              if(startRecording)
              {
                graphlabels.push(value.hour);
                seriesorders.push(value.noOfOrders);
                seriessales.push(value.totalSale);
                seriesavgsales.push(value.avgSalePerOrder);
                seriesminbill.push(value.minBill);
                seriesmaxbill.push(value.maxBill);
              }
            })
            $scope.graphlabels = graphlabels;
            $scope.graphseriessales = ['Billed Amount', 'Avg Order Value', 'Max Bill', 'Min Bill'];
            $scope.graphseriesorders = ['No of Orders'];
            $scope.graphdatasales = [seriessales, seriesavgsales, seriesmaxbill, seriesminbill];
            $scope.graphdataorders = [seriesorders];
            $scope.colorssales = ['#45b7cd', '#ff6384', '#ff8e72'];
            $scope.colorsorders = ['#ff6384'];
            $scope.options = {
              legend: {display: true}
            };
            //Let DOM get ready
/*            $timeout(function() {
              var canvas = document.getElementById('chartingcanvas1');
              //dynamically adjust the height of chart
              canvas.height = data.records*2+400;
              var canvas2 = document.getElementById('chartingcanvas2');
              //dynamically adjust the height of chart
              canvas2.height = data.records*2+300;
            }, 0);*/
          }
          else
          {
            var alertPopup = $ionicPopup.alert({
              title: 'No Records Found!',
              template: "No records were found for given period, please select different search period!"
            });
          }

          //console.log(JSON.stringify(data));
  			  $ionicLoading.hide();
        }
  		},
      function(err){
        console.log(err);
			  $ionicLoading.hide();
        var alertPopup = $ionicPopup.alert({
          title: 'Unable to Communicate with Server!',
          template: "Is your device connected to the Internet.\nPlease check your Internet connection and retry!"
        });
        //$ionicHistory.goBack();
  		}
    );
	};
  if(AuthService.getAutoRefreshReports())
    $scope.doRefresh();
  else //initilise the dates etc
  {
    $scope.day = new Date();
  }
})

.controller('itemWiseSaleCtrl', function($scope, $rootScope, $stateParams, $timeout, $ionicLoading, $ionicHistory, $state, $ionicPopup, APIServices, AuthService) {
  $scope.cash = {};
  $scope.sortType="item";
  $scope.sortDirection=false;
  $scope.sortData = function(type){
    if($scope.sortType && $scope.sortType===type ){
        $scope.sortDirection = !$scope.sortDirection;
      } else {
        $scope.sortType = type;
        $scope.sortDirection = true;
      }
  }

  $scope.reportby = $stateParams.reportby;
	$scope.doRefresh = function(startdate = null, enddate = null) {
		$ionicLoading.show({
      template: '<div class="loader"><svg class="circular"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>Loading'
		});

    if(startdate == null)
    {
      startdate = new Date();
      startdate.setHours(0);
      startdate.setMinutes(0);
      startdate.setSeconds(0);
      startdate.setMilliseconds(0);
      $scope.startdate = startdate;

      enddate = new Date();
      enddate.setHours(23);
      enddate.setMinutes(59);
      enddate.setSeconds(0);
      enddate.setMilliseconds(0);
      $scope.enddate = enddate;
    }

		APIServices.getItemWiseSale($rootScope.storeid, AuthService.getMobile(), AuthService.getToken(),$scope.reportby, startdate, enddate)
		.then(
      function(data){
        if(data.status == 'error')
        {
			    $ionicLoading.hide();
          var alertPopup = $ionicPopup.alert({
            title: 'Server Returned an Error',
            template: data.msg+".\nPlease retry after sometime!"
          });
          console.log(JSON.stringify(data));
          //$ionicHistory.goBack();
        }
        else
        {
          $scope.showCharts = false;
          if(data.records == 0)
          {
            var alertPopup = $ionicPopup.alert({
              title: 'No Records Found!',
              template: "No records were found for given period, please select different search period!"
            });
          }
          else
          {
            $scope.items = data;
            var currentValue = 0;
            $scope.dataStatsArray = Object.keys(data.stats).map(function(key,index) {
                var obj = data.stats[key];
                if(obj.day == "TOTAL") {
                    currentValue =  index ;
                }
                return data.stats[key];
            });
            //console.log(JSON.stringify($scope.dataStatsArray));
            switch(data.intervalindays)
            {
              case 1:
                if(data.records < 40)
                {
                  $scope.showPieCharts = true;
                  var seriesunits = [];
                  var seriessales = [];
                  var graphlabels = [];
                  angular.forEach(data.stats,function(value,index){
                    //console.log(value);
                    graphlabels.push(value.Classification);
                    seriesunits.push(value.Quantity);
                    seriessales.push(value.Rate);
                  })
                  $scope.graphlabels = graphlabels;
                  //$scope.graphseries = ['Units Sold', 'Sales'];
                  $scope.graphdatasales = seriessales;
                  $scope.graphdataunits = seriesunits;
                  $scope.options = {
                    responsive: true,
                    maintainAspectRatio: true,
                    legend: {display: true},
                    legendCallback: function (chart) {
                      console.log(chart);
                      return 'a';
                    }
                  };
                  //Let DOM get ready
                  $timeout(function() {
                    var canvas = document.getElementById('chartingcanvas1');
                    //dynamically adjust the height of chart
                    canvas.height = data.records*2+400;
                    var canvas2 = document.getElementById('chartingcanvas2');
                    //dynamically adjust the height of chart
                    canvas2.height = data.records*2+300;
                  }, 0);
                }
                break;
              default: //more than 1 days, plot trend
                  $scope.showTrendCharts = true;
                  var seriesunits = [];
                  var seriessales = [];
                  var graphlabels = [];
                  var graphseries = [];
                  var i_max = 0;
                  angular.forEach(data.salestrend,function(value,index){
                    //console.log(value);
                    graphlabels.push(index);
                    var i = 0;
                    angular.forEach(value,function(i_value,i_index){
                      graphseries.push(i_index);
                      //console.log(i_index);
                      if(!Array.isArray(seriessales[i]))
                        seriessales[i] = [];
                      seriessales[i].push(i_value.Rate);
                      if(!Array.isArray(seriesunits[i]))
                        seriesunits[i] = [];
                      seriesunits[i].push(i_value.qty);
                      i++;
                    });
                    if(i > i_max)
                      i_max = i;
                  });
                  $scope.graphlabels = graphlabels;
                  $scope.graphseries = graphseries;
                  $scope.graphdatasales = [];
                  for(c = 0; c < i_max; c++)
                    //console.log(seriessales[c]);
                    $scope.graphdatasales.push(seriesunits[c]);
                    //$scope.graphdatasales = [[1,2,3],[4,5,6]];
                  //$scope.graphdatasales = [seriessales, seriesunits];
                  $scope.options = {
                    responsive: true,
                    maintainAspectRatio: true,
                    legend: {display: true}
                  };
                  break;
            }
          }

          console.log("Labels: "+JSON.stringify($scope.graphlabels));
          console.log("Data: "+JSON.stringify($scope.graphdata));
  			  $ionicLoading.hide();
        }
  		},
      function(err){
        console.log(err);
			  $ionicLoading.hide();
        var alertPopup = $ionicPopup.alert({
          title: 'Unable to Communicate with Server!',
          template: "Is your device connected to the Internet.\nPlease check your Internet connection and retry!"
        });
        //$ionicHistory.goBack();
  		}
    );
	};
  if(AuthService.getAutoRefreshReports())
    $scope.doRefresh();
  else //initilise the dates etc
  {
    startdate = new Date();
    startdate.setHours(0);
    startdate.setMinutes(0);
    startdate.setSeconds(0);
    startdate.setMilliseconds(0);
    $scope.startdate = startdate;

    enddate = new Date();
    enddate.setHours(23);
    enddate.setMinutes(59);
    enddate.setSeconds(0);
    enddate.setMilliseconds(0);
    $scope.enddate = enddate;
  }
})

.controller('cashStatusBetweenDaysCtrl', function($scope, $rootScope, $stateParams, $timeout, $ionicLoading, $ionicHistory, $state, $ionicPopup, APIServices, AuthService) {
  $scope.cash = {};
  $scope.sortType="srno";
  $scope.sortDirection=false;
  $scope.sortData = function(type){
    if($scope.sortType && $scope.sortType===type ){
        $scope.sortDirection = !$scope.sortDirection;
      } else {
        $scope.sortType = type;
        $scope.sortDirection = true;
      }
  }
  $scope.reportby = $stateParams.reportby;
	$scope.doRefresh = function(startdate = null, enddate = null) {
		$ionicLoading.show({
      template: '<div class="loader"><svg class="circular"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>Loading'
		});

    if(startdate == null)
    {
      startdate = new Date();
      startdate.setHours(0);
      startdate.setMinutes(0);
      startdate.setSeconds(0);
      startdate.setMilliseconds(0);
      $scope.startdate = startdate;

      enddate = new Date();
      enddate.setHours(23);
      enddate.setMinutes(59);
      enddate.setSeconds(0);
      enddate.setMilliseconds(0);
      $scope.enddate = enddate;
    }

		APIServices.getCashStatusBetweenDays($rootScope.storeid, AuthService.getMobile(), AuthService.getToken(), startdate, enddate)
		.then(
      function(data){
        if(data.status == 'error')
        {
			    $ionicLoading.hide();
          var alertPopup = $ionicPopup.alert({
            title: 'Server Returned an Error',
            template: data.msg+".\nPlease retry after sometime!"
          });
          console.log(JSON.stringify(data));
          //$ionicHistory.goBack();
        }
        else
        {
          $scope.showCharts = false;
          if(data.records == 0)
          {
            var alertPopup = $ionicPopup.alert({
              title: 'No Records Found!',
              template: "No records were found for given period, please select different search period!"
            });
          }
          else
          {
            $scope.data = data;
            var currentValue = 0;
            $scope.dataStatsArray = Object.keys(data.stats).map(function(key,index) {
                var obj = data.stats[key];
                if(obj.day == "TOTAL") {
                    currentValue =  index ;
                }
                return data.stats[key];
            });
            //$scope.dataStatsArray.splice(currentValue,1);
            $scope.showCharts = true;
            var seriesorders = [];
            var seriessales = [];
            var seriesavgsales = [];
            var seriesminbill = [];
            var seriesmaxbill = [];
            var graphlabels = [];
            var startRecording = false;
            angular.forEach(data.hourlyData,function(value,index){
              //console.log(value);
              if(value.noOfOrders > 0 || value.totalSale > 0)
                startRecording = true;
              if(startRecording)
              {
                graphlabels.push(value.hour);
                seriesorders.push(value.noOfOrders);
                seriessales.push(value.totalSale);
                seriesavgsales.push(value.avgSalePerOrder);
                seriesminbill.push(value.minBill);
                seriesmaxbill.push(value.maxBill);
              }
            })
            $scope.graphlabels = graphlabels;
            $scope.graphseriessales = ['Billed Amount', 'Avg Order Value', 'Max Bill', 'Min Bill'];
            $scope.graphseriesorders = ['No of Orders'];
            $scope.graphdatasales = [seriessales, seriesavgsales, seriesmaxbill, seriesminbill];
            $scope.graphdataorders = [seriesorders];
            $scope.colorssales = ['#45b7cd', '#ff6384', '#ff8e72'];
            $scope.colorsorders = ['#ff6384'];
            $scope.options = {
              legend: {display: true}
            };
          }

          console.log("Labels: "+JSON.stringify($scope.graphlabels));
          console.log("Data: "+JSON.stringify($scope.graphdata));
  			  $ionicLoading.hide();
        }
  		},
      function(err){
        console.log(err);
			  $ionicLoading.hide();
        var alertPopup = $ionicPopup.alert({
          title: 'Unable to Communicate with Server!',
          template: "Is your device connected to the Internet.\nPlease check your Internet connection and retry!"
        });
        //$ionicHistory.goBack();
  		}
    );
	};
  if(AuthService.getAutoRefreshReports())
    $scope.doRefresh();
  else //initilise the dates etc
  {
    startdate = new Date();
    startdate.setHours(0);
    startdate.setMinutes(0);
    startdate.setSeconds(0);
    startdate.setMilliseconds(0);
    $scope.startdate = startdate;

    enddate = new Date();
    enddate.setHours(23);
    enddate.setMinutes(59);
    enddate.setSeconds(0);
    enddate.setMilliseconds(0);
    $scope.enddate = enddate;
  }

})
.controller('salesByEmployeesBetweenDatesCtrl', function($scope, $rootScope, $stateParams, $timeout, $ionicLoading, $ionicHistory, $state, $ionicPopup, APIServices, AuthService) {
  $scope.cash = {};
  $scope.reportby = $stateParams.reportby;
	$scope.doRefresh = function(startdate = null, enddate = null) {
		$ionicLoading.show({
      template: '<div class="loader"><svg class="circular"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>Loading'
		});

    if(startdate == null)
    {
      startdate = new Date();
      startdate.setHours(0);
      startdate.setMinutes(0);
      startdate.setSeconds(0);
      startdate.setMilliseconds(0);
      $scope.startdate = startdate;

      enddate = new Date();
      enddate.setHours(23);
      enddate.setMinutes(59);
      enddate.setSeconds(0);
      enddate.setMilliseconds(0);
      $scope.enddate = enddate;
    }

		APIServices.getSalesByEmployeesBetweenDates($rootScope.storeid, AuthService.getMobile(), AuthService.getToken(), startdate, enddate)
		.then(
      function(data){
        if(data.status == 'error')
        {
			    $ionicLoading.hide();
          var alertPopup = $ionicPopup.alert({
            title: 'Server Returned an Error',
            template: data.msg+".\nPlease retry after sometime!"
          });
          console.log(JSON.stringify(data));
          //$ionicHistory.goBack();
        }
        else
        {
          $scope.showCharts = false;
          if(data.records == 0)
          {
            var alertPopup = $ionicPopup.alert({
              title: 'No Records Found!',
              template: "No records were found for given period, please select different search period!"
            });
          }
          else
          {
            $scope.data = data;
          }

          console.log("Labels: "+JSON.stringify($scope.graphlabels));
          console.log("Data: "+JSON.stringify($scope.graphdata));
  			  $ionicLoading.hide();
        }
  		},
      function(err){
        console.log(err);
			  $ionicLoading.hide();
        var alertPopup = $ionicPopup.alert({
          title: 'Unable to Communicate with Server!',
          template: "Is your device connected to the Internet.\nPlease check your Internet connection and retry!"
        });
        //$ionicHistory.goBack();
  		}
    );
	};
  if(AuthService.getAutoRefreshReports())
    $scope.doRefresh();
  else //initilise the dates etc
  {
    startdate = new Date();
    startdate.setHours(0);
    startdate.setMinutes(0);
    startdate.setSeconds(0);
    startdate.setMilliseconds(0);
    $scope.startdate = startdate;

    enddate = new Date();
    enddate.setHours(23);
    enddate.setMinutes(59);
    enddate.setSeconds(0);
    enddate.setMilliseconds(0);
    $scope.enddate = enddate;
  }
})
.controller('salesByTablesBetweenDatesCtrl', function($scope, $rootScope, $stateParams, $timeout, $ionicLoading, $ionicHistory, $state, $ionicPopup, APIServices, AuthService) {
  $scope.cash = {};
  $scope.reportby = $stateParams.reportby;
	$scope.doRefresh = function(startdate = null, enddate = null) {
		$ionicLoading.show({
      template: '<div class="loader"><svg class="circular"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>Loading'
		});

    if(startdate == null)
    {
      startdate = new Date();
      startdate.setHours(0);
      startdate.setMinutes(0);
      startdate.setSeconds(0);
      startdate.setMilliseconds(0);
      $scope.startdate = startdate;

      enddate = new Date();
      enddate.setHours(23);
      enddate.setMinutes(59);
      enddate.setSeconds(0);
      enddate.setMilliseconds(0);
      $scope.enddate = enddate;
    }

		APIServices.getSalesByTablesBetweenDates($rootScope.storeid, AuthService.getMobile(), AuthService.getToken(), startdate, enddate)
		.then(
      function(data){
        if(data.status == 'error')
        {
			    $ionicLoading.hide();
          var alertPopup = $ionicPopup.alert({
            title: 'Server Returned an Error',
            template: data.msg+".\nPlease retry after sometime!"
          });
          console.log(JSON.stringify(data));
          //$ionicHistory.goBack();
        }
        else
        {
          $scope.showCharts = false;
          if(data.records == 0)
          {
            var alertPopup = $ionicPopup.alert({
              title: 'No Records Found!',
              template: "No records were found for given period, please select different search period!"
            });
          }
          else
          {
            $scope.data = data;
          }

          console.log("Labels: "+JSON.stringify($scope.graphlabels));
          console.log("Data: "+JSON.stringify($scope.graphdata));
  			  $ionicLoading.hide();
        }
  		},
      function(err){
        console.log(err);
			  $ionicLoading.hide();
        var alertPopup = $ionicPopup.alert({
          title: 'Unable to Communicate with Server!',
          template: "Is your device connected to the Internet.\nPlease check your Internet connection and retry!"
        });
        //$ionicHistory.goBack();
  		}
    );
	};
  if(AuthService.getAutoRefreshReports())
    $scope.doRefresh();
  else //initilise the dates etc
  {
    startdate = new Date();
    startdate.setHours(0);
    startdate.setMinutes(0);
    startdate.setSeconds(0);
    startdate.setMilliseconds(0);
    $scope.startdate = startdate;

    enddate = new Date();
    enddate.setHours(23);
    enddate.setMinutes(59);
    enddate.setSeconds(0);
    enddate.setMilliseconds(0);
    $scope.enddate = enddate;
  }
})
.controller('topCustomersBetweenDatesCtrl', function($scope, $rootScope, $stateParams, $timeout, $ionicLoading, $ionicHistory, $state, $ionicPopup, APIServices, AuthService) {
  $scope.cash = {};
  $scope.reportby = $stateParams.reportby;
	$scope.doRefresh = function(startdate = null, enddate = null) {
		$ionicLoading.show({
      template: '<div class="loader"><svg class="circular"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>Loading'
		});

    if(startdate == null)
    {
      startdate = new Date();
      startdate.setHours(0);
      startdate.setMinutes(0);
      startdate.setSeconds(0);
      startdate.setMilliseconds(0);
      $scope.startdate = startdate;

      enddate = new Date();
      enddate.setHours(23);
      enddate.setMinutes(59);
      enddate.setSeconds(0);
      enddate.setMilliseconds(0);
      $scope.enddate = enddate;
    }

		APIServices.getTopCustomersBetweenDates($rootScope.storeid, AuthService.getMobile(), AuthService.getToken(), startdate, enddate)
		.then(
      function(data){
        if(data.status == 'error')
        {
			    $ionicLoading.hide();
          var alertPopup = $ionicPopup.alert({
            title: 'Server Returned an Error',
            template: data.msg+".\nPlease retry after sometime!"
          });
          console.log(JSON.stringify(data));
          //$ionicHistory.goBack();
        }
        else
        {
          $scope.showCharts = false;
          if(data.records == 0)
          {
            var alertPopup = $ionicPopup.alert({
              title: 'No Records Found!',
              template: "No records were found for given period, please select different search period!"
            });
          }
          else
          {
            $scope.data = data;
          }

          console.log("Labels: "+JSON.stringify($scope.graphlabels));
          console.log("Data: "+JSON.stringify($scope.graphdata));
  			  $ionicLoading.hide();
        }
  		},
      function(err){
        console.log(err);
			  $ionicLoading.hide();
        var alertPopup = $ionicPopup.alert({
          title: 'Unable to Communicate with Server!',
          template: "Is your device connected to the Internet.\nPlease check your Internet connection and retry!"
        });
        //$ionicHistory.goBack();
  		}
    );
	};
  if(AuthService.getAutoRefreshReports())
    $scope.doRefresh();
  else //initilise the dates etc
  {
    startdate = new Date();
    startdate.setHours(0);
    startdate.setMinutes(0);
    startdate.setSeconds(0);
    startdate.setMilliseconds(0);
    $scope.startdate = startdate;

    enddate = new Date();
    enddate.setHours(23);
    enddate.setMinutes(59);
    enddate.setSeconds(0);
    enddate.setMilliseconds(0);
    $scope.enddate = enddate;
  }
})
.controller('inventoryConsumedBetweenDatesCtrl', function($scope, $rootScope, $stateParams, $timeout, $ionicLoading, $ionicHistory, $state, $ionicPopup, APIServices, AuthService) {
  $scope.cash = {};

  $scope.sortType="itemname";
  $scope.sortDirection=false;
  $scope.sortData = function(type){
    if($scope.sortType && $scope.sortType===type ){
        $scope.sortDirection = !$scope.sortDirection;
      } else {
        $scope.sortType = type;
        $scope.sortDirection = true;
      }
  }

  $scope.reportby = $stateParams.reportby;
	$scope.doRefresh = function(startdate = null, enddate = null) {
		$ionicLoading.show({
      template: '<div class="loader"><svg class="circular"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>Loading'
		});

    if(startdate == null)
    {
      startdate = new Date();
      startdate.setHours(0);
      startdate.setMinutes(0);
      startdate.setSeconds(0);
      startdate.setMilliseconds(0);
      $scope.startdate = startdate;

      enddate = new Date();
      enddate.setHours(23);
      enddate.setMinutes(59);
      enddate.setSeconds(0);
      enddate.setMilliseconds(0);
      $scope.enddate = enddate;
    }

		APIServices.getInventoryConsumedBetweenDates($rootScope.storeid, AuthService.getMobile(), AuthService.getToken(), startdate, enddate)
		.then(
      function(data){
        if(data.status == 'error')
        {
			    $ionicLoading.hide();
          var alertPopup = $ionicPopup.alert({
            title: 'Server Returned an Error',
            template: data.msg+".\nPlease retry after sometime!"
          });
          console.log(JSON.stringify(data));
          //$ionicHistory.goBack();
        }
        else
        {
          if(data.records == 0)
          {
            var alertPopup = $ionicPopup.alert({
              title: 'No Records Found!',
              template: "No records were found for given period, please select different search period!"
            });
          }
          else
          {
            $scope.data = data;
            $scope.dataStatsArray = Object.keys(data.stats).map(function(key,index) {
              var obj = data.stats[key];
              return data.stats[key];
            });
          }
  			  $ionicLoading.hide();
        }
  		},
      function(err){
        console.log(err);
			  $ionicLoading.hide();
        var alertPopup = $ionicPopup.alert({
          title: 'Unable to Communicate with Server!',
          template: "Is your device connected to the Internet.\nPlease check your Internet connection and retry!"
        });
        //$ionicHistory.goBack();
  		}
    );
	};
  if(AuthService.getAutoRefreshReports())
    $scope.doRefresh();
  else //initilise the dates etc
  {
    startdate = new Date();
    startdate.setHours(0);
    startdate.setMinutes(0);
    startdate.setSeconds(0);
    startdate.setMilliseconds(0);
    $scope.startdate = startdate;

    enddate = new Date();
    enddate.setHours(23);
    enddate.setMinutes(59);
    enddate.setSeconds(0);
    enddate.setMilliseconds(0);
    $scope.enddate = enddate;
  }
})
.controller('cancelledOrdersBetweenDatesCtrl', function($scope, $rootScope, $stateParams, $timeout, $ionicLoading, $ionicModal,$ionicHistory, $state, $ionicPopup, APIServices, AuthService) {
  $scope.supplystatus = {};

    $ionicModal.fromTemplateUrl('bill-modal.html', {
      scope: $scope,
      animation: 'slide-in-up'
    }).then(function(modal) {
      $scope.modal = modal;
    });

    $scope.openBillModal = function(order) {
      $scope.bill=order;
      $scope.modal.show();
    };

    $scope.closeBillModal = function() {
      $scope.modal.hide();
    };

    // Cleanup the modal when we're done with it
    $scope.$on('$destroy', function() {
      $scope.modal.remove();
    });

    $scope.doRefresh = function(startdate = null, enddate= null) {
      $ionicLoading.show({
        template: '<div class="loader"><svg class="circular"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>Loading'
      });

      if(startdate == null)
      {
        startdate = new Date();
        startdate.setHours(0);
        startdate.setMinutes(0);
        startdate.setSeconds(0);
        startdate.setMilliseconds(0);
        $scope.startdate = startdate;

        enddate = new Date();
        enddate.setHours(23);
        enddate.setMinutes(59);
        enddate.setSeconds(0);
        enddate.setMilliseconds(0);
        $scope.enddate = enddate;
      }

      APIServices.getCancelledOrders($rootScope.storeid, AuthService.getMobile(), AuthService.getToken(), startdate, enddate)
      .then(
        function(data){
          if(data.status == 'error')
          {
            $ionicLoading.hide();
            var alertPopup = $ionicPopup.alert({
              title: 'Server Returned an Error',
              template: data.msg+".\nPlease retry after sometime!"
            });
            //$ionicHistory.goBack();
          }
          else
          {
            $scope.cancelled = data;
            $scope.totalCancelled = data && data.orders ? Object.keys(data.orders).length : 0;
            console.log(JSON.stringify(data));
            $ionicLoading.hide();
          }
        },
        function(err){
          console.log(err);
          $ionicLoading.hide();
          var alertPopup = $ionicPopup.alert({
            title: 'Unable to Communicate with Server!',
            template: "Is your device connected to the Internet.\nPlease check your Internet connection and retry!"
          });
          $ionicHistory.goBack();
        }
      );
    };

    if(AuthService.getAutoRefreshReports())
      $scope.doRefresh();
    else //initilise the dates etc
    {
      startdate = new Date();
      startdate.setHours(0);
      startdate.setMinutes(0);
      startdate.setSeconds(0);
      startdate.setMilliseconds(0);
      $scope.startdate = startdate;

      enddate = new Date();
      enddate.setHours(23);
      enddate.setMinutes(59);
      enddate.setSeconds(0);
      enddate.setMilliseconds(0);
      $scope.enddate = enddate;
    }
})
.controller('unpaidOrdersBetweenDatesCtrl', function($scope, $rootScope, $stateParams, $timeout, $ionicLoading, $ionicModal,$ionicHistory, $state, $ionicPopup, APIServices, AuthService) {
  $scope.supplystatus = {};

    $ionicModal.fromTemplateUrl('bill-modal.html', {
      scope: $scope,
      animation: 'slide-in-up'
    }).then(function(modal) {
      $scope.modal = modal;
    });

    $scope.openBillModal = function(order) {
      $scope.bill=order;
      $scope.modal.show();
    };

    $scope.closeBillModal = function() {
      $scope.modal.hide();
    };

    // Cleanup the modal when we're done with it
    $scope.$on('$destroy', function() {
      $scope.modal.remove();
    });

    $scope.doRefresh = function(startdate = null, enddate= null) {
      $ionicLoading.show({
        template: '<div class="loader"><svg class="circular"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>Loading'
      });

      if(startdate == null)
      {
        startdate = new Date();
        startdate.setHours(0);
        startdate.setMinutes(0);
        startdate.setSeconds(0);
        startdate.setMilliseconds(0);
        $scope.startdate = startdate;

        enddate = new Date();
        enddate.setHours(23);
        enddate.setMinutes(59);
        enddate.setSeconds(0);
        enddate.setMilliseconds(0);
        $scope.enddate = enddate;
      }

      APIServices.getUnpaidOrders($rootScope.storeid, AuthService.getMobile(), AuthService.getToken(), startdate, enddate)
      .then(
        function(data){
          if(data.status == 'error')
          {
            $ionicLoading.hide();
            var alertPopup = $ionicPopup.alert({
              title: 'Server Returned an Error',
              template: data.msg+".\nPlease retry after sometime!"
            });
            //$ionicHistory.goBack();
          }
          else
          {
            $scope.unpaid = data;
            $scope.totalUnpaid = data && data.orders ? Object.keys(data.orders).length : 0;
            console.log(JSON.stringify(data));
            $ionicLoading.hide();
          }
        },
        function(err){
          console.log(err);
          $ionicLoading.hide();
          var alertPopup = $ionicPopup.alert({
            title: 'Unable to Communicate with Server!',
            template: "Is your device connected to the Internet.\nPlease check your Internet connection and retry!"
          });
          $ionicHistory.goBack();
        }
      );
    };

    if(AuthService.getAutoRefreshReports())
      $scope.doRefresh();
    else //initilise the dates etc
    {
      startdate = new Date();
      startdate.setHours(0);
      startdate.setMinutes(0);
      startdate.setSeconds(0);
      startdate.setMilliseconds(0);
      $scope.startdate = startdate;

      enddate = new Date();
      enddate.setHours(23);
      enddate.setMinutes(59);
      enddate.setSeconds(0);
      enddate.setMilliseconds(0);
      $scope.enddate = enddate;
    }
})
.controller('AboutCtrl', function($rootScope, $scope, $http, $timeout, $stateParams, ionicMaterialInk, $ionicNavBarDelegate, $cordovaAppVersion, $cordovaDevice, $cordovaNetwork)
{
  $scope.appVersion = "(Build)";
  $scope.appBuild = null;
  $scope.appName = null;
  $scope.appPackage = null;

  $scope.deviceVersion = "(Browser)";
  $scope.platform = null;

  $scope.networkState = "Unknown";

  document.addEventListener("deviceready", function () {

    // listen for Online event
    $rootScope.$on('$cordovaNetwork:online', function(event, networkState){
      switch(networkState)
      {
        case "Connection.UNKNOWN":
          $scope.networkState = "Unknown connection";
          break;
        case "Connection.ETHERNET":
          $scope.networkState = "Ethernet connection";
          break;
        case "Connection.WIFI":
          $scope.networkState = "WiFi connection";
          break;
        case "Connection.CELL_2G":
          $scope.networkState = "Cell 2G connection";
           break;
        case "Connection.CELL_3G":
          $scope.networkState = "Cell 3G connection";
          break;
        case "Connection.CELL_4G":
          $scope.networkState = "Cell 4G connection";
          break;
        case "Connection.CELL":
          $scope.networkState = "Cell generic connection";
          break;
        case "Connection.NONE":
          $scope.networkState = "No network connection";
          break;
        default:
          $scope.networkState = "Unknown";
          break;
      }
    });

    // listen for Offline event
    $rootScope.$on('$cordovaNetwork:offline', function(event, networkState){
      var offlineState = networkState;
    })

    $cordovaAppVersion.getVersionNumber().then(function (version) {
      $scope.appVersion = version;
    });
    $cordovaAppVersion.getVersionCode().then(function (build) {
      $scope.appBuild = build;
    });
    $cordovaAppVersion.getAppName().then(function (name) {
      $scope.appName = name;
    });
    $cordovaAppVersion.getPackageName().then(function (package) {
      $scope.appPackage = package;
    });

    $scope.platform = $cordovaDevice.getPlatform();
    $scope.deviceVersion = $cordovaDevice.getVersion();

    //$chromeWebviewChecker.getChromeWebviewVersion()
      //.then(function(enabled) { console.log(enabled); })
      //.catch(function(error) { console.error(error); });

  }, false);
})
;
