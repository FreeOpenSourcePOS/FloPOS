angular.module('starter.services', [])

// API FUNCTIONS
.service('APIServices', function ($http, $q, SERVER){

  this.getListOfOrders = function(storeid, mobile, token, startdate = null, enddate = null) {
    var deferred = $q.defer();
    var url = SERVER.urlAPIforGetListOfOrders+'&storeid='+storeid+"&mobile="+mobile+"&token="+token;
    if(startdate)
      url += "&startdate="+encodeURIComponent(startdate.getFullYear() + "-" + (startdate.getMonth() + 1) + "-" + startdate.getDate()+' '+startdate.getHours()+':'+startdate.getMinutes()+':'+startdate.getSeconds());
    if(enddate)
      url += "&enddate="+encodeURIComponent(enddate.getFullYear() + "-" + (enddate.getMonth() + 1) + "-" + enddate.getDate()+' '+enddate.getHours()+':'+enddate.getMinutes()+':'+enddate.getSeconds());
    //console.log("Calling: "+url);
    $http.jsonp(url,{timeout: 60000})
    .success(function(data) {
      //console.log("Success: "+JSON.stringify(data));
      deferred.resolve(data);
    })
    .error(function(data) {
      //console.log('ERR:'+JSON.stringify(data));
      deferred.reject(data);
    });
    return deferred.promise;
  };
  this.getCancelledOrders = function(storeid, mobile, token, startdate = null, enddate = null){
    var deferred = $q.defer();
    var url = SERVER.urlAPIforGetCancelledOrders+'&storeid='+storeid+"&mobile="+mobile+"&token="+token+"&orderstatusid=3&";
    if(startdate)
      url += "&startdate="+encodeURIComponent(startdate.getFullYear() + "-" + (startdate.getMonth() + 1) + "-" + startdate.getDate()+' '+startdate.getHours()+':'+startdate.getMinutes()+':'+startdate.getSeconds());
    if(enddate)
      url += "&enddate="+encodeURIComponent(enddate.getFullYear() + "-" + (enddate.getMonth() + 1) + "-" + enddate.getDate()+' '+enddate.getHours()+':'+enddate.getMinutes()+':'+enddate.getSeconds());
    //console.log("Calling: "+url);
    $http.jsonp(url,{timeout: 60000})
    .success(function(data) {
      //console.log("Success: "+JSON.stringify(data));
      deferred.resolve(data);
    })
    .error(function(data) {
      //console.log('ERR:'+JSON.stringify(data));
      deferred.reject(data);
    });

    return deferred.promise;
  }
  this.getUnpaidOrders = function(storeid, mobile, token, startdate = null, enddate = null){
    var deferred = $q.defer();
    var url = SERVER.urlAPIforGetCancelledOrders+'&storeid='+storeid+"&mobile="+mobile+"&token="+token+"&orderstatusid=2&";
    if(startdate)
      url += "&startdate="+encodeURIComponent(startdate.getFullYear() + "-" + (startdate.getMonth() + 1) + "-" + startdate.getDate()+' '+startdate.getHours()+':'+startdate.getMinutes()+':'+startdate.getSeconds());
    if(enddate)
      url += "&enddate="+encodeURIComponent(enddate.getFullYear() + "-" + (enddate.getMonth() + 1) + "-" + enddate.getDate()+' '+enddate.getHours()+':'+enddate.getMinutes()+':'+enddate.getSeconds());
    //console.log("Calling: "+url);
    $http.jsonp(url,{timeout: 60000})
    .success(function(data) {
      //console.log("Success: "+JSON.stringify(data));
      deferred.resolve(data);
    })
    .error(function(data) {
      //console.log('ERR:'+JSON.stringify(data));
      deferred.reject(data);
    });

    return deferred.promise;
  }
  this.getDiscountOffered = function(storeid, mobile, token, startdate = null, enddate = null) {
    var deferred = $q.defer();
    var url = SERVER.urlAPIforGetDiscountOffered+'&storeid='+storeid+"&mobile="+mobile+"&token="+token;
    if(startdate)
      url += "&startdate="+encodeURIComponent(startdate.getFullYear() + "-" + (startdate.getMonth() + 1) + "-" + startdate.getDate()+' '+startdate.getHours()+':'+startdate.getMinutes()+':'+startdate.getSeconds());
    if(enddate)
      url += "&enddate="+encodeURIComponent(enddate.getFullYear() + "-" + (enddate.getMonth() + 1) + "-" + enddate.getDate()+' '+enddate.getHours()+':'+enddate.getMinutes()+':'+enddate.getSeconds());
    //console.log("Calling: "+url);
    $http.jsonp(url,{timeout: 60000})
    .success(function(data) {
      //console.log("Success: "+JSON.stringify(data));
      deferred.resolve(data);
    })
    .error(function(data) {
      //console.log('ERR:'+JSON.stringify(data));
      deferred.reject(data);
    });

    return deferred.promise;
  };

  this.getCashStatusBetweenDays = function(storeid, mobile, token, startdate = null, enddate = null) {
    var deferred = $q.defer();
    var url = SERVER.urlAPIforGetCashStatusBetweenDays+'&storeid='+storeid+"&mobile="+mobile+"&token="+token;
    if(startdate)
      url += "&startdate="+encodeURIComponent(startdate.getFullYear() + "-" + (startdate.getMonth() + 1) + "-" + startdate.getDate()) + ' 00:00:00';//+' '+startdate.getHours()+':'+startdate.getMinutes()+':'+startdate.getSeconds());
    if(enddate)
      url += "&enddate="+encodeURIComponent(enddate.getFullYear() + "-" + (enddate.getMonth() + 1) + "-" + enddate.getDate()) + ' 23:59:59';//+' '+enddate.getHours()+':'+enddate.getMinutes()+':'+enddate.getSeconds());
    console.log("Calling: "+url);
    $http.jsonp(url,{timeout: 60000})
    .success(function(data) {
      //console.log("Success: "+JSON.stringify(data));
      deferred.resolve(data);
    })
    .error(function(data) {
      //console.log('ERR:'+JSON.stringify(data));
      deferred.reject(data);
    });

    return deferred.promise;
  };

  this.getSalesByEmployeesBetweenDates = function(storeid, mobile, token, startdate = null, enddate = null) {
    var deferred = $q.defer();
    var url = SERVER.urlAPIforGetSalesByEmployeesBetweenDates+'&storeid='+storeid+"&mobile="+mobile+"&token="+token;
    if(startdate)
      url += "&startdate="+encodeURIComponent(startdate.getFullYear() + "-" + (startdate.getMonth() + 1) + "-" + startdate.getDate()) + ' 00:00:00';//+' '+startdate.getHours()+':'+startdate.getMinutes()+':'+startdate.getSeconds());
    if(enddate)
      url += "&enddate="+encodeURIComponent(enddate.getFullYear() + "-" + (enddate.getMonth() + 1) + "-" + enddate.getDate()) + ' 23:59:59';//+' '+enddate.getHours()+':'+enddate.getMinutes()+':'+enddate.getSeconds());
    console.log("Calling: "+url);
    $http.jsonp(url,{timeout: 60000})
    .success(function(data) {
      //console.log("Success: "+JSON.stringify(data));
      deferred.resolve(data);
    })
    .error(function(data) {
      //console.log('ERR:'+JSON.stringify(data));
      deferred.reject(data);
    });

    return deferred.promise;
  };

  this.getSalesByTablesBetweenDates = function(storeid, mobile, token, startdate = null, enddate = null) {
    var deferred = $q.defer();
    var url = SERVER.urlAPIforGetTablesSummaryBetweenDays+'&storeid='+storeid+"&mobile="+mobile+"&token="+token;
    if(startdate)
      url += "&startdate="+encodeURIComponent(startdate.getFullYear() + "-" + (startdate.getMonth() + 1) + "-" + startdate.getDate()) + ' 00:00:00';//+' '+startdate.getHours()+':'+startdate.getMinutes()+':'+startdate.getSeconds());
    if(enddate)
      url += "&enddate="+encodeURIComponent(enddate.getFullYear() + "-" + (enddate.getMonth() + 1) + "-" + enddate.getDate()) + ' 23:59:59';//+' '+enddate.getHours()+':'+enddate.getMinutes()+':'+enddate.getSeconds());
    console.log("Calling: "+url);
    $http.jsonp(url,{timeout: 60000})
    .success(function(data) {
      //console.log("Success: "+JSON.stringify(data));
      deferred.resolve(data);
    })
    .error(function(data) {
      //console.log('ERR:'+JSON.stringify(data));
      deferred.reject(data);
    });

    return deferred.promise;
  };

  this.getTopCustomersBetweenDates = function(storeid, mobile, token, startdate = null, enddate = null, showHowMany = null) {
    var deferred = $q.defer();
    var url = SERVER.urlAPIforGetTopCustomersBetweenDates+'&storeid='+storeid+"&mobile="+mobile+"&token="+token;
    if(startdate)
      url += "&startdate="+encodeURIComponent(startdate.getFullYear() + "-" + (startdate.getMonth() + 1) + "-" + startdate.getDate()) + ' 00:00:00';//+' '+startdate.getHours()+':'+startdate.getMinutes()+':'+startdate.getSeconds());
    if(enddate)
      url += "&enddate="+encodeURIComponent(enddate.getFullYear() + "-" + (enddate.getMonth() + 1) + "-" + enddate.getDate()) + ' 23:59:59';//+' '+enddate.getHours()+':'+enddate.getMinutes()+':'+enddate.getSeconds());
    if(showHowMany)
      url += "&showHowMany="+encodeURIComponent(showHowMany);
    console.log("Calling: "+url);
    $http.jsonp(url,{timeout: 60000})
    .success(function(data) {
      //console.log("Success: "+JSON.stringify(data));
      deferred.resolve(data);
    })
    .error(function(data) {
      //console.log('ERR:'+JSON.stringify(data));
      deferred.reject(data);
    });

    return deferred.promise;
  };

  this.getCashStatus = function(storeid, mobile, token, day = null) {
    var deferred = $q.defer();
    var url = SERVER.urlAPIforGetCashStatus+'&storeid='+storeid+"&mobile="+mobile+"&token="+token;
    if(day)
      url += "&day="+encodeURIComponent(day.getFullYear() + "-" + (day.getMonth() + 1) + "-" + day.getDate());
    console.log("Calling: "+url);
    $http.jsonp(url,{timeout: 60000})
    .success(function(data) {
      //console.log("Success: "+JSON.stringify(data));
      deferred.resolve(data);
    })
    .error(function(data) {
      //console.log('ERR:'+JSON.stringify(data));
      deferred.reject(data);
    });

    return deferred.promise;
  };

  this.getItemWiseSale = function(storeid, mobile, token, reportyby = null, startdate = null, enddate = null) {
    var deferred = $q.defer();
    var url = SERVER.urlAPIforGetItemWiseSale+'&storeid='+storeid+"&mobile="+mobile+"&token="+token;
    if(startdate)
      url += "&startdate="+encodeURIComponent(startdate.getFullYear() + "-" + (startdate.getMonth() + 1) + "-" + startdate.getDate()+' '+startdate.getHours()+':'+startdate.getMinutes()+':'+startdate.getSeconds());
    if(enddate)
      url += "&enddate="+encodeURIComponent(enddate.getFullYear() + "-" + (enddate.getMonth() + 1) + "-" + enddate.getDate()+' '+enddate.getHours()+':'+enddate.getMinutes()+':'+enddate.getSeconds());
    if(reportyby)
      url += "&reportby="+reportyby;
    console.log("Calling: "+url);
    $http.jsonp(url,{timeout: 60000})
    .success(function(data) {
      //console.log("Success: "+JSON.stringify(data));
      deferred.resolve(data);
    })
    .error(function(data) {
      //console.log('ERR:'+JSON.stringify(data));
      deferred.reject(data);
    });

    return deferred.promise;
  };

  this.getInventoryConsumedBetweenDates = function(storeid, mobile, token, startdate = null, enddate = null) {
    var deferred = $q.defer();
    var url = SERVER.urlAPIforGetInventoryConsumedBetweenDates+'&storeid='+storeid+"&mobile="+mobile+"&token="+token;
    if(startdate)
      url += "&startdate="+encodeURIComponent(startdate.getFullYear() + "-" + (startdate.getMonth() + 1) + "-" + startdate.getDate()+' '+startdate.getHours()+':'+startdate.getMinutes()+':'+startdate.getSeconds());
    if(enddate)
      url += "&enddate="+encodeURIComponent(enddate.getFullYear() + "-" + (enddate.getMonth() + 1) + "-" + enddate.getDate()+' '+enddate.getHours()+':'+enddate.getMinutes()+':'+enddate.getSeconds());
    console.log("Calling: "+url);
    $http.jsonp(url,{timeout: 60000})
    .success(function(data) {
      deferred.resolve(data);
    })
    .error(function(data) {
      deferred.reject(data);
    });
    return deferred.promise;
  };
})

.service('AuthService', function($http, $q, SERVER, $interval) {
  var LOCAL_LANGUAGE = 'LOCAL_LANGUAGE';
  var LOCAL_TOKEN_KEY = 'LOCAL_TOKEN';
  var LOCAL_MOBILENO_KEY = 'LOCAL_MOBILE';
  var LOCAL_USERNAME = 'LOCAL_USERNAME';
  var LOCAL_AUTOREFRESHREPORTS = 'LOCAL_AUTOREFRESHREPORTS';
  var LOCAL_USER_RESTAURANT_LIST = 'LOCAL_USER_RESTAURANT_LIST';

  var mobile = 0;
  var isAuthenticated = false;
  var role = '';
  var authToken;
  var username = '';
  var restaurants = [];


  function setUserName(uname) {
    console.log('Set Local Storage User Name: '+uname);
    window.localStorage.setItem(LOCAL_USERNAME, uname);
    username = uname;
  }

  function setRestaurantJSON(restaurantJSON) {
    //overwrite local JSON
    window.localStorage.setItem(LOCAL_USER_RESTAURANT_LIST, JSON.stringify(restaurantJSON));
    restaurants = restaurantJSON;
  }

  function setUserMobile(umobile) {
    console.log('Set Local Storage Mobile: '+umobile);
    window.localStorage.setItem(LOCAL_MOBILENO_KEY, umobile);
    mobile = umobile;
  }

  function setLanguage(language) {
    console.log('Set Local Storage Language: '+language);
    window.localStorage.setItem(LOCAL_LANGUAGE, language);
  }

  function setAutoRefreshReports(autorefresh) {
    console.log('Set Local Storage Refresh Reports: '+autorefresh);
    window.localStorage.setItem(LOCAL_AUTOREFRESHREPORTS, (autorefresh?1:0));
  }

  function loadUserCredentials() {
    var token = window.localStorage.getItem(LOCAL_TOKEN_KEY);
    var mobile = window.localStorage.getItem(LOCAL_MOBILENO_KEY);
    var restaurants = window.localStorage.getItem(LOCAL_USER_RESTAURANT_LIST);

    if (token) {
      useCredentials(token);
      isAuthenticated = true;
    }
    else
      isAuthenticated = false;
  }

  function storeUserCredentials(token) {
    window.localStorage.setItem(LOCAL_TOKEN_KEY, token);
    useCredentials(token);
  }

  function useCredentials(token) {
    // Set the token as header for http requests!
    $http.defaults.headers.common['X-Auth-Token'] = token;
  }

  function destroyUserCredentials() {
    authToken = undefined;
    mobile = null;
    isAuthenticated = false;
    $http.defaults.headers.common['X-Auth-Token'] = undefined;
    window.localStorage.removeItem(LOCAL_TOKEN_KEY);
    window.localStorage.removeItem(LOCAL_MOBILENO_KEY);
    window.localStorage.removeItem(LOCAL_USER_RESTAURANT_LIST);
    window.localStorage.removeItem(LOCAL_USERNAME);
    window.localStorage.removeItem(LOCAL_LANGUAGE);
    window.localStorage.removeItem(LOCAL_AUTOREFRESHREPORTS);
  }

  var generateLoginCode = function(usermobile) {
    return $q(function(resolve, reject) {
      if(usermobile)//.lenght == 10)
      {
        var surl = SERVER.urlformobilecode+'&mobile='+usermobile;
        console.log("Calling API: "+surl);
        $http.defaults.useXDomain = true;
        //$http.defaults.withCredentials = false;
        delete $http.defaults.headers.common['X-Requested-With'];
        //$http.defaults.headers.common["Accept"] = "application/json";
        //$http.defaults.headers.common["Content-Type"] = "application/json";

        // Make a request and receive your auth token from your server
        $http({
          method: 'JSONP',
          url: surl})
        .success(function(data, status, headers, config) {
          console.log(' GOT THIS: ' + data.msg + "");
          if(data.status == "success")
          {
            setUserMobile(usermobile);
            resolve('Code Has Been Generated.');
          }
          else
          {
            console.log(data.status+':'+data.msg);
            reject('Mobile Verification Rejected by Server. ' + data.msg);
          }
        })
        .error(function(data, status, headers, config) {
          console.log(status+':'+data);
          reject('Try again, Mobile Verification Failed from Server.');
        });
      } else {
        reject('Mobile number entered is not in correct format.');
      }
    });
  };

  var login = function(mobile, pw) {
    return $q(function(resolve, reject) {

      if(pw)//.lenght == 10)
      {
        var surl = SERVER.urlforcodesubmittion+'&mobile='+mobile+'&verificationcode='+pw;
        console.log(surl);
        $http.defaults.useXDomain = true;
        //$http.defaults.withCredentials = false;
        delete $http.defaults.headers.common['X-Requested-With'];
        //$http.defaults.headers.common["Accept"] = "application/json";
        //$http.defaults.headers.common["Content-Type"] = "application/json";

        // Make a request and receive your auth token from your server
        $http({
          method: 'JSONP',
          url: surl})
        .success(function(data, status, headers, config) {
          console.log(' GOT THIS: ' + data.msg + "");
          if(data.status == "success")
          {
            storeUserCredentials(data.token);
            console.log(JSON.stringify(data.stores));
            setRestaurantJSON(data.stores);
            setUserName(data.nameofuser);
            resolve('Code Has Been Verified.');
          }
          else
            reject('Verification Code appears to be incorrect. ' + data.msg);
        })
        .error(function(data, status, headers, config) {
          reject('Try again, Mobile Verification Failed from Server.');
        });
      } else {
        reject('Verification Code number entered is not in correct format.');
      }
    });
  };

  var logout = function() {
    destroyUserCredentials();
  };

  var isAuthorized = function() {
    loadUserCredentials();
    return (isAuthenticated);
  };

  loadUserCredentials();

  function fetchStores(){
    var mobile = window.localStorage.getItem(LOCAL_MOBILENO_KEY);
    var token = window.localStorage.getItem(LOCAL_TOKEN_KEY);

    if(!mobile || !token)
      return;

      var surl = SERVER.urlfornewstores+'&mobile='+mobile+'&token='+token;
      $http.defaults.useXDomain = true;

      delete $http.defaults.headers.common['X-Requested-With'];

      // Make a request and receive your auth token from your server
      $http({
        method: 'JSONP',
        url: surl})
      .success(function(data, status, headers, config) {
        console.log(' GOT THIS: ' + data.msg + "");
        if(data.status == "success")
        {
          console.log(JSON.stringify(data.stores));
          setRestaurantJSON(data.stores);
        }
      });
  };
  //$interval(fetchStores, 5000);

  return {
    //local storage for mobiles
    getMobile: function() { return window.localStorage.getItem(LOCAL_MOBILENO_KEY);},
    getToken: function() { return window.localStorage.getItem(LOCAL_TOKEN_KEY);},
    getRestaurantJSON: function() { return JSON.parse(window.localStorage.getItem(LOCAL_USER_RESTAURANT_LIST));},
    getUserName: function() { return window.localStorage.getItem(LOCAL_USERNAME);},
    getLanguage: function() { return window.localStorage.getItem(LOCAL_LANGUAGE);},
    getAutoRefreshReports: function() { return (window.localStorage.getItem(LOCAL_AUTOREFRESHREPORTS) == 1?true:false);},

    setUserMobile: setUserMobile,
    setLanguage: setLanguage,
    setAutoRefreshReports: setAutoRefreshReports,
    fetchStores : fetchStores,

    login: login,
    generateLoginCode: generateLoginCode,
    logout: logout,
    isAuthorized: isAuthorized,
  };
})

;
