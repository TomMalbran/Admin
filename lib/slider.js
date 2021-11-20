(function () {
    "use strict";

    /**
     * Inits the Sliders
     * @returns {Void}
     */
    function init() {
        /** @type {NodeListOf<HTMLElement>} */
        const sliders = document.querySelectorAll(".slider");

        // @ts-ignore
        for (const container of sliders) {
            const isMove      = container.classList.contains("slider-move");
            const isAsymetric = container.classList.contains("slider-asymetric");
            const isOpacity   = container.classList.contains("slider-opacity");
            const isAuto      = Boolean(container.getAttribute("data-auto"));
            const setWidth    = Boolean(container.getAttribute("data-width"));

            const elem        = container.querySelector(".slider-list");
            const dots        = container.querySelector(".slider-dots");
            const lis         = container.querySelectorAll(".slider-dots li");
            const prev        = container.querySelector(".slider-prev");
            const next        = container.querySelector(".slider-next");
            const slides      = container.querySelectorAll(".slider-slide");
            const rect        = container.getBoundingClientRect();
            const total       = slides.length;
            const widths      = [];
            let   last        = total;


            // For Asymetric Widths
            if (isAsymetric) {
                for (const slide of slides) {
                    widths.push(slide.getBoundingClientRect().width);
                }
                let width = rect.width;
                let index = 1;
                while (width > 0 && index < total) {
                    width -= widths[total - index];
                    index += 1;
                }
                last = total - Math.max(0, index - 2);

            // For Constant Widths
            } else if (setWidth) {
                for (const slide of slides) {
                    slide.style.width = `calc(100%/${total})`;
                }

            // For the Rest
            } else {
                const slideRect = slides.length   ? slides[0].getBoundingClientRect() : {};
                const amount    = slideRect.width ? Math.max(Math.floor(rect.width / slideRect.width), 1) : 1;
                last = total - amount + 1;
            }

            const data = {
                isMove, isAsymetric, isOpacity, isAuto,
                container, elem, dots, widths,
                total, last,
                index   : 0,
                timeout : null
            };


            // Add the Listeners
            if (prev) {
                prev.addEventListener("click", (e) => {
                    showItem(data, undefined, -1);
                    e.preventDefault();
                });
            }
            if (next) {
                next.addEventListener("click", (e) => {
                    showItem(data, undefined, 1);
                    e.preventDefault();
                });
            }
            if (dots) {
                for (const dot of lis) {
                    dot.addEventListener("click", (e) => {
                        const index = e.target.innerText;
                        showItem(data, Number(index) - 1);
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
     * Shows the Slide
     * @param {Object}  data
     * @param {Number=} index
     * @param {Number=} dir
     * @returns {Void}
     */
    function showItem(data, index, dir) {
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
     * Set the Styles
     * @param {Object} data
     * @returns {Void}
     */
    function setStyles(data) {
        const newIndex = data.index + 1;
        const oldSlide = data.elem.querySelector(".selected");
        const newSlide = data.elem.querySelector(`:nth-child(${newIndex})`);

        // Move the Slide
        if (data.isMove) {
            data.elem.style.width     = `${100 * data.total}%`;
            data.elem.style.transform = `translateX(calc(-100%/${data.total}*${data.index}))`;

        } else if (data.isAsymetric) {
            let width = 0;
            for (let i = 0; i < newIndex - 1; i++) {
                width += data.widths[i];
            }
            data.elem.style.transform = `translateX(-${width}px)`;

        } else if (data.isOpacity) {
            if (oldSlide) {
                oldSlide.classList.remove("selected");
            }
            if (newSlide) {
                newSlide.classList.add("selected");
            }
        }

        // Apply styles to the slide
        if (newSlide) {
            const newColor = newSlide.getAttribute("data-color");
            if (newColor) {
                data.container.style.setProperty("--slider-main-color", newColor);
            } else {
                data.container.style.setProperty("--slider-main-color", "black");
            }
        }

        // Mark the selected dot
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
            data.timeout = window.setTimeout(() => showItem(data), 10000);
        }
    }



    // Init the Slider
    window.addEventListener("load", () => {
        init();
    });

}());
