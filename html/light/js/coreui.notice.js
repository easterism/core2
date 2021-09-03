
var CoreUI = typeof CoreUI !== 'undefined' ? CoreUI : {};

CoreUI.notice = {

    __autoincrement: 0,

    /**
     * @param options
     * @param type
     * @param icon
     * @returns {HTMLDivElement}
     */
    create: function(options, type, icon) {

        if (typeof options === 'string') {
            var text = options;
            options = {};
            options.text = text;
            options.type = type || 'default';
            options.icon = icon || '';
        }

        let limit = 3;
        let container = document.getElementById("coreui-notice-container");

        if ( ! container) {
            const container = document.createElement("div");
            container.id = "coreui-notice-container";
            document.body.appendChild(container);
        }

        const toast = document.createElement("div");
        toast.id = ++this.__autoincrement;
        toast.id = "toast-" + toast.id;
        if (options.animationIn) {
            toast.className = "coreui-notice animated " + options.animationIn;
        } else {
            toast.className = "coreui-notice animated fadeIn";
        }

        const containertoast = document.createElement("div");
        containertoast.className = "vh";
        toast.appendChild(containertoast);

        //imagen
        if (options.image) {
            const containerimage = document.createElement("span");
            containerimage.className = "b4cimg";
            containertoast.appendChild(containerimage);
            const img = document.createElement("img");
            img.src = options.image;
            img.className = "bAimg";
            containerimage.appendChild(img);
            if (options.important) {
                const important = document.createElement("i");
                important.className = "important";
                containerimage.appendChild(important);
            }
        }

        //add icon
        if (options.icon) {
            const containericono = document.createElement("span");
            containericono.className = "b4cicon";
            containertoast.appendChild(containericono);
            const icono = document.createElement("i");
            icono.className = options.icon;
            containericono.appendChild(icono);
            if (options.important) {
                const importanticon = document.createElement("i");
                importanticon.className = "important";
                containericono.appendChild(importanticon);
            }
        }

        // descripcion texto
        const p = document.createElement("span");
        p.className = "bAq";
        if (options.text) {
            p.innerHTML = options.text;
        } else {
            p.innerHTML = "";
        }
        containertoast.appendChild(p);

        const buttoncontainer = document.createElement("span");
        buttoncontainer.className = "bAo";
        containertoast.appendChild(buttoncontainer);

        //button ok

        if (typeof options.callbackOk === "function") {
            const buttonOK = document.createElement("span");
            if (options.buttonOk) {
                buttonOK.innerHTML = options.buttonOk;
            } else {
                buttonOK.innerHTML = "OK";
            }
            buttonOK.className = "a8k";
            buttoncontainer.appendChild(buttonOK);

            buttonOK.addEventListener("click", function(event) {
                event.stopPropagation();
                options.callbackOk.call(removeSnackbar());
            });
        }

        //botton cancelar

        if (typeof options.callbackCancel === "function") {
            const buttonCancel = document.createElement("span");
            if (options.buttonCancel) {
                buttonCancel.innerHTML = options.buttonCancel;
            } else {
                buttonCancel.innerHTML = "No";
            }
            buttonCancel.className = "a8k";
            buttoncontainer.appendChild(buttonCancel);

            buttonCancel.addEventListener("click", function(event) {
                event.stopPropagation();
                options.callbackCancel.call(removeSnackbar());
            });
        }



        //botton cerrar notificacion
        const contenedorClose = document.createElement("div");
        contenedorClose.className = "bBe";
        containertoast.appendChild(contenedorClose);

        const buttonClose = document.createElement("div");
        buttonClose.className = "bBf";
        contenedorClose.appendChild(buttonClose);

        contenedorClose.addEventListener("click", function(event) {
            event.stopPropagation();
            removeSnackbar();
        });

        toast.hide = function() {
            if (options.animationIn) {
                toast.classList.remove(options.animationIn);
            } else {
                toast.classList.remove("fadeIn");
            }

            if (options.animationOut) {
                toast.classList.add(options.animationOut);
            } else {
                toast.classList.add("fadeOut");
            }
            window.setTimeout(function() {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 200);
        };

        // auto close
        if (typeof options.duration === 'number') {
            if (options.duration > 0) {
                window.setTimeout(toast.hide, options.duration);
            }

        } else {
            window.setTimeout(toast.hide, 6000);
        }

        if (options.rounded) {
            toast.className += " rounded";
        }

        if (options.type) {
            toast.className += " coreui-notice-" + options.type;
        } else {
            toast.className += " coreui-notice-default";
        }

        if (options.classes) {
            toast.className += " " + options.classes;
        }

        if (typeof options.limit === 'number') {
            if (options.limit >= 0) {
                limit = options.limit;
            }
        }

        const removeSnackbar = function() {
            if (options.animationIn) {
                toast.classList.remove(options.animationIn);
            } else {
                toast.classList.remove("fadeIn");
            }

            if (options.animationOut) {
                toast.classList.add(options.animationOut);
            } else {
                toast.classList.add("fadeOut");
            }
            window.setTimeout(
                function () {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }.bind(this),
                200
            );
        }

        container = document.getElementById("coreui-notice-container");

        if (limit > 0 && container && container.childNodes.length >= limit) {
            container.childNodes[0].hide();
        }

        container.appendChild(toast);
        return toast;
    },
}