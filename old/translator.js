angular.module('starter.translations', [])
.config(['$translateProvider', function ($translateProvider) {

  //$translateProvider.translations('en', translations_en);
  //$translateProvider.preferredLanguage('en');

  //$translateProvider.preferredLanguage(AuthService.getLanguage());
  //$translateProvider.determinePreferredLanguage();
  $translateProvider.useSanitizeValueStrategy('escape');
}]);
