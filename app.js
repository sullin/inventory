var app = angular.module("myApp", ["ngTable", "ui.bootstrap", 'ngCookies', 'pascalprecht.translate']);

app.config(function ($translateProvider) {
	$translateProvider
	.useStaticFilesLoader({
		prefix: 'locales/locale-',
		suffix: '.json'
	}) 
	.useSanitizeValueStrategy('sanitizeParameters')    
	.useMissingTranslationHandlerLog()
	.registerAvailableLanguageKeys(
		['et', 'en'], {
			'en*': 'en',
			'et*': 'et',
			'*': 'en'
		}
	)
	.fallbackLanguage('en')
	.preferredLanguage('en')
	.useCookieStorage()
	.determinePreferredLanguage();
});

app.controller("viewerController", function($scope, $q, $http, NgTableParams, $uibModal, $translate) {
	$translate('TITLE').then(function (text) { document.title=text; });

	$scope.changeLang = function (key) {
		$translate.use(key);
	};

	$scope.alerts = [];
	$scope.colsList = [
		{ field: "code", sortable: "code", title: "", show: true },
		{ field: "name", sortable: "name", title: "", show: true },
		{ field: "quantity", sortable: "quantity", title: "", show: true },
	];
	$scope.cols = _.indexBy($scope.colsList, "field");

	$translate('CODE').then(function (text) { $scope.cols['code'].title=text; });
	$translate('NAME').then(function (text) { $scope.cols['name'].title=text; });
	$translate('QUANTITY').then(function (text) { $scope.cols['quantity'].title=text; });

	$scope.msg_errloadingdata = "";
	$translate('ERR_LOADINGDATA').then(function (text) { $scope.msg_errloadingdata=text; });
	$scope.msg_itemnotfound = "";
	$translate('ERR_ITEMNOTFOUND').then(function (text) { $scope.msg_itemnotfound=text; });

	$scope.tableParams = new NgTableParams({
		page: 1,
		count: 10,
		filter: { code: "" } 
	}, {
		filterDelay: 300,
		getData: function(params) {
			return $http.get('api/data.php/items', {cache: false, params: params.url()}).then(function(data) {
				$scope.closeAlerts("data");
				params.total(data["data"]["total"]);
				return data["data"]["items"];
			}, function() {
				$scope.alerts.push({cat: "data", msg: $scope.msg_errloadingdata});
			});
		}
	});
	$scope.closeAlerts = function(cat) {
		$scope.alerts = $scope.alerts.filter(function(v,i,a) { 
			return v.cat != cat;
		});
	}
	$scope.closeAlert = function(index) {
		$scope.alerts.splice(index, 1);
	};

	$scope.reload = function(item) {
		$scope.tableParams.reload();
	};
	$scope.showItem = function(item) {
		$uibModal.open({
			animation: false,
			templateUrl: 'itemDetails.html',
			controller: 'itemDetailsController',
			size: 'lg',
			resolve: {
				item: function () {return item;}
			}
		}).result.then(function () {
			$scope.tableParams.reload();
		}, function() {});
	};

	$scope.addItem = function() {
		$uibModal.open({
			animation: false,
			templateUrl: 'addItem.html',
			controller: 'addItemController',
			size: 'sm',
		}).result.then(function () {
			$scope.tableParams.reload();
		}, function() {});
	};

	$scope.openItem = function(code) {
		$http.get('api/data.php/item', {cache: false, params: {"code": code}}).then(function(data) {
			if (data["data"]["ok"]) {
				$scope.closeAlerts("open");
				$scope.showItem(data["data"]["item"]);
			} else {
				$scope.closeAlerts("open");
				$scope.alerts.push({cat: "open", msg: $scope.msg_itemnotfound + code});
			}
		}, function() {
			$scope.closeAlerts("open");
			$scope.alerts.push({cat: "open", msg: $scope.msg_itemnotfound + code});
		});
	};

	$scope.searchKeyPress = function(event) {
		if (event.keyCode==13) {
			var code = $scope.tableParams.filter().code;
			if (code) {
				$scope.openItem(code);
				$scope.tableParams.filter().code = "";
			}
		}
	};
});

app.controller("itemDetailsController", function($scope, item, $http, NgTableParams, $uibModalInstance, $uibModal, $translate) {
	$scope.alerts = [];
	$scope.item = item;
	$scope.totalq = 0;
	$scope.close = function() {
		return $uibModalInstance.close();
	};

	$scope.colsList = [
		{ field: "quantity", sortable: "quantity", title: "", show: true },
		{ field: "date", sortable: "date", title: "", show: true },
		{ field: "comment", sortable: "comment", title: "", show: true },
	];
	$scope.cols = _.indexBy($scope.colsList, "field");

	$translate('QUANTITY').then(function (text) { $scope.cols['quantity'].title=text; });
	$translate('DATE').then(function (text) { $scope.cols['date'].title=text; });
	$translate('COMMENT').then(function (text) { $scope.cols['comment'].title=text; });

	$scope.msg_errloadingdata = "";
	$translate('ERR_LOADINGDATA').then(function (text) { $scope.msg_errloadingdata=text; });

	$scope.tableParams = new NgTableParams({
		page: 1,
		count: 10,
		filter: { comment: "" },
		sorting: { date: "desc" }
	}, {
		filterDelay: 300,
		getData: function(params) {
			return $http.get('api/data.php/itemtransactions', {cache: false, params: Object.assign({id: $scope.item['id']}, params.url()) }).then(function(data) {
				$scope.closeAlerts("data");
				params.total(data["data"]["total"]);
				$scope.totalq = data["data"]["totalq"];
				return data["data"]["transactions"];
			}, function() {
				$scope.closeAlerts("data");
				$scope.alerts.push({type: "data", msg: $scope.msg_errloadingdata});
			});
		}
	});

	$scope.closeAlerts = function(cat) {
		$scope.alerts = $scope.alerts.filter(function(v,i,a) {
			return v.cat != cat;
		});
	}
	$scope.closeAlert = function(index) {
		$scope.alerts.splice(index, 1);
	};
	$scope.reload = function(item) {
		$scope.tableParams.reload();
	};
	$scope.addTransaction = function() {
		$uibModal.open({
			animation: false,
			templateUrl: 'addTransaction.html',
			controller: 'addTransactionController',
			size: 'sm',
			resolve: {
				item: function(){ return $scope.item; }
			}
		}).result.then(function (selectedItem) {
			$scope.tableParams.reload();
		}, function() {
		});
	};
});


app.controller("addItemController", function($scope, $http, $uibModalInstance, $translate) {
	$scope.close = function() {
		return $uibModalInstance.close();
	};
	$scope.data = {code:'', name:''};
	$scope.alert = "";

	$scope.msg_erraddingitem = "";
	$translate('ERR_FAILEDADDINGITEM').then(function (text) { $scope.msg_erraddingitem=text; });

	$scope.submit = function() {
		$http.post('api/data.php/item', $scope.data).then(function(data) {
			if (data["data"]["ok"])
				$uibModalInstance.close();
			else
				$scope.alert = $scope.msg_erraddingitem;
		}, function(data) {
			$scope.alert = $scope.msg_erraddingitem;
		});
	};
});


app.controller("addTransactionController", function($scope, item, $http, $uibModalInstance, $translate) {
	$scope.item = item;
	$scope.close = function() {
		return $uibModalInstance.close();
	};
	$scope.data = {itemid: item.id, quantity:0, date: new Date(), comment:''};
	$scope.alert = "";

	$scope.msg_erraddingtrans = "";
	$translate('ERR_FAILEDADDINGTRANS').then(function (text) { $scope.msg_erraddingtrans=text; });

	$scope.submit = function() {
		$http.post('api/data.php/transaction', $scope.data).then(function(data) {
			if (data["data"]["ok"])
				$uibModalInstance.close();
			else
				$scope.alert = $scope.msg_erraddingtrans;
		}, function(data) {
			$scope.alert = $scope.msg_erraddingtrans;
		});
	};

	var now = new Date();
	$scope.datepickerOptions = {
		formatYear: 'yy',
		maxDate: new Date(now.getFullYear()+2, 1, 1),
		minDate: new Date(now.getFullYear()-2, 1, 1),
		startingDay: 1,
		showWeeks: false
	};

	$scope.datepicker_opened = false;
	$scope.opendatepicker = function() {
		$scope.datepicker_opened = !$scope.datepicker_opened;
	};
});

