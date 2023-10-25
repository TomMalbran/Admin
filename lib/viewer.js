(function () {
    "use strict";

    /**
     * Inits the Viewer
     * @returns {Void}
     */
    function init() {
        /** @type {NodeListOf<HTMLElement>} */
        const viewers = document.querySelectorAll(".viewer");

        // @ts-ignore
        for (const container of viewers) {
            const name = container.getAttribute("data-name")  || "";

            const close  = container.querySelector(".viewer-close");
            const prev   = container.querySelector(".viewer-prev");
            const next   = container.querySelector(".viewer-next");
            const inside = container.querySelector(".viewer-inside");
            const image  = container.querySelector(".viewer-image");
            const video  = container.querySelector(".viewer-video");

            // Generate the Items
            const elements = document.querySelectorAll(name ? `.viewer-item[data-name=${name}]` : ".viewer-item");
            const items    = [];

            // @ts-ignore
            for (const [ index, elem ] of elements.entries()) {
                items.push({
                    src  : elem.getAttribute("data-src"),
                    name : elem.getAttribute("data-name"),
                });
            }

            // Save the Data
            const content = image || video || inside;
            const data    = {
                container, content, items, image, video,
                name  : container.querySelector(".viewer-name"),
                index : 0,
                last  : items.length,
            };


            // Add the Listeners
            container.addEventListener("click", (e) => {
                closeViewer(container);
                e.preventDefault();
            });
            if (close) {
                close.addEventListener("click", (e) => {
                    closeViewer(container);
                    e.preventDefault();
                });
            }
            if (prev) {
                prev.addEventListener("click", (e) => {
                    showItem(data, undefined, -1);
                    e.stopPropagation();
                    e.preventDefault();
                });
            }
            if (next) {
                next.addEventListener("click", (e) => {
                    showItem(data, undefined, 1);
                    e.stopPropagation();
                    e.preventDefault();
                });
            }
            if (content) {
                content.addEventListener("click", (e) => {
                    e.stopPropagation();
                });
            }

            // @ts-ignore
            for (const [ index, elem ] of elements.entries()) {
                elem.addEventListener("click", (e) => {
                    let items = elem.getAttribute("data-items") || "";
                    if (items) {
                        data.items = JSON.parse(items);
                        data.last  = data.items.length;
                        showItem(data, 0);
                    } else {
                        showItem(data, index);
                    }
                    e.preventDefault();
                });
            }
        }
    }

    /**
     * Closes the Viewer
     * @param {HTMLElement} container
     * @returns {Void}
     */
    function closeViewer(container) {
        container.style.opacity = "0";
        window.setTimeout(() => {
            container.style.display = "none";
        }, 300);
    }

    /**
     * Shows an Item
     * @param {Object}  data
     * @param {Number=} index
     * @param {Number=} dir
     * @returns {Void}
     */
    function showItem(data, index, dir) {
        data.index = index !== undefined ? index : data.index + (dir || 1);
        if (data.index < 0) {
            data.index = data.last - 1;
        } else if (data.index > data.last - 1) {
            data.index = 0;
        }
        const item = data.items[data.index];

        data.container.style.display = "flex";
        data.container.style.opacity = "1";

        if (data.image) {
            data.image.setAttribute("src", item.src);
        } else if (data.video) {
            data.video.setAttribute("src", item.src);
        }

        if (item.name) {
            if (data.image) {
                data.image.setAttribute("alt", item.name);
            }
            if (data.name) {
                data.name.innerHTML = item.name;
            }
        }
    }



    // Init the Viewer
    window.addEventListener("load", () => {
        init();
    });

}());
