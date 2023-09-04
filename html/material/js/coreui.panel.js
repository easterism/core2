
var CoreUI = typeof CoreUI !== 'undefined' ? CoreUI : {};

CoreUI.panel = {


    /**
     * Загрузка контента в тело панели
     * @param resource
     * @param tabId
     * @param url
     * @param event
     * @param callback
     * @returns {HTMLDivElement}
     */
    loadContent: function(resource, tabId, url, event, callback) {

        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        preloader.show();

        url = url.replace('#', '?');

        $('#core-panel-' + resource + ' > .core-panel-body > .core-panel-content').load(url, function () {

            $('#core-panel-' + resource + ' > .core-panel-body > .core-panel-tabs > li').removeClass('active');
            $('#core-panel-' + resource + ' > .core-panel-body > .core-panel-tabs > li#panel-' + resource + '-' + tabId).addClass('active');

            preloader.hide();

            if (typeof callback === 'function') {
                callback();
            }
        })
    }
}