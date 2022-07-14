
var CoreUI = typeof CoreUI !== 'undefined' ? CoreUI : {};

CoreUI.tabs = {


    /**
     * Загрузка контента в тело
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

        $('#core-tabs-' + resource + ' > .core-tabs-body > .core-tabs-content').load(url, function () {

            $('#core-tabs-' + resource + ' > .core-tabs-body > .core-tabs-tabs > li').removeClass('active');
            $('#core-tabs-' + resource + ' > .core-tabs-body > .core-tabs-tabs > li#panel-tab-' + tabId).addClass('active');

            preloader.hide();

            if (typeof callback === 'function') {
                callback();
            }
        })
    }
}