(function () {
    "use strict";

    let ajax, media, dialog;

    /** Global Variables */
    let field   = "";
    let fieldID = 10000;



    /**
     * Returns true if the Browser supports the History API and Fetch
     * @returns {Boolean}
     */
    function supportsHistoryAndFetch() {
        return !!(window.history && history.pushState && window.fetch);
    }



    /** Initialize the TinyMCE */
    if (window.tinymce) {
        window.tinymce.init({
            selector           : ".tinymce",
            height             : 600,
            theme              : "silver",
            language           : "es",
            plugins            : "paste link hr table lists code",
            menubar            : false,
            relative_urls      : false,
            remove_script_host : false,
            paste_as_text      : true,
            image_advtab       : true,
            toolbar            : `
                undo redo | formatselect fontselect fontsizeselect |
                bold italic underline strikethrough forecolor backcolor removeformat |
                alignleft aligncenter alignright alignjustify | link | table outdent indent |
                bullist numlist | blockquote hr code |
            `,
        });
    }

    /** Initialize the Datepicker */
    $.datepicker.setDefaults({
        dateFormat  : "dd-mm-yy",
        prevText    : "«",
        nextText    : "»",
        dayNamesMin : [ "Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sá" ],
        dayNames    : [ "Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado" ],
        monthNames  : [ "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre" ]
    });



    /** Add the Required Listeners */
    $("html")
        // Menu
        .on("click", ".topbar-back, .sidebar", (e) => {
            const $elem = $(e.target);
            if ($elem.hasClass("topbar-back") || $elem.hasClass("sidebar")) {
                ajax.hideMenu();
                e.stopImmediatePropagation();
                e.preventDefault();
            }
        })
        .on("click", ".topbar-menu", (e) => {
            ajax.showMenu();
            e.preventDefault();
        })

        // Result
        .on("click", ".result-bar", (e) => {
            $(e.target).fadeOut();
        })

        // Tables
        .on("click", ".table-container:not([data-noclick]) tr", (e) => {
            const selection = window.getSelection();
            let   handle    = true;
            if (selection) {
                const text = selection.toString();
                handle = !text;
            }
            if (handle) {
                const $tr = $(e.target).closest("tr");
                if ($tr.attr("data-url")) {
                    ajax.loadUrl($tr.attr("data-url"));
                } else {
                    const $links = $tr.find("a");
                    if ($links.length) {
                        $links[0].click();
                    }
                }
                e.stopImmediatePropagation();
                e.preventDefault();
            }
        })

        // File Input
        .on("change", ".file-box input[type='file']", (e) => {
            const $elem = $(e.target);
            const path  = String($elem.val());
            const name  = path.substr(path.lastIndexOf("\\") + 1);
            $elem.parent().parent().find("input[type='text']").val(name);
        })

        // Tabs
        .on("click", ".tabs-bar li", (e) => {
            ajax.gotoTab($(e.target), true);
        })

        // Accordeon
        .on("click", ".accordeon-header", (e) => {
            const $elem = $(e.target).closest(".accordeon");
            ajax.setAccordeon($elem.attr("data-accordeon"), true);
        })

        // Adds and Removes Rows
        .on("click", "[data-add]", (e) => {
            const name   = $(e.target).data("add");
            const $copy  = $(`[data-rows="${name}"]`).find(".copy-row");
            const $clone = $copy.clone(true).removeClass("copy-row").insertBefore($copy);

            $clone.find(".datepicker").datepicker();
            if ($copy.data("field")) {
                const $input = $clone.find(".input-row");
                const $link  = $clone.find(".link-row");
                $input.attr("id", $input.attr("id") + fieldID);
                $link.attr("data-field", $link.attr("data-field") + fieldID);
                fieldID += 1;
            }
            $(document.activeElement).blur();
            e.preventDefault();
        })
        .on("click", ".remove-row", (e) => {
            $(e.target).parent().parent().remove();
            e.preventDefault();
        })

        // Media Actions
        .on("click", ".media", (e) => {
            e.preventDefault();
            const $elem = $(e.target).closest("a");
            let   href  = $elem.attr("href");
            field = $elem.attr("data-field");

            if (field) {
                const val = $(`#${field}`).val();
                if (val) {
                    href += `&selected=${val}`;
                }
            }
            ajax.loadDialog(`${href}&select=1`, "media");
        })
        .on("click", ".dialog-media .media-sel", (e) => {
            e.stopPropagation();
            e.preventDefault();

            const path = $(e.target).closest(".media-sel").attr("data-path");
            if (path && field) {
                $(`#${field}`).val(path);
            }
            ajax.closeDialog();
        })
        .on("click", ".content-frame .media-sel", (e) => {
            e.stopPropagation();
            e.preventDefault();

            const $elem  = $(e.target).closest(".media-sel");
            const url    = $elem.attr("data-url");
            const name   = $elem.attr("data-name");
            const tiny   = window.parent.tinymce;
            const editor = tiny && tiny.activeEditor ? tiny.activeEditor.windowManager : null;

            if (url && name && editor) {
                editor.setUrl(url, name);
                editor.close();
            }
        })
        .on("click", ".resizer-btn", (e) => {
            e.stopPropagation();
            e.preventDefault();

            $(".resizer-btn").hide();
            media.resizeImages();
        })

        // Confirm Dialog
        .on("click", "[data-confirm]", (e) => {
            const $elem = $(e.target).closest("a");
            const title = $(e.target).data("title") || $(e.target).text() || "Confirmar";
            const text  = $elem.data("confirm");
            const href  = $elem.attr("href");
            const txt   = text && text !== 1 ? text : "¿Realmente desea eliminar este elemento?";

            dialog.confirm(title, txt).then(() => {
                ajax.loadUrl(href, "confirmed", "1");
            });
            e.preventDefault();
            e.stopPropagation();
        })

        // Prompt Dialog
        .on("click", "[data-prompt]", (e) => {
            const $elem = $(e.target).closest("a");
            const title = $elem.data("title") || $elem.text() || "Editar";
            const text  = $elem.data("prompt");
            const href  = $elem.attr("href");
            const key   = $elem.data("key") || "reason";
            const val   = $elem.data("value");

            dialog.prompt(title, text, val).then((value) => {
                ajax.loadUrl(href, key, value);
            });
            e.preventDefault();
            e.stopPropagation();
        })

        // Ajax Content
        .on("click", `.logout`, (e) => {
            ajax.unsetToken();
            ajax.loadPage(`session/logout?redirectUrl=${location.href}`, true);
            e.preventDefault();
        })
        .on("click", `a[href^="${window.ADMIN_URL}"]`, (e) => {
            const $elem    = $(e.target).closest("a");
            const $media   = $(e.target).closest(".dialog-media");
            const $menu    = $(e.target).closest(".sidebar-menu");
            const $submenu = $elem.next(".sub-nav");
            const href     = $elem.attr("href");
            const target   = $elem.attr("target");
            const elem     = $elem[0];

            if (target !== "_blank" &&
                href !== "#" &&
                href.indexOf("files") === -1 &&
                !$elem.hasClass("media") &&
                !elem.hasAttribute("data-confirm") &&
                !elem.hasAttribute("data-prompt") &&
                !elem.hasAttribute("data-noajax") &&
                supportsHistoryAndFetch()
            ) {
                e.preventDefault();
                if (elem.hasAttribute("data-download")) {
                    ajax.download(href);
                } else if (elem.hasAttribute("data-redirect")) {
                    ajax.loadPage(href);
                } else if ($media.length) {
                    ajax.loadDialog(`${href}&select=1`, "media");
                } else if ($menu.length && $submenu.length && $(window).width() < 900) {
                    ajax.selectMenu($elem, true);
                } else {
                    ajax.loadUrl(href);
                }
            }
        })
        .on("submit", `form[action^="${window.ADMIN_URL}"]`, (e) => {
            const $elem = $(e.target);
            const elem  = $elem[0];

            if (!elem.hasAttribute("data-noajax") && supportsHistoryAndFetch()) {
                e.preventDefault();
                ajax.postForm($elem.attr("action"), $elem);
            }
        });



    /** Start the required Functions */
    $(() => {
        ajax   = new Ajax();
        dialog = new Dialog();
        media  = new Media(ajax);

        ajax.init(media, dialog);
        ajax.loadPage(window.location.href, true);

        window.ajax   = ajax;
        window.dialog = dialog;
        window.media  = media;

        if (supportsHistoryAndFetch()) {
            window.addEventListener("popstate", () => {
                ajax.loadPage(window.location.href, false);
            });
        }
        document.addEventListener("scroll", () => {
            ajax.handleSidebarScroll();
        });
        window.onresize = () => {
            ajax.setStyles();
        };
    });

}());
