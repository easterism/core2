/**
 * Created by StepovichPE on 15.03.2017.
 */

var gl = {
    'xxx' : {},
    'modal': function () {
        $('#modal_gitlab').modal({
                autoResize: true,
                minHeight: 450,
                maxHeight: 650,
                minWidth: 830,
                maxWidth: 930,
                position: [350, '20%'],
                onShow: function (dialog) {
                    load('index.php?module=admin&action=modules&gitlab=1', {}, 'modal_gitlab');
                },
                onClose: function (dialog) {
                    dialog.data.fadeOut('fast', function () {
                        dialog.container.slideUp('fast', function () {
                            dialog.overlay.fadeOut('fast', function () {
                                $.modal.close();
                            });
                        });
                    });
                }
            }
        )
    },
    selectTag : function (group, tag) {
        if (group && tag) {
            this.xxx['group'] = group;
            this.xxx['tag'] = tag;
        }
    }
}