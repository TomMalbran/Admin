(function () {
    "use strict";

    let form, button, success, fail, recaptcha, errors, url;
    let sending = false;


    /**
     * Inits the Form
     * @param {String=} clientID
     * @returns {Void}
     */
    function initForm(clientID = "") {
        form = document.querySelector("#form");
        if (!form) {
            return;
        }

        button    = form.querySelector("button");
        success   = form.querySelector(".form-success");
        fail      = form.querySelector(".form-fail");
        recaptcha = form.querySelector(".form-recaptcha");

        errors    = form.querySelectorAll(".form-error");
        url       = form.getAttribute("action");

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

            if (clientID) {
                window.grecaptcha.execute(clientID, {
                    action : "validate_captcha",
                }).then((token) => sendForm());
            } else {
                sendForm();
            }
        });
    }

    /**
     * Sends the Form
     * @param {String=} token
     * @returns {Void}
     */
    function sendForm(token = "") {
        const body = new FormData(form);
        if (token) {
            body.append("g-recaptcha-response", token);
        }
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
                    const errelem = form.querySelector(`[data-error=${error}]`);
                    if (errelem) {
                        errelem.style.display = "block";
                    }
                }
            } else if (response.success) {
                success.style.display = "block";
                form.reset();
                if (window.onContactSent) {
                    window.onContactSent();
                }
            }

            button.classList.remove("loading");
            sending = false;
        }).catch(() => {
            fail.style.display = "block";
            button.classList.remove("loading");
            sending = false;
        });
    }




    // Init the Stuff
    const script = document.currentScript.src;
    const search = script.substr(script.indexOf(".js?") + 4);
    const params = new URLSearchParams(search);

    if (params.has("recaptcha") && params.get("recaptcha")) {
        window.onRecaptchaLoad = () => {
            const isMobile = window.outerWidth < 820;
            const clientID = window.grecaptcha.render("recaptcha-badge", {
                "sitekey" : params.get("recaptcha"),
                "badge"   : params.has("render") ? params.get("render") : (isMobile ? "inline" : "bottomright"),
                "size"    : "invisible"
            });
            window.grecaptcha.ready(() => initForm(clientID));
        };
    } else {
        initForm();
    }

}());
