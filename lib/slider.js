(function () {
    "use strict";

    /**
     * Inits the Sliders
     * @returns {Void}
     */
    function init() {
        /** @type {NodeListOf<HTMLElement>} */
        const sliders = document.querySelectorAll(".slider");

        for (const container of sliders) {
            const isOpacity = container.classList.contains("slider-opacity");
            const isSlide   = container.classList.contains("slider-slide");
            const isAuto    = Boolean(container.getAttribute("data-auto"));
            const setWidth  = Boolean(container.getAttribute("data-width"));

            const elem      = container.querySelector(".slider-list");
            const dots      = container.querySelector(".slider-dots");
            const lis       = container.querySelectorAll(".slider-dots li");
            const next      = container.querySelector(".slider-next");
            const prev      = container.querySelector(".slider-prev");
            const slides    = container.querySelectorAll(".slider-slide");
            const total     = slides.length;

            let last = total;
            if (setWidth) {
                for (const slide of slides) {
                    slide.style.width = `calc(100%/${total})`;
                }
            } else {
                const rect      = container.getBoundingClientRect();
                const slideRect = slides.length   ? slides[0].getBoundingClientRect() : {};
                const amount    = slideRect.width ? Math.max(Math.floor(rect.width / slideRect.width), 1) : 1;
                last = total - amount + 1;
            }

            const data = {
                isSlide, isOpacity, isAuto,
                container, elem, dots,
                total, last,
                index   : 0,
                timeout : null
            };



            // Add the Listeners
            if (next) {
                next.addEventListener("click", (e) => {
                    move(data, undefined, 1);
                    e.preventDefault();
                });
            }
            if (prev) {
                prev.addEventListener("click", (e) => {
                    move(data, undefined, -1);
                    e.preventDefault();
                });
            }
            if (dots) {
                for (const dot of lis) {
                    dot.addEventListener("click", (e) => {
                        const index = e.target.innerText;
                        move(data, Number(index) - 1);
                        e.preventDefault();
                    });
                }
            }

            // Start the Slider
            setStyles(data);
            autoRun(data);
        }
    }

    /**
     * Moves the Slider one tothe left or to the given slide
     * @param {Object}  data
     * @param {Number=} index
     * @param {Number=} dir
     * @returns {Void}
     */
    function move(data, index, dir) {
        window.clearTimeout(data.timeout);

        data.index = index !== undefined ? index : data.index + (dir || 1);
        if (data.index < 0) {
            data.index = data.last - 1;
        } else if (data.index > data.last - 1) {
            data.index = 0;
        }

        setStyles(data);
        autoRun(data);
    }

    /**
     * Set the Slide
     * @param {Object} data
     * @returns {Void}
     */
    function setStyles(data) {
        const newIndex = data.index + 1;
        const oldSlide = data.elem.querySelector(".selected");
        const newSlide = data.elem.querySelector(`:nth-child(${newIndex})`);

        if (data.isSlide) {
            data.elem.style.width     = `calc(100%*${data.total})`;
            data.elem.style.transform = `translateX(calc(-100%/${data.total}*${data.index}))`;
        } else if (data.isOpacity) {
            if (oldSlide) {
                oldSlide.classList.remove("selected");
            }
            if (newSlide) {
                newSlide.classList.add("selected");
            }
        }

        if (newSlide) {
            const newColor = newSlide.getAttribute("data-color");
            if (newColor) {
                data.container.style.setProperty("--slider-main-color", newColor);
            } else {
                data.container.style.setProperty("--slider-main-color", "black");
            }
        }

        if (data.dots) {
            const oldDot = data.dots.querySelector(".selected");
            if (oldDot) {
                oldDot.classList.remove("selected");
            }
            data.dots.querySelector(`li:nth-child(${newIndex})`).classList.add("selected");
        }
    }

    /**
     * Sets the timeout to move the slider in x seconds
     * @param {Object} data
     * @returns {Void}
     */
    function autoRun(data) {
        if (data.isAuto) {
            data.timeout = window.setTimeout(() => move(data), 10000);
        }
    }



    // Init the Slider
    window.addEventListener("load", () => {
        init();
    });

}());
