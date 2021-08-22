(function () {
    "use strict";

    /**
     * Inits the Viewer
     * @returns {Void}
     */
    function init() {
        /** @type {HTMLElement} */
        const viewer = document.querySelector(".viewer");
        if (!viewer) {
            return;
        }

        let   current = 0;
        const close   = viewer.querySelector(".viewer-close");
        const prev    = viewer.querySelector(".viewer-prev");
        const next    = viewer.querySelector(".viewer-next");

        /** @type {HTMLElement} */
        const image   = viewer.querySelector(".viewer-image");

        /** @type {NodeListOf<HTMLElement>} */
        const items   = document.querySelectorAll(".viewer-item");


        close.addEventListener("click", (e) => {
            viewer.style.opacity = "0";
            window.setTimeout(() => {
                viewer.style.display = "none";
            }, 300);
            e.preventDefault();
        });

        prev.addEventListener("click", (e) => {
            current -= 1;
            if (current < 0) {
                current = items.length - 1;
            }
            showItem(viewer, image, items[current]);
            e.preventDefault();
        });
        next.addEventListener("click", (e) => {
            current += 1;
            if (current > items.length - 1) {
                current = 0;
            }
            showItem(viewer, image, items[current]);
            e.preventDefault();
        });

        for (const [ index, item ] of items.entries()) {
            item.addEventListener("click", (e) => {
                current = index;
                showItem(viewer, image, item);
                e.preventDefault();
            });
        }
    }

    /**
     * Shows an Item
     * @param {HTMLElement} viewer
     * @param {HTMLElement} image
     * @param {HTMLElement} item
     * @returns {Void}
     */
    function showItem(viewer, image, item) {
        const src  = item.getAttribute("data-src");
        const name = item.getAttribute("data-name");

        viewer.style.display = "flex";
        viewer.style.opacity = "1";

        image.setAttribute("src", src);

        if (name) {
            image.setAttribute("alt", name);
            const nameElem = viewer.querySelector(".viewer-name");
            if (nameElem) {
                nameElem.innerHTML = name;
            }
        }
    }



    // Init the Viewer
    window.addEventListener("load", () => {
        init();
    });

}());
