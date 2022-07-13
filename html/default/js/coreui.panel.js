
var CoreUI = typeof CoreUI !== 'undefined' ? CoreUI : {};

CoreUI.panel = {


    /**
     * Загрузка контента в тело панели
     * @param resource
     * @param url
     * @param callback
     * @returns {HTMLDivElement}
     */
    loadContent: function(resource, url, callback) {

        preloader.show();

        $('#core-panel-' + resource + ' > .core-panel-body > .core-panel-content').load(url, function () {
            preloader.hide();

            if (typeof callback === 'function') {
                callback();
            }
        })
    }
}