/**
 * Ajax Manager
 */
class Ajax {

    /**
     * Ajax Manager constructor
     */
    constructor() {
        this.jwt       = localStorage.adminToken || window.JWT_TOKEN;
        this.$root     = $("#root");
        this.$iframe   = $("#frame");

        this.$item     = null;
        this.curUrl    = null;
        this.curHref   = "";
        this.fieldID   = 0;
        this.scrollOld = $(document).scrollTop();
        this.scrollTo  = 0;
    }

    /**
     * Initializes the Ajax Manager
     * @param {Media}  media
     * @param {Dialog} dialog
     * @returns {Void}
     */
    init(media, dialog) {
        this.media  = media;
        this.dialog = dialog;
    }

    /**
     * Sets the Token
     * @param {String} jwt
     * @returns {Void}
     */
    setToken(jwt) {
        this.jwt = jwt;
        localStorage.setItem("adminToken", jwt);
    }

    /**
     * Unsets the Token
     * @returns {Void}
     */
    unsetToken() {
        this.jwt = "";
        localStorage.removeItem("adminToken");
    }



    /**
     * Shows the Loader
     * @returns {Void}
     */
    showLoader() {
        if (this.$loader) {
            this.$loader.addClass("show-loader");
        }
    }

    /**
     * Hides the Loader
     * @returns {Void}
     */
    hideLoader() {
        if (this.$loader) {
            this.$loader.removeClass("show-loader");
        }
    }

    /**
     * Shows the Menu
     * @returns {Void}
     */
    showMenu() {
        if (this.$sidebar) {
            this.$sidebar.addClass("show-sidebar");
            this.$menu.addClass("topbar-back");
        }
    }

    /**
     * Hides the Menu
     * @returns {Void}
     */
    hideMenu() {
        if (this.$sidebar) {
            this.$sidebar.removeClass("show-sidebar");
            this.$menu.removeClass("topbar-back");
            if (this.$item) {
                this.selectMenu(this.$item, false);
            }
        }
    }

    /**
     * Selects a Menu Item
     * @param {JQuery}  $newItem
     * @param {Boolean} isTemp
     * @returns {Void}
     */
    selectMenu($newItem, isTemp) {
        const $oldItem = $(".menu-item-selected").removeClass("menu-item-selected");
        $newItem.addClass("menu-item-selected");

        if (isTemp) {
            this.$item = $oldItem;
        } else {
            this.$item = null;
        }
    }



    /**
     * Does a Fetch request
     * @param {String}    route
     * @param {FormData=} body
     * @param {Object=}   params
     * @param {Boolean=}  reload
     * @returns {Promise}
     */
    fetch(route, body, params, reload) {
        const href = (!route.startsWith("http") ? window.ADMIN_URL : "") + route;
        const url  = new URL(href);

        if (!body) {
            body = new FormData();
        }
        body.append("ajax", "1");
        if (reload) {
            body.append("reload", "1");
        }
        if (this.jwt) {
            body.append("jwt", this.jwt);
        }

        if (params) {
            for (const [ key, value ] of Object.entries(params)) {
                if (Array.isArray(value)) {
                    for (const val of value) {
                        body.append(`${key}[]`, val);
                    }
                } else {
                    body.append(key, value);
                }
            }
        }

        return fetch(url.toString(), {
            method      : "post",
            body        : body,
            credentials : "same-origin",
        }).then((response) => response.text()).then((response) => {
            let result = response, error = response;
            if (response.indexOf("<br />") > -1) {
                result = response.split("<br />")[0];
            }
            try {
                return JSON.parse(result);
            } catch (e) {
                throw error;
            }
        });
    }

    /**
     * Does a GET request
     * @param {String}  route
     * @param {Object=} params
     * @returns {Promise}
     */
    get(route, params) {
        const body = new FormData();
        return this.fetch(route, body, params);
    }

    /**
     * Does a POST request
     * @param {String}                  route
     * @param {JQuery<HTMLFormElement>} $form
     * @param {Object=}                 params
     * @returns {Promise}
     */
    post(route, $form, params) {
        const body = new FormData($form.get(0));
        return this.fetch(route, body, params);
    }

    /**
     * Downloads the given url
     * @param {String} href
     * @returns {Void}
     */
    download(href) {
        const src = `${href}${href.indexOf("?") > -1 ? "&" : "?"}jwt=${this.jwt}`;
        this.$iframe.attr("src", src);
    }



    /**
     * Loads a Page
     * @param {String}  href
     * @param {Boolean} forBody
     * @param {Object=} data
     * @returns {Promise}
     */
    loadPage(href, forBody, data) {
        this.showLoader();
        const result = this.fetch(href, null, data, forBody).then((response) => {
            if (this.curHref === href) {
                this.resolveResponse(response);
            } else {
                this.hideLoader();
                this.hideMenu();
            }
        }).catch(this.resolveError.bind(this));
        this.curHref = href;
        return result;
    }

    /**
     * Loads an Url with the given Params
     * @param {String}  href
     * @param {String=} key
     * @param {String=} value
     * @returns {Promise}
     */
    loadUrl(href, key, value) {
        const dialog = this.dialog.get();
        let   url    = href;

        if (key && value) {
            url = `${href}${href.indexOf("?") > -1 ? "&" : "?"}${key}=${value}`;
        }
        if (!dialog && this.curUrl !== url) {
            history.pushState(null, null, url);
            this.curUrl = url;
        }
        return this.loadPage(url, false);
    }

    /**
     * Loads a Dialog
     * @param {String}  href
     * @param {String}  type
     * @param {Object=} data
     * @returns {Promise}
     */
    loadDialog(href, type, data) {
        this.showLoader();
        const result = this.fetch(href, null, data).then((response) => {
            const dialog = this.dialog.get();
            if (dialog && dialog.type === type) {
                this.setContent(response, dialog.$main);
                this.initComponents();
            } else {
                this.dialog.create(type, null, response.content, false);
                this.initComponents();
            }
            this.hideLoader();
            this.hideMenu();
        }).catch(this.resolveError.bind(this));
        return result;
    }

    /**
     * Posts a Form
     * @param {String}                  href
     * @param {JQuery<HTMLFormElement>} $form
     * @returns {Promise}
     */
    postForm(href, $form) {
        this.showLoader();
        return this.post(href, $form)
            .then((response) => this.resolveResponse(response))
            .catch(this.resolveError.bind(this));
    }

    /**
     * Resolve the Fetch Response
     * @param {Object} response
     * @returns {Void}
     */
    resolveResponse(response) {
        const dialog = this.dialog.get();
        if (response.adminJWT) {
            this.setToken(response.adminJWT);
        }

        if (response.redirect) {
            const url = response.redirect || window.ADMIN_URL;
            if (response.reload) {
                this.loadPage(url, true, response.storage);
            } else if (dialog) {
                this.loadDialog(url, dialog.type, response.storage);
            } else if (!url.startsWith(window.ADMIN_URL)) {
                this.hideLoader();
                window.open(url);
            } else {
                history.replaceState(null, null, url);
                this.loadPage(url, response.forBody, response.storage);
            }
        } else {
            const $container = this.getContainer(dialog, response.forBody);
            this.setContent(response, $container);
            this.initElems();
            this.hideLoader();
            this.hideMenu();

            if (!dialog) {
                this.scrollToTop();
                this.initEditors();
                this.setMenu(response);
            }
            this.initComponents()
        }
    }

    /**
     * Returns the Container
     * @param {Object}  dialog
     * @param {Boolean} forBody
     * @returns {JQuery}
     */
    getContainer(dialog, forBody) {
        if (dialog) {
            return dialog.$main;
        }
        if (forBody) {
            return this.$root;
        }
        if (this.$auth && this.$auth.length) {
            return this.$auth;
        }
        if (this.$frame && this.$frame.length) {
            return this.$frame;
        }
        return this.$content;
    }

    /**
     * Resolve the Fetch Error
     * @param {(String|Object)} error
     * @returns {Void}
     */
    resolveError(error) {
        this.hideLoader();
        this.hideMenu();
        const message = error && error.message ? error.message : error;
        if (message) {
            this.dialog.alert("Error", message.replace(/</g, "&lt").replace(/</g, "&gt"), true);
        }
    }

    /**
     * Closes the Current Dialog
     * @returns {Void}
     */
    closeDialog() {
        const dialog = this.dialog.get();
        if (dialog) {
            dialog.close();
        }
    }

    /**
     * Restores the Position
     * @returns {Void}
     */
    restorePosition() {
        this.scrollTo = $(document).scrollTop();
    }

    /**
     * Sets the Content
     * @param {Object} response
     * @param {JQuery} $container
     * @returns {Void}
     */
    setContent(response, $container) {
        $container.html(response.content);
    }

    /**
     * Scrolls to the Top
     * @returns {Void}
     */
    scrollToTop() {
        $("html, body").animate({ scrollTop : this.scrollTo }, "fast");
        this.scrollTo = 0;
    }

    /**
     * Sets the Menu
     * @param {Object} response
     * @returns {Void}
     */
    setMenu(response) {
        $(".menu-item-selected").removeClass("menu-item-selected");
        $(".sub-item-selected").removeClass("sub-item-selected");
        if (response.mainMenu) {
            const $menu = $(`[data-menu=${response.mainMenu}]`);
            this.selectMenu($menu, false);
        }
        if (response.subMenu) {
            $(`[data-sub=${response.subMenu}]`).addClass("sub-item-selected");
        }
    }

    /**
     * Initializes all the Elements
     * @returns {Void}
     */
    initElems() {
        this.$loader  = $(".loader");
        this.$auth    = $(".auth-content");
        this.$content = $(".content-wrapper");
        this.$frame   = $(".content-frame");
        this.$sidebar = $(".sidebar");
        this.$title   = $(".subbar h1");
        this.$menu    = $(".topbar-menu");
        this.$nav     = $(".sidebar-menu");
    }

    /**
     * Start the Required Components
     * @returns {Void}
     */
    initComponents() {
        this.setStyles();
        this.media.init();

        $(".fancybox").fancybox({
            openEffect  : "elastic",
            closeEffect : "elastic",
        });
        $(".sortable").sortable({
            handle : ".sortable-drag",
            start  : (e, ui) => ui.placeholder.height(ui.item.height()),
            update : (e, ui) => {
                const $elem = $(ui.item).closest(".sortable-form");
                if ($elem.length) {
                    // @ts-ignore
                    this.postForm($elem.attr("action"), $elem);
                }
            }
        });
        $(".media-sortable").sortable({
            handle : ".media-image",
            start  : (e, ui) => ui.placeholder.height(ui.item.height()),
            stop   : () => $(".media-sortable").trigger("submit"),
        });
        $(".datepicker").datepicker();
        $(".autogrow").autogrow();

        $(".result-bar").each((index, elem) => {
            window.setTimeout(() => $(elem).fadeOut(), 10000);
        });
    }

    /**
     * Adds and Removes TinyMCE Editors
     * @returns {Void}
     */
    initEditors() {
        if (!window.tinymce) {
            return;
        }
        window.tinymce.remove(".tinymce");
        window.tinymce.remove(".tinymce-simple");

        const options = {
            theme              : "silver",
            language           : "es",
            plugins            : "paste link hr table lists image media code mediamanager",
            menubar            : false,
            relative_urls      : false,
            remove_script_host : false,
            paste_as_text      : true,
            image_advtab       : true,
            mediamanager_path  : window.ADMIN_URL,
            mediamanager_files : window.FILES_URL,
            mediamanager_title : "Archivos",
            external_plugins   : {
                mediamanager : `${window.PUBLIC_URL}thirdparty/tinymedia.js`,
            },
        };

        window.tinymce.init($.extend({
            selector : ".tinymce",
            height   : 500,
            toolbar  : `
                undo redo | formatselect fontsizeselect | bold italic underline strikethrough forecolor backcolor removeformat |
                alignleft aligncenter alignright alignjustify | link image media mediamanager | table outdent indent |
                bullist numlist | blockquote hr code |
            `,
        }, options));
        window.tinymce.init($.extend({
            selector : ".tinymce-simple",
            height   : 300,
            toolbar  : `
                undo redo | bold italic underline strikethrough forecolor |
                link outdent indent | bullist numlist | blockquote hr |
            `,
        }, options));
    }



    /**
     * Handles the Initial Styles
     * @returns {Void}
     */
    setStyles() {
        this.$nav.css("margin-top", "0px");
        this.setMinHeight();
        this.setSubNavTops();

        const hash = window.location.hash;
        if (hash) {
            this.setTab(hash.substr(1), false);
            this.setAccordion(hash.substr(1), false);
        } else {
            this.setTabSelector($(".tab-selected"));
        }
    }

    /**
     * Handles the Sidebar scroll
     * @returns {Void}
     */
    handleSidebarScroll() {
        if (!this.$nav.length) {
            return;
        }

        const titleHeight = this.$title.outerHeight();
        const navHeight   = this.$nav.outerHeight() + titleHeight;
        const sideHeight  = this.$sidebar.outerHeight();
        const scrollTop   = $(document).scrollTop();
        const scrollDiff  = this.scrollOld - scrollTop;
        const marginTop   = Number(this.$nav.css("margin-top").replace("px", ""));
        const navBottom   = navHeight + marginTop;
        const maxMargin   = sideHeight - navHeight;
        let   newMargin   = null;

        if (navHeight > sideHeight) {
            if (scrollDiff < 0 && navBottom > sideHeight) {
                newMargin = marginTop + scrollDiff;
            } else if (scrollDiff > 0 && marginTop <= 0) {
                newMargin = marginTop + scrollDiff;
            }
            if (newMargin !== null) {
                newMargin = Math.max(Math.min(newMargin, 0), maxMargin);
                this.$nav.css("margin-top", `${newMargin}px`);
                this.$title.toggleClass("show-more", !!newMargin);
            }
        }
        this.scrollOld = scrollTop;
    }

    /**
     * Sets the Min Height of the Body
     * @returns {Void}
     */
    setMinHeight() {
        const titleHeight = this.$title.outerHeight();
        const navHeight   = this.$nav.outerHeight();
        $(document.body).css("min-height", (titleHeight + navHeight + 20) + "px");
    }

    /**
     * Set the SubNavs Tops
     * @returns {Void}
     */
    setSubNavTops() {
        const winHeight = $(window).height();
        const $subs     = this.$nav.find(".sub-nav");

        $subs.each((index, elem) => {
            const $sub        = $(elem);
            const $item       = $sub.parent();
            const itemTop     = $item.position().top;
            const itemHeight  = $item.outerHeight();
            const subHeight   = $sub.outerHeight();
            const titleHeight = this.$title.outerHeight();
            const subBottom   = titleHeight + itemTop + subHeight;

            if (subBottom > winHeight) {
                $sub.css("top", (-subHeight + itemHeight) + "px");
                $sub.addClass("sub-top");
            } else {
                $sub.css("top", "0px");
                $sub.removeClass("sub-top");
            }
        });
    }

    /**
     * Goes to the Tab
     * @param {JQuery}  $elem
     * @param {Boolean} animate
     * @returns {Void}
     */
    gotoTab($elem, animate) {
        const href = $elem.attr("data-href");
        const tab  = $elem.attr("data-tab");

        if (href) {
            this.loadUrl(href);
        } else if (tab) {
            this.setTab(tab, animate);
        }
    }

    /**
     * Sets the given Tab
     * @param {String}  name
     * @param {Boolean} animate
     * @returns {Void}
     */
    setTab(name, animate) {
        const $tabs    = $(".content-tabs");
        const $current = $(".tabs-show");
        const $new     = $(`.tabs-content[data-tab="${name}"]`);

        $tabs.find(".tab-selected").removeClass("tab-selected");
        const $elem = $(`.tabs-bar li[data-tab="${name}"]`).addClass("tab-selected");
        $(".subsection").val(name);
        this.setTabSelector($elem);

        if (animate) {
            if ($current.length) {
                $current.fadeOut(() => {
                    $current.removeClass("tabs-show");
                    $new.addClass("tabs-show").fadeIn(() => {
                        $(".autogrow").css({ height : "" }).autogrow();
                    });
                    history.pushState(null, null, `#${name}`);
                });
            } else {
                $new.addClass("tabs-show").fadeIn(() => {
                    $(".autogrow").css({ height : "" }).autogrow();
                });
                history.pushState(null, null, `#${name}`);
            }
        } else {
            $current.removeClass("tabs-show");
            $new.addClass("tabs-show");
        }
    }

    /**
     * Set the Tab Selector Style
     * @param {JQuery} $elem
     * @returns {Void}
     */
    setTabSelector($elem) {
        if ($elem.length) {
            const $horizontal = $elem.closest(".content-horizontal");
            const $content    = $elem.closest(".content-tabs");
            const $selector   = $content.find(".tabs-selector");
            const top         = $elem.offset().top  - $content.offset().top  + $content.scrollTop();
            const left        = $elem.offset().left - $content.offset().left + $content.scrollLeft();

            if ($horizontal.length) {
                $selector.css({
                    top    : `${top}px`,
                    height : `${$elem.outerHeight()}px`,
                });
            } else {
                $selector.css({
                    left  : `${left}px`,
                    width : `${$elem.outerWidth()}px`,
                });
            }

            if (!$selector.hasClass("tabs-selector-animate")) {
                $selector.addClass("tabs-selector-animate");
            }
        }
    }

    /**
     * Sets the Accordion
     * @param {String}  name
     * @param {Boolean} animate
     * @returns {Void}
     */
    setAccordion(name, animate) {
        const $current = $(".accordion-show");
        const current  = $(".accordion-show").attr("data-accordion");
        const $new     = $(`.accordion[data-accordion="${name}"]`);

        $(".subsection").val(name);

        if (animate) {
            $current.find(".accordion-body").slideUp(() => {
                $current.removeClass("accordion-show");
            });
            if (current !== name) {
                $new.find(".accordion-body").slideDown(() => {
                    $new.addClass("accordion-show");
                });
                history.pushState(null, null, `#${name}`);
            } else {
                history.pushState(null, null, "#");
            }
        } else {
            $current.removeClass("accordion-show");
            if (current !== name) {
                $new.addClass("accordion-show");
            }
        }
    }
}
