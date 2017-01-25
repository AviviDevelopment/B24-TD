/* Routine */
(function () {
	/**
	 * Returns the class name of object.
	 * @param object {Object}
	 * @returns Class name of object
	 * @type String
	 */
	 var getClass = function (object) {
	   return Object.prototype.toString.call(object).slice(8, -1);
	 };

   /**
    * Returns true of obj is a collection.
    * @param obj {Object}
    * @returns True if object is a collection
    * @type {bool}
    */
	var isValidCollection = function (obj) {
		try {
			return (
				typeof obj !== "undefined" &&  // Element exists
				getClass(obj) !== "String" &&  // weed out strings for length check
				typeof obj.length !== "undefined" &&  // Is an indexed element
				!obj.tagName &&  // Element is not an HTML node
				!obj.alert &&  // Is not window
				typeof obj[0] !== "undefined"  // Has at least one element
			);
		} catch (e) {
			return false;
		}
	};

	/**
	 * Merges an array with an array-like object or
	 * two objects.
	 * @param arr1 {Array|Object} Array that arr2 will be merged into
	 * @param arr2 {Array|NodeList|Object} Array-like object or Object to merge into arr1
	 * @returns Merged array
	 * @type {Array|Object}
	 */
	window.array_merge = function (arr1, arr2) {
		// Variable declarations
		var arr1Class, arr2Class, i, il;

		// Save class names for arguments
		arr1Class = getClass(arr1);
		arr2Class = getClass(arr2);

		if (arr1Class === "Array" && isValidCollection(arr2)) {  // Array-like merge
			if (arr2Class === "Array") {
				arr1 = arr1.concat(arr2);
			} else {  // Collections like NodeList lack concat method
				for (i = 0, il = arr2.length; i < il; i++) {
					arr1.push(arr2[i]);
				}
			}
		} else if (arr1Class === "Object" && arr1Class === arr2Class) {  // Object merge
			for (i in arr2) {
				if (i in arr1) {
					if (getClass(arr1[i]) === getClass(arr2[i])) {  // If properties are same type
						if (typeof arr1[i] === "object") {  // And both are objects
							arr1[i] = array_merge(arr1[i], arr2[i]);  // Merge them
						} else {
							arr1[i] = arr2[i];  // Otherwise, replace current
						}
					}
				} else {
					arr1[i] = arr2[i];  // Add new property to arr1
				}
			}
		}
		return arr1;
	};

	var domain = '';

})();

// our application constructor
function application () {

	/*var curapp = this;*/
	this.getAppInfo();
	window.domain = this.appInfo['DOMAIN'];

    BX24.init(function(){
    	var supportedLangs = {
			'en':'lang/en.js',
			'de':'lang/de.js',
			'ru':'lang/ru.js',
			'ua':'lang/ua.js'
		};

	var currentLang = BX24.getLang();

	if(typeof supportedLangs[currentLang] == 'undefined')
		currentLang = 'en';

	$.getScript(supportedLangs[currentLang], function(data, textStatus) {
		$('#tapp').html(window.menuMessage.TITILE_APPLICATION);
		$('#tTD').html(window.menuMessage.TITLE_TD_P1 + '<a  href="https://webapi.timedoctor.com/app" target="_blank">' + window.menuMessage.TITLE_TD_P2 + '</a>');
		$('#tB24').html(window.menuMessage.TITLE_B24_P1 + '<a  href="https://' + window.domain + '/marketplace/local/list/" target="_blank">' + window.menuMessage.TITLE_B24_P2 + '</a>');
		$('#tprTD').html(window.menuMessage.TITLE_PROJECT_TD);
		$('#save-btn').html('<i class="fa fa-check"></i>' + window.menuMessage.SAVE_BUTTON + '<div class="ripple-wrapper"></div>');
		});

	var currentSize = BX24.getScrollSize();
	console.log(currentSize);
	minHeight = currentSize.scrollHeight;
	minWidth = currentSize.scrollWidth;

	if (minHeight < 1000) minHeight = 1000;
	BX24.resizeWindow(minWidth, minHeight);

    });
}

/** installation methods */
application.prototype.finishInstallation = function () {

	// start saving
	$('#save-btn').find('i').removeClass('fa-check').addClass('fa-spinner').addClass('fa-spin');
	var DBparams = {
		TDclientId 		: $('#TD_client_id').val(),
		TDsecretKey 	: $('#TD_secret_key').val(),
		TDprojectName 	: $('#TD_project_name').val(),

		B24clientId 	: $('#B24_client_id').val(),
		B24clientSecret : $('#B24_client_secret').val()
	};


	var authParams = BX24.getAuth(),
		params = array_merge({operation: 'portaladd'}, authParams),
		params = array_merge(params, DBparams),
		curapp = this;
		
	var isEmptyFields = false;
	for(prop in DBparams) {
		if (DBparams[prop].length === 0) 
		 	isEmptyFields = true;
	}

	if (isEmptyFields){
		$('#error').removeClass('hidden').html('<p>Все поля обязательны!</p>');
	}
	else{
		$.post(
			"application.php",
			params,
			function (data)
			{
				$('#error').addClass('hidden').html('');
				$('#save-btn').find('i').removeClass('fa-spinner').removeClass('fa-spin').addClass('fa').addClass('fa-check');
			}

		);
	}
	
}

/* common methods */
application.prototype.getAppInfo = function() {
	this.appInfo = [];
    var arParameters = document.location.search;
    if (arParameters.length > 0) {
        arParameters = arParameters.split('&');
        for (var i = 0; i < arParameters.length; i++) {

            var aPair = arParameters[i].split('=');
            var aKey = aPair[0].replace(new RegExp("\\?", 'g'), "");

            this.appInfo[aKey] = aPair[1];
        }
    }
}

 app = new application();