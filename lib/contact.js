(function () {
    "use strict";

    /**
     * Inits the Form
     * @param {String} clientID
     * @returns {Void}
     */
    function initForm(clientID) {
        /** @type {HTMLFormElement} */
        const form = document.querySelector("#form");
        if (!form) {
            return;
        }

        /** @type {HTMLElement} */
        const button    = form.querySelector("button");
        /** @type {HTMLElement} */
        const success   = form.querySelector(".form-success");
        /** @type {HTMLElement} */
        const fail      = form.querySelector(".form-fail");
        /** @type {HTMLElement} */
        const recaptcha = form.querySelector(".form-recaptcha");

        const errors    = form.querySelectorAll(".form-error");
        const url       = form.getAttribute("action");
        let   sending   = false;

        form.addEventListener("submit", (e) => {
            e.preventDefault();
            if (sending) {
                return;
            }

            sending = true;
            button.classList.add("loading");
            success.style.display = "none";
            fail.style.display = "none";
            recaptcha.style.display = "none";
            for (const error of errors) {
                error.style.display = "none";
            }

            window.grecaptcha.execute(clientID, {
                action : "validate_captcha",
            }).then((token) => {
                const body = new FormData(form);
                body.append("g-recaptcha-response", token);
                body.append("ajax", "1");

                fetch(url, { method : "post", body }).then((response) => response.text()).then((response) => {
                    let result = response, error = response;
                    if (response.indexOf("<br />") > -1) {
                        result = response.split("<br />")[0];
                    }
                    try {
                        return JSON.parse(result);
                    } catch (e) {
                        throw error;
                    }
                }).then((response) => {
                    if (response.fail) {
                        fail.style.display = "block";
                    } else if (response.errors) {
                        if (response.errors.recaptcha) {
                            recaptcha.style.display = "block";
                        }
                        for (const error of Object.keys(response.errors)) {
                            /** @type {HTMLElement} */
                            const errelem = form.querySelector(`[data-error=${error}]`);
                            if (errelem) {
                                errelem.style.display = "block";
                            }
                        }
                    } else if (response.success) {
                        success.style.display = "block";
                        form.reset();
                    }
                    button.classList.remove("loading");
                    sending = false;
                }).catch(() => {
                    fail.style.display = "block";
                    button.classList.remove("loading");
                    sending = false;
                });
            });
        });
    }




    // Init the Stuff
    window.onRecaptchaLoad = () => {
        const isMobile = window.outerWidth < 820;
        const clientID = window.grecaptcha.render("recaptcha-badge", {
            "sitekey" : window.RECAPTCHA,
            "badge"   : isMobile ? "inline" : "bottomright",
            "size"    : "invisible"
        });
        window.grecaptcha.ready(() => initForm(clientID));
    };

}());
