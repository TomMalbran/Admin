/**
 * Dialog Manager
 */
class Dialog {

    /**
     * Dialog Manager constructor
     */
    constructor() {
        this.dialogs = [];
    }



    /**
     * Creates a Dialog
     * @param {String}    type
     * @param {String}    title
     * @param {String}    content
     * @param {Boolean}   hasPrimary
     * @param {Function=} onOk
     * @param {Function=} onCancel
     * @returns {Object}
     */
    create(type, title, content, hasPrimary, onOk, onCancel) {
        const $elem = $(`<div class="dialog-backdrop">
            <dialog class="dialog dialog-${type}">
                ${title ? `<header><h2>${title}</h2></header>` : ""}
                <main>${content}</main>
                <footer>
                    ${hasPrimary ? `<button class="btn btn-primary dialog-ok">Aceptar</button>` : ""}
                    <button class="btn btn-cancel dialog-cancel">Cancelar</button>
                </footer>
            <dialog>
        </div>`);

        const $main  = $elem.find("main");
        const dialog = {
            type, $elem, $main,
            isOpen : true,
            find   : (query) => $elem.find(query),
            html   : (val)   => $main.html(val),
            submit : ()      => this.close(dialog, onOk),
            cancel : ()      => this.close(dialog, onCancel),
            close  : ()      => this.close(dialog),
        };

        $(document.body).append($elem);
        $elem
            .on("click", ".dialog-ok", () => {
                this.close(dialog, onOk);
            })
            .on("click", ".dialog-cancel", () => {
                this.close(dialog, onCancel);
            })
            .on("mousedown", (e) => {
                const $main = $(e.target).closest(".dialog");
                if (!$main.length) {
                    this.close(dialog, onCancel);
                }
            });

        this.dialogs.push(dialog);
        return dialog;
    }

    /**
     * Closes the Dialog
     * @param {Dialog}    dialog
     * @param {Function=} callback
     * @returns {Void}
     */
    close(dialog, callback) {
        dialog.$elem.addClass("dialog-close").off("animationend").on("animationend", () => {
            if (dialog.isOpen) {
                dialog.isOpen = false;
                window.setTimeout(() => {
                    dialog.$elem.remove();
                    this.dialogs.pop();
                    if (callback) {
                        callback();
                    }
                }, 100);
            }
        });
    }

    /**
     * Returns the Latests Dialog or Null
     * @returns {?Object}
     */
    get() {
        return this.dialogs[this.dialogs.length - 1];
    }



    /**
     * A Confirm Dialog
     * @param {String} title
     * @param {String} text
     * @returns {Promise}
     */
    confirm(title, text) {
        return new Promise((resolve) => {
            this.create("confirm", title, text, true, resolve);
        });
    }

    /**
     * A Prompt Dialog
     * @param {String} title
     * @param {String} text
     * @param {*}      value
     * @returns {Promise}
     */
    prompt(title, text, value) {
        return new Promise((resolve) => {
            const content = `<div>${text}</div>
                <div style="margin-top:20px;">
                    <input type="text" value="${value || ""}" class="dialog-input full-width" />
                </div>`;

            let $input   = null;
            const dialog = this.create("prompt", title, content, true, () => {
                const val = $input.val();
                resolve(val);
            });
            $input = dialog.find(".dialog-input").on("keydown", (e) => {
                if (e.keyCode === 13 && $input.val()) {
                    dialog.submit();
                }
            }).focus();
        });
    }

    /**
     * An Alert Dialog
     * @param {String}  title
     * @param {String}  text
     * @param {Boolean} isCode
     * @returns {Promise}
     */
    alert(title, text, isCode) {
        return new Promise((resolve) => {
            const content = isCode ? `<pre>${text}</pre>` : `<div>${text}</div>`;
            this.create("alert", title, content, false, resolve);
        });
    }
}
