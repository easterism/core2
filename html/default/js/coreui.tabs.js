
var CoreUI = typeof CoreUI !== 'undefined' ? CoreUI : {};

CoreUI.tabs = {


    /**
     * Загрузка контента в тело
     * @param resource
     * @param url
     * @param callback
     * @returns {HTMLDivElement}
     */
    loadContent: function(resource, url, callback) {

        preloader.show();

        $('#core-tabs-' + resource + ' > .core-tabs-body > .core-tabs-content').load(url, function () {
            preloader.hide();

            if (typeof callback === 'function') {
                callback();
            }
        })
    }
}