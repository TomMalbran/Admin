// Adds the the Window Interface
interface Window {
    JWT_TOKEN       : string;
    ADMIN_URL       : string;
    FILES_URL       : string;
    PUBLIC_URL      : string;

    ajax            : Ajax;
    dialog          : Dialog;
    media           : Media;

    tinymce         : any;
    fancybox        : any;

    RECAPTCHA       : string;
    grecaptcha      : any;
    onRecaptchaLoad : Function;
}

// Adds the the JQuery Interface
interface JQuery {
    autogrow : Function;
    fancybox : Function;
}
